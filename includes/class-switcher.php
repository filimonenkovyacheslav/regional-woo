<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Switcher {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_shortcode('rockside_region_switcher', array($this, 'render_switcher_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'print_switcher_script'), 99);
    }

    public function enqueue_assets() {
        $css = '
        .rockside-region-switcher {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .rockside-region-switcher-label {
            margin: 0;
            font-size: 14px;
            line-height: 1.2;
            color: #111;
            white-space: nowrap;
        }
        .rockside-region-switcher-select {
            min-width: 110px;
            height: 40px;
            padding: 0 34px 0 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            font-size: 14px;
            line-height: 38px;
        }
        .rockside-region-switcher--mobile {
            display: flex;
            width: 100%;
            justify-content: center;
            padding: 8px 12px;
        }
        .rockside-region-switcher--mobile .rockside-region-switcher-label {
            display: none;
        }
        .rockside-region-switcher--mobile .rockside-region-switcher-select {
            width: 100%;
            max-width: 220px;
        }
        @media (max-width: 767px) {
            .rockside-region-switcher-label {
                display: none;
            }
            .rockside-region-switcher-select {
                min-width: 140px;
                width: 100%;
            }
        }';

        wp_register_style('rockside-region-switcher-inline', false);
        wp_enqueue_style('rockside-region-switcher-inline');
        wp_add_inline_style('rockside-region-switcher-inline', $css);
    }

    private function get_enabled_regions_for_switcher() {
        $all_regions = $this->context->get_regions();
        $enabled = get_option('rockside_enabled_switcher_regions', array());

        if (!is_array($enabled) || empty($enabled)) {
            return array(
                'kharkiv' => $all_regions['kharkiv'],
            );
        }

        $result = array();

        foreach ($enabled as $region_key) {
            $region_key = sanitize_key($region_key);

            if (isset($all_regions[$region_key])) {
                $result[$region_key] = $all_regions[$region_key];
            }
        }

        if (empty($result)) {
            $result['kharkiv'] = $all_regions['kharkiv'];
        }

        return $result;
    }

    public function render_switcher_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'mobile' => '0',
        ), $atts, 'rockside_region_switcher');

        $regions = $this->get_enabled_regions_for_switcher();
        $current_region = $this->context->get_current_region();

        // Якщо поточний регіон не входить у дозволені — у select не повинно бути "порожнього" стану
        if (!isset($regions[$current_region])) {
            $current_region = array_key_first($regions);
            if (!$current_region) {
                $current_region = 'kharkiv';
            }
        }

        $wrapper_classes = array('rockside-region-switcher');
        if (!empty($atts['mobile']) && $atts['mobile'] !== '0') {
            $wrapper_classes[] = 'rockside-region-switcher--mobile';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-current-region="<?php echo esc_attr($current_region); ?>">
            <?php if (empty($atts['mobile']) || $atts['mobile'] === '0') : ?>
                <label class="rockside-region-switcher-label" for="rockside-region-switcher-select">Ваш регіон</label>
            <?php endif; ?>

            <select id="rockside-region-switcher-select" class="rockside-region-switcher-select" aria-label="Вибір регіону">
                <?php foreach ($regions as $region_key => $region_data) : ?>
                    <option
                        value="<?php echo esc_attr($region_key); ?>"
                        data-url="<?php echo esc_url($this->get_switch_url_for_region($region_key)); ?>"
                        <?php selected($current_region, $region_key); ?>
                    >
                        <?php echo esc_html($region_data['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    public function print_switcher_script() {
        ?>
        <script>
        (function() {
            function bindRocksideRegionSwitcher(root) {
                var selects = (root || document).querySelectorAll('.rockside-region-switcher-select');

                selects.forEach(function(select) {
                    if (select.dataset.rocksideBound === '1') {
                        return;
                    }

                    select.dataset.rocksideBound = '1';

                    select.addEventListener('change', function() {
                        var option = select.options[select.selectedIndex];
                        if (!option) {
                            return;
                        }

                        var targetUrl = option.getAttribute('data-url');
                        if (!targetUrl) {
                            return;
                        }

                        window.location.href = targetUrl;
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    bindRocksideRegionSwitcher(document);
                });
            } else {
                bindRocksideRegionSwitcher(document);
            }
        })();
        </script>
        <?php
    }

    private function get_switch_url_for_region($target_region) {
        if (!$this->context->is_valid_region($target_region)) {
            $target_region = 'kharkiv';
        }

        if (is_front_page() || is_home()) {
            return $this->get_home_url_for_region($target_region);
        }

        if (is_product()) {
            return $this->get_product_url_for_region($target_region);
        }

        if (is_tax('product_cat')) {
            return $this->get_product_category_url_for_region($target_region);
        }

        if (is_post_type_archive('portfolio')) {
            return $this->get_portfolio_archive_url_for_region($target_region);
        }

        if (is_singular('portfolio')) {
            return $this->get_portfolio_single_url_for_region($target_region);
        }

        if (is_tax('project-cat')) {
            return $this->get_project_cat_url_for_region($target_region);
        }

        if (is_page() || is_single()) {
            return $this->get_linked_object_url_for_region($target_region);
        }

        $current_url = $this->get_current_request_url();
        return $this->context->build_regional_url($current_url, $target_region);
    }

    private function get_home_url_for_region($target_region) {
        if ($target_region === 'kharkiv') {
            return trailingslashit(home_url('/'));
        }

        return trailingslashit(home_url('/' . $this->context->get_region_slug($target_region)));
    }

    private function get_product_url_for_region($target_region) {
        $product_id = get_queried_object_id();
        if (!$product_id) {
            return $this->get_home_url_for_region($target_region);
        }

        $url = get_permalink($product_id);
        if (!$url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($url, $target_region);
    }

    private function get_product_category_url_for_region($target_region) {
        $term = get_queried_object();
        if (!$term || empty($term->term_id)) {
            return $this->get_home_url_for_region($target_region);
        }

        $url = get_term_link((int) $term->term_id, 'product_cat');
        if (is_wp_error($url) || !$url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($url, $target_region);
    }

    private function get_portfolio_archive_url_for_region($target_region) {
        $url = get_post_type_archive_link('portfolio');
        if (!$url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($url, $target_region);
    }

    private function get_portfolio_single_url_for_region($target_region) {
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return $this->get_home_url_for_region($target_region);
        }

        $url = get_permalink($post_id);
        if (!$url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($url, $target_region);
    }

    private function get_project_cat_url_for_region($target_region) {
        $term = get_queried_object();
        if (!$term || empty($term->term_id)) {
            return $this->get_home_url_for_region($target_region);
        }

        $url = get_term_link((int) $term->term_id, 'project-cat');
        if (is_wp_error($url) || !$url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($url, $target_region);
    }

    private function get_linked_object_url_for_region($target_region) {
        $object_id = get_queried_object_id();
        if (!$object_id) {
            return $this->get_home_url_for_region($target_region);
        }

        $post = get_post($object_id);
        if (!$post) {
            return $this->get_home_url_for_region($target_region);
        }

        if ($target_region === 'kharkiv') {
            $base_id = $this->find_base_object_id_for_current_object($object_id, $post->post_type);
            $base_url = get_permalink($base_id ?: $object_id);

            if ($base_url) {
                return $this->context->build_regional_url($base_url, 'kharkiv');
            }

            return $this->get_home_url_for_region('kharkiv');
        }

        $meta_key = '';
        if ($target_region === 'kyiv') {
            $meta_key = '_rockside_kyiv_page_id';
        } elseif ($target_region === 'lviv') {
            $meta_key = '_rockside_lviv_page_id';
        } elseif ($target_region === 'odesa') {
            $meta_key = '_rockside_odesa_page_id';
        }

        $base_id = $this->find_base_object_id_for_current_object($object_id, $post->post_type);
        $source_id = $base_id ?: $object_id;

        $linked_id = 0;
        if ($meta_key) {
            $linked_id = (int) get_post_meta($source_id, $meta_key, true);
        }

        $target_id = $linked_id ?: $source_id;
        $target_url = get_permalink($target_id);

        if (!$target_url) {
            return $this->get_home_url_for_region($target_region);
        }

        return $this->context->build_regional_url($target_url, $target_region);
    }

    private function find_base_object_id_for_current_object($object_id, $post_type = 'page') {
        $object_id = (int) $object_id;
        if ($object_id <= 0) {
            return 0;
        }

        $meta_keys = array(
            '_rockside_kyiv_page_id',
            '_rockside_lviv_page_id',
            '_rockside_odesa_page_id',
        );

        foreach ($meta_keys as $meta_key) {
            $parent_ids = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array(
                        'key'   => $meta_key,
                        'value' => $object_id,
                    ),
                ),
            ));

            if (!empty($parent_ids)) {
                return (int) $parent_ids[0];
            }
        }

        return $object_id;
    }

    private function get_current_request_url() {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '';
        $uri    = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        return $scheme . '://' . $host . $uri;
    }
}