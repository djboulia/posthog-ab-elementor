<?php
namespace PHAB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-level A/B variant redirect.
 *
 * PostHog feature flags are resolved client-side (JS), so we cannot do a
 * server-side PHP redirect on the first request.  Instead we inject a small
 * inline script into <head> that reads the flag via posthog.getFeatureFlag()
 * and, if it matches the configured variant key, immediately replaces the page
 * URL with the variant URL using window.location.replace() — no flash of the
 * wrong page and no extra round-trip for returning visitors whose flag is
 * already cached by the PostHog SDK.
 *
 * The script only fires on pages that have a page-level A/B test configured
 * via the "PostHog A/B Page Test" meta box (see Admin/PageMeta.php).
 */
class PageVariant {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priority 5 — after posthog snippet (priority 1) but before body content.
		add_action( 'wp_head', [ $this, 'maybe_inject_redirect' ], 5 );
	}

	public function maybe_inject_redirect() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		$data    = get_post_meta( $post_id, \PHAB\Admin\PageMeta::META_KEY, true );

		if ( empty( $data['flag_key'] ) || empty( $data['variant_url'] ) ) {
			return;
		}

		$flag_key    = wp_json_encode( $data['flag_key'] );
		$variant_key = wp_json_encode( $data['variant_key'] ?? 'test' );
		$variant_url = wp_json_encode( $data['variant_url'] );

		?>
<script>
(function() {
  var TIMEOUT = 2500;
  var done    = false;

  function maybeRedirect() {
    if (done) return;
    done = true;
    if (!window.posthog || !window.posthog.getFeatureFlag) return;
    var val = window.posthog.getFeatureFlag(<?php echo $flag_key; // phpcs:ignore WordPress.Security.EscapeOutput ?>);
    if (val === <?php echo $variant_key; // phpcs:ignore WordPress.Security.EscapeOutput ?>) {
      window.location.replace(<?php echo $variant_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>);
    }
  }

  // Attempt resolution once flags are ready.
  if (window.posthog && window.posthog.onFeatureFlags) {
    window.posthog.onFeatureFlags(function() {
      clearTimeout(timer);
      maybeRedirect();
    });
  }

  // Safety: if PostHog takes too long, do nothing (show control).
  var timer = setTimeout(maybeRedirect, TIMEOUT);
})();
</script>
		<?php
	}
}
