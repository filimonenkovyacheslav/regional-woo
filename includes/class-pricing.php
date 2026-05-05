<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Pricing {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    /** @var bool */
    private $is_collecting_blocked_terms = false;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_filter('woocommerce_product_get_price', array($this, 'filter_product_price'), 9999, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'filter_product_price'), 9999, 2);

        add_filter('woocommerce_get_price_html', array($this, 'maybe_hide_price_html'), 9999, 2);
        add_filter('woocommerce_variable_price_html', array($this, 'filter_variable_price_html_by_region'), 9999, 2);

        add_filter('woocommerce_is_purchasable', array($this, 'filter_product_purchasable_by_region'), 9999, 2);
        add_filter('woocommerce_variation_is_purchasable', array($this, 'filter_product_purchasable_by_region'), 9999, 2);

        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_region_hidden_field'));
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart_region_visibility'), 10, 5);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_regional_cart_item_data'), 10, 4);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'restore_regional_cart_item_data'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'sync_cart_item_prices'), 9999);
        add_filter('woocommerce_cart_item_price', array($this, 'refresh_cart_item_price_display'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'refresh_cart_item_subtotal_display'), 10, 3);

        add_filter('woocommerce_structured_data_product', array($this, 'filter_product_schema'), 9999, 2);

        add_action('template_redirect', array($this, 'maybe_redirect_hidden_products'));
        add_action('template_redirect', array($this, 'maybe_redirect_hidden_product_category'), 20);

        add_action('woocommerce_before_single_product', array($this, 'render_hidden_product_notice'), 1);

        add_action('woocommerce_product_query', array($this, 'filter_catalog_queries'), 9999, 2);
        add_action('pre_get_posts', array($this, 'filter_frontend_product_queries_by_region'), 9999);
        add_filter('the_posts', array($this, 'filter_catalog_posts_with_hidden_variations'), 9999, 2);

        add_filter('woocommerce_related_products', array($this, 'filter_related_products'), 9999, 3);

        add_filter('woocommerce_available_variation', array($this, 'filter_available_variation_by_region'), 10, 3);
        add_filter('woocommerce_variation_is_active', array($this, 'keep_hidden_variation_selectable'), 9999, 2);
        add_filter('woocommerce_single_variation', array($this, 'replace_hidden_variation_add_to_cart_message'), 9999);

        add_action('pre_get_terms', array($this, 'filter_product_cat_terms_query_by_region'), 9999, 1);
        add_filter('wp_nav_menu_objects', array($this, 'filter_nav_menu_items_by_region'), 9999, 2);
    }

    public function render_region_hidden_field() {
        $region = $this->context->get_current_region();
        echo '<input type="hidden" name="rockside_region" value="' . esc_attr($region) . '">';
    }

    public function get_regional_price_for_product($product) {
        if (!$product instanceof WC_Product) {
            return null;
        }

        $region = $this->context->get_current_region();
        return $this->get_regional_price_for_product_by_region($product, $region);
    }

    private function get_regional_price_for_product_by_region($product, $region) {
        if (!$product instanceof WC_Product) {
            return null;
        }

        if ($region === 'kharkiv') {
            return null;
        }

        $meta_key = $this->context->get_region_price_meta_key($region);
        if (!$meta_key) {
            return null;
        }

        $product_id = $product->get_id();
        $value = get_post_meta($product_id, $meta_key, true);

        if (is_array($value)) {
            if (isset($value[$product_id]) && $value[$product_id] !== '') {
                $candidate = wc_format_decimal($value[$product_id]);
                if ($candidate !== '' && (float) $candidate > 0) {
                    return $candidate;
                }
            }
            $value = '';
        }

        if ($value !== '' && $value !== null) {
            $value = wc_format_decimal($value);
            if ($value !== '' && (float) $value > 0) {
                return $value;
            }
        }

        if ($product instanceof WC_Product_Variation) {
            $parent_id = $product->get_parent_id();

            if ($parent_id) {
                $parent_value = get_post_meta($parent_id, $meta_key, true);

                if (is_array($parent_value)) {
                    if (isset($parent_value[$product_id]) && $parent_value[$product_id] !== '') {
                        $candidate = wc_format_decimal($parent_value[$product_id]);
                        if ($candidate !== '' && (float) $candidate > 0) {
                            return $candidate;
                        }
                    }
                    return null;
                }

                if ($parent_value !== '' && $parent_value !== null) {
                    $parent_value = wc_format_decimal($parent_value);
                    if ($parent_value !== '' && (float) $parent_value > 0) {
                        return $parent_value;
                    }
                }
            }
        }

        return null;
    }

    private function get_raw_base_price_for_frontend($variation, $parent_product = null) {
        if (!$variation instanceof WC_Product_Variation) {
            return null;
        }

        $variation_id = $variation->get_id();
        $parent_id = $variation->get_parent_id();

        $price = get_post_meta($variation_id, '_price', true);
        $regular_price = get_post_meta($variation_id, '_regular_price', true);

        if (!is_array($price) && $price !== '' && $price !== null) {
            $price = wc_format_decimal($price);
            if ($price !== '' && (float) $price > 0) {
                return array(
                    'price' => (float) $price,
                    'regular_price' => (!is_array($regular_price) && $regular_price !== '' && $regular_price !== null)
                        ? (float) wc_format_decimal($regular_price)
                        : (float) $price,
                );
            }
        }

        if (!is_array($regular_price) && $regular_price !== '' && $regular_price !== null) {
            $regular_price = wc_format_decimal($regular_price);
            if ($regular_price !== '' && (float) $regular_price > 0) {
                return array(
                    'price' => (float) $regular_price,
                    'regular_price' => (float) $regular_price,
                );
            }
        }

        if ($parent_id) {
            $parent_price = get_post_meta($parent_id, '_price', true);
            $parent_regular_price = get_post_meta($parent_id, '_regular_price', true);

            if (!is_array($parent_price) && $parent_price !== '' && $parent_price !== null) {
                $parent_price = wc_format_decimal($parent_price);
                if ($parent_price !== '' && (float) $parent_price > 0) {
                    return array(
                        'price' => (float) $parent_price,
                        'regular_price' => (!is_array($parent_regular_price) && $parent_regular_price !== '' && $parent_regular_price !== null)
                            ? (float) wc_format_decimal($parent_regular_price)
                            : (float) $parent_price,
                    );
                }
            }

            if (!is_array($parent_regular_price) && $parent_regular_price !== '' && $parent_regular_price !== null) {
                $parent_regular_price = wc_format_decimal($parent_regular_price);
                if ($parent_regular_price !== '' && (float) $parent_regular_price > 0) {
                    return array(
                        'price' => (float) $parent_regular_price,
                        'regular_price' => (float) $parent_regular_price,
                    );
                }
            }
        }

        return null;
    }

    private function is_enabled_hide_flag($value) {
        return in_array($value, array('yes', '1', 1, true, 'true', 'on'), true);
    }

    private function is_single_product_query($query) {
        if (!($query instanceof WP_Query)) {
            return false;
        }

        $post_type  = $query->get('post_type');
        $name       = $query->get('name');
        $page_id    = $query->get('p');
        $product_qv = $query->get('product');

        if ($post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true))) {
            if (!empty($name) || !empty($page_id) || !empty($product_qv)) {
                return true;
            }
        }

        if (!empty($product_qv)) {
            return true;
        }

        return false;
    }

    private function get_product_category_ids_with_ancestors($product_id) {
        $this->is_collecting_blocked_terms = true;

        $term_ids = wp_get_post_terms($product_id, 'product_cat', array(
            'fields' => 'ids',
        ));

        $this->is_collecting_blocked_terms = false;

        if (is_wp_error($term_ids) || empty($term_ids)) {
            return array();
        }

        $all_ids = array_map('intval', $term_ids);

        foreach ($term_ids as $term_id) {
            $ancestors = get_ancestors((int) $term_id, 'product_cat', 'taxonomy');
            if (!empty($ancestors) && is_array($ancestors)) {
                $all_ids = array_merge($all_ids, array_map('intval', $ancestors));
            }
        }

        return array_values(array_unique($all_ids));
    }

    public function filter_product_price($price, $product) {
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }

        if (!$product instanceof WC_Product) {
            return $price;
        }

        if ($this->is_product_hidden_for_current_region($product->get_id(), $product)) {
            return '';
        }

        if ($product->is_type('variable') || $product instanceof WC_Product_Variation) {
            return $price;
        }

        $regional_price = $this->get_regional_price_for_product($product);

        if ($regional_price === null || $regional_price === '') {
            return $price;
        }

        return $regional_price;
    }

    public function maybe_hide_price_html($html, $product) {
        if (!$product instanceof WC_Product) {
            return $html;
        }

        if ($this->is_product_hidden_for_current_region($product->get_id(), $product)) {
            return '<span class="price out-of-region">Недоступно у вибраному регіоні</span>';
        }

        return $html;
    }

    private function get_min_regional_variation_price($product, $region) {
        if (!$product instanceof WC_Product_Variable) {
            return null;
        }

        $variation_ids = $product->get_children();
        if (empty($variation_ids)) {
            return null;
        }

        $prices = array();

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation || !($variation instanceof WC_Product_Variation)) {
                continue;
            }

            if ($this->is_product_hidden_for_region((int) $variation_id, $region)) {
                continue;
            }

            $regional_price = $this->get_regional_price_for_product_by_region($variation, $region);

            if ($regional_price !== null && $regional_price !== '') {
                $regional_price = (float) wc_format_decimal($regional_price);

                if ($regional_price > 0) {
                    $prices[] = $regional_price;
                }
            }
        }

        if (empty($prices)) {
            return null;
        }

        return min($prices);
    }

    public function filter_variable_price_html_by_region($price_html, $product) {
        if (!$product instanceof WC_Product_Variable) {
            return $price_html;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $price_html;
        }

        if ($this->is_product_hidden_for_region($product->get_id(), $region)) {
            return '<span class="price out-of-region">Недоступно у вибраному регіоні</span>';
        }

        if ($this->are_all_variations_hidden_for_region($product, $region)) {
            return '<span class="price out-of-region">Недоступно у вибраному регіоні</span>';
        }

        $min_price = $this->get_min_regional_variation_price($product, $region);
        if ($min_price === null) {
            return $price_html;
        }

        return wc_price($min_price);
    }

    public function filter_product_purchasable_by_region($purchasable, $product) {
        if (!$product instanceof WC_Product) {
            return $purchasable;
        }

        if ($this->is_product_hidden_for_current_region($product->get_id(), $product)) {
            return false;
        }

        return $purchasable;
    }

    public function is_product_hidden_for_current_region($product_id, $product = null) {
        $region = $this->context->get_current_region();
        return $this->is_product_hidden_for_region($product_id, $region);
    }

    private function is_product_hidden_for_region($product_id, $region) {
        if ($region === 'kharkiv') {
            return false;
        }

        $meta_key = $this->context->get_region_hide_meta_key($region);
        if (!$meta_key) {
            return false;
        }

        $parent_id = wp_get_post_parent_id($product_id);
        $check_id = $parent_id ?: $product_id;

        if ($this->is_enabled_hide_flag(get_post_meta($check_id, $meta_key, true))) {
            return true;
        }

        // окремий hide flag на самій варіації
        if ($parent_id && $this->is_enabled_hide_flag(get_post_meta($product_id, $meta_key, true))) {
            return true;
        }

        $term_meta_key = $this->get_term_hide_meta_key_for_region($region);
        if (!$term_meta_key) {
            return false;
        }

        $all_term_ids = $this->get_product_category_ids_with_ancestors($check_id);

        if (!empty($all_term_ids)) {
            foreach ($all_term_ids as $term_id) {
                if ($this->is_enabled_hide_flag(get_term_meta((int) $term_id, $term_meta_key, true))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function validate_add_to_cart_region_visibility($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        $check_id = $variation_id ?: $product_id;

        if ($this->is_product_hidden_for_current_region($check_id)) {
            wc_add_notice('Цей товар недоступний у вибраному регіоні.', 'error');
            return false;
        }

        return $passed;
    }

    public function add_regional_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity) {
        $region = 'kharkiv';

        if (!empty($_POST['rockside_region'])) {
            $region = sanitize_key(wp_unslash($_POST['rockside_region']));
        } elseif (!empty($_POST['region_id'])) {
            $region = sanitize_key(wp_unslash($_POST['region_id']));
        } else {
            $region = $this->context->get_current_region();
        }

        if (!$this->context->is_valid_region($region)) {
            $region = 'kharkiv';
        }

        $target_id = $variation_id ?: $product_id;
        $product = wc_get_product($target_id);

        if (!$product) {
            return $cart_item_data;
        }

        $regional_price = $this->get_regional_price_for_product_by_region($product, $region);

        $cart_item_data['rockside_region'] = $region;
        $cart_item_data['rockside_source_product_id'] = $target_id;

        if ($regional_price !== null && $regional_price !== '') {
            $cart_item_data['rockside_regional_price'] = wc_format_decimal($regional_price);
        }

        return $cart_item_data;
    }

    public function restore_regional_cart_item_data($cart_item, $session_values, $cart_item_key) {
        if (isset($session_values['rockside_region'])) {
            $cart_item['rockside_region'] = $session_values['rockside_region'];
        }

        if (isset($session_values['rockside_regional_price'])) {
            $cart_item['rockside_regional_price'] = $session_values['rockside_regional_price'];
        }

        if (isset($session_values['rockside_source_product_id'])) {
            $cart_item['rockside_source_product_id'] = $session_values['rockside_source_product_id'];
        }

        return $cart_item;
    }

    public function sync_cart_item_prices($cart) {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
                continue;
            }

            $product = $cart_item['data'];

            $item_region = !empty($cart_item['rockside_region'])
                ? sanitize_key($cart_item['rockside_region'])
                : $this->context->get_current_region();

            if ($this->is_product_hidden_for_region($product->get_id(), $item_region)) {
                continue;
            }

            if (isset($cart_item['rockside_regional_price']) && $cart_item['rockside_regional_price'] !== '') {
                $regional_price = wc_format_decimal($cart_item['rockside_regional_price']);
            } else {
                $regional_price = $this->get_regional_price_for_product_by_region($product, $item_region);
            }

            if ($regional_price !== null && $regional_price !== '') {
                $product->set_price($regional_price);
                $product->set_regular_price($regional_price);
                $product->set_sale_price('');
            }
        }
    }

    public function refresh_cart_item_price_display($price_html, $cart_item, $cart_item_key) {
        if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
            return $price_html;
        }

        return wc_price($cart_item['data']->get_price());
    }

    public function refresh_cart_item_subtotal_display($subtotal_html, $cart_item, $cart_item_key) {
        if (empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
            return $subtotal_html;
        }

        $line_total = (float) $cart_item['data']->get_price() * (float) $cart_item['quantity'];
        return wc_price($line_total);
    }

    public function filter_product_schema($markup, $product) {
        if (!is_array($markup) || !$product instanceof WC_Product) {
            return $markup;
        }

        $price = $product->get_price();

        if (isset($markup['offers'])) {
            if (isset($markup['offers']['price'])) {
                $markup['offers']['price'] = (string) wc_format_decimal($price);
            }

            if (isset($markup['offers']['url'])) {
                $markup['offers']['url'] = $this->context->build_regional_url(get_permalink($product->get_id()));
            }

            if ($this->is_product_hidden_for_current_region($product->get_id(), $product)) {
                $markup['offers']['availability'] = 'https://schema.org/OutOfStock';
            }
        }

        return $markup;
    }

    public function maybe_redirect_hidden_products() {
        if (!is_product()) {
            return;
        }

        global $post, $wp_query;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        if (!$this->is_product_hidden_for_current_region($post->ID, $product)) {
            return;
        }

        status_header(200);

        if ($wp_query) {
            $wp_query->is_404 = false;
        }
    }

    public function render_hidden_product_notice() {
        if (!is_product()) {
            return;
        }

        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        if (!$this->is_product_hidden_for_current_region($post->ID, $product)) {
            return;
        }

        wc_print_notice('Товар не доступний у Вашому регіоні.', 'notice');

        echo '<style>
            body.single-product .single-product-page .product .summary,
            body.single-product .single-product-page .product .woocommerce-product-gallery,
            body.single-product .single-product-page .product .images,
            body.single-product .single-product-page .product form.cart,
            body.single-product .single-product-page .product .cart,
            body.single-product .single-product-page .product .woocommerce-tabs,
            body.single-product .single-product-page .product .related,
            body.single-product .single-product-page .product .upsells,
            body.single-product .single-product-page .product .wd-product-meta,
            body.single-product .single-product-page .product .wd-product-brands,
            body.single-product .single-product-page .product .wd-accordion-tabs,
            body.single-product .single-product-page .product .wd-single-add-btn,
            body.single-product .single-product-page .product .wd-action-btn {
                display: none !important;
            }
        </style>';
    }

    public function filter_catalog_queries($query, $query_vars) {
        if (is_admin()) {
            return;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return;
        }

        $meta_key = $this->context->get_region_hide_meta_key($region);
        if (!$meta_key) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => $meta_key,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => $meta_key,
                'value'   => 'yes',
                'compare' => '!=',
            ),
        );
        $query->set('meta_query', $meta_query);

        $blocked_category_ids = $this->get_blocked_category_ids_for_region($region);
        if (!empty($blocked_category_ids)) {
            $tax_query = (array) $query->get('tax_query');
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $blocked_category_ids,
                'operator' => 'NOT IN',
            );
            $query->set('tax_query', $tax_query);
        }
    }

    public function filter_frontend_product_queries_by_region($query) {
        if (is_admin() || !($query instanceof WP_Query)) {
            return;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return;
        }

        if ($this->is_single_product_query($query)) {
            return;
        }

        $is_product_context =
            $this->query_contains_products($query) ||
            $query->is_tax('product_cat') ||
            $query->is_post_type_archive('product');

        if (!$is_product_context) {
            return;
        }

        $meta_key = $this->context->get_region_hide_meta_key($region);
        if ($meta_key) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => $meta_key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => $meta_key,
                    'value'   => 'yes',
                    'compare' => '!=',
                ),
            );
            $query->set('meta_query', $meta_query);
        }

        $blocked_category_ids = $this->get_blocked_category_ids_for_region($region);
        if (!empty($blocked_category_ids)) {
            $tax_query = (array) $query->get('tax_query');
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $blocked_category_ids,
                'operator' => 'NOT IN',
            );
            $query->set('tax_query', $tax_query);
        }
    }

    public function filter_related_products($related_posts, $product_id, $args) {
        if (empty($related_posts)) {
            return $related_posts;
        }

        return array_values(array_filter($related_posts, function ($related_id) {
            return !$this->should_hide_product_from_catalog_by_region((int) $related_id);
        }));
    }

    private function get_blocked_category_ids_for_region($region) {
        if ($region === 'kharkiv') {
            return array();
        }

        $term_meta_key = $this->get_term_hide_meta_key_for_region($region);
        if (!$term_meta_key) {
            return array();
        }

        $this->is_collecting_blocked_terms = true;

        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'fields'     => 'ids',
            'meta_query' => array(
                array(
                    'key'   => $term_meta_key,
                    'value' => 'yes',
                ),
            ),
        ));

        $this->is_collecting_blocked_terms = false;

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $all_ids = array_map('intval', $terms);

        foreach ($terms as $term_id) {
            $children = get_term_children((int) $term_id, 'product_cat');
            if (!is_wp_error($children) && !empty($children)) {
                $all_ids = array_merge($all_ids, array_map('intval', $children));
            }
        }

        return array_values(array_unique($all_ids));
    }

    private function query_contains_products($query) {
        $post_type = $query->get('post_type');

        if (empty($post_type)) {
            return (bool) $query->is_search();
        }

        if ($post_type === 'product') {
            return true;
        }

        if (is_array($post_type) && in_array('product', $post_type, true)) {
            return true;
        }

        return false;
    }

    private function are_all_variations_hidden_for_region($product, $region) {
        if (!$product instanceof WC_Product_Variable) {
            return false;
        }

        $variation_ids = $product->get_children();
        if (empty($variation_ids)) {
            return false;
        }

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation || !($variation instanceof WC_Product_Variation)) {
                continue;
            }

            if (!$this->is_product_hidden_for_region((int) $variation_id, $region)) {
                return false;
            }
        }

        return true;
    }

    private function should_hide_product_from_catalog_by_region($product_id) {
        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return false;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        if ($this->is_product_hidden_for_region((int) $product_id, $region)) {
            return true;
        }

        if ($product instanceof WC_Product_Variable && $this->are_all_variations_hidden_for_region($product, $region)) {
            return true;
        }

        return false;
    }

    public function filter_catalog_posts_with_hidden_variations($posts, $query) {
        if (is_admin() || !($query instanceof WP_Query) || empty($posts)) {
            return $posts;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $posts;
        }

        $is_product_context = $query->is_main_query() && (
            $query->is_tax('product_cat') ||
            $query->is_post_type_archive('product') ||
            $query->is_search()
        );

        if (!$is_product_context) {
            return $posts;
        }

        $filtered = array();

        foreach ($posts as $post) {
            if (empty($post->ID) || $post->post_type !== 'product') {
                $filtered[] = $post;
                continue;
            }

            if ($this->should_hide_product_from_catalog_by_region((int) $post->ID)) {
                continue;
            }

            $filtered[] = $post;
        }

        return array_values($filtered);
    }

    public function keep_hidden_variation_selectable($active, $variation) {
        if (!$variation instanceof WC_Product_Variation) {
            return $active;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $active;
        }

        $meta_key = $this->context->get_region_hide_meta_key($region);
        if (!$meta_key) {
            return $active;
        }

        $variation_hidden = $this->is_enabled_hide_flag(get_post_meta($variation->get_id(), $meta_key, true));

        if ($variation_hidden) {
            return true;
        }

        return $active;
    }

    public function filter_available_variation_by_region($data, $product, $variation) {
        if (!$variation instanceof WC_Product_Variation) {
            return $data;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $data;
        }

        $meta_key = $this->context->get_region_hide_meta_key($region);
        if (!$meta_key) {
            return $data;
        }

        $variation_hidden = $this->is_enabled_hide_flag(get_post_meta($variation->get_id(), $meta_key, true));
        $parent_hidden = $this->is_enabled_hide_flag(get_post_meta($product->get_id(), $meta_key, true));

        if ($variation_hidden || $parent_hidden) {
            $data['is_in_stock'] = false;
            $data['is_purchasable'] = false;
            $data['variation_is_active'] = true;
            $data['availability_html'] = '<p class="stock out-of-stock">Недоступно у вибраному регіоні</p>';
            $data['price_html'] = '<span class="price out-of-region">Недоступно у вибраному регіоні</span>';
            return $data;
        }

        $regional_price = $this->get_regional_price_for_product_by_region($variation, $region);

        if ($regional_price !== null && $regional_price !== '') {
            $regional_price = (float) wc_format_decimal($regional_price);

            if ($regional_price > 0) {
                $data['display_price'] = $regional_price;
                $data['display_regular_price'] = $regional_price;
                $data['price_html'] = wc_price($regional_price);
            }

            return $data;
        }

        $incoming_price = isset($data['display_price']) ? (float) $data['display_price'] : 0;

        if ($incoming_price <= 1) {
            $base_prices = $this->get_raw_base_price_for_frontend($variation, $product);

            if (!empty($base_prices) && !empty($base_prices['price']) && (float) $base_prices['price'] > 1) {
                $display_price = (float) $base_prices['price'];
                $display_regular_price = !empty($base_prices['regular_price']) && (float) $base_prices['regular_price'] > 1
                    ? (float) $base_prices['regular_price']
                    : $display_price;

                $data['display_price'] = $display_price;
                $data['display_regular_price'] = $display_regular_price;
                $data['price_html'] = wc_price($display_price);
            }
        }

        return $data;
    }

    public function replace_hidden_variation_add_to_cart_message($html) {
        if (!is_product()) {
            return $html;
        }

        global $product;

        if (!$product || !$product->is_type('variable')) {
            return $html;
        }

        return $html . '<style>
            .single_variation_wrap .woocommerce-variation-add-to-cart-disabled .single_add_to_cart_button,
            .single_variation_wrap .woocommerce-variation-add-to-cart-disabled .quantity {
                display: none !important;
            }
            .single_variation_wrap .rockside-variation-unavailable-message {
                display: none;
                margin-top: 12px;
                font-weight: 600;
            }
            .single_variation_wrap.rockside-variation-hidden .rockside-variation-unavailable-message {
                display: block;
            }
        </style>
        <div class="rockside-variation-unavailable-message">Недоступно у вибраному регіоні</div>
        <script>
        jQuery(function($){
            var $form = $("form.variations_form");

            function toggleRocksideVariationMessage(variation) {
                var $wrap = $form.find(".single_variation_wrap");

                if (variation && variation.is_purchasable === false && variation.availability_html && variation.availability_html.indexOf("Недоступно у вибраному регіоні") !== -1) {
                    $wrap.addClass("rockside-variation-hidden");
                } else {
                    $wrap.removeClass("rockside-variation-hidden");
                }
            }

            $form.on("found_variation", function(event, variation){
                toggleRocksideVariationMessage(variation);
            });

            $form.on("reset_data hide_variation", function(){
                $form.find(".single_variation_wrap").removeClass("rockside-variation-hidden");
            });
        });
        </script>';
    }

    private function get_term_hide_meta_key_for_region($region) {
        if ($region === 'kyiv') {
            return '_rockside_hide_in_kyiv';
        }

        if ($region === 'lviv') {
            return '_rockside_hide_in_lviv';
        }

        if ($region === 'odesa') {
            return '_rockside_hide_in_odesa';
        }

        return '';
    }

    private function is_product_category_hidden_for_region($term_id, $region) {
        if ($region === 'kharkiv') {
            return false;
        }

        $term_meta_key = $this->get_term_hide_meta_key_for_region($region);
        if (!$term_meta_key) {
            return false;
        }

        return $this->is_enabled_hide_flag(get_term_meta((int) $term_id, $term_meta_key, true));
    }

    private function is_specific_term_resolution_query($term_query) {
        if (!($term_query instanceof WP_Term_Query)) {
            return false;
        }

        $query_vars = isset($term_query->query_vars) && is_array($term_query->query_vars)
            ? $term_query->query_vars
            : array();

        if (!empty($query_vars['slug'])) {
            return true;
        }

        if (!empty($query_vars['name'])) {
            return true;
        }

        if (!empty($query_vars['term_taxonomy_id'])) {
            return true;
        }

        if (!empty($query_vars['include'])) {
            return true;
        }

        return false;
    }

    public function filter_product_cat_terms_query_by_region($term_query) {
        if (is_admin()) {
            return;
        }

        if ($this->is_collecting_blocked_terms) {
            return;
        }

        if (!($term_query instanceof WP_Term_Query)) {
            return;
        }

        $taxonomies = isset($term_query->query_vars['taxonomy']) ? $term_query->query_vars['taxonomy'] : array();

        if (is_string($taxonomies) && $taxonomies !== '') {
            $taxonomies = array($taxonomies);
        }

        if (!is_array($taxonomies) || !in_array('product_cat', $taxonomies, true)) {
            return;
        }

        // дозволяємо WP резолвити конкретну category archive, навіть якщо вона прихована
        if ($this->is_specific_term_resolution_query($term_query)) {
            return;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return;
        }

        $blocked_ids = $this->get_blocked_category_ids_for_region($region);
        if (empty($blocked_ids)) {
            return;
        }

        $existing_exclude = isset($term_query->query_vars['exclude']) ? $term_query->query_vars['exclude'] : array();

        if (!is_array($existing_exclude)) {
            $existing_exclude = array_filter(array_map('intval', explode(',', (string) $existing_exclude)));
        }

        $term_query->query_vars['exclude'] = array_values(array_unique(array_merge($existing_exclude, $blocked_ids)));
    }

    public function filter_nav_menu_items_by_region($items, $args) {
        if (is_admin() || empty($items)) {
            return $items;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $items;
        }

        $blocked_ids = $this->get_blocked_category_ids_for_region($region);
        if (empty($blocked_ids)) {
            return $items;
        }

        $hidden_menu_item_ids = array();
        $filtered = array();

        foreach ($items as $item) {
            $hide_item = false;

            if (
                !empty($item->object) &&
                $item->object === 'product_cat' &&
                !empty($item->object_id) &&
                in_array((int) $item->object_id, $blocked_ids, true)
            ) {
                $hide_item = true;
            }

            if (
                !$hide_item &&
                !empty($item->type) &&
                $item->type === 'custom' &&
                !empty($item->url)
            ) {
                $term_id = $this->detect_product_cat_term_id_from_url($item->url);

                if ($term_id && in_array((int) $term_id, $blocked_ids, true)) {
                    $hide_item = true;
                }
            }

            if ($hide_item) {
                $hidden_menu_item_ids[] = (int) $item->ID;
                continue;
            }

            $filtered[] = $item;
        }

        if (!empty($hidden_menu_item_ids)) {
            $changed = true;

            while ($changed) {
                $changed = false;
                $next_filtered = array();

                foreach ($filtered as $item) {
                    if (in_array((int) $item->menu_item_parent, $hidden_menu_item_ids, true)) {
                        $hidden_menu_item_ids[] = (int) $item->ID;
                        $changed = true;
                        continue;
                    }

                    $next_filtered[] = $item;
                }

                $filtered = $next_filtered;
                $hidden_menu_item_ids = array_values(array_unique(array_map('intval', $hidden_menu_item_ids)));
            }
        }

        return array_values($filtered);
    }

    private function detect_product_cat_term_id_from_url($url) {
        $parts = wp_parse_url($url);
        if (empty($parts['path'])) {
            return 0;
        }

        $path = trim((string) $parts['path'], '/');
        $path = preg_replace('#^(kyiv|lviv|odesa)(/|$)#', '', $path);
        $path = trim((string) $path, '/');

        if ($path === '') {
            return 0;
        }

        if (!preg_match('#^product-category/(.+)$#', $path, $matches)) {
            return 0;
        }

        $slug_path = trim($matches[1], '/');
        if ($slug_path === '') {
            return 0;
        }

        $segments = explode('/', $slug_path);
        $slug = end($segments);

        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term || is_wp_error($term)) {
            return 0;
        }

        return (int) $term->term_id;
    }

    public function maybe_redirect_hidden_product_category() {
        // більше не редиректимо приховану категорію на головну
        return;
    }
}