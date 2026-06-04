<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * PostHog A/B Section Widget
 *
 * Each variant points to a saved Elementor local template. The widget
 * renders the selected template via get_builder_content_for_display(),
 * so each variant can contain any Elementor content.
 *
 * Workflow:
 *   1. Build your Control layout → save it as an Elementor template
 *      (Add Template → give it a name → Save).
 *   2. Build your Variant layout → save as a separate template.
 *   3. Drop this widget on any page, set the Feature Flag Key, and
 *      select Control Template / Variant Template from the dropdowns.
 */
class PHAB_AB_Section_Widget extends Widget_Base {

	public function get_name() {
		return 'phab_ab_section';
	}

	public function get_title() {
		return __( 'A/B Section', 'posthog-ab' );
	}

	public function get_icon() {
		return 'eicon-dual-button';
	}

	public function get_categories() {
		return [ 'posthog-ab' ];
	}

	public function get_keywords() {
		return [ 'ab', 'test', 'posthog', 'experiment', 'variant', 'template' ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns [ template_id => title ] for all saved local Elementor templates.
	 */
	private function get_local_templates(): array {
		$options = [ '' => __( '— Select a template —', 'posthog-ab' ) ];

		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		if ( ! $source ) {
			return $options;
		}

		$templates = $source->get_items();
		foreach ( $templates as $tpl ) {
			$options[ $tpl['template_id'] ] = $tpl['title'] . ' (' . $tpl['type'] . ')';
		}

		return $options;
	}

	/**
	 * Renders an Elementor local template by ID.
	 * Returns empty string if the ID is invalid.
	 */
	private function render_template( int $template_id ): string {
		if ( ! $template_id ) {
			return '';
		}
		return \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	protected function register_controls() {

		// ── Experiment ────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_experiment', [
			'label' => __( 'Experiment', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'flag_key', [
			'label'       => __( 'Feature Flag Key', 'posthog-ab' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'my-homepage-test',
			'description' => __( 'The PostHog feature flag key — must match exactly.', 'posthog-ab' ),
			'dynamic'     => [ 'active' => false ],
		] );

		$this->add_control( 'variant_key', [
			'label'       => __( 'Variant Key', 'posthog-ab' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => 'test',
			'placeholder' => 'test',
			'description' => __( 'The value PostHog returns for the B group. Control always uses "control".', 'posthog-ab' ),
			'dynamic'     => [ 'active' => false ],
		] );

		$this->end_controls_section();

		// ── Templates ─────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_templates', [
			'label' => __( 'Templates', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'phab_templates_notice', [
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'Build each variant as a saved Elementor template, then select it below. <a href="' . admin_url( 'edit.php?post_type=elementor_library' ) . '" target="_blank">Manage templates →</a>', 'posthog-ab' ),
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
		] );

		$this->add_control( 'control_template_id', [
			'label'       => __( 'Control Template (A)', 'posthog-ab' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => $this->get_local_templates(),
			'default'     => '',
			'description' => __( 'Shown to visitors in the control group.', 'posthog-ab' ),
		] );

		$this->add_control( 'variant_template_id', [
			'label'       => __( 'Variant Template (B)', 'posthog-ab' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => $this->get_local_templates(),
			'default'     => '',
			'description' => __( 'Shown to visitors in the variant group.', 'posthog-ab' ),
		] );

		$this->add_control( 'preview_variant', [
			'label'   => __( 'Preview in Editor', 'posthog-ab' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'control' => __( 'Control (A)', 'posthog-ab' ),
				'variant' => __( 'Variant (B)', 'posthog-ab' ),
				'both'    => __( 'Both (stacked)', 'posthog-ab' ),
			],
			'default' => 'control',
		] );

		$this->end_controls_section();

		// ── Style ─────────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Editor Labels', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'editor_label_color', [
			'label'     => __( 'Label Color', 'posthog-ab' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#6435c9',
			'selectors' => [
				'{{WRAPPER}} .phab-editor-label' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	protected function render() {
		$settings         = $this->get_settings_for_display();
		$flag_key         = sanitize_key( $settings['flag_key'] ?? '' );
		$variant_key      = sanitize_key( $settings['variant_key'] ?? 'test' );
		$control_id       = (int) ( $settings['control_template_id'] ?? 0 );
		$variant_id       = (int) ( $settings['variant_template_id'] ?? 0 );
		$is_editor        = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$preview          = $settings['preview_variant'] ?? 'control';

		// ── Validation notices (editor only) ──────────────────────────────────
		if ( $is_editor ) {
			if ( ! $flag_key ) {
				echo '<div style="padding:.75em 1em;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:8px;font-size:13px;">' .
					esc_html__( '⚠ Set a Feature Flag Key in the Experiment panel.', 'posthog-ab' ) .
					'</div>';
			}
			if ( ! $control_id || ! $variant_id ) {
				echo '<div style="padding:.75em 1em;background:#d1ecf1;border-left:4px solid #17a2b8;margin-bottom:8px;font-size:13px;">' .
					esc_html__( 'Select a Control and Variant template in the Templates panel.', 'posthog-ab' ) .
					'</div>';
			}
		}

		// ── Control slot ──────────────────────────────────────────────────────
		$control_style = '';
		if ( $is_editor && 'variant' === $preview ) {
			$control_style = 'display:none;';
		}

		printf(
			'<div data-phab-flag="%1$s" data-phab-variant="control" class="phab-ab-container phab-control" style="%2$s">',
			esc_attr( $flag_key ),
			esc_attr( $control_style )
		);
		if ( $is_editor ) {
			echo '<div class="phab-editor-label" style="display:inline-block;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;margin-bottom:6px;">' .
				esc_html__( 'A – Control', 'posthog-ab' ) . '</div>';
		}
		if ( $control_id ) {
			echo $this->render_template( $control_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( $is_editor ) {
			echo '<div style="padding:2em;border:2px dashed #ccc;text-align:center;color:#999;">' .
				esc_html__( 'No control template selected', 'posthog-ab' ) . '</div>';
		}
		echo '</div>';

		// ── Variant slot ──────────────────────────────────────────────────────
		$variant_style = '';
		if ( $is_editor && 'control' === $preview ) {
			$variant_style = 'display:none;';
		}

		printf(
			'<div data-phab-flag="%1$s" data-phab-variant="%2$s" class="phab-ab-container phab-variant" style="%3$s">',
			esc_attr( $flag_key ),
			esc_attr( $variant_key ),
			esc_attr( $variant_style )
		);
		if ( $is_editor ) {
			echo '<div class="phab-editor-label" style="display:inline-block;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;margin-bottom:6px;">' .
				sprintf(
					/* translators: %s = variant key */
					esc_html__( 'B – Variant (%s)', 'posthog-ab' ),
					esc_html( $variant_key )
				) . '</div>';
		}
		if ( $variant_id ) {
			echo $this->render_template( $variant_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( $is_editor ) {
			echo '<div style="padding:2em;border:2px dashed #ccc;text-align:center;color:#999;">' .
				esc_html__( 'No variant template selected', 'posthog-ab' ) . '</div>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?>
		<#
		var flagKey    = settings.flag_key || '';
		var variantKey = settings.variant_key || 'test';
		var preview    = settings.preview_variant || 'control';
		var controlId  = settings.control_template_id || 0;
		var variantId  = settings.variant_template_id || 0;
		#>

		<# if ( ! flagKey ) { #>
		<div style="padding:.75em 1em;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:8px;font-size:13px;">
			⚠ Set a Feature Flag Key in the Experiment panel.
		</div>
		<# } #>

		<# if ( ! controlId || ! variantId ) { #>
		<div style="padding:.75em 1em;background:#d1ecf1;border-left:4px solid #17a2b8;margin-bottom:8px;font-size:13px;">
			Select a Control and Variant template in the Templates panel.
		</div>
		<# } #>

		<div data-phab-flag="{{ flagKey }}" data-phab-variant="control"
			class="phab-ab-container phab-control"
			style="{{ 'variant' === preview ? 'display:none;' : '' }}">
			<div class="phab-editor-label"
				style="display:inline-block;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;margin-bottom:6px;">
				A – Control<# if ( controlId ) { #> (template #{{ controlId }})<# } #>
			</div>
			<# if ( ! controlId ) { #>
			<div style="padding:2em;border:2px dashed #ccc;text-align:center;color:#999;">No control template selected</div>
			<# } #>
		</div>

		<div data-phab-flag="{{ flagKey }}" data-phab-variant="{{ variantKey }}"
			class="phab-ab-container phab-variant"
			style="{{ 'control' === preview ? 'display:none;' : '' }}">
			<div class="phab-editor-label"
				style="display:inline-block;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;margin-bottom:6px;">
				B – Variant ({{ variantKey }})<# if ( variantId ) { #> (template #{{ variantId }})<# } #>
			</div>
			<# if ( ! variantId ) { #>
			<div style="padding:2em;border:2px dashed #ccc;text-align:center;color:#999;">No variant template selected</div>
			<# } #>
		</div>
		<?php
	}
}
