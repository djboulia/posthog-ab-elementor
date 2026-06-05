<?php

use Elementor\Controls_Manager;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Plugin;
use Elementor\Repeater;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Elementor Nested Accordion widget.
 *
 * Elementor widget that displays a collapsible display of content in an
 * accordion style.
 *
 * @since 3.15.0
 */
class PHAB_Container_Widget extends Widget_Nested_Base
{

    private $optimized_markup = null;
    private $widget_container_selector = '';

    protected function is_dynamic_content(): bool
    {
        return true;
    }

    public function get_name()
    {
        return 'phab-container';
    }

    public function get_title()
    {
        return esc_html__('PostHog A/B Test', 'posthog-ab');
    }

    public function get_icon()
    {
        return 'eicon-accordion';
    }

    public function get_keywords()
    {
        return ['nested', 'toggle', 'ab', 'test', 'posthog',];
    }

    public function get_style_depends(): array
    {
        return ['phab-container'];
    }

    public function get_script_depends(): array
    {
        return ['phab-container-frontend'];
    }

    public function show_in_panel(): bool
    {
        return Plugin::$instance->experiments->is_feature_active('nested-elements', true);
    }

    public function has_widget_inner_wrapper(): bool
    {
        return ! Plugin::$instance->experiments->is_feature_active('e_optimized_markup');
    }

    protected function item_content_container(string $name)
    {
        return [
            'elType' => 'container',
            'settings' => [
                '_title' => sprintf(
                    /* translators: %s: Item name. */
                    __('%s', 'posthog-ab'),
                    $name
                ),
                'content_width' => 'full',
            ],
        ];
    }

    protected function get_default_children_elements()
    {
        return [
            $this->item_content_container("control"),
            $this->item_content_container("test"),
        ];
    }

    protected function get_default_repeater_title_setting_key()
    {
        return 'item_title';
    }

    protected function get_default_children_title()
    {
        /* translators: %d: Item index. */
        return esc_html__('Item #%d', 'posthog-ab');
    }

    protected function get_default_children_placeholder_selector()
    {
        return '.phab-container';
    }

    protected function get_default_children_container_placeholder_selector()
    {
        return '.phab-container-item';
    }

    protected function get_html_wrapper_class()
    {
        return 'phab-widget-accordion';
    }

