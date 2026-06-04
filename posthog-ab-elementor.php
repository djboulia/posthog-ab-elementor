<?php

/**
 * Plugin Name: PostHog A/B Testing for Elementor
 * Plugin URI:  https://github.com/your-org/posthog-ab-elementor
 * Description: Run PostHog feature-flag–driven A/B tests on Elementor widgets, sections, and pages.
 * Version:     1.0.0
 * Author:      Charity Navigator
 * License:     GPL-2.0+
 * Text Domain: posthog-ab
 */

if (! defined('ABSPATH')) {
	exit;
}

define('PHAB_VERSION',     '1.0.0');
define('PHAB_FILE',        __FILE__);
define('PHAB_DIR',         plugin_dir_path(__FILE__));
define('PHAB_URL',         plugin_dir_url(__FILE__));
define('PHAB_OPTION_KEY',  'phab_settings');

// Autoloader
spl_autoload_register(function ($class) {
	$prefix = 'PHAB\\';
	if (strpos($class, $prefix) !== 0) {
		return;
	}
	$relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
	$file = PHAB_DIR . 'includes/' . $relative . '.php';
	if (file_exists($file)) {
		require $file;
	}
});

/**
 * Main plugin bootstrap — runs after all plugins are loaded.
 */
function phab_init()
{
	// Elementor must be active.
	if (! did_action('elementor/loaded')) {
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__('PostHog A/B for Elementor requires the Elementor plugin to be active.', 'posthog-ab') .
				'</p></div>';
		});
		return;
	}

	// Boot subsystems.
	\PHAB\PostHog::instance();
	\PHAB\Admin\Settings::instance();
	\PHAB\Admin\PageMeta::instance();
	\PHAB\PageVariant::instance();

	// Register Elementor widget category + widgets.
	add_action('elementor/elements/categories_registered', 'phab_register_category');
	add_action('elementor/widgets/register',               'phab_register_widgets');

	// Enqueue widget assets on the frontend.
	add_action('wp_enqueue_scripts', 'phab_enqueue_widget_assets');

	// Also enqueue inside the Elementor editor preview iframe.
	add_action('elementor/preview/enqueue_styles',  'phab_enqueue_widget_assets');
}
add_action('plugins_loaded', 'phab_init');

function phab_register_category($elements_manager)
{
	$elements_manager->add_category('posthog-ab', [
		'title' => __('PostHog A/B', 'posthog-ab'),
		'icon'  => 'fa fa-flask',
	]);
}

function phab_register_widgets($widgets_manager)
{
	require_once PHAB_DIR . 'widgets/class-ab-section-widget.php';
	$widgets_manager->register(new \PHAB_AB_Section_Widget());

	require_once PHAB_DIR . 'widgets/class-accordion-widget.php';
	$widgets_manager->register(new \PHAB_Accordion_Widget());

	require_once PHAB_DIR . 'widgets/phab-nested-accordion.php';
	$widgets_manager->register(new \PHAB_Nested_Accordion_Widget());
}

add_action('elementor/editor/before_enqueue_scripts', function () {

	wp_enqueue_script(
		'phab-nested-accordion',
		PHAB_URL . 'assets/js/editor/index.js',
		['elementor-editor'], // depends on Elementor editor JS
		'1.0',
		true
	);
});

function phab_enqueue_widget_assets()
{
	wp_register_style(
		'phab-accordion',
		PHAB_URL . 'assets/css/phab-accordion.css',
		[],
		PHAB_VERSION
	);

	wp_register_script(
		'phab-accordion',
		PHAB_URL . 'assets/js/phab-accordion.js',
		[],
		PHAB_VERSION,
		true // footer
	);

	wp_register_style(
		'phab-nested-accordion',
		PHAB_URL . 'assets/css/phab-nested-accordion.css',
		[],
		PHAB_VERSION
	);

	// wp_register_script(
	// 	'phab-nested-accordion',
	// 	PHAB_URL . 'assets/js/editor/index.js',
	// 	[],
	// 	PHAB_VERSION,
	// 	false
	// );

	wp_register_script(
		'phab-nested-accordion-frontend',
		PHAB_URL . 'assets/js/frontend/handlers/phab-nested-accordion.js',
		[],
		PHAB_VERSION,
		true // footer
	);

	// Assets are enqueued on demand via get_style_depends() / get_script_depends()
	// on the widget class — no need to enqueue globally here.
}
