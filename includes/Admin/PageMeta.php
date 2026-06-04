<?php
namespace PHAB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a meta box to Pages and Posts that lets editors configure
 * a page-level A/B test redirect via a PostHog feature flag.
 *
 * When the flag value matches the configured variant key, the visitor
 * is transparently redirected to the Variant Page URL.
 */
class PageMeta {

	const META_KEY = '_phab_page_test';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post',      [ $this, 'save_meta' ] );
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	public function add_meta_box() {
		add_meta_box(
			'phab_page_test',
			__( 'PostHog A/B Page Test', 'posthog-ab' ),
			[ $this, 'render_meta_box' ],
			[ 'page', 'post' ],
			'side',
			'default'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'phab_page_test_nonce', 'phab_page_test_nonce' );
		$data        = get_post_meta( $post->ID, self::META_KEY, true ) ?: [];
		$flag_key    = $data['flag_key']    ?? '';
		$variant_key = $data['variant_key'] ?? 'test';
		$variant_url = $data['variant_url'] ?? '';
		?>
		<p>
			<label for="phab_flag_key"><strong><?php esc_html_e( 'Feature Flag Key', 'posthog-ab' ); ?></strong></label><br>
			<input type="text" id="phab_flag_key" name="phab_page_test[flag_key]"
				value="<?php echo esc_attr( $flag_key ); ?>" class="widefat"
				placeholder="my-page-test">
		</p>
		<p>
			<label for="phab_variant_key"><strong><?php esc_html_e( 'Variant Key', 'posthog-ab' ); ?></strong></label><br>
			<input type="text" id="phab_variant_key" name="phab_page_test[variant_key]"
				value="<?php echo esc_attr( $variant_key ); ?>" class="widefat"
				placeholder="test">
			<em style="font-size:11px;"><?php esc_html_e( 'PostHog flag value that triggers the redirect (e.g. "test").', 'posthog-ab' ); ?></em>
		</p>
		<p>
			<label for="phab_variant_url"><strong><?php esc_html_e( 'Variant Page URL', 'posthog-ab' ); ?></strong></label><br>
			<input type="url" id="phab_variant_url" name="phab_page_test[variant_url]"
				value="<?php echo esc_url( $variant_url ); ?>" class="widefat"
				placeholder="https://example.com/page-b">
			<em style="font-size:11px;"><?php esc_html_e( 'Visitors in the variant group are redirected here.', 'posthog-ab' ); ?></em>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save_meta( $post_id ) {
		if ( ! isset( $_POST['phab_page_test_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['phab_page_test_nonce'] ) ), 'phab_page_test_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['phab_page_test'] ) ? (array) $_POST['phab_page_test'] : [];

		$data = [
			'flag_key'    => sanitize_key( $raw['flag_key']    ?? '' ),
			'variant_key' => sanitize_key( $raw['variant_key'] ?? 'test' ),
			'variant_url' => esc_url_raw( $raw['variant_url'] ?? '' ),
		];

		update_post_meta( $post_id, self::META_KEY, $data );
	}
}
