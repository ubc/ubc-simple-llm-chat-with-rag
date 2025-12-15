<?php

namespace UBC\SimpleChat;

class Chat_Handler {

	/**
	 * Initialize AJAX actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_ubc_chat_send_message', [ __CLASS__, 'handle_send_message' ] );
		add_action( 'wp_ajax_ubc_chat_get_history', [ __CLASS__, 'handle_get_history' ] );
		add_action( 'wp_ajax_ubc_chat_new_chat', [ __CLASS__, 'handle_new_chat' ] );
		add_action( 'wp_ajax_ubc_chat_delete_chat', [ __CLASS__, 'handle_delete_chat' ] );
	}

	/**
	 * Handles the AJAX request to send a message.
	 *
	 * 1. Sanitizes input.
	 * 2. Retrieves RAG context.
	 * 3. Constructs the augmented message.
	 * 4. Calls the LLM (OpenAI or Ollama).
	 * 5. Stores the conversation.
	 * 6. Returns the response.
	 *
	 * @return void
	 */
	public static function handle_send_message() {
		check_ajax_referer( 'ubc_simple_chat_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in' );
		}

		$message = sanitize_text_field( $_POST['message'] );
		$chat_id = sanitize_text_field( $_POST['chat_id'] );

		if ( empty( $message ) ) {
			wp_send_json_error( 'Message cannot be empty' );
		}

		// Get post type restrictions if provided
		$restricted_post_types = [];
		if ( ! empty( $_POST['restricted_post_types'] ) ) {
			$restricted_post_types = json_decode( stripslashes( $_POST['restricted_post_types'] ), true );
			if ( ! is_array( $restricted_post_types ) ) {
				$restricted_post_types = [];
			}
		}

		// 1. Search RAG
		$rag_context = self::get_rag_context( $message, $restricted_post_types );
		file_put_contents( WP_CONTENT_DIR . '/rag-debug.log', "RAG Context: " . print_r( $rag_context, true ) . "\n", FILE_APPEND );

		// 2. Construct Augmented Message
		$augmented_content = $message;
		if ( ! empty( $rag_context['text'] ) ) {
			$augmented_content = "User Message:\n" . $message . "\n\n---\n\nIn order to help you reply to the user's message, here is some additional information that is contextually relevant:\n\n" . $rag_context['text'];
		}

		// 3. Prepare History for LLM
		$chats = get_user_meta( $user_id, 'ubc_simple_chat_history', true ) ?: [];
		$history = $chats[ $chat_id ]['messages'] ?? [];

		$api_messages = [];
		
		// System Prompt
		$options = get_option( 'ubc_simple_chat_options' );
		$system_prompt = $options['system_prompt'] ?? 'You are a helpful assistant.';
		$api_messages[] = [
			'role' => 'system',
			'content' => $system_prompt,
		];

		// Add History
		foreach ( $history as $msg ) {
			$content = $msg['augmented_content'] ?? $msg['content'];
			$api_messages[] = [
				'role' => $msg['role'],
				'content' => $content,
			];
		}

		// Add Current Message
		$api_messages[] = [
			'role' => 'user',
			'content' => $augmented_content,
		];
		file_put_contents( WP_CONTENT_DIR . '/rag-debug.log', "API Messages: " . print_r( $api_messages, true ) . "\n", FILE_APPEND );

		// 4. Call LLM
		$llm_response = self::call_llm( $api_messages );
		file_put_contents( WP_CONTENT_DIR . '/rag-debug.log', "LLM Response: " . print_r( $llm_response, true ) . "\n", FILE_APPEND );

		if ( is_wp_error( $llm_response ) ) {
			wp_send_json_error( $llm_response->get_error_message() );
		}

		$response_text = $llm_response['choices'][0]['message']['content'] ?? 'Error: No response from LLM';

		// 5. Store Message (User)
		$message_data = [
			'role' => 'user',
			'content' => $message,
			'augmented_content' => $augmented_content,
			'timestamp' => time(),
		];
		self::save_message( $user_id, $chat_id, $message_data );

		// 6. Store Message (Assistant)
		$response_data = [
			'role' => 'assistant',
			'content' => $response_text,
			'sources' => $rag_context['sources'],
			'timestamp' => time(),
		];
		self::save_message( $user_id, $chat_id, $response_data );

