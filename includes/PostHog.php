<?php
namespace PHAB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles enqueueing the PostHog JS snippet and exposing
 * a small runtime helper (phab) for feature-flag resolution.
 */
class PostHog {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_head',            [ $this, 'inline_bootstrap' ], 1 );
	}

	private function settings() {
		return get_option( PHAB_OPTION_KEY, [] );
	}

	/**
	 * Enqueue the plugin's frontend helper script.
	 */
	public function enqueue() {
		wp_enqueue_script(
			'phab-frontend',
			PHAB_URL . 'assets/js/phab-frontend.js',
			[],
			PHAB_VERSION,
			false  // <head> so it runs before body content is parsed
		);
	}

	/**
	 * Inline the PostHog snippet and pass config to the helper.
	 * Placed at priority 1 so it runs before any other <head> output.
	 */
	public function inline_bootstrap() {
		$settings = $this->settings();
		$api_key  = ! empty( $settings['api_key'] )  ? $settings['api_key']  : '';
		$host     = ! empty( $settings['host'] )      ? $settings['host']     : 'https://app.posthog.com';

		if ( ! $api_key ) {
			// No key configured — output a stub so phab.getFlag() degrades gracefully.
			echo "<script>window.posthog={capture:function(){},identify:function(){},onFeatureFlags:function(cb){cb();}};</script>\n";
			return;
		}
		?>
<script>
!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]);t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(a!==void 0?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return a!=="posthog"&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
posthog.init(<?php echo wp_json_encode( $api_key ); ?>, {
	api_host: <?php echo wp_json_encode( $host ); ?>,
	loaded: function(ph) {
		ph.reloadFeatureFlags();
	}
});
</script>
		<?php
	}
}
