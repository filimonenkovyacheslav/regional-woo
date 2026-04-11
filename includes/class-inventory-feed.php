<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Inventory_Feed {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_action('init', array($this, 'register_feed_endpoint'));
    }

    public function register_feed_endpoint() {
        add_rewrite_rule('^regional-feed/(kyiv|lviv)/?$', 'index.php?rockside_regional_feed=$matches[1]', 'top');
        add_rewrite_tag('%rockside_regional_feed%', '([^&]+)');

        add_action('template_redirect', array($this, 'render_feed_if_requested'));
    }

    public function render_feed_if_requested() {
        $region = get_query_var('rockside_regional_feed');

        if (!$region || !in_array($region, array('kyiv', 'lviv'), true)) {
            return;
        }

        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            $this->render_debug_output($region);
            exit;
        }

        $xml = $this->generate_feed_xml($region);

        nocache_headers();
        header('Content-Type: application/xml; charset=' . get_bloginfo('charset'));

        echo $xml;
        exit;
    }

    private function render_debug_output($region) {
        nocache_headers();
        header('Content-Type: text/plain; charset=' . get_bloginfo('charset'));

        $path = $this->resolve_primary_feed_path();
        $raw_ids = $this->get_primary_feed_ids(false);
        $unique_ids = $this->get_primary_feed_ids(true);
        $items = $this->get_items_for_feed($region);

        echo "ROCKSIDE REGIONAL FEED DEBUG\n";
        echo "============================\n";
        echo "Region: " . $region . "\n";
        echo "Saved primary URL option: " . get_option('rockside_primary_feed_url', '') . "\n";
        echo "Resolved primary path: " . ($path ?: '[EMPTY]') . "\n";
        echo "Primary file exists: " . ($path && file_exists($path) ? 'YES' : 'NO') . "\n";
        echo "Primary file readable: " . ($path && is_readable($path) ? 'YES' : 'NO') . "\n";
        echo "Primary IDs total: " . count($raw_ids) . "\n";
        echo "Primary IDs unique: " . count($unique_ids) . "\n";
        echo "Regional items generated: " . count($items) . "\n";
        echo "\n";

        echo "First 20 primary IDs:\n";
        echo implode(', ', array_slice($unique_ids, 0, 20)) . "\n\n";

        echo "First 20 regional item IDs:\n";
        echo implode(', ', array_map(function ($row) {
            return $row['id'];
        }, array_slice($items, 0, 20))) . "\n\n";

        $regional_ids = array_map(function ($row) {
            return (int) $row['id'];
        }, $items);

        $missing_from_regional = array_values(array_diff($unique_ids, $regional_ids));
        $extra_in_regional = array_values(array_diff($regional_ids, $unique_ids));

        echo "Missing from regional (first 20):\n";
        echo implode(', ', array_slice($missing_from_regional, 0, 20)) . "\n\n";

        echo "Extra in regional (first 20):\n";
        echo implode(', ', array_slice($extra_in_regional, 0, 20)) . "\n\n";

        echo "Done.\n";
    }

    private function get_google_region_id($region) {
        $map = array(
            'kyiv' => 'kyiv',
            'lviv' => 'lviv',
        );

        return isset($map[$region]) ? $map[$region] : '';
    }

    private function get_region_price_meta_key($region) {
        switch ($region) {
            case 'kyiv':
                return '_price_kyiv';
            case 'lviv':
                return '_price_lviv';
            default:
                return '';
        }
    }

    private function get_region_hide_meta_key($region) {
        switch ($region) {
            case 'kyiv':
                return '_hide_in_kyiv';
            case 'lviv':
                return '_hide_in_lviv';
            default:
                return '';
        }
    }

    private function get_term_hide_meta_key_for_region($region) {
        if ($region === 'kyiv') {
            return '_rockside_hide_in_kyiv';
        }

        if ($region === 'lviv') {
            return '_rockside_hide_in_lviv';
        }

        return '';
    }

    private function is_enabled_hide_flag($value) {
        return in_array($value, array('yes', '1', 1, true, 'true', 'on'), true);
    }

    private function get_product_category_ids_with_ancestors($product_id) {
        $term_ids = wp_get_post_terms($product_id, 'product_cat', array(
            'fields' => 'ids',
        ));

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

    private function is_product_hidden_for_region($product_id, $region) {
        if ($region === 'kharkiv') {
            return false;
        }

        $meta_key = $this->get_region_hide_meta_key($region);
        if (!$meta_key) {
            return false;
        }

        $parent_id = wp_get_post_parent_id($product_id);
        $check_id = $parent_id ?: $product_id;

        if ($this->is_enabled_hide_flag(get_post_meta($check_id, $meta_key, true))) {
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

    private function get_regional_price_for_product($product, $region) {
        if (!$product instanceof WC_Product) {
            return null;
        }

        if ($region === 'kharkiv') {
            return null;
        }

        $meta_key = $this->get_region_price_meta_key($region);
        if (!$meta_key) {
            return null;
        }

        $product_id = $product->get_id();
        $value = get_post_meta($product_id, $meta_key, true);

        if (is_array($value)) {
            if (isset($value[$product_id]) && $value[$product_id] !== '') {
                $candidate = wc_format_decimal($value[$product_id]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            $value = '';
        }

        if ($value !== '' && $value !== null) {
            $value = wc_format_decimal($value);
            if ($value !== '') {
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
                        if ($candidate !== '') {
                            return $candidate;
                        }
                    }

                    return null;
                }

                if ($parent_value !== '' && $parent_value !== null) {
                    $parent_value = wc_format_decimal($parent_value);
                    if ($parent_value !== '') {
                        return $parent_value;
                    }
                }
            }
        }

        return null;
    }

    private function format_google_price($price) {
        $price = wc_format_decimal($price, 2);

        if ($price === '' || $price === null) {
            $price = '0.00';
        }

        return $price . ' ' . get_woocommerce_currency();
    }

    private function get_google_availability($product) {
        if (!$product instanceof WC_Product) {
            return 'out_of_stock';
        }

        if ($product->is_in_stock()) {
            return 'in_stock';
        }

        return 'out_of_stock';
    }

    private function get_primary_feed_directory() {
        return WP_CONTENT_DIR . '/uploads/woo-product-feed-pro/xml/';
    }

    private function is_candidate_primary_google_feed($file_path) {
        if (!is_string($file_path) || $file_path === '' || !file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        if (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'xml') {
            return false;
        }

        $basename = strtolower(basename($file_path));

        if (
            strpos($basename, 'regional') !== false ||
            strpos($basename, 'kyiv') !== false ||
            strpos($basename, 'lviv') !== false
        ) {
            return false;
        }

        $head = file_get_contents($file_path, false, null, 0, 120000);
        if (!is_string($head) || $head === '') {
            return false;
        }

        if (strpos($head, 'http://base.google.com/ns/1.0') === false) {
            return false;
        }

        if (strpos($head, '<g:region_id>') !== false) {
            return false;
        }

        if (strpos($head, '<g:id>') === false) {
            return false;
        }

        return true;
    }

    private function score_primary_feed_candidate($file_path) {
        $score = 0;

        $basename = strtolower(basename($file_path));
        $head = file_get_contents($file_path, false, null, 0, 120000);

        if (strpos($basename, 'rock-google-feed') !== false) {
            $score += 100;
        }

        if (is_string($head) && $head !== '') {
            if (strpos($head, '<title>rock-google-feed.xml.') !== false || strpos($head, '<title>rock-google-feed.xml</title>') !== false) {
                $score += 100;
            }

            if (strpos($head, 'utm_campaign=rock-google-feed.xml') !== false) {
                $score += 50;
            }

            if (strpos($head, '<g:region_id>') === false) {
                $score += 20;
            }

            $score += min(substr_count($head, '<g:id>'), 100);
        }

        $mtime = @filemtime($file_path);
        if ($mtime) {
            $score += (int) ($mtime / 1000000);
        }

        return $score;
    }

    public function compare_primary_feed_candidates($a, $b) {
        $scoreA = $this->score_primary_feed_candidate($a);
        $scoreB = $this->score_primary_feed_candidate($b);

        if ($scoreA === $scoreB) {
            return 0;
        }

        return ($scoreA > $scoreB) ? -1 : 1;
    }

    private function resolve_primary_feed_path() {
        $saved_url = trim((string) get_option('rockside_primary_feed_url', ''));

        if ($saved_url !== '') {
            $uploads = wp_get_upload_dir();

            if (!empty($uploads['baseurl']) && !empty($uploads['basedir'])) {
                $baseurl = rtrim((string) $uploads['baseurl'], '/');
                $basedir = rtrim((string) $uploads['basedir'], '/');

                if (strpos($saved_url, $baseurl) === 0) {
                    $relative = ltrim(substr($saved_url, strlen($baseurl)), '/');
                    $local_path = $basedir . '/' . $relative;

                    if (file_exists($local_path) && is_readable($local_path)) {
                        return $local_path;
                    }
                }
            }
        }

        $custom = apply_filters('rockside_primary_google_feed_path', '');
        if (is_string($custom) && $custom !== '' && file_exists($custom) && is_readable($custom)) {
            return $custom;
        }

        $dir = $this->get_primary_feed_directory();
        if (!is_dir($dir) || !is_readable($dir)) {
            return '';
        }

        $files = glob(trailingslashit($dir) . '*.xml');
        if (empty($files)) {
            return '';
        }

        $candidates = array();

        foreach ($files as $file) {
            if ($this->is_candidate_primary_google_feed($file)) {
                $candidates[] = $file;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        usort($candidates, array($this, 'compare_primary_feed_candidates'));

        return $candidates[0];
    }

    private function get_primary_feed_ids($unique_only = true) {
        $file_path = $this->resolve_primary_feed_path();

        if ($file_path === '') {
            return array();
        }

        $xml = @simplexml_load_file($file_path);
        if (!$xml) {
            return array();
        }

        $xml->registerXPathNamespace('g', 'http://base.google.com/ns/1.0');

        $ids = array();

        if (!empty($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $g_id_nodes = $item->xpath('./g:id');

                if (!empty($g_id_nodes) && isset($g_id_nodes[0])) {
                    $id = trim((string) $g_id_nodes[0]);

                    if ($id !== '' && ctype_digit($id)) {
                        $ids[] = (int) $id;
                    }
                }
            }
        }

        if ($unique_only) {
            return array_values(array_unique($ids));
        }

        return $ids;
    }

    private function get_items_for_feed($region) {
        $primary_ids = $this->get_primary_feed_ids(true);

        if (empty($primary_ids)) {
            return array();
        }

        $items = array();

        foreach ($primary_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            if ($this->is_product_hidden_for_region($product_id, $region)) {
                continue;
            }

            $price = $this->get_regional_price_for_product($product, $region);

            if ($price === null || $price === '') {
                $price = wc_format_decimal($product->get_price(), 2);
            }

            if ($price === '' || $price === null) {
                continue;
            }

            $items[] = array(
                'id'           => $product_id,
                'region_id'    => $this->get_google_region_id($region),
                'price'        => $this->format_google_price($price),
                'availability' => $this->get_google_availability($product),
            );
        }

        return $items;
    }

    public function generate_feed_xml($region) {
        $items = $this->get_items_for_feed($region);

        $xml  = '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . '"?>' . "\n";
        $xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
        $xml .= "<channel>\n";
        $xml .= '  <title>' . esc_html(strtoupper($region) . ' Regional Inventory Feed') . "</title>\n";
        $xml .= '  <link>' . esc_url(home_url('/')) . "</link>\n";
        $xml .= '  <description>' . esc_html('Regional inventory feed for ' . $region) . "</description>\n";

        foreach ($items as $item) {
            $xml .= "  <item>\n";
            $xml .= '    <g:id>' . esc_html($item['id']) . "</g:id>\n";
            $xml .= '    <g:region_id>' . esc_html($item['region_id']) . "</g:region_id>\n";
            $xml .= '    <g:price>' . esc_html($item['price']) . "</g:price>\n";
            $xml .= '    <g:availability>' . esc_html($item['availability']) . "</g:availability>\n";
            $xml .= "  </item>\n";
        }

        $xml .= "</channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }
}