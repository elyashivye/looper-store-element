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
            echo '<p>' . esc_html__('הגדר איזה שורטקוד של Smart Slider שייך לכל קטגוריית מוצרים.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('אפשר להזין slug / מזהה קטגוריה / שם קטגוריה / URL מלא של הקטגוריה.', 'looper-dynamic-slider') . '</p>';
            echo '<p>' . esc_html__('בשדה הקטגוריה תראה גם את שם דף היעד שהמערכת מזהה עבורך.', 'looper-dynamic-slider') . '</p>';
            echo '<p><code>[ldss_dynamic_slider]</code> ' . esc_html__('זה השורטקוד הקבוע לשימוש בתוך Elementor.', 'looper-dynamic-slider') . '</p>';
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

            $this->render_admin_styles();
            echo '<div id="ldss-mapping-rows">';

            foreach ($mappings as $index => $mapping) {
                $category = isset($mapping['category']) ? $mapping['category'] : '';
                $shortcode = isset($mapping['shortcode']) ? $mapping['shortcode'] : '';
                $this->render_mapping_row($index, $category, $shortcode, $smart_sliders, $product_categories);
            }

            echo '</div>';

            echo '<button type="button" class="button button-primary" id="ldss-add-row">' . esc_html__('הוספת מיפוי', 'looper-dynamic-slider') . '</button>';

            $this->render_admin_script($smart_sliders, $product_categories);
        }

        private function render_mapping_row($index, $category, $shortcode, $smart_sliders, $product_categories)
        {
            $slider_id_from_shortcode = $this->extract_slider_id_from_shortcode($shortcode);
            $category_preview = $this->resolve_category_preview($category, $product_categories);

            echo '<div class="ldss-row">';
            echo '<div class="ldss-field">';
            echo '<label class="ldss-label">' . esc_html__('קטגוריית יעד', 'looper-dynamic-slider') . '</label>';
            echo '<div class="ldss-category-picker">';
            echo '<input type="text" class="ldss-category-input" autocomplete="off" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][category]" value="' . esc_attr($category) . '" placeholder="חיפוש קטגוריה לפי שם / slug / URL" />';
            echo '<div class="ldss-category-dropdown" hidden></div>';
            echo '<div class="ldss-category-preview">' . esc_html($category_preview) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="ldss-field">';
            echo '<label class="ldss-label">' . esc_html__('סליידר', 'looper-dynamic-slider') . '</label>';
            echo '<select class="ldss-slider-select" style="min-width:300px;">';
            echo '<option value="">' . esc_html__('בחירת סליידר (או להזין שורטקוד ידנית)', 'looper-dynamic-slider') . '</option>';
            foreach ($smart_sliders as $slider_option) {
                $selected = ((string) $slider_option['id'] === (string) $slider_id_from_shortcode) ? 'selected' : '';
                echo '<option value="' . esc_attr($slider_option['id']) . '" ' . esc_attr($selected) . '>' . esc_html($slider_option['label']) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '<div class="ldss-field">';
            echo '<label class="ldss-label">' . esc_html__('שורטקוד', 'looper-dynamic-slider') . '</label>';
            echo '<input type="text" class="ldss-shortcode-input" name="' . esc_attr(self::OPTION_KEY) . '[mappings][' . esc_attr($index) . '][shortcode]" value="' . esc_attr($shortcode) . '" placeholder="[smartslider3 slider=&quot;2&quot;]" />';
            echo '</div>';

            echo '<button type="button" class="button ldss-remove-row">' . esc_html__('הסר', 'looper-dynamic-slider') . '</button>';
            echo '</div>';
        }

        private function render_admin_script($smart_sliders, $product_categories)
        {
            $slider_options_html = '<option value="">' . esc_html__('בחירת סליידר (או להזין שורטקוד ידנית)', 'looper-dynamic-slider') . '</option>';
            foreach ($smart_sliders as $slider_option) {
                $slider_options_html .= '<option value="' . esc_attr($slider_option['id']) . '">' . esc_html($slider_option['label']) . '</option>';
            }
            ?>
            <script>
                (function () {
                    const container = document.getElementById('ldss-mapping-rows');
                    const addBtn = document.getElementById('ldss-add-row');
                    const sliderOptionsHtml = <?php echo wp_json_encode($slider_options_html); ?>;
                    const categories = <?php echo wp_json_encode($product_categories); ?>;

                    if (!container || !addBtn) {
                        return;
                    }

                    const decodeMaybe = function (value) {
                        try {
                            return decodeURIComponent(value);
                        } catch (err) {
                            return value;
                        }
                    };

                    const getCategoryMatch = function (rawValue) {
                        const value = (rawValue || '').trim();
                        if (!value) {
                            return null;
                        }

                        const cleanValue = decodeMaybe(value).replace(/^\/+|\/+$/g, '');
                        const parts = cleanValue.split('/');
                        const lastPart = parts.length ? parts[parts.length - 1] : cleanValue;
                        const normalized = lastPart.toLowerCase();

                        return categories.find(function (item) {
                            return (
                                String(item.id) === value ||
                                item.slug.toLowerCase() === normalized ||
                                item.name.toLowerCase() === decodeMaybe(value).toLowerCase() ||
                                item.slug.toLowerCase() === value.toLowerCase()
                            );
                        }) || null;
                    };

                    const updateCategoryPreview = function (row) {
                        const input = row.querySelector('.ldss-category-input');
                        const preview = row.querySelector('.ldss-category-preview');
                        if (!input || !preview) {
                            return;
                        }

                        const match = getCategoryMatch(input.value);
                        if (!match) {
                            preview.textContent = 'דף יעד: לא זוהתה קטגוריה (אפשר עדיין לשמור ידנית)';
                            return;
                        }

                        preview.textContent = 'דף יעד: ' + match.name + ' (' + match.slug + ')';
                    };

                    const renderCategoryDropdown = function (row) {
                        const input = row.querySelector('.ldss-category-input');
                        const dropdown = row.querySelector('.ldss-category-dropdown');
                        if (!input || !dropdown) {
                            return;
                        }

                        const query = (input.value || '').trim().toLowerCase();
                        const matches = categories.filter(function (item) {
                            if (!query) {
                                return true;
                            }

                            return (
                                item.name.toLowerCase().includes(query) ||
                                item.slug.toLowerCase().includes(query) ||
                                String(item.id).includes(query)
                            );
                        }).slice(0, 8);

                        if (!matches.length) {
                            dropdown.hidden = true;
                            dropdown.innerHTML = '';
                            return;
                        }

                        dropdown.innerHTML = matches.map(function (item) {
                            return '<button type="button" class="ldss-category-option" data-slug="' + item.slug + '">' +
                                '<strong>' + item.name + '</strong> <span>(' + item.slug + ' · #' + item.id + ')</span>' +
                            '</button>';
                        }).join('');
                        dropdown.hidden = false;
                    };

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
                            '<div class="ldss-field">' +
                                '<label class="ldss-label">קטגוריית יעד</label>' +
                                '<div class="ldss-category-picker">' +
                                    '<input type="text" class="ldss-category-input" autocomplete="off" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][category]" placeholder="חיפוש קטגוריה לפי שם / slug / URL" />' +
                                    '<div class="ldss-category-dropdown" hidden></div>' +
                                    '<div class="ldss-category-preview">דף יעד: לא נבחר</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="ldss-field">' +
                                '<label class="ldss-label">סליידר</label>' +
                                '<select class="ldss-slider-select" style="min-width:300px;">' + sliderOptionsHtml + '</select>' +
                            '</div>' +
                            '<div class="ldss-field">' +
                                '<label class="ldss-label">שורטקוד</label>' +
                                '<input type="text" class="ldss-shortcode-input" name="<?php echo esc_js(self::OPTION_KEY); ?>[mappings][' + idx + '][shortcode]" placeholder="[smartslider3 slider=&quot;2&quot;]" />' +
                            '</div>' +
                            '<button type="button" class="button ldss-remove-row"><?php echo esc_js(__('הסר', 'looper-dynamic-slider')); ?></button>';

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

                    container.addEventListener('focusin', function (event) {
                        if (!event.target.classList.contains('ldss-category-input')) {
                            return;
                        }

                        const row = event.target.closest('.ldss-row');
                        if (row) {
                            renderCategoryDropdown(row);
                            updateCategoryPreview(row);
                        }
                    });

                    container.addEventListener('input', function (event) {
                        if (!event.target.classList.contains('ldss-category-input')) {
                            return;
                        }

                        const row = event.target.closest('.ldss-row');
                        if (row) {
                            renderCategoryDropdown(row);
                            updateCategoryPreview(row);
                        }
                    });

                    container.addEventListener('click', function (event) {
                        const optionButton = event.target.closest('.ldss-category-option');
                        if (!optionButton) {
                            return;
                        }

                        const row = optionButton.closest('.ldss-row');
                        const input = row ? row.querySelector('.ldss-category-input') : null;
                        const dropdown = row ? row.querySelector('.ldss-category-dropdown') : null;
                        if (!row || !input || !dropdown) {
                            return;
                        }

                        input.value = optionButton.getAttribute('data-slug') || '';
                        dropdown.hidden = true;
                        dropdown.innerHTML = '';
                        updateCategoryPreview(row);
                    });

                    document.addEventListener('click', function (event) {
                        if (event.target.closest('.ldss-category-picker')) {
                            return;
                        }

                        container.querySelectorAll('.ldss-category-dropdown').forEach(function (dropdown) {
                            dropdown.hidden = true;
                        });
                    });
                })();
            </script>
            <?php
        }

        private function render_admin_styles()
        {
            ?>
            <style>
                #ldss-mapping-rows {
                    margin: 16px 0;
                    display: grid;
                    gap: 14px;
                }

                .ldss-row {
                    display: grid;
                    grid-template-columns: minmax(250px, 1fr) minmax(240px, 1fr) minmax(240px, 1fr) auto;
                    gap: 12px;
                    align-items: end;
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 14px;
                    padding: 14px;
                    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
                }

                .ldss-field {
                    display: grid;
                    gap: 6px;
                }

                .ldss-label {
                    color: #334155;
                    font-weight: 600;
                    font-size: 12px;
                }

                .ldss-category-picker {
                    position: relative;
                }

                .ldss-category-input,
                .ldss-shortcode-input,
                .ldss-slider-select {
                    width: 100%;
                    min-height: 40px;
                    border-radius: 10px;
                    border-color: #cbd5e1;
                }

                .ldss-category-dropdown {
                    position: absolute;
                    z-index: 99;
                    left: 0;
                    right: 0;
                    top: calc(100% + 6px);
                    border: 1px solid #ccd0d4;
                    background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
                    border-radius: 12px;
                    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.08);
                    max-height: 220px;
                    overflow: auto;
                    padding: 6px;
                }

                .ldss-category-option {
                    width: 100%;
                    text-align: right;
                    border: 0;
                    background: transparent;
                    border-radius: 8px;
                    padding: 8px 10px;
                    cursor: pointer;
                }

                .ldss-category-option:hover {
                    background: #e0efff;
                }

                .ldss-category-option span {
                    color: #4f5d6b;
                    font-size: 12px;
                }

                .ldss-category-preview {
                    margin-top: 6px;
                    color: #475569;
                    font-size: 12px;
                }

                .ldss-remove-row {
                    min-height: 40px;
                    border-radius: 10px;
                }

                .ldss-credit {
                    margin-top: 18px;
                    text-align: center;
                    font-size: 11px;
                    color: #64748b;
                    opacity: 0.85;
                }

                .ldss-credit a {
                    color: #475569;
                    text-decoration: none;
                    border-bottom: 1px dotted #94a3b8;
                }
            </style>
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
                    'id' => (string) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                    'label' => '#' . (string) $term->term_id . ' — ' . (string) $term->name . ' (' . (string) $term->slug . ')',
                );
            }

            return $options;
        }

        private function resolve_category_preview($value, $categories)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return 'דף יעד: לא נבחר';
            }

            $expanded = $this->expand_category_candidates($value);
            foreach ($categories as $category) {
                $id = isset($category['id']) ? (string) $category['id'] : '';
                $slug = isset($category['slug']) ? (string) $category['slug'] : '';
                $name = isset($category['name']) ? (string) $category['name'] : '';

                foreach ($expanded as $candidate) {
                    if ($candidate === $id || $candidate === $slug || $candidate === $name) {
                        return 'דף יעד: ' . $name . ' (' . $slug . ')';
                    }
                }
            }

            return 'דף יעד: לא זוהתה קטגוריה (אפשר עדיין לשמור ידנית)';
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
            echo '<p class="description">' . esc_html__('יוצג כאשר לא נמצא מיפוי תואם לקטגוריה הנוכחית.', 'looper-dynamic-slider') . '</p>';
        }

        public function render_admin_page()
        {
            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Dynamic Smart Slider for Product Categories', 'looper-dynamic-slider') . '</h1>';
            echo '<p>' . esc_html__('ממשק מיפוי מהיר לבחירת קטגוריה וסליידר בכמה קליקים.', 'looper-dynamic-slider') . '</p>';
            echo '<form method="post" action="options.php">';

            settings_fields('ldss_settings_group');
            do_settings_sections('ldss-dynamic-smart-slider');
            submit_button();

            echo '</form>';
            echo '<div class="ldss-credit">כל הזכויות שמורות ל- clicknow — שיווק שמבין עסקים · <a href="https://clicknow.space" target="_blank" rel="noopener noreferrer">clicknow.space</a></div>';
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
