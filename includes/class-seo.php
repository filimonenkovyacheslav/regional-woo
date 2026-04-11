<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_SEO {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_filter('post_type_link', array($this, 'filter_post_type_link'), 10, 2);
        add_filter('term_link', array($this, 'filter_term_link'), 10, 3);
        add_filter('page_link', array($this, 'filter_page_link'), 10, 2);
        add_filter('post_link', array($this, 'filter_blog_post_link'), 10, 2);

        add_filter('get_search_form', array($this, 'filter_search_form'));
        add_filter('woocommerce_product_search_form', array($this, 'filter_search_form'));

        add_filter('wp_nav_menu_objects', array($this, 'filter_nav_menu_objects'), 10, 2);

        add_action('template_redirect', array($this, 'inject_canonical_header'), 2);

        add_filter('wp_robots', array($this, 'filter_wp_robots'));

        add_filter('wpseo_title', array($this, 'filter_yoast_title'), 99);
        add_filter('wpseo_metadesc', array($this, 'filter_yoast_metadesc'), 99);
        add_filter('wpseo_canonical', array($this, 'filter_yoast_canonical'), 99);
        add_filter('wpseo_opengraph_url', array($this, 'filter_yoast_canonical'), 99);
        add_filter('home_url', array($this, 'filter_home_url'), 10, 4);

        add_filter('woocommerce_get_cart_url', array($this, 'filter_woocommerce_special_page_url'));
        add_filter('woocommerce_get_checkout_url', array($this, 'filter_woocommerce_special_page_url'));
        add_filter('woocommerce_get_permalink', array($this, 'filter_woocommerce_special_page_permalink'), 10, 2);
        add_filter('woocommerce_get_script_data', array($this, 'filter_woocommerce_script_data'), 20, 2);
    }

    public function filter_woocommerce_special_page_url($url) {
        if (is_admin()) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_woocommerce_special_page_permalink($url, $page_id) {
        if (is_admin() || !$page_id) {
            return $url;
        }

        $special_pages = array_filter(array(
            wc_get_page_id('cart'),
            wc_get_page_id('checkout'),
            wc_get_page_id('myaccount'),
            wc_get_page_id('shop'),
        ));

        if (!in_array((int) $page_id, array_map('intval', $special_pages), true)) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_woocommerce_script_data($params, $handle) {
        if (is_admin() || !is_array($params)) {
            return $params;
        }

        $region = $this->context->get_current_region();

        if ($handle === 'wc-add-to-cart-js' && !empty($params['cart_url'])) {
            if ($region === 'kharkiv') {
                $params['cart_url'] = remove_query_arg('region_id', $params['cart_url']);
            } else {
                $params['cart_url'] = $this->context->build_regional_url($params['cart_url'], $region);
            }
        }

        if ($handle === 'wc-checkout' && !empty($params['checkout_url'])) {
            if ($region === 'kharkiv') {
                $params['checkout_url'] = remove_query_arg('region_id', $params['checkout_url']);
            } else {
                $params['checkout_url'] = $this->context->build_regional_url($params['checkout_url'], $region);
            }
        }

        if ($handle === 'woocommerce-js' && !empty($params['wc_ajax_url'])) {
            if ($region === 'kharkiv') {
                $params['wc_ajax_url'] = remove_query_arg('region_id', $params['wc_ajax_url']);
            } else {
                $params['wc_ajax_url'] = $this->context->build_regional_url($params['wc_ajax_url'], $region);
            }
        }

        return $params;
    }

    public function filter_home_url($url, $path, $orig_scheme, $blog_id) {
        if (is_admin()) {
            return $url;
        }

        if (function_exists('get_current_blog_id') && (int) $blog_id !== (int) get_current_blog_id()) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        $normalized_path = trim((string) $path, '/');

        // Для непустых путей не вмешиваемся.
        if ($normalized_path !== '') {
            return $url;
        }

        // ВАЖНО: не вызывать home_url('/') внутри фильтра home_url.
        return $this->context->build_regional_url($url, $region);
    }

    public function filter_blog_post_link($url, $post) {
        if ($post->post_type !== 'post') {
            return $url;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_post_type_link($url, $post) {
        if (empty($post->post_type)) {
            return $url;
        }

        $allowed_post_types = array(
            'product',
            'portfolio',
        );

        if (!in_array($post->post_type, $allowed_post_types, true)) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_term_link($url, $term, $taxonomy) {
        $allowed_taxonomies = array(
            'product_cat',
            'project-cat',
        );

        if (!in_array($taxonomy, $allowed_taxonomies, true)) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_page_link($url, $post_id) {
        if (is_admin()) {
            return $url;
        }

        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return remove_query_arg('region_id', $url);
        }

        return $this->context->build_regional_url($url, $region);
    }

    public function filter_search_form($form) {
        if (is_admin()) {
            return $form;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $form;
        }

        $search_action = esc_url(home_url('/'));

        $form = preg_replace(
            '#action=("|\')(.*?)\1#',
            'action="' . $search_action . '"',
            $form,
            1
        );

        if (strpos($form, 'name="region_id"') === false && strpos($form, "name='region_id'") === false) {
            $hidden = '<input type="hidden" name="region_id" value="' . esc_attr($region) . '">';
            $form = preg_replace('#</form>#', $hidden . '</form>', $form, 1);
        }

        return $form;
    }

    public function filter_nav_menu_objects($items, $args) {
        if (is_admin()) {
            return $items;
        }

        $region = $this->context->get_current_region();
        $filtered = array();
        $hidden_ids = array();

        foreach ($items as $item) {
            if ($this->is_menu_item_hidden_for_region($item)) {
                $hidden_ids[] = (int) $item->ID;
                continue;
            }

            $filtered[] = $item;
        }

        // Убираем дочерние пункты скрытых родительских элементов
        if (!empty($hidden_ids)) {
            $filtered = array_filter($filtered, function ($item) use ($hidden_ids) {
                return !in_array((int) $item->menu_item_parent, $hidden_ids, true);
            });
        }

        // Регионализируем ссылки только для оставшихся пунктов
        if ($region !== 'kharkiv') {
            foreach ($filtered as $item) {
                if (empty($item->url)) {
                    continue;
                }

                $url = $item->url;

                if (!$this->is_internal_url($url)) {
                    continue;
                }

                if (
                    strpos($url, '#') === 0 ||
                    strpos($url, 'mailto:') === 0 ||
                    strpos($url, 'tel:') === 0 ||
                    strpos($url, 'javascript:') === 0
                ) {
                    continue;
                }

                $item->url = $this->context->build_regional_url($url, $region);
            }
        }

        return array_values($filtered);
    }

    private function is_internal_url($url) {
        $home = wp_parse_url(home_url('/'));
        $test = wp_parse_url($url);

        if (empty($test['host'])) {
            return true;
        }

        if (empty($home['host'])) {
            return false;
        }

        return strtolower($test['host']) === strtolower($home['host']);
    }

    public function inject_canonical_header() {
        if (is_admin()) {
            return;
        }

        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', function () {
            $canonical = $this->get_canonical_url();
            if ($canonical) {
                echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
            }
        }, 1);
    }

    private function get_base_page_id_for_region_context() {
        $base_page_id = (int) get_query_var('rockside_base_page_id');
        if ($base_page_id > 0) {
            return $base_page_id;
        }

        if (is_front_page()) {
            return (int) get_option('page_on_front');
        }

        if (is_page()) {
            return get_queried_object_id();
        }

        return 0;
    }

    private function has_linked_page_for_region($base_page_id, $region) {
        if ($region === 'kyiv') {
            return (int) get_post_meta($base_page_id, '_rockside_kyiv_page_id', true) > 0;
        }

        if ($region === 'lviv') {
            return (int) get_post_meta($base_page_id, '_rockside_lviv_page_id', true) > 0;
        }

        return false;
    }

    private function get_canonical_url() {
        if (is_front_page()) {
            return $this->context->build_regional_url(home_url('/'));
        }

        if (is_singular('page') || is_singular('post')) {
            $region = $this->context->get_current_region();
            $base_object_id = $this->get_base_page_id_for_region_context();

            if ($base_object_id > 0) {
                return $this->context->build_regional_url(get_permalink($base_object_id), $region);
            }
        }

        if (is_singular()) {
            return $this->context->build_regional_url(get_permalink());
        }

        if (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                return $this->context->build_regional_url(get_term_link($term));
            }
        }

        if (is_search()) {
            $region = $this->context->get_current_region();
            return add_query_arg(
                array(
                    's' => get_search_query(),
                    'post_type' => isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '',
                    'region_id' => $region !== 'kharkiv' ? $region : null,
                ),
                home_url('/')
            );
        }

        global $wp;
        $request = isset($wp->request) ? $wp->request : '';

        return $this->context->build_regional_url(home_url('/' . ltrim($request, '/')));
    }

    public function filter_wp_robots($robots) {
        if (!empty($_GET['region_id'])) {
            $robots['index'] = false;
            $robots['follow'] = true;
        }

        return $robots;
    }

    public function filter_yoast_title($title) {
        if (!is_singular('page') && !is_front_page() && !is_singular('post')) {
            return $title;
        }

        $base_object_id = $this->get_base_page_id_for_region_context();
        if (!$base_object_id) {
            return $title;
        }

        $region = $this->context->get_current_region();

        if ($this->has_linked_page_for_region($base_object_id, $region)) {
            return $title;
        }

        $regional_title = $this->get_regional_page_meta($base_object_id, 'title');
        return $regional_title !== '' ? $regional_title : $title;
    }

    public function filter_yoast_metadesc($description) {
        if (!is_singular('page') && !is_front_page() && !is_singular('post')) {
            return $description;
        }

        $base_object_id = $this->get_base_page_id_for_region_context();
        if (!$base_object_id) {
            return $description;
        }

        $region = $this->context->get_current_region();

        if ($this->has_linked_page_for_region($base_object_id, $region)) {
            return $description;
        }

        $regional_description = $this->get_regional_page_meta($base_object_id, 'description');
        return $regional_description !== '' ? $regional_description : $description;
    }

    public function filter_yoast_canonical($canonical) {
        $regional_canonical = $this->get_canonical_url();
        return $regional_canonical ?: $canonical;
    }

    private function get_regional_page_meta($base_page_id, $field) {
        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return '';
        }

        $map = array(
            'kyiv' => array(
                'title' => '_rockside_kyiv_title',
                'description' => '_rockside_kyiv_description',
            ),
            'lviv' => array(
                'title' => '_rockside_lviv_title',
                'description' => '_rockside_lviv_description',
            ),
        );

        if (empty($map[$region][$field])) {
            return '';
        }

        return trim((string) get_post_meta($base_page_id, $map[$region][$field], true));
    }

    private function is_term_hidden_in_current_region($term_id, $taxonomy = '') {
        $region = $this->context->get_current_region();

        if ($region === 'kharkiv') {
            return false;
        }

        if ((string) $taxonomy !== 'product_cat') {
            return false;
        }

        $hide_meta_key = $this->context->get_region_hide_meta_key($region);
        if (!$hide_meta_key) {
            return false;
        }

        $value = get_term_meta((int) $term_id, $hide_meta_key, true);

        return in_array($value, array('1', 1, true, 'yes', 'on'), true);
    }

    private function is_menu_item_hidden_for_region($item) {
        if (empty($item->object)) {
            return false;
        }

        // Пункт меню = категория WooCommerce
        if ($item->object === 'product_cat' && !empty($item->object_id)) {
            return $this->is_term_hidden_in_current_region((int) $item->object_id, 'product_cat');
        }

        // Кастомная ссылка — пробуем определить по URL
        if ($item->object === 'custom' && !empty($item->url)) {
            $term_id = url_to_postid($item->url);
            if ($term_id) {
                return false;
            }

            $term = get_term_by('slug', basename(untrailingslashit(parse_url($item->url, PHP_URL_PATH))), 'product_cat');
            if ($term && !is_wp_error($term)) {
                return $this->is_term_hidden_in_current_region((int) $term->term_id, 'product_cat');
            }
        }

        return false;
    }
}