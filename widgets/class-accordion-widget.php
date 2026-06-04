<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Repeater;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PostHog Accordion Widget
 *
 * A clone of Elementor's Nested Accordion widget using <details>/<summary>
 * with full container support per panel.
 *
 * Requires Elementor 3.15+ with Nested Elements experiment enabled.
 */
class PHAB_Accordion_Widget extends Widget_Nested_Base {

	public function get_name() {
		return 'phab_accordion';
	}

	public function get_title() {
		return __( 'Accordion', 'posthog-ab' );
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	public function get_categories() {
		return [ 'posthog-ab' ];
	}

	public function get_keywords() {
		return [ 'accordion', 'toggle', 'collapse', 'faq' ];
	}

	public function get_style_depends(): array {
		return [ 'phab-accordion' ];
	}

	public function get_script_depends(): array {
		return [ 'phab-accordion' ];
	}

	public function get_html_wrapper_class() {
		return 'elementor-widget-phab-accordion';
	}

	// -------------------------------------------------------------------------
	// Nested Elements API — these are the correct method names
	// -------------------------------------------------------------------------

	protected function get_default_children_elements() {
		return [
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Item #1', 'posthog-ab' ), 'content_width' => 'full' ] ],
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Item #2', 'posthog-ab' ), 'content_width' => 'full' ] ],
			[ 'elType' => 'container', 'settings' => [ '_title' => __( 'Item #3', 'posthog-ab' ), 'content_width' => 'full' ] ],
		];
	}

	protected function get_default_repeater_title_setting_key() {
		return 'item_title';
	}

	protected function get_default_children_title() {
		/* translators: %d: Item index. */
		return __( 'Item #%d', 'posthog-ab' );
	}

	/**
	 * Where in the rendered HTML Elementor injects nested child elements.
	 * Must match exactly one element.
	 */
	protected function get_default_children_placeholder_selector() {
		return '.phab-n-accordion';
	}

	/**
	 * The element within each child slot that wraps the container.
	 * Tells the editor each container lives inside a <details> element.
	 */
	protected function get_default_children_container_placeholder_selector() {
		return '.phab-n-accordion-item';
	}

	/**
	 * Critical: tells the editor this widget has interlaced repeater+children,
	 * uses <details> nodes, and supports the improved repeater (Add Item button
	 * creates both a repeater entry AND a nested container simultaneously).
	 */
	protected function get_initial_config(): array {
		return array_merge( parent::get_initial_config(), [
			'support_improved_repeaters' => true,
			'target_container'           => [ '.phab-n-accordion' ],
			'node'                       => 'details',
			'is_interlaced'              => true,
		] );
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	protected function register_controls() {

		// ── Items ─────────────────────────────────────────────────────────────
		$this->start_controls_section( 'section_items', [
			'label' => __( 'Layout', 'posthog-ab' ),
		] );

		$repeater = new Repeater();

		$repeater->add_control( 'item_title', [
			'label'       => __( 'Title', 'posthog-ab' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => __( 'Item Title', 'posthog-ab' ),
			'label_block' => true,
			'dynamic'     => [ 'active' => true ],
		] );

		$repeater->add_control( 'element_css_id', [
			'label'   => __( 'CSS ID', 'posthog-ab' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '',
			'dynamic' => [ 'active' => true ],
			'title'   => __( 'Add your custom id WITHOUT the Pound key. e.g: my-id', 'posthog-ab' ),
			'style_transfer' => false,
		] );

		// Use Control_Nested_Repeater so "Add Item" creates both a repeater
		// entry and a child container together in the editor.
		$this->add_control( 'items', [
			'label'       => __( 'Items', 'posthog-ab' ),
			'type'        => Control_Nested_Repeater::CONTROL_TYPE,
			'fields'      => $repeater->get_controls(),
			'default'     => [
				[ 'item_title' => __( 'Item #1', 'posthog-ab' ) ],
				[ 'item_title' => __( 'Item #2', 'posthog-ab' ) ],
				[ 'item_title' => __( 'Item #3', 'posthog-ab' ) ],
			],
			'title_field' => '{{{ item_title }}}',
			'button_text' => __( 'Add Item', 'posthog-ab' ),
		] );

		// ── Icon ──────────────────────────────────────────────────────────────
		$this->add_control( 'heading_icon', [
			'type'      => Controls_Manager::HEADING,
			'label'     => __( 'Icon', 'posthog-ab' ),
			'separator' => 'before',
		] );

		$this->add_responsive_control( 'accordion_item_title_icon_position', [
			'label'   => __( 'Position', 'posthog-ab' ),
			'type'    => Controls_Manager::CHOOSE,
			'options' => [
				'start' => [ 'title' => __( 'Start', 'posthog-ab' ), 'icon' => 'eicon-h-align-left' ],
				'end'   => [ 'title' => __( 'End', 'posthog-ab' ),   'icon' => 'eicon-h-align-right' ],
			],
			'selectors_dictionary' => [
				'start' => '--phab-accordion-title-icon-order: -1;',
				'end'   => '--phab-accordion-title-icon-order: initial;',
			],
			'selectors' => [ '{{WRAPPER}}' => '{{VALUE}}' ],
		] );

		$this->add_control( 'accordion_item_title_icon', [
			'label'   => __( 'Expand', 'posthog-ab' ),
			'type'    => Controls_Manager::ICONS,
			'default' => [ 'value' => 'fas fa-plus', 'library' => 'fa-solid' ],
			'skin'    => 'inline',
			'label_block' => false,
		] );

		$this->add_control( 'accordion_item_title_icon_active', [
			'label'   => __( 'Collapse', 'posthog-ab' ),
			'type'    => Controls_Manager::ICONS,
			'default' => [ 'value' => 'fas fa-minus', 'library' => 'fa-solid' ],
			'condition' => [ 'accordion_item_title_icon[value]!' => '' ],
			'skin'    => 'inline',
			'label_block' => false,
		] );

		$this->add_control( 'title_tag', [
			'label'   => __( 'Title HTML Tag', 'posthog-ab' ),
			'type'    => Controls_Manager::SELECT,
			'options' => [
				'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
				'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
				'div' => 'div', 'span' => 'span', 'p' => 'p',
			],
			'default'     => 'div',
			'separator'   => 'before',
			'render_type' => 'template',
		] );

		$this->end_controls_section();

		// ── Interactions ──────────────────────────────────────────────────────
		$this->start_controls_section( 'section_interactions', [
			'label' => __( 'Interactions', 'posthog-ab' ),
		] );

		$this->add_control( 'default_state', [
			'label'              => __( 'Default State', 'posthog-ab' ),
			'type'               => Controls_Manager::SELECT,
			'options'            => [
				'expanded'     => __( 'First expanded', 'posthog-ab' ),
				'all_collapsed' => __( 'All collapsed', 'posthog-ab' ),
			],
			'default'            => 'expanded',
			'frontend_available' => true,
		] );

		$this->add_control( 'max_items_expended', [
			'label'              => __( 'Max Items Expanded', 'posthog-ab' ),
			'type'               => Controls_Manager::SELECT,
			'options'            => [
				'one'      => __( 'One', 'posthog-ab' ),
				'multiple' => __( 'Multiple', 'posthog-ab' ),
			],
			'default'            => 'one',
			'frontend_available' => true,
		] );

		$this->add_control( 'n_accordion_animation_duration', [
			'label'              => __( 'Animation Duration', 'posthog-ab' ),
			'type'               => Controls_Manager::SLIDER,
			'size_units'         => [ 's', 'ms' ],
			'default'            => [ 'unit' => 'ms', 'size' => 400 ],
			'frontend_available' => true,
		] );

		$this->end_controls_section();

		// ── Style: Accordion ──────────────────────────────────────────────────
		$this->start_controls_section( 'section_accordion_style', [
			'label' => __( 'Accordion', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'accordion_item_title_space_between', [
			'label'      => __( 'Space between Items', 'posthog-ab' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em', 'rem' ],
			'default'    => [ 'size' => 0 ],
			'selectors'  => [ '{{WRAPPER}}' => '--phab-accordion-item-space-between: {{SIZE}}{{UNIT}}' ],
		] );

		$this->add_responsive_control( 'accordion_border_radius', [
			'label'      => __( 'Border Radius', 'posthog-ab' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%' ],
			'selectors'  => [ '{{WRAPPER}}' => '--phab-accordion-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		] );

		$this->end_controls_section();

		// ── Style: Header ─────────────────────────────────────────────────────
		$this->start_controls_section( 'section_header_style', [
			'label' => __( 'Header', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'title_typography',
			'selector' => '{{WRAPPER}} .phab-n-accordion-item-title-text',
		] );

		$this->add_responsive_control( 'header_padding', [
			'label'      => __( 'Padding', 'posthog-ab' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'default'    => [ 'top' => '14', 'right' => '20', 'bottom' => '14', 'left' => '20', 'unit' => 'px', 'isLinked' => false ],
			'selectors'  => [ '{{WRAPPER}}' => '--phab-accordion-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		] );

		$this->start_controls_tabs( 'header_color_tabs' );

		foreach ( [ 'normal' => __( 'Normal', 'posthog-ab' ), 'hover' => __( 'Hover', 'posthog-ab' ), 'active' => __( 'Active', 'posthog-ab' ) ] as $state => $label ) {
			$var = '--phab-accordion-title-' . $state . '-color';
			$this->start_controls_tab( 'header_' . $state . '_tab', [ 'label' => $label ] );
			$this->add_control( 'header_' . $state . '_color', [
				'label'     => __( 'Text Color', 'posthog-ab' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [ '{{WRAPPER}}' => $var . ': {{VALUE}};' ],
			] );
			$this->add_group_control( Group_Control_Background::get_type(), [
				'name'     => 'header_' . $state . '_bg',
				'selector' => 'normal' === $state
					? '{{WRAPPER}} .phab-n-accordion-item:not([open]) > .phab-n-accordion-item-title'
					: ( 'hover' === $state
						? '{{WRAPPER}} .phab-n-accordion-item:not([open]) > .phab-n-accordion-item-title:hover'
						: '{{WRAPPER}} .phab-n-accordion-item[open] > .phab-n-accordion-item-title' ),
			] );
			$this->end_controls_tab();
		}

		$this->end_controls_tabs();

		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'      => 'header_border',
			'selector'  => '{{WRAPPER}} .phab-n-accordion-item-title',
			'separator' => 'before',
		] );

		$this->end_controls_section();

		// ── Style: Icon ───────────────────────────────────────────────────────
		$this->start_controls_section( 'section_icon_style', [
			'label' => __( 'Icon', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_responsive_control( 'icon_size', [
			'label'      => __( 'Size', 'posthog-ab' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'em' ],
			'default'    => [ 'unit' => 'px', 'size' => 15 ],
			'selectors'  => [ '{{WRAPPER}}' => '--phab-accordion-icon-size: {{SIZE}}{{UNIT}}' ],
		] );

		$this->add_control( 'icon_color', [
			'label'     => __( 'Color', 'posthog-ab' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}}' => '--phab-accordion-icon-normal-color: {{VALUE}};' ],
		] );

		$this->add_control( 'icon_active_color', [
			'label'     => __( 'Active Color', 'posthog-ab' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}}' => '--phab-accordion-icon-active-color: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// ── Style: Content ────────────────────────────────────────────────────
		$this->start_controls_section( 'section_content_style', [
			'label' => __( 'Content', 'posthog-ab' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Background::get_type(), [
			'name'     => 'content_bg',
			'selector' => '{{WRAPPER}} .phab-n-accordion-item > .e-con',
		] );

		$this->add_group_control( Group_Control_Border::get_type(), [
			'name'     => 'content_border',
			'selector' => '{{WRAPPER}} .phab-n-accordion-item > .e-con',
		] );

		$this->add_responsive_control( 'content_padding', [
			'label'      => __( 'Padding', 'posthog-ab' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .phab-n-accordion-item > .e-con' => '--padding-top: {{TOP}}{{UNIT}}; --padding-right: {{RIGHT}}{{UNIT}}; --padding-bottom: {{BOTTOM}}{{UNIT}}; --padding-left: {{LEFT}}{{UNIT}};',
			],
		] );

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	private function render_accordion_icons( array $settings ): string {
		$icon_html = Icons_Manager::try_get_icon_html( $settings['accordion_item_title_icon'], [ 'aria-hidden' => 'true' ] );

		$has_active = ! empty( $settings['accordion_item_title_icon_active']['value'] );
		$icon_active_html = $has_active
			? Icons_Manager::try_get_icon_html( $settings['accordion_item_title_icon_active'], [ 'aria-hidden' => 'true' ] )
			: $icon_html;

		return sprintf(
			'<span class="phab-n-accordion-item-title-icon"><span class="e-opened">%s</span><span class="e-closed">%s</span></span>',
			$icon_active_html,
			$icon_html
		);
	}

	protected function render() {
		$settings      = $this->get_settings_for_display();
		$items         = $settings['items'];
		$id_int        = substr( $this->get_id_int(), 0, 3 );
		$default_state = $settings['default_state'];
		$title_tag     = Utils::validate_html_tag( $settings['title_tag'] );
		$icons_html    = ! empty( $settings['accordion_item_title_icon']['value'] )
			? $this->render_accordion_icons( $settings )
			: '';

		$this->add_render_attribute( 'accordion', [
			'class'      => 'phab-n-accordion',
			'aria-label' => __( 'Accordion. Open links with Enter or Space, close with Escape, and navigate with Arrow Keys', 'posthog-ab' ),
		] );

		?>
		<div <?php $this->print_render_attribute_string( 'accordion' ); ?>>
		<?php foreach ( $items as $index => $item ) :
			$accordion_count = $index + 1;
			$item_id         = empty( $item['element_css_id'] )
				? 'phab-accordion-item-' . $id_int . $index
				: esc_attr( $item['element_css_id'] );
			$is_open         = 'expanded' === $default_state && 0 === $index;
			$item_key        = $this->get_repeater_setting_key( 'item_title', 'items', $index );
			$summary_key     = $this->get_repeater_setting_key( 'item_summary', 'items', $index );

			$this->add_render_attribute( $item_key, [
				'id'    => $item_id,
				'class' => 'phab-n-accordion-item',
			] );

			$this->add_render_attribute( $summary_key, [
				'class'              => 'phab-n-accordion-item-title',
				'data-accordion-index' => $accordion_count,
				'tabindex'           => 0 === $index ? 0 : -1,
				'aria-expanded'      => $is_open ? 'true' : 'false',
				'aria-controls'      => $item_id,
			] );

			// Capture child container output so we can also use it for FAQ schema.
			ob_start();
			$this->print_child( $index, $item_id );
			$item_content = ob_get_clean();
			?>
			<details <?php echo wp_kses_post( $this->get_render_attribute_string( $item_key ) . ( $is_open ? ' open' : '' ) ); ?>>
				<summary <?php $this->print_render_attribute_string( $summary_key ); ?>>
					<span class="phab-n-accordion-item-title-header">
						<<?php echo esc_attr( $title_tag ); ?> class="phab-n-accordion-item-title-text">
							<?php echo esc_html( $item['item_title'] ); ?>
						</<?php echo esc_attr( $title_tag ); ?>>
					</span>
					<?php echo $icons_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</summary>
				<?php echo $item_content; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</details>
		<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * print_child override — adds aria-labelledby to the child container.
	 */
	public function print_child( $index, $item_id = null ) {
		$children = $this->get_children();

		if ( empty( $children[ $index ] ) ) {
			return;
		}

		if ( $item_id ) {
			$add_aria = function( $should_render, $container ) use ( $item_id ) {
				$container->add_render_attribute( '_wrapper', [
					'role'            => 'region',
					'aria-labelledby' => $item_id,
				] );
				return $should_render;
			};
			add_filter( 'elementor/frontend/container/should_render', $add_aria, 10, 2 );
			$children[ $index ]->print_element();
			remove_filter( 'elementor/frontend/container/should_render', $add_aria );
		} else {
			$children[ $index ]->print_element();
		}
	}

	// -------------------------------------------------------------------------
	// Editor JS templates
	// -------------------------------------------------------------------------

	/**
	 * Template for a single item added via the "Add Item" button.
	 * Elementor outputs this as #tmpl-elementor-phab_accordion-content-single.
	 */
	protected function content_template_single_repeater_item() {
		?>
		<#
		const elementUid = view.getIDInt().toString().substring( 0, 3 ) + view.collection.length;

		view.addRenderAttribute( 'details-wrap', {
			'id':    'phab-accordion-item-' + elementUid,
			'class': [ 'phab-n-accordion-item', 'e-normal' ],
		}, null, true );

		view.addRenderAttribute( 'summary-wrap', {
			'class':               [ 'phab-n-accordion-item-title' ],
			'data-accordion-index': view.collection.length + 1,
			'tabindex':            -1,
			'aria-expanded':       'false',
			'aria-controls':       'phab-accordion-item-' + elementUid,
		}, null, true );

		view.addRenderAttribute( 'title-text', {
			'class':                  [ 'phab-n-accordion-item-title-text' ],
			'data-binding-index':     view.collection.length + 1,
			'data-binding-type':      'repeater-item',
			'data-binding-repeater-name': 'items',
			'data-binding-setting':   [ 'item_title', 'element_css_id' ],
			'data-binding-config':    JSON.stringify( {
				element_css_id: { editType: 'attribute', attr: 'id', selector: 'details' },
				item_title:     { editType: 'text' },
			} ),
		}, null, true );
		#>
		<details {{{ view.getRenderAttributeString( 'details-wrap' ) }}}>
			<summary {{{ view.getRenderAttributeString( 'summary-wrap' ) }}}>
				<span class="phab-n-accordion-item-title-header">
					<div {{{ view.getRenderAttributeString( 'title-text' ) }}}>{{{ data.item_title }}}</div>
				</span>
				<span class="phab-n-accordion-item-title-icon">
					<span class="e-opened"><i aria-hidden="true" class="fas fa-minus"></i></span>
					<span class="e-closed"><i aria-hidden="true" class="fas fa-plus"></i></span>
				</span>
			</summary>
		</details>
		<?php
	}

	/**
	 * Full editor JS template — renders all items from the repeater.
	 * Child containers are injected into .phab-n-accordion by Elementor separately.
	 */
	protected function content_template() {
		?>
		<div class="phab-n-accordion" aria-label="Accordion">
		<# if ( settings.items ) {
			const elementUid  = view.getIDInt().toString().substring( 0, 3 );
			const titleTag    = elementor.helpers.validateHTMLTag( settings.title_tag );
			const defaultState = settings.default_state;
			const iconExpand = elementor.helpers.renderIcon( view, settings['accordion_item_title_icon'], { 'aria-hidden': true }, 'i', 'object' ) ?? '';
			const iconCollapse = '' === settings.accordion_item_title_icon_active?.value
				? iconExpand
				: elementor.helpers.renderIcon( view, settings['accordion_item_title_icon_active'], { 'aria-hidden': true }, 'i', 'object' );

			_.each( settings.items, function( item, index ) {
				const itemUid      = elementUid + index;
				const itemId       = item.element_css_id || ( 'phab-accordion-item-' + itemUid );
				const isOpen       = 'expanded' === defaultState && 0 === index;
				const ariaExpanded = isOpen ? 'true' : 'false';
				const itemTitleKey = 'item-' + itemUid;
				const itemTextKey  = 'item-title-text-' + itemUid;

				view.addRenderAttribute( itemUid, {
					'id':    itemId,
					'class': [ 'phab-n-accordion-item', 'e-normal' ],
				} );
				if ( isOpen ) {
					view.addRenderAttribute( itemUid, 'open', true );
				}

				view.addRenderAttribute( itemTitleKey, {
					'class':               [ 'phab-n-accordion-item-title' ],
					'data-accordion-index': index + 1,
					'tabindex':            0 === index ? 0 : -1,
					'aria-expanded':       ariaExpanded,
					'aria-controls':       itemId,
				} );

				view.addRenderAttribute( itemTextKey, {
					'class':                  [ 'phab-n-accordion-item-title-text' ],
					'data-binding-index':     index + 1,
					'data-binding-type':      'repeater-item',
					'data-binding-repeater-name': 'items',
					'data-binding-setting':   [ 'item_title', 'element_css_id' ],
					'data-binding-config':    JSON.stringify( {
						element_css_id: { editType: 'attribute', attr: 'id', selector: 'details' },
						item_title:     { editType: 'text' },
					} ),
				} );
		#>
			<details {{{ view.getRenderAttributeString( itemUid ) }}}>
				<summary {{{ view.getRenderAttributeString( itemTitleKey ) }}}>
					<span class="phab-n-accordion-item-title-header">
						<{{{ titleTag }}} {{{ view.getRenderAttributeString( itemTextKey ) }}}>
							{{{ item.item_title }}}
						</{{{ titleTag }}}>
					</span>
					<# if ( settings.accordion_item_title_icon?.value ) { #>
					<span class="phab-n-accordion-item-title-icon">
						<span class="e-opened">{{{ iconCollapse.value }}}</span>
						<span class="e-closed">{{{ iconExpand.value }}}</span>
					</span>
					<# } #>
				</summary>
			</details>
		<# } ); } #>
		</div>
		<?php
	}
}
