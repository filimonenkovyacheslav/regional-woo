<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Output_Fix {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_action('template_redirect', array($this, 'start_buffer'), 0);
    }

    public function start_buffer() {
        if (is_admin()) {
            return;
        }

        ob_start(array($this, 'rewrite_output'));
    }

    public function rewrite_output($html) {
        if (!is_string($html) || $html === '') {
            return $html;
        }

        $html = $this->rewrite_home_links($html);
        $html = $this->rewrite_region_blocked_search_message($html);
        $html = $this->rewrite_hidden_product_category_output($html);

        return $html;
    }

    private function rewrite_home_links($html) {
        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $html;
        }

        $slug = $this->context->get_region_slug($region);
        if (!$slug) {
            return $html;
        }

        $regional_home = trailingslashit(home_url('/' . $slug));

        $home_variants = array_unique(array(
            home_url('/'),
            home_url(''),
            rtrim(home_url('/'), '/'),
        ));

        foreach ($home_variants as $home_url) {
            $escaped_home = preg_quote($home_url, '#');

            $html = preg_replace(
                '#(<a\b[^>]*href=")' . $escaped_home . '(/?)("(?=[^>]*class="[^"]*(?:wd-logo|custom-logo-link|site-logo-link|main-logo|sticky-logo)[^"]*")[^>]*>)#i',
                '$1' . esc_url($regional_home) . '$3',
                $html
            );

            $html = preg_replace(
                '#(<a\b(?=[^>]*class="[^"]*(?:wd-logo|custom-logo-link|site-logo-link|main-logo|sticky-logo)[^"]*")[^>]*href=")' . $escaped_home . '(/?)(")#i',
                '$1' . esc_url($regional_home) . '$3',
                $html
            );

            $html = preg_replace(
                '#(<nav\b[^>]*class="[^"]*wd-breadcrumbs[^"]*"[^>]*>.*?<a\b[^>]*href=")' . $escaped_home . '(/?)(")#is',
                '$1' . esc_url($regional_home) . '$3',
                $html,
                1
            );
        }

        return $html;
    }

    private function rewrite_region_blocked_search_message($html) {
        if (!$this->should_replace_no_products_message($html)) {
            return $html;
        }

        $replacement = '<div class="woocommerce-no-products-found"><div class="woocommerce-info" role="status">Товар не доступний у Вашому регіоні.</div></div>';

        $html = preg_replace(
            '#<div class="woocommerce-no-products-found">\s*<div class="woocommerce-info"[^>]*>.*?</div>\s*</div>#su',
            $replacement,
            $html,
            1
        );

        return str_replace(
            'Товарів, відповідних вашому запиту, не знайдено.',
            'Товар не доступний у Вашому регіоні.',
            $html
        );
    }

    private function rewrite_hidden_product_category_output($html) {
        if (is_admin() || !is_tax('product_cat')) {
            return $html;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return $html;
        }

        $term = get_queried_object();
        if (!$term || empty($term->term_id) || empty($term->name)) {
            return $html;
        }

        $meta_key = '';
        if ($region === 'kyiv') {
            $meta_key = '_rockside_hide_in_kyiv';
        } elseif ($region === 'lviv') {
            $meta_key = '_rockside_hide_in_lviv';
        }

        if (!$meta_key) {
            return $html;
        }

        $value = get_term_meta((int) $term->term_id, $meta_key, true);
        $hidden = in_array($value, array('yes', '1', 1, true, 'true', 'on'), true);

        if (!$hidden) {
            return $html;
        }

        $replacement = '<div class="woocommerce-no-products-found"><div class="woocommerce-info" role="status">Товар не доступний у Вашому регіоні.</div></div>';

        // 1. Breadcrumb 404 -> назва категорії
        $html = preg_replace(
            '#(<nav\b[^>]*class="[^"]*wd-breadcrumbs[^"]*woocommerce-breadcrumb[^"]*"[^>]*>.*?<span class="wd-last">\s*)(Помилка 404)(\s*</span>)#isu',
            '$1' . esc_html($term->name) . '$3',
            $html,
            1
        );

        // 2. Стандартний текст no-products -> наш текст
        $html = str_replace(
            'Товарів, відповідних вашому запиту, не знайдено.',
            'Товар не доступний у Вашому регіоні.',
            $html
        );

        // 3. Якщо notice ще нема — вставимо після shop-loop-head
        if (strpos($html, 'Товар не доступний у Вашому регіоні.') === false) {
            $html = preg_replace(
                '#(<div class="shop-loop-head">.*?</div>)#isu',
                '$1' . $replacement,
                $html,
                1
            );
        }

        // 4. Зводимо дублікати notices до одного
        $html = preg_replace(
            '#(?:<div class="woocommerce-no-products-found">\s*<div class="woocommerce-info"[^>]*>.*?</div>\s*</div>\s*)+#isu',
            $replacement,
            $html,
            1
        );

        return $html;
    }

    private function should_replace_no_products_message($html) {
        if (is_admin() || !is_search()) {
            return false;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
        if ($post_type !== 'product') {
            return false;
        }

        $region = $this->context->get_current_region();
        if ($region === 'kharkiv') {
            return false;
        }

        if (strpos($html, 'woocommerce-no-products-found') === false) {
            return false;
        }

        global $wp_query;
        if ($wp_query && (int) $wp_query->found_posts > 0) {
            return false;
        }

        $search_term = trim((string) get_search_query());
        if ($search_term === '') {
            return false;
        }

        return $this->has_products_without_regional_filter($search_term);
    }

    private function has_products_without_regional_filter($search_term) {
        global $wpdb;

        $like = '%' . $wpdb->esc_like($search_term) . '%';

        $sql = $wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE post_type = 'product'
               AND post_status = 'publish'
               AND post_title LIKE %s
             LIMIT 1",
            $like
        );

        $found_id = $wpdb->get_var($sql);

        return !empty($found_id);
    }
}