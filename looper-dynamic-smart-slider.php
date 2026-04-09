<?php
/**
 * Plugin Name: Looper Dynamic Smart Slider for WooCommerce
 * Description: Loads a Smart Slider 3 shortcode dynamically on WooCommerce product category archive pages based on category mapping.
 * Version: 1.0.0
 * Author: Looper
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: looper-dynamic-slider
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Looper_Dynamic_Smart_Slider')) {
    class Looper_Dynamic_Smart_Slider
    {
        const OPTION_KEY = 'ldss_category_slider_mappings';
        const SHORTCODE_TAG = 'ldss_dynamic_slider';

        public function __construct()
        {
            add_action('admin_menu', array($this, 'register_admin_page'));
            add_action('admin_init', array($this, 'register_settings'));
            add_shortcode(self::SHORTCODE_TAG, array($this, 'render_dynamic_slider_shortcode'));
        }

        public function register_admin_page()
        {
            add_submenu_page(
                'woocommerce',
                __('Dynamic Smart Slider', 'looper-dynamic-slider'),
                __('Dynamic Smart Slider', 'looper-dynamic-slider'),
                'manage_woocommerce',
                'ldss-dynamic-smart-slider',
                array($this, 'render_admin_page')
            );
        }

        public function register_settings()
        {
            register_setting(
                'ldss_settings_group',
                self::OPTION_KEY,
                array($this, 'sanitize_mappings')
            );

            add_settings_section(
                'ldss_main_section',
                __('Category to Slider Mapping', 'looper-dynamic-slider'),
                array($this, 'render_section_description'),
                'ldss-dynamic-smart-slider'
            );

            add_settings_field(
                'ldss_mappings_field',
                __('Mappings', 'looper-dynamic-slider'),
                array($this, 'render_mappings_field'),
                'ldss-dynamic-smart-slider',
                'ldss_main_section'
            );

            add_settings_field(
                'ldss_default_shortcode_field',
                __('Default Shortcode (optional)', 'looper-dynamic-slider'),
                array($this, 'render_default_shortcode_field'),
                'ldss-dynamic-smart-slider',
                'ldss_main_section'
            );
        }

        public function sanitize_mappings($input)
        {
            $sanitized = array(
                'mappings' => array(),
                'default_shortcode' => '',
            );

            if (!is_array($input)) {
                return $sanitized;
            }

            if (!empty($input['mappings']) && is_array($input['mappings'])) {
                foreach ($input['mappings'] as $row) {
                    if (empty($row['category']) || empty($row['shortcode'])) {
                        continue;
                    }

                    $category = sanitize_text_field($row['category']);
                    $shortcode = wp_kses_post($row['shortcode']);

                    $sanitized['mappings'][] = array(
                        'category' => $category,
                        'shortcode' => $shortcode,
                    );
                }
            }

            if (!empty($input['default_shortcode'])) {
                $sanitized['default_shortcode'] = wp_kses_post($input['default_shortcode']);
            }

            return $sanitized;
        }

        public function render_section_description()
        {
            echo '<p>' . esc_html__('Define which Smart Slider shortcode belongs to each product category.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('You can use category slug (recommended), term ID, or exact category name.', 'looper-dynamic-slider') . '</p>';
            echo '<p><code>[ldss_dynamic_slider]</code> ' . esc_html__('is the fixed shortcode to use inside Elementor.', 'looper-dynamic-slider') . '</p>';
        }

        public function render_mappings_field()
        {
            $value = get_option(self::OPTION_KEY, array());
            $mappings = isset($value['mappings']) && is_array($value['mappings']) ? $value['mappings'] : array();

            if (empty($mappings)) {
                $mappings = array(
                    array('category' => '', 'shortcode' => ''),
                );
            }

            echo '<div id="ldss-mapping-rows">';

            foreach ($mappings as $index => $mapping) {
                $category = isset($mapping['category']) ? $mapping['category'] : '';
                $shortcode = isset($mapping['shortcode']) ? $mapping['shortcode'] : '';
                $this->render_mapping_row($index, $category, $shortcode);
            }

            echo '</div>';

            echo '<button type="button" class="button" id="ldss-add-row">' . esc_html__('Add Mapping', 'looper-dynamic-slider') . '</button>';

            $this->render_admin_script();
        }

        private function render_mapping_row($index, $category, $shortcode)
        {
            echo '<div class="ldss-row" style="display:flex;gap:10px;margin-bottom:10px;align-items:center;">';

            echo '<input type="text" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][category]" value="' . esc_attr($category) . '" placeholder="product_cat slug / ID / name" style="min-width:260px;" />';

            echo '<input type="text" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][shortcode]" value="' . esc_attr($shortcode) . '" placeholder="[smartslider3 slider=&quot;2&quot;]" style="min-width:320px;" />';

            echo '<button type="button" class="button ldss-remove-row">' . esc_html__('Remove', 'looper-dynamic-slider') . '</button>';
            echo '</div>';
        }

        private function render_admin_script()
        {
            ?>
            <script>
                (function () {
                    const container = document.getElementById('ldss-mapping-rows');
                    const addBtn = document.getElementById('ldss-add-row');

                    if (!container || !addBtn) {
                        return;
                    }

                    addBtn.addEventListener('click', function () {
                        const idx = container.querySelectorAll('.ldss-row').length;
                        const row = document.createElement('div');
                        row.className = 'ldss-row';
                        row.style.display = 'flex';
                        row.style.gap = '10px';
                        row.style.marginBottom = '10px';
                        row.style.alignItems = 'center';

                        row.innerHTML =
                            '<input type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][category]" placeholder="product_cat slug / ID / name" style="min-width:260px;" />' +
                            '<input type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][shortcode]" placeholder="[smartslider3 slider=&quot;2&quot;]" style="min-width:320px;" />' +
                            '<button type="button" class="button ldss-remove-row"><?php echo esc_js(__('Remove', 'looper-dynamic-slider')); ?></button>';

                        container.appendChild(row);
                    });

                    container.addEventListener('click', function (event) {
                        if (!event.target.classList.contains('ldss-remove-row')) {
                            return;
                        }

                        const row = event.target.closest('.ldss-row');
                        if (row) {
                            row.remove();
                        }
                    });
                })();
            </script>
            <?php
        }

        public function render_default_shortcode_field()
        {
            $value = get_option(self::OPTION_KEY, array());
            $default_shortcode = isset($value['default_shortcode']) ? $value['default_shortcode'] : '';

            echo '<input type="text" name="' . esc_attr(self::OPTION_KEY) . '[default_shortcode]" value="' . esc_attr($default_shortcode) . '" placeholder="[smartslider3 slider=&quot;1&quot;]" style="min-width:320px;" />';
            echo '<p class="description">' . esc_html__('Displayed when no mapping is found for the current category.', 'looper-dynamic-slider') . '</p>';
        }

        public function render_admin_page()
        {
            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Dynamic Smart Slider for Product Categories', 'looper-dynamic-slider') . '</h1>';
            echo '<form method="post" action="options.php">';

            settings_fields('ldss_settings_group');
            do_settings_sections('ldss-dynamic-smart-slider');
            submit_button();

            echo '</form>';
            echo '</div>';
        }

        public function render_dynamic_slider_shortcode($atts)
        {
            if (!function_exists('is_product_category') || !is_product_category()) {
                return '';
            }

            $term = get_queried_object();
            if (!$term || !isset($term->term_id)) {
                return '';
            }

            $value = get_option(self::OPTION_KEY, array());
            $mappings = isset($value['mappings']) && is_array($value['mappings']) ? $value['mappings'] : array();

            $matched_shortcode = $this->find_matching_shortcode($term, $mappings);

            if (!$matched_shortcode && !empty($value['default_shortcode'])) {
                $matched_shortcode = $value['default_shortcode'];
            }

            if (!$matched_shortcode) {
                return '';
            }

            return do_shortcode($matched_shortcode);
        }

        private function find_matching_shortcode($term, $mappings)
        {
            $term_id = (string) $term->term_id;
            $term_slug = isset($term->slug) ? (string) $term->slug : '';
            $term_name = isset($term->name) ? (string) $term->name : '';

            foreach ($mappings as $mapping) {
                if (empty($mapping['category']) || empty($mapping['shortcode'])) {
                    continue;
                }

                $candidate = trim((string) $mapping['category']);

                if ($candidate === $term_id || $candidate === $term_slug || $candidate === $term_name) {
                    return $mapping['shortcode'];
                }
            }

            return '';
        }
    }

    new Looper_Dynamic_Smart_Slider();
}
