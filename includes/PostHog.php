<?php

namespace PHAB;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Handles enqueueing the PostHog JS snippet and exposing
 * a small runtime helper (phab) for feature-flag resolution.
 */
class PostHog
{

	private static $instance = null;

	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueue']);
	}

	private function settings()
	{
		return get_option(PHAB_OPTION_KEY, []);
	}

	/**
	 * Enqueue the plugin's frontend helper script.
	 */
	public function enqueue()
	{
		wp_enqueue_script(
			'phab-frontend',
			PHAB_URL . 'assets/js/phab-frontend.js',
			[],
			PHAB_VERSION,
			false  // <head> so it runs before body content is parsed
		);
	}
}
