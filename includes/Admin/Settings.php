<?php

namespace PHAB\Admin;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * WP Admin settings page for PostHog A/B Elementor.
 * Located at Settings → PostHog A/B.
 */
class Settings
{

	const SLUG = 'phab-settings';

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
		add_action('admin_menu',    [$this, 'add_menu']);
		add_action('admin_init',    [$this, 'register_settings']);
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu()
	{
		add_options_page(
			__('PostHog A/B Settings', 'posthog-ab'),
			__('PostHog A/B', 'posthog-ab'),
			'manage_options',
			self::SLUG,
			[$this, 'render_page']
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings()
	{
		register_setting('phab_settings_group', PHAB_OPTION_KEY, [
			'sanitize_callback' => [$this, 'sanitize'],
		]);

		// ── Connection ────────────────────────────────────────────────────────
		add_settings_section(
			'phab_connection',
			__('PostHog Connection', 'posthog-ab'),
			function () {
				echo '<p>' . esc_html__('Enter your PostHog project credentials. Find these in PostHog → Project Settings.', 'posthog-ab') . '</p>';
			},
			self::SLUG
		);

		add_settings_field('api_key', __('Project API Key', 'posthog-ab'), [$this, 'field_api_key'], self::SLUG, 'phab_connection');
		add_settings_field('host',    __('PostHog Host', 'posthog-ab'),    [$this, 'field_host'],    self::SLUG, 'phab_connection');

		// ── Experiments ───────────────────────────────────────────────────────
		add_settings_section(
			'phab_experiments',
			__('Registered Experiments', 'posthog-ab'),
			function () {
				echo '<p>' .
					esc_html__('Document your experiments here for reference. This list does not affect live behaviour — actual routing is controlled by your PostHog feature flags.', 'posthog-ab') .
					'</p>';
			},
			self::SLUG
		);

		add_settings_field('experiments', '', [$this, 'field_experiments'], self::SLUG, 'phab_experiments');
	}

	public function sanitize($input)
	{
		$output = [];
		$output['api_key'] = sanitize_text_field($input['api_key'] ?? '');
		$output['host']    = esc_url_raw($input['host'] ?? 'https://app.posthog.com');

		// Experiments: array of [ flag_key, description, status ]
		$experiments = [];
		if (! empty($input['experiments']) && is_array($input['experiments'])) {
			foreach ($input['experiments'] as $exp) {
				if (empty($exp['flag_key'])) {
					continue;
				}
				$experiments[] = [
					'flag_key'    => sanitize_key($exp['flag_key']),
					'description' => sanitize_text_field($exp['description'] ?? ''),
					'status'      => in_array($exp['status'], ['active', 'paused', 'completed'], true) ? $exp['status'] : 'active',
				];
			}
		}
		$output['experiments'] = $experiments;

		return $output;
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function field_api_key()
	{
		$settings = get_option(PHAB_OPTION_KEY, []);
		$value    = $settings['api_key'] ?? '';
		echo '<input type="text" name="' . esc_attr(PHAB_OPTION_KEY) . '[api_key]" value="' . esc_attr($value) . '" class="regular-text" placeholder="phc_xxxxxxxxxxxxx">';
		echo '<p class="description">' . esc_html__('Your PostHog project API key (public, starts with phc_).', 'posthog-ab') . '</p>';
	}

	public function field_host()
	{
		$settings = get_option(PHAB_OPTION_KEY, []);
		$value    = $settings['host'] ?? 'https://app.posthog.com';
		echo '<input type="url" name="' . esc_attr(PHAB_OPTION_KEY) . '[host]" value="' . esc_attr($value) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__('Use https://eu.posthog.com for EU Cloud, or your self-hosted URL.', 'posthog-ab') . '</p>';
	}

	public function field_experiments()
	{
		$settings    = get_option(PHAB_OPTION_KEY, []);
		$experiments = $settings['experiments'] ?? [];
		$statuses    = [
			'active'    => __('Active', 'posthog-ab'),
			'paused'    => __('Paused', 'posthog-ab'),
			'completed' => __('Completed', 'posthog-ab'),
		];
?>
		<table class="widefat striped" id="phab-experiments-table" style="max-width:700px;">
			<thead>
				<tr>
					<th><?php esc_html_e('Feature Flag Key', 'posthog-ab'); ?></th>
					<th><?php esc_html_e('Description', 'posthog-ab'); ?></th>
					<th><?php esc_html_e('Status', 'posthog-ab'); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="phab-experiments-rows">
				<?php foreach ($experiments as $i => $exp) : ?>
					<tr>
						<td><input type="text" name="<?php echo esc_attr(PHAB_OPTION_KEY); ?>[experiments][<?php echo (int) $i; ?>][flag_key]" value="<?php echo esc_attr($exp['flag_key']); ?>" class="regular-text" placeholder="my-flag"></td>
						<td><input type="text" name="<?php echo esc_attr(PHAB_OPTION_KEY); ?>[experiments][<?php echo (int) $i; ?>][description]" value="<?php echo esc_attr($exp['description']); ?>" class="regular-text"></td>
						<td>
							<select name="<?php echo esc_attr(PHAB_OPTION_KEY); ?>[experiments][<?php echo (int) $i; ?>][status]">
								<?php foreach ($statuses as $val => $label) : ?>
									<option value="<?php echo esc_attr($val); ?>" <?php selected($exp['status'], $val); ?>><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><button type="button" class="button phab-remove-row"><?php esc_html_e('Remove', 'posthog-ab'); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="phab-add-experiment"><?php esc_html_e('+ Add Experiment', 'posthog-ab'); ?></button>
		</p>
		<script>
			(function() {
				var tbody = document.getElementById('phab-experiments-rows');
				var key = <?php echo wp_json_encode(PHAB_OPTION_KEY); ?>;

				document.getElementById('phab-add-experiment').addEventListener('click', function() {
					var idx = tbody.rows.length;
					var tr = document.createElement('tr');
					tr.innerHTML =
						'<td><input type="text" name="' + key + '[experiments][' + idx + '][flag_key]" class="regular-text" placeholder="my-flag"></td>' +
						'<td><input type="text" name="' + key + '[experiments][' + idx + '][description]" class="regular-text"></td>' +
						'<td><select name="' + key + '[experiments][' + idx + '][status]">' +
						'<option value="active">Active</option>' +
						'<option value="paused">Paused</option>' +
						'<option value="completed">Completed</option>' +
						'</select></td>' +
						'<td><button type="button" class="button phab-remove-row">Remove</button></td>';
					tbody.appendChild(tr);
				});

				tbody.addEventListener('click', function(e) {
					if (e.target.classList.contains('phab-remove-row')) {
						e.target.closest('tr').remove();
					}
				});
			})();
		</script>
	<?php
	}

	// -------------------------------------------------------------------------
	// Page variant index
	// -------------------------------------------------------------------------

	/**
	 * Returns all pages/posts that have an active page-level A/B test configured.
	 * Queries by the presence of a non-empty flag_key in the meta.
	 */
	private function get_page_variants(): array
	{
		global $wpdb;

		// We store the meta as a serialised array; the quickest way to find
		// rows with a non-empty flag_key is a LIKE on the serialised value.
		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = %s
			   AND pm.meta_value LIKE %s
			   AND p.post_status != 'trash'
			 ORDER BY p.post_title ASC",
			\PHAB\Admin\PageMeta::META_KEY,
			'%' . $wpdb->esc_like('flag_key') . '%'
		));

		$rows = [];
		foreach ($results as $row) {
			$data = maybe_unserialize($row->meta_value);
			if (empty($data['flag_key'])) {
				continue;  // skip rows where flag_key is actually empty
			}
			$rows[] = [
				'id'          => (int) $row->ID,
				'title'       => $row->post_title ?: __('(no title)', 'posthog-ab'),
				'post_type'   => $row->post_type,
				'post_status' => $row->post_status,
				'flag_key'    => $data['flag_key'],
				'variant_key' => $data['variant_key'] ?? 'test',
				'variant_url' => $data['variant_url'] ?? '',
			];
		}
		return $rows;
	}

	// -------------------------------------------------------------------------
	// Widget A/B index
	// -------------------------------------------------------------------------

	/**
	 * Recursively walks an Elementor elements tree and collects every
	 * phab-container widget's settings along with the post it lives in.
	 */
	private function collect_ab_widgets(array $elements, int $post_id, string $post_title, string $post_type, string $post_status, array &$out): void
	{
		foreach ($elements as $el) {
			if (($el['widgetType'] ?? '') === 'phab-container') {
				$s = $el['settings'] ?? [];
				if (! empty($s['flag_key'])) {
					$out[] = [
						'id'             => $post_id,
						'title'          => $post_title,
						'post_type'      => $post_type,
						'post_status'    => $post_status,
						'flag_key'       => $s['flag_key'],
						'variant_key'    => $s['variant_key'] ?? 'test',
					];
				}
			}
			// Recurse into child elements (sections → columns → widgets, or containers).
			if (! empty($el['elements'])) {
				$this->collect_ab_widgets($el['elements'], $post_id, $post_title, $post_type, $post_status, $out);
			}
		}
	}

	/**
	 * Returns every phab-container widget instance found across all published content.
	 */
	private function get_ab_widget_instances(): array
	{
		global $wpdb;

		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_type, p.post_status, pm.meta_value
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_elementor_data'
			   AND pm.meta_value LIKE %s
			   AND p.post_status = 'publish'
			 ORDER BY p.post_title ASC",
			'%' . $wpdb->esc_like('phab-container') . '%'
		));

