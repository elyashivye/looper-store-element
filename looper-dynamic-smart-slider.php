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

                    $category = $this->sanitize_category_mapping_value($row['category']);
                    $shortcode = wp_kses_post($row['shortcode']);

                    if ($category === '') {
                        continue;
                    }

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

        private function sanitize_category_mapping_value($value)
        {
            $value = trim((string) wp_unslash($value));
            if ($value === '') {
                return '';
            }

            // Keep encoded URL/path characters (for Hebrew slugs), but remove HTML.
            $value = trim(wp_strip_all_tags($value));
            if ($value === '') {
                return '';
            }

            if (preg_match('#^https?://#i', $value)) {
                return esc_url_raw($value, array('http', 'https'));
            }

            return $value;
        }

        public function render_section_description()
        {
            echo '<p>' . esc_html__('Define which Smart Slider shortcode belongs to each product category.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('You can use category slug (recommended), term ID, or exact category name.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('Use the category and slider search fields below for faster selection.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('Tip: You can also paste the category URL (with or without trailing slash); the plugin will extract the slug automatically.', 'looper-dynamic-slider') . '</p>';
            echo '<p><code>[ldss_dynamic_slider]</code> ' . esc_html__('is the fixed shortcode to use inside Elementor.', 'looper-dynamic-slider') . '</p>';
        }

        public function render_mappings_field()
        {
            $value = get_option(self::OPTION_KEY, array());
            $mappings = isset($value['mappings']) && is_array($value['mappings']) ? $value['mappings'] : array();
            $product_categories = $this->get_product_categories_for_admin();
            $smart_sliders = $this->get_smart_sliders_for_admin();

            if (empty($mappings)) {
                $mappings = array(
                    array('category' => '', 'shortcode' => ''),
                );
            }

            echo '<datalist id="ldss-category-suggestions">';
            foreach ($product_categories as $category_option) {
                echo '<option value="' . esc_attr($category_option['slug']) . '">' . esc_html($category_option['label']) . '</option>';
            }
            echo '</datalist>';

            echo '<div id="ldss-mapping-rows">';

            foreach ($mappings as $index => $mapping) {
                $category = isset($mapping['category']) ? $mapping['category'] : '';
                $shortcode = isset($mapping['shortcode']) ? $mapping['shortcode'] : '';
                $this->render_mapping_row($index, $category, $shortcode, $smart_sliders);
            }

            echo '</div>';

            echo '<button type="button" class="button" id="ldss-add-row">' . esc_html__('Add Mapping', 'looper-dynamic-slider') . '</button>';

            $this->render_admin_script($smart_sliders);
        }

        private function render_mapping_row($index, $category, $shortcode, $smart_sliders)
        {
            $slider_id_from_shortcode = $this->extract_slider_id_from_shortcode($shortcode);

            echo '<div class="ldss-row" style="display:flex;gap:10px;margin-bottom:10px;align-items:center;">';

            echo '<input type="text" class="ldss-category-input" list="ldss-category-suggestions" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][category]" value="' . esc_attr($category) . '" placeholder="Search category by name / slug / URL" style="min-width:260px;" />';

            echo '<select class="ldss-slider-select" style="min-width:300px;">';
            echo '<option value="">' . esc_html__('Select Smart Slider (or keep custom shortcode)', 'looper-dynamic-slider') . '</option>';
            foreach ($smart_sliders as $slider_option) {
                $selected = ((string) $slider_option['id'] === (string) $slider_id_from_shortcode) ? 'selected' : '';
                echo '<option value="' . esc_attr($slider_option['id']) . '" ' . esc_attr($selected) . '>' . esc_html($slider_option['label']) . '</option>';
            }
            echo '</select>';

            echo '<input type="text" class="ldss-shortcode-input" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][shortcode]" value="' . esc_attr($shortcode) . '" placeholder="[smartslider3 slider=&quot;2&quot;]" style="min-width:320px;" />';

            echo '<button type="button" class="button ldss-remove-row">' . esc_html__('Remove', 'looper-dynamic-slider') . '</button>';
            echo '</div>';
        }

        private function render_admin_script($smart_sliders)
        {
            $slider_options_html = '<option value="">' . esc_html__('Select Smart Slider (or keep custom shortcode)', 'looper-dynamic-slider') . '</option>';
            foreach ($smart_sliders as $slider_option) {
                $slider_options_html .= '<option value="' . esc_attr($slider_option['id']) . '">' . esc_html($slider_option['label']) . '</option>';
            }
            ?>
            <script>
                (function () {
                    const container = document.getElementById('ldss-mapping-rows');
                    const addBtn = document.getElementById('ldss-add-row');
                    const sliderOptionsHtml = <?php echo wp_json_encode($slider_options_html); ?>;

                    if (!container || !addBtn) {
                        return;
                    }

                    const updateShortcodeFromSelect = function (row) {
                        const select = row.querySelector('.ldss-slider-select');
                        const shortcodeInput = row.querySelector('.ldss-shortcode-input');
                        if (!select || !shortcodeInput || !select.value) {
                            return;
                        }

                        shortcodeInput.value = '[smartslider3 slider="' + select.value + '"]';
                    };

                    const syncSelectFromShortcode = function (row) {
                        const select = row.querySelector('.ldss-slider-select');
                        const shortcodeInput = row.querySelector('.ldss-shortcode-input');
                        if (!select || !shortcodeInput) {
                            return;
                        }

                        const match = shortcodeInput.value.match(/slider\s*=\s*["']?(\d+)["']?/i);
                        if (!match) {
                            select.value = '';
                            return;
                        }

                        const sliderId = match[1];
                        const optionExists = Array.prototype.some.call(select.options, function (option) {
                            return option.value === sliderId;
                        });

                        select.value = optionExists ? sliderId : '';
                    };

                    addBtn.addEventListener('click', function () {
                        const idx = container.querySelectorAll('.ldss-row').length;
                        const row = document.createElement('div');
                        row.className = 'ldss-row';
                        row.style.display = 'flex';
                        row.style.gap = '10px';
                        row.style.marginBottom = '10px';
                        row.style.alignItems = 'center';

                        row.innerHTML =
                            '<input type="text" class="ldss-category-input" list="ldss-category-suggestions" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][category]" placeholder="Search category by name / slug / URL" style="min-width:260px;" />' +
                            '<select class="ldss-slider-select" style="min-width:300px;">' + sliderOptionsHtml + '</select>' +
                            '<input type="text" class="ldss-shortcode-input" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][shortcode]" placeholder="[smartslider3 slider=&quot;2&quot;]" style="min-width:320px;" />' +
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

                    container.addEventListener('change', function (event) {
                        if (!event.target.classList.contains('ldss-slider-select')) {
                            return;
                        }

                        const row = event.target.closest('.ldss-row');
                        if (row) {
                            updateShortcodeFromSelect(row);
                        }
                    });

                    container.addEventListener('input', function (event) {
                        if (!event.target.classList.contains('ldss-shortcode-input')) {
                            return;
                        }

                        const row = event.target.closest('.ldss-row');
                        if (row) {
                            syncSelectFromShortcode(row);
                        }
                    });
                })();
            </script>
            <?php
        }

        private function extract_slider_id_from_shortcode($shortcode)
        {
            $shortcode = (string) $shortcode;
            if (preg_match('/slider\s*=\s*["\']?(\d+)["\']?/i', $shortcode, $matches)) {
                return $matches[1];
            }

            return '';
        }

        private function get_product_categories_for_admin()
        {
            if (!function_exists('get_terms')) {
                return array();
            }

            $terms = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));

            if (is_wp_error($terms) || !is_array($terms)) {
                return array();
            }

            $options = array();
            foreach ($terms as $term) {
                if (!isset($term->slug) || !isset($term->name) || !isset($term->term_id)) {
                    continue;
                }

                $options[] = array(
                    'slug' => (string) $term->slug,
                    'label' => '#' . (string) $term->term_id . ' — ' . (string) $term->name . ' (' . (string) $term->slug . ')',
                );
            }

            return $options;
        }

        private function get_smart_sliders_for_admin()
        {
            global $wpdb;

            if (!isset($wpdb) || !isset($wpdb->prefix)) {
                return array();
            }

            $table_name = $wpdb->prefix . 'nextend2_smartslider3_sliders';
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($table_exists !== $table_name) {
                return array();
            }

            $results = $wpdb->get_results("SELECT id, title FROM {$table_name} ORDER BY title ASC");
            if (!is_array($results)) {
                return array();
            }

            $options = array();
            foreach ($results as $slider) {
                if (!isset($slider->id) || !isset($slider->title)) {
                    continue;
                }

                $options[] = array(
                    'id' => (string) $slider->id,
                    'label' => '#' . (string) $slider->id . ' — ' . (string) $slider->title,
                );
            }

            return $options;
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
            $term_slug_normalized = sanitize_title(wp_unslash($term_slug));
            $term_name_lc = function_exists('mb_strtolower') ? mb_strtolower($term_name) : strtolower($term_name);

            foreach ($mappings as $mapping) {
                if (empty($mapping['category']) || empty($mapping['shortcode'])) {
                    continue;
                }

                $raw_candidate = (string) $mapping['category'];
                $candidates = $this->expand_category_candidates($raw_candidate);

                foreach ($candidates as $candidate) {
                    if ($candidate === $term_id || $candidate === $term_slug || $candidate === $term_name) {
                        return $mapping['shortcode'];
                    }

                    if (sanitize_title(wp_unslash($candidate)) === $term_slug_normalized) {
                        return $mapping['shortcode'];
                    }

                    $candidate_lc = function_exists('mb_strtolower') ? mb_strtolower($candidate) : strtolower($candidate);
                    if ($candidate_lc === $term_name_lc) {
                        return $mapping['shortcode'];
                    }
                }
            }

            return '';
        }

        private function expand_category_candidates($raw_candidate)
        {
            $raw_candidate = trim((string) $raw_candidate);
            if ($raw_candidate === '') {
                return array();
            }

            $values = array(
                $raw_candidate,
                trim($raw_candidate, '/'),
                rawurldecode($raw_candidate),
                trim(rawurldecode($raw_candidate), '/'),
            );

            $path = wp_parse_url($raw_candidate, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $trimmed_path = trim($path, '/');
                if ($trimmed_path !== '') {
                    $values[] = $trimmed_path;
                    $values[] = rawurldecode($trimmed_path);

                    $path_parts = explode('/', $trimmed_path);
                    $last_part = end($path_parts);
                    if (is_string($last_part) && $last_part !== '') {
                        $values[] = $last_part;
                        $values[] = rawurldecode($last_part);
                    }
                }
            }

            return array_values(array_unique(array_filter(array_map('trim', $values))));
        }
    }

    new Looper_Dynamic_Smart_Slider();
}
