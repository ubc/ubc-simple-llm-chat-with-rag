<?php

namespace UBC\SimpleChat;

class Settings {

	/**
	 * Initialize settings.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Add the admin menu page.
	 *
	 * @return void
	 */
	public static function add_admin_menu() {
		add_options_page(
			'UBC Chat RAG Settings',
			'UBC Chat RAG',
			'manage_options',
			'ubc-simple-chat-rag',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting( 'ubc_simple_chat_options', 'ubc_simple_chat_options' );

		add_settings_section(
			'ubc_simple_chat_main_section',
			'Main Settings',
			null,
			'ubc-simple-chat-rag'
		);

		add_settings_field(
			'provider',
			'LLM Provider',
			[ __CLASS__, 'render_select_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'provider',
				'options'   => [
					'openai' => 'OpenAI',
					'ollama' => 'Ollama',
				],
			]
		);

		// --- OpenAI Settings ---
		add_settings_field(
			'model',
			'OpenAI Model Name',
			[ __CLASS__, 'render_text_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'model',
				'description' => 'e.g., gpt-4o, gpt-3.5-turbo',
				'class' => 'openai-setting',
			]
		);

		add_settings_field(
			'api_key',
			'OpenAI API Key',
			[ __CLASS__, 'render_password_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'api_key',
				'class' => 'openai-setting',
			]
		);

		add_settings_field(
			'temperature',
			'OpenAI Temperature',
			[ __CLASS__, 'render_number_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'temperature',
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.1,
				'description' => '0.0 to 1.0',
				'class' => 'openai-setting',
			]
		);

		// --- Ollama Settings ---
		add_settings_field(
			'ollama_url',
			'Ollama Server URL',
			[ __CLASS__, 'render_text_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'ollama_url',
				'description' => 'e.g., http://localhost:11434',
				'class' => 'ollama-setting',
			]
		);

		add_settings_field(
			'ollama_model',
			'Ollama Model Name',
			[ __CLASS__, 'render_text_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'ollama_model',
				'description' => 'e.g., llama3, mistral',
				'class' => 'ollama-setting',
			]
		);

		add_settings_field(
			'ollama_api_key',
			'Ollama API Key',
			[ __CLASS__, 'render_password_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'ollama_api_key',
				'description' => 'Optional. Only if your Ollama instance requires auth.',
				'class' => 'ollama-setting',
			]
		);

		add_settings_field(
			'ollama_temperature',
			'Ollama Temperature',
			[ __CLASS__, 'render_number_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'ollama_temperature',
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.1,
				'description' => '0.0 to 1.0',
				'class' => 'ollama-setting',
			]
		);

		add_settings_field(
			'system_prompt',
			'System Prompt',
			[ __CLASS__, 'render_textarea_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'system_prompt',
			]
		);

		add_settings_field(
			'min_sim_score',
			'Minimum Similarity Score',
			[ __CLASS__, 'render_number_field' ],
			'ubc-simple-chat-rag',
			'ubc_simple_chat_main_section',
			[
				'label_for' => 'min_sim_score',
				'min'       => 0,
				'max'       => 1,
				'step'      => 0.05,
				'description' => 'Minimum score (0.0 - 1.0) for RAG results to be included.',
			]
		);
	}

	/**
	 * Renders the settings page HTML.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h1>UBC Simple LLM Chat with RAG Settings</h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'ubc_simple_chat_options' );
				do_settings_sections( 'ubc-simple-chat-rag' );
				submit_button();
				?>
			</form>
		</div>
		<script>
			// Simple inline script to toggle settings based on provider
			// Why not a separate file? For a simple settings page with minimal logic, 
			// inline is often cleaner than enqueuing a separate asset that needs to be managed.
			document.addEventListener('DOMContentLoaded', function() {
				const providerSelect = document.getElementById('provider');
				const openaiRows = document.querySelectorAll('.openai-setting');
				const ollamaRows = document.querySelectorAll('.ollama-setting');

				function toggleSettings() {
					const provider = providerSelect.value;
					if ( provider === 'openai' ) {
						openaiRows.forEach(row => row.style.display = 'table-row');
						ollamaRows.forEach(row => row.style.display = 'none');
					} else {
						openaiRows.forEach(row => row.style.display = 'none');
						ollamaRows.forEach(row => row.style.display = 'table-row');
					}
				}

				if ( providerSelect ) {
					providerSelect.addEventListener('change', toggleSettings);
					toggleSettings(); // Initial state
				}
			});
		</script>
		<style>
			/* Helper class to target rows. WordPress adds classes to tr based on field ID but we need a group selector */
		</style>
		<?php
	}

	/**
	 * Renders a text input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_text_field( $args ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		// Add a class to the row (tr) for toggling visibility. 
		// We use a little JS hack in the field output to close the td and add a class to the parent tr? 
		// No, that's messy. Better to use the 'class' argument in add_settings_field if WP supported it well for TRs, 
		// but standard WP Settings API doesn't easily let you add classes to the TR.
		// Instead, we'll rely on the row ID which WP generates as 'tr-field_id' or similar, 
		// OR we can just wrap the input in a div with the class and use JS to find the parent TR.
		$class = isset( $args['class'] ) ? $args['class'] : '';
		?>
		<input type="text" name="ubc_simple_chat_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $class ) : ?>
			<script>
				// Helper to add class to parent TR for toggling
				document.currentScript.closest('tr').classList.add('<?php echo esc_js( $class ); ?>');
			</script>
		<?php endif;
	}

	/**
	 * Renders a password input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_password_field( $args ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		$class = isset( $args['class'] ) ? $args['class'] : '';
		?>
		<input type="password" name="ubc_simple_chat_options[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( $class ) : ?>
			<script>
				document.currentScript.closest('tr').classList.add('<?php echo esc_js( $class ); ?>');
			</script>
		<?php endif;
	}

	/**
	 * Renders a number input field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_number_field( $args ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		$class = isset( $args['class'] ) ? $args['class'] : '';
		?>
		<input type="number" 
			name="ubc_simple_chat_options[<?php echo esc_attr( $args['label_for'] ); ?>]" 
			value="<?php echo esc_attr( $value ); ?>"
			min="<?php echo esc_attr( $args['min'] ); ?>"
			max="<?php echo esc_attr( $args['max'] ); ?>"
			step="<?php echo esc_attr( $args['step'] ); ?>"
		>
		<?php if ( isset( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $class ) : ?>
			<script>
				document.currentScript.closest('tr').classList.add('<?php echo esc_js( $class ); ?>');
			</script>
		<?php endif;
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_textarea_field( $args ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<textarea name="ubc_simple_chat_options[<?php echo esc_attr( $args['label_for'] ); ?>]" rows="5" cols="50"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Renders a select dropdown field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_select_field( $args ) {
		$options = get_option( 'ubc_simple_chat_options' );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		?>
		<select name="ubc_simple_chat_options[<?php echo esc_attr( $args['label_for'] ); ?>]" id="<?php echo esc_attr( $args['label_for'] ); ?>">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
