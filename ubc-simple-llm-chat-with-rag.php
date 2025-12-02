<?php
/**
 * Plugin Name: UBC Simple LLM Chat with RAG
 * Description: A simple chat interface integrating with the UBC RAG system.
 * Version: 1.0.0
 * Author: UBC
 * Text Domain: ubc-simple-llm-chat-with-rag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UBC_SIMPLE_CHAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'UBC_SIMPLE_CHAT_URL', plugin_dir_url( __FILE__ ) );

// Autoloader for classes
spl_autoload_register( function ( $class ) {
	$prefix = 'UBC\\SimpleChat\\';
	$base_dir = UBC_SIMPLE_CHAT_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . 'class-' . str_replace( '_', '-', strtolower( $relative_class ) ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Initialize Plugin
function ubc_simple_chat_init() {
	// Initialize Settings
	\UBC\SimpleChat\Settings::init();

	// Initialize Chat Handler
	\UBC\SimpleChat\Chat_Handler::init();
}
add_action( 'plugins_loaded', 'ubc_simple_chat_init' );

// Register Shortcode
function ubc_simple_chat_shortcode() {
	// Enqueue Scripts and Styles
	wp_enqueue_style( 'ubc-simple-chat-css', UBC_SIMPLE_CHAT_URL . 'assets/css/chat.css', [], '1.0.0' );
	wp_enqueue_script( 'ubc-simple-chat-js', UBC_SIMPLE_CHAT_URL . 'assets/js/chat.js', [], '1.0.0', true );

	wp_localize_script( 'ubc-simple-chat-js', 'ubcSimpleChat', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ubc_simple_chat_nonce' ),
	] );

	ob_start();
	?>
	<div id="ubc-simple-chat-wrapper">
		<!-- Chat Interface will be injected here by JS or rendered here -->
		<div class="ubc-chat-sidebar">
			<button id="ubc-chat-new-btn">+ New Chat</button>
			<ul id="ubc-chat-list"></ul>
		</div>
		<div class="ubc-chat-main">
			<div id="ubc-chat-messages"></div>
			<div class="ubc-chat-input-area">
				<textarea id="ubc-chat-input" placeholder="Type your message..."></textarea>
				<button id="ubc-chat-send-btn">Send</button>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ubc_simple_chat', 'ubc_simple_chat_shortcode' );