		$rows = [];
		foreach ($results as $row) {
			$data = json_decode($row->meta_value, true);
			if (! is_array($data)) {
				continue;
			}
			$this->collect_ab_widgets(
				$data,
				(int) $row->ID,
				$row->post_title ?: __('(no title)', 'posthog-ab'),
				$row->post_type,
				$row->post_status,
				$rows
			);
		}
		return $rows;
	}

	private function render_ab_widgets_table(): void
	{
		$rows = $this->get_ab_widget_instances();
	?>
		<h2><?php esc_html_e('Active Widget A/B Tests', 'posthog-ab'); ?></h2>
		<p><?php esc_html_e('Every A/B widget instance found across all Elementor-built pages and posts.', 'posthog-ab'); ?></p>
		<?php if (empty($rows)) : ?>
			<p><em><?php esc_html_e('No A/B widgets found. Add the widget to any Elementor page to get started.', 'posthog-ab'); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e('Page / Post', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Type', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Status', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Feature Flag Key', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Variant Key', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Actions', 'posthog-ab'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row) :
						$edit_url     = get_edit_post_link($row['id']) . '&action=elementor';
						$view_url     = get_permalink($row['id']);
					?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url($edit_url); ?>">
										<?php echo esc_html($row['title']); ?>
									</a>
								</strong>
							</td>
							<td><?php echo esc_html($row['post_type']); ?></td>
							<td><?php echo esc_html(ucfirst($row['post_status'])); ?></td>
							<td><code><?php echo esc_html($row['flag_key']); ?></code></td>
							<td><code><?php echo esc_html($row['variant_key']); ?></code></td>
							<td>
								<a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
									<?php esc_html_e('Edit', 'posthog-ab'); ?>
								</a>
								<?php if ('publish' === $row['post_status'] && $view_url) : ?>
									<a href="<?php echo esc_url($view_url); ?>" class="button button-small" target="_blank" rel="noopener">
										<?php esc_html_e('View', 'posthog-ab'); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	private function render_page_variants_table(): void
	{
		$rows = $this->get_page_variants();
		?>
		<h2><?php esc_html_e('Active Page Variant Tests', 'posthog-ab'); ?></h2>
		<p><?php esc_html_e('All pages and posts with a PostHog page-level A/B test configured.', 'posthog-ab'); ?></p>
		<?php if (empty($rows)) : ?>
			<p><em><?php esc_html_e('No page variant tests configured yet. Edit any page or post and fill in the "PostHog A/B Page Test" sidebar box to add one.', 'posthog-ab'); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th><?php esc_html_e('Page / Post', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Type', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Status', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Feature Flag Key', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Variant Key', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Variant URL', 'posthog-ab'); ?></th>
						<th><?php esc_html_e('Actions', 'posthog-ab'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row) :
						$edit_url    = get_edit_post_link($row['id']);
						$view_url    = get_permalink($row['id']);
						$status_label = ucfirst($row['post_status']);
					?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url($edit_url); ?>">
										<?php echo esc_html($row['title']); ?>
									</a>
								</strong>
							</td>
							<td><?php echo esc_html($row['post_type']); ?></td>
							<td><?php echo esc_html($status_label); ?></td>
							<td><code><?php echo esc_html($row['flag_key']); ?></code></td>
							<td><code><?php echo esc_html($row['variant_key']); ?></code></td>
							<td>
								<?php if ($row['variant_url']) : ?>
									<a href="<?php echo esc_url($row['variant_url']); ?>" target="_blank" rel="noopener">
										<?php echo esc_html($row['variant_url']); ?>
									</a>
								<?php else : ?>
									<em style="color:#999;"><?php esc_html_e('Not set', 'posthog-ab'); ?></em>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
									<?php esc_html_e('Edit', 'posthog-ab'); ?>
								</a>
								<?php if ('publish' === $row['post_status'] && $view_url) : ?>
									<a href="<?php echo esc_url($view_url); ?>" class="button button-small" target="_blank" rel="noopener">
										<?php esc_html_e('View', 'posthog-ab'); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page()
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e('PostHog A/B Settings', 'posthog-ab'); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('phab_settings_group');
				do_settings_sections(self::SLUG);
				submit_button();
				?>
			</form>

			<hr>
			<?php $this->render_ab_widgets_table(); ?>

			<hr>
			<?php $this->render_page_variants_table(); ?>
		</div>
<?php
	}
}
