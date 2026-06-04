<?php
namespace PHAB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Admin settings page for PostHog A/B Elementor.
 * Located at Settings → PostHog A/B.
 */
class Settings {

	const SLUG = 'phab-settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',    [ $this, 'add_menu' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu() {
		add_options_page(
			__( 'PostHog A/B Settings', 'posthog-ab' ),
			__( 'PostHog A/B', 'posthog-ab' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings() {
		register_setting( 'phab_settings_group', PHAB_OPTION_KEY, [
			'sanitize_callback' => [ $this, 'sanitize' ],
		] );

		// ── Connection ────────────────────────────────────────────────────────
		add_settings_section(
			'phab_connection',
			__( 'PostHog Connection', 'posthog-ab' ),
			function() {
				echo '<p>' . esc_html__( 'Enter your PostHog project credentials. Find these in PostHog → Project Settings.', 'posthog-ab' ) . '</p>';
			},
			self::SLUG
		);

		add_settings_field( 'api_key', __( 'Project API Key', 'posthog-ab' ), [ $this, 'field_api_key' ], self::SLUG, 'phab_connection' );
		add_settings_field( 'host',    __( 'PostHog Host', 'posthog-ab' ),    [ $this, 'field_host' ],    self::SLUG, 'phab_connection' );

		// ── Experiments ───────────────────────────────────────────────────────
		add_settings_section(
			'phab_experiments',
			__( 'Registered Experiments', 'posthog-ab' ),
			function() {
				echo '<p>' .
					esc_html__( 'Document your experiments here for reference. This list does not affect live behaviour — actual routing is controlled by your PostHog feature flags.', 'posthog-ab' ) .
					'</p>';
			},
			self::SLUG
		);

		add_settings_field( 'experiments', '', [ $this, 'field_experiments' ], self::SLUG, 'phab_experiments' );
	}

	public function sanitize( $input ) {
		$output = [];
		$output['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
		$output['host']    = esc_url_raw( $input['host'] ?? 'https://app.posthog.com' );

		// Experiments: array of [ flag_key, description, status ]
		$experiments = [];
		if ( ! empty( $input['experiments'] ) && is_array( $input['experiments'] ) ) {
			foreach ( $input['experiments'] as $exp ) {
				if ( empty( $exp['flag_key'] ) ) {
					continue;
				}
				$experiments[] = [
					'flag_key'    => sanitize_key( $exp['flag_key'] ),
					'description' => sanitize_text_field( $exp['description'] ?? '' ),
					'status'      => in_array( $exp['status'], [ 'active', 'paused', 'completed' ], true ) ? $exp['status'] : 'active',
				];
			}
		}
		$output['experiments'] = $experiments;

		return $output;
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function field_api_key() {
		$settings = get_option( PHAB_OPTION_KEY, [] );
		$value    = $settings['api_key'] ?? '';
		echo '<input type="text" name="' . esc_attr( PHAB_OPTION_KEY ) . '[api_key]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="phc_xxxxxxxxxxxxx">';
		echo '<p class="description">' . esc_html__( 'Your PostHog project API key (public, starts with phc_).', 'posthog-ab' ) . '</p>';
	}

	public function field_host() {
		$settings = get_option( PHAB_OPTION_KEY, [] );
		$value    = $settings['host'] ?? 'https://app.posthog.com';
		echo '<input type="url" name="' . esc_attr( PHAB_OPTION_KEY ) . '[host]" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Use https://eu.posthog.com for EU Cloud, or your self-hosted URL.', 'posthog-ab' ) . '</p>';
	}

	public function field_experiments() {
		$settings    = get_option( PHAB_OPTION_KEY, [] );
		$experiments = $settings['experiments'] ?? [];
		$statuses    = [
			'active'    => __( 'Active', 'posthog-ab' ),
			'paused'    => __( 'Paused', 'posthog-ab' ),
			'completed' => __( 'Completed', 'posthog-ab' ),
		];
		?>
		<table class="widefat striped" id="phab-experiments-table" style="max-width:700px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feature Flag Key', 'posthog-ab' ); ?></th>
					<th><?php esc_html_e( 'Description', 'posthog-ab' ); ?></th>
					<th><?php esc_html_e( 'Status', 'posthog-ab' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="phab-experiments-rows">
				<?php foreach ( $experiments as $i => $exp ) : ?>
				<tr>
					<td><input type="text" name="<?php echo esc_attr( PHAB_OPTION_KEY ); ?>[experiments][<?php echo (int) $i; ?>][flag_key]" value="<?php echo esc_attr( $exp['flag_key'] ); ?>" class="regular-text" placeholder="my-flag"></td>
					<td><input type="text" name="<?php echo esc_attr( PHAB_OPTION_KEY ); ?>[experiments][<?php echo (int) $i; ?>][description]" value="<?php echo esc_attr( $exp['description'] ); ?>" class="regular-text"></td>
					<td>
						<select name="<?php echo esc_attr( PHAB_OPTION_KEY ); ?>[experiments][<?php echo (int) $i; ?>][status]">
							<?php foreach ( $statuses as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $exp['status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td><button type="button" class="button phab-remove-row"><?php esc_html_e( 'Remove', 'posthog-ab' ); ?></button></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="phab-add-experiment"><?php esc_html_e( '+ Add Experiment', 'posthog-ab' ); ?></button>
		</p>
		<script>
		(function(){
			var tbody = document.getElementById('phab-experiments-rows');
			var key   = <?php echo wp_json_encode( PHAB_OPTION_KEY ); ?>;

			document.getElementById('phab-add-experiment').addEventListener('click', function(){
				var idx = tbody.rows.length;
				var tr  = document.createElement('tr');
				tr.innerHTML =
					'<td><input type="text" name="'+key+'[experiments]['+idx+'][flag_key]" class="regular-text" placeholder="my-flag"></td>' +
					'<td><input type="text" name="'+key+'[experiments]['+idx+'][description]" class="regular-text"></td>' +
					'<td><select name="'+key+'[experiments]['+idx+'][status]">' +
						'<option value="active">Active</option>' +
						'<option value="paused">Paused</option>' +
						'<option value="completed">Completed</option>' +
					'</select></td>' +
					'<td><button type="button" class="button phab-remove-row">Remove</button></td>';
				tbody.appendChild(tr);
			});

			tbody.addEventListener('click', function(e){
				if (e.target.classList.contains('phab-remove-row')) {
					e.target.closest('tr').remove();
				}
			});
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PostHog A/B Settings', 'posthog-ab' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'phab_settings_group' );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