		wp_send_json_success( $response_data );
	}

	/**
	 * Retrieves relevant context from the RAG system.
	 *
	 * @param string $query The user's search query.
	 * @param array  $restricted_post_types Array of post types to restrict search to (e.g., ['page', 'post']).
	 * @return array An array containing the combined context text and a list of sources.
	 */
	private static function get_rag_context( $query, $restricted_post_types = [] ) {
		if ( ! class_exists( '\UBC\RAG\API' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/rag-debug.log', "RAG API not found\n", FILE_APPEND );
			return [ 'text' => '', 'sources' => [] ];
		}

		$options = get_option( 'ubc_simple_chat_options' );
		$min_score = isset( $options['min_sim_score'] ) ? floatval( $options['min_sim_score'] ) : 0.0;

		// Build filter array for RAG API
		// The UBC RAG Plugin now supports both single values and arrays:
		// Single value: ['content_type' => 'page']
		// Multiple values: ['content_type' => ['page', 'post']] (uses Qdrant's 'any' operator or SQL IN clause)
		$filter = [];
		if ( ! empty( $restricted_post_types ) ) {
			if ( count( $restricted_post_types ) === 1 ) {
				$filter['content_type'] = $restricted_post_types[0];
			} else {
				$filter['content_type'] = $restricted_post_types;
			}
		}

		$results = \UBC\RAG\API::search( $query, 5, $filter );
		
		$context_text = "";
		$sources = [];
		$seen_urls = [];
		$i = 1;

		foreach ( $results as $result ) {
			if ( $result['score'] < $min_score ) {
				continue;
			}

			$text = $result['payload']['chunk_text'];
			$source_url = $result['payload']['metadata']['source_url'] ?? '#';
			
			// Try to get title from metadata, then post_id/content_id
			$title = $result['payload']['metadata']['title'] ?? '';
			if ( empty( $title ) ) {
				$post_id = $result['payload']['content_id'] ?? 0;
				if ( ! $post_id && isset( $result['payload']['metadata']['post_id'] ) ) {
					$post_id = $result['payload']['metadata']['post_id'];
				}
				
				if ( $post_id ) {
					$title = get_the_title( $post_id );
				}
			}
			
			if ( empty( $title ) ) {
				$title = 'Unknown Source';
			}

			$context_text .= "Source $i URL: $source_url\nSource $i Content: $text\n--\n\n";
			
			if ( ! in_array( $source_url, $seen_urls, true ) ) {
				$sources[] = [
					'url' => $source_url,
					'title' => $title,
					'score' => $result['score'],
				];
				$seen_urls[] = $source_url;
			}
			$i++;
		}

		return [
			'text' => $context_text,
			'sources' => $sources,
		];
	}

	/**
	 * Dispatches the LLM call to the appropriate provider.
	 *
	 * @param array $messages The conversation history.
	 * @return array|\WP_Error The API response or error.
	 */
	private static function call_llm( $messages ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$provider = $options['provider'] ?? 'openai';

		if ( 'ollama' === $provider ) {
			return self::call_ollama( $messages );
		}

		return self::call_openai( $messages );
	}

	/**
	 * Calls the OpenAI API.
	 *
	 * @param array $messages The conversation history.
	 * @return array|\WP_Error The API response or error.
	 */
	private static function call_openai( $messages ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$api_key = $options['api_key'] ?? '';
		$model = $options['model'] ?? 'gpt-4o';
		$temperature = isset( $options['temperature'] ) ? floatval( $options['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_api_key', 'OpenAI API Key is missing' );
		}

		$body = [
			'model' => $model,
			'messages' => $messages,
			'temperature' => $temperature,
		];

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode( $body ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		return json_decode( $response_body, true );
	}

	/**
	 * Calls the Ollama API.
	 *
	 * @param array $messages The conversation history.
	 * @return array|\WP_Error The API response or error.
	 */
	private static function call_ollama( $messages ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$url = $options['ollama_url'] ?? 'http://localhost:11434';
		$model = $options['ollama_model'] ?? 'llama3';
		$temperature = isset( $options['ollama_temperature'] ) ? floatval( $options['ollama_temperature'] ) : 0.7;
		$api_key = $options['ollama_api_key'] ?? '';

		// Ensure URL doesn't have trailing slash
		$url = untrailingslashit( $url );

		$body = [
			'model' => $model,
			'messages' => $messages,
			'stream' => false, // We don't support streaming yet
			'options' => [
				'temperature' => $temperature,
			],
		];

		$args = [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => json_encode( $body ),
			'timeout' => 60,
		];

		// Add API Key if provided (some Ollama proxies require it)
		if ( ! empty( $api_key ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_post( $url . '/api/chat', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		// Normalize response format to match OpenAI's structure for the frontend
		if ( isset( $data['message'] ) ) {
			return [
				'choices' => [
					[
						'message' => $data['message'],
					]
				]
			];
		}

		return $data;
	}

	public static function handle_get_history() {
		check_ajax_referer( 'ubc_simple_chat_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$chats = get_user_meta( $user_id, 'ubc_simple_chat_history', true ) ?: [];
		wp_send_json_success( $chats );
	}

	public static function handle_new_chat() {
		check_ajax_referer( 'ubc_simple_chat_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$chat_id = uniqid( 'chat_' );
		
		$chats = get_user_meta( $user_id, 'ubc_simple_chat_history', true ) ?: [];
		$chats[ $chat_id ] = [
			'id' => $chat_id,
			'title' => 'New Chat',
			'messages' => [],
			'created_at' => time(),
		];

		update_user_meta( $user_id, 'ubc_simple_chat_history', $chats );
		wp_send_json_success( [ 'chat_id' => $chat_id ] );
	}

	public static function handle_delete_chat() {
		check_ajax_referer( 'ubc_simple_chat_nonce', 'nonce' );
		$user_id = get_current_user_id();
		$chat_id = sanitize_text_field( $_POST['chat_id'] );

		$chats = get_user_meta( $user_id, 'ubc_simple_chat_history', true ) ?: [];
		if ( isset( $chats[ $chat_id ] ) ) {
			unset( $chats[ $chat_id ] );
			update_user_meta( $user_id, 'ubc_simple_chat_history', $chats );
		}
		wp_send_json_success();
	}

	private static function save_message( $user_id, $chat_id, $message ) {
		$chats = get_user_meta( $user_id, 'ubc_simple_chat_history', true ) ?: [];
		
		if ( ! isset( $chats[ $chat_id ] ) ) {
			// Create if not exists (shouldn't happen if flow is correct)
			$chats[ $chat_id ] = [
				'id' => $chat_id,
				'title' => 'New Chat',
				'messages' => [],
				'created_at' => time(),
			];
		}

		$chats[ $chat_id ]['messages'][] = $message;

		// Update title if it's the first user message
		if ( count( $chats[ $chat_id ]['messages'] ) === 1 && $message['role'] === 'user' ) {
			$chats[ $chat_id ]['title'] = substr( $message['content'], 0, 30 ) . '...';
		}

		update_user_meta( $user_id, 'ubc_simple_chat_history', $chats );
	}
}