    protected function register_controls()
    {
        if (null === $this->optimized_markup) {
            $this->optimized_markup = Plugin::$instance->experiments->is_feature_active('e_optimized_markup') && ! $this->has_widget_inner_wrapper();
            $this->widget_container_selector = $this->optimized_markup ? '' : ' > .phab-widget-accordion';
        }

        $this->start_controls_section('section_items', [
            'label' => esc_html__('Experiment', 'posthog-ab'),
        ]);

        $this->add_control('flag_key', [
            'label'       => __('Feature Flag Key', 'posthog-ab'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'my-homepage-test',
            'description' => __('The PostHog feature flag key — must match exactly.', 'posthog-ab'),
            'dynamic'     => ['active' => false],
        ]);

        $repeater = new Repeater();

        $repeater->add_control(
            'item_title',
            [
                'label' => esc_html__('Variant Name', 'posthog-ab'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Item Title', 'posthog-ab'),
                'placeholder' => esc_html__('Item Title', 'posthog-ab'),
                'label_block' => true,
                'dynamic' => [
                    'active' => false,
                ],
            ]
        );

        $this->add_control(
            'items',
            [
                'label' => esc_html__('A/B Test Variants', 'posthog-ab'),
                'type' => Control_Nested_Repeater::CONTROL_TYPE,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'item_title' => esc_html__('control', 'posthog-ab'),
                    ],
                    [
                        'item_title' => esc_html__('test', 'posthog-ab'),
                    ],
                ],
                'title_field' => '{{{ item_title }}}',
                'prevent_empty' => true, // Prevents removing all items
                'classes' => 'phab-container-repeater',    // set this class so our editor hook can find it
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $items = $settings['items'];
        $id_int = substr($this->get_id_int(), 0, 3);
        $items_title_html = '';
        $this->add_render_attribute('elementor-accordion', 'class', 'phab-container');
        $this->add_render_attribute('elementor-accordion', 'aria-label', 'Accordion. Open links with Enter or Space, close with Escape, and navigate with Arrow Keys');
        $default_state = 'expanded'; // for our AB test, we want all items to be expanded by default, so we set this to 'expanded' and ignore any user setting for default state
        $flag_key         = sanitize_key($settings['flag_key'] ?? '');
        $is_editor        = \Elementor\Plugin::$instance->editor->is_edit_mode();
        $variant_key     = sanitize_key($settings['items'][1]['item_title'] ?? 'test'); // we use the first variant's name as the variant key for the frontend

        if ($is_editor) {
            // ── Validation notice (editor only) ──────────────────────────────────
            if (! $flag_key) {
                echo '<div style="padding:.75em 1em;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:8px;font-size:13px;">' .
                    esc_html__('⚠ Set a Feature Flag Key in the Experiment panel.', 'posthog-ab') .
                    '</div>';
            }

            foreach ($items as $index => $item) {
                $accordion_count = $index + 1;
                $item_setting_key = $this->get_repeater_setting_key('item_title', 'items', $index);
                $item_summary_key = $this->get_repeater_setting_key('item_summary', 'items', $index);
                $item_classes = ['phab-container-item'];
                $item_id =  'phab-container-item-' . $id_int . $index;
                $item_title = $index === 0 ? 'A: ' : 'B: ' . $item['item_title'];
                $is_open = 'expanded' === $default_state && 0 === $index ? 'open' : '';

                $this->add_render_attribute($item_setting_key, [
                    'id' => $item_id,
                    'class' => $item_classes,
                ]);

                $this->add_render_attribute($item_summary_key, [
                    'class' => ['phab-container-item-title'],
                    'data-accordion-index' => $accordion_count,
                    'tabindex' => 0 === $index ? 0 : -1,
                    'aria-expanded' => 'true',
                    'aria-controls' => $item_id,
                ]);

                $title_render_attributes = $this->get_render_attribute_string($item_setting_key);
                $title_render_attributes = $title_render_attributes . ' ' . $is_open;

                $summary_render_attributes = $this->get_render_attribute_string($item_summary_key);

                // items content.
                ob_start();
                $this->print_child($index, $item_id);
                $item_content = ob_get_clean();

                ob_start();
?>
                <details <?php echo wp_kses_post($title_render_attributes); ?>>
                    <summary <?php echo wp_kses_post($summary_render_attributes); ?>>
                        <span class='phab-item-title-header'><?php echo wp_kses_post("<div class=\"phab-container-item-title-text\"> $item_title </div>"); ?></span>
                    </summary>
                    <?php echo $item_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                    ?>
                </details>
            <?php
                $items_title_html .= ob_get_clean();
            }

            ?>
            <div <?php $this->print_render_attribute_string('elementor-accordion'); ?>>
                <?php echo $items_title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                ?>
            </div>
        <?php
        } else {
            // on the frontend, we just render the A and B children wrapped with attributes 
            // (initially hidden) so that the JS running on the page can show/hide the right experiment

            $style = 'visibility:hidden; height:0; overflow:hidden;';

            // ── Control slot ──────────────────────────────────────────────────────
            printf(
                '<div data-phab-flag="%1$s" data-phab-variant="control" class="phab-ab-container phab-control" style="%2$s">',
                esc_attr($flag_key),
                esc_attr($style)
            );
            $this->print_child(0);
            echo '</div>';

            printf(
                '<div data-phab-flag="%1$s" data-phab-variant="%2$s" class="phab-ab-container phab-variant" style="%3$s">',
                esc_attr($flag_key),
                esc_attr($variant_key),
                esc_attr($style)
            );
            $this->print_child(1);
            echo '</div>';
        }
    }

    public function print_child($index, $item_id = null)
    {
        $children = $this->get_children();

        if (! empty($children[$index])) {
            // Add data-tab-index attribute to the content area.
            $add_attribute_to_container = function ($should_render, $container) use ($item_id) {
                $this->add_attributes_to_container($container, $item_id);

                return $should_render;
            };

            add_filter('elementor/frontend/container/should_render', $add_attribute_to_container, 10, 3);
            $children[$index]->print_element();
            remove_filter('elementor/frontend/container/should_render', $add_attribute_to_container);
        }
    }

    protected function add_attributes_to_container($container, $item_id)
    {
        $container->add_render_attribute('_wrapper', [
            'role' => 'region',
            'aria-labelledby' => $item_id,
        ]);
    }

    protected function get_initial_config(): array
    {
        return array_merge(parent::get_initial_config(), [
            'support_improved_repeaters' => true,
            'target_container' => ['.phab-container'],
            'node' => 'details',
            'is_interlaced' => true,
        ]);
    }

    protected function content_template()
    {
        ?>
        <#
            var flagKey=settings.flag_key || '' ;
            #>

            <# if ( ! flagKey ) { #>
                <div style="padding:.75em 1em;background:#fff3cd;border-left:4px solid #ffc107;margin-bottom:8px;font-size:13px;">
                    ⚠ Set a Feature Flag Key in the Experiment panel.
                </div>
                <# } #>

                    <div class="phab-container" aria-label="Accordion. Open links with Enter or Space, close with Escape, and navigate with Arrow Keys">
                        <# if ( settings['items'] ) {
                            const elementUid=view.getIDInt().toString().substring( 0, 3 ),
                            defaultState='expanded'
                            #>

                            <# _.each( settings['items'], function( item, index ) {
                                const itemCount=index + 1,
                                itemUid=elementUid + index,
                                itemTitleTextKey='item-title-text-' + itemUid,
                                itemWrapperKey=itemUid,
                                itemTitleKey='item-' + itemUid,
                                ariaExpanded='true' ;

                                itemId='phab-container-item-' + itemUid;

                                const itemWrapperAttributes={ 'id' : itemId, 'class' : [ 'phab-container-item' , 'e-normal' ],
                                };

                                itemWrapperAttributes['open']=true;

                                view.addRenderAttribute( itemWrapperKey, itemWrapperAttributes );

                                view.addRenderAttribute( itemTitleKey, { 'class' : ['phab-container-item-title'], 'data-accordion-index' : itemCount, 'tabindex' : 0===index ? 0 : -1, 'aria-expanded' : ariaExpanded, 'aria-controls' : itemId,
                                });

                                view.addRenderAttribute( itemTitleTextKey, { 'class' : ['phab-container-item-title-text'], 'data-binding-index' : itemCount, 'data-binding-type' : 'repeater-item' , 'data-binding-repeater-name' : 'items' , 'data-binding-setting' : ['item_title', 'element_css_id' ], 'data-binding-config' : JSON.stringify({ 'element_css_id' : {
                                editType: 'attribute' ,
                                attr: 'id' ,
                                selector: 'details'
                                }, 'item_title' : {
                                editType: 'text'
                                }
                                }),
                                });
                                #>

                                <details {{{ view.getRenderAttributeString( itemWrapperKey ) }}}>
                                    <summary {{{ view.getRenderAttributeString( itemTitleKey ) }}}>
                                        <span class="phab-item-title-header">
                                            <div {{{ view.getRenderAttributeString( itemTitleTextKey ) }}}>
                                                {{{ index === 0 ? 'A: ' : 'B: ' }}} {{{ item.item_title }}}
                                            </div>
                                        </span>
                                    </summary>
                                </details>
                                <# } ); #>
                                    <# } #>
                    </div>
            <?php
        }
    }
