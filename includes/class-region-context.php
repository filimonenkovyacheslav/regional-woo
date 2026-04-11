<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Region_Context {
    const COOKIE_NAME = 'rockside_region';
    const QUERY_VAR   = 'rockside_region';

    private $regions = array(
        'kharkiv' => array(
            'label'      => 'Харків',
            'slug'       => '',
            'region_id'  => 'kharkiv',
            'is_default' => true,
        ),
        'kyiv' => array(
            'label'      => 'Київ',
            'slug'       => 'kyiv',
            'region_id'  => 'kyiv',
            'is_default' => false,
        ),
        'lviv' => array(
            'label'      => 'Львів',
            'slug'       => 'lviv',
            'region_id'  => 'lviv',
            'is_default' => false,
        ),
    );

    public function boot() {
        add_action('init', array($this, 'register_rewrite'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('parse_request', array($this, 'capture_region_from_request'));
        add_filter('request', array($this, 'rockside_map_home_request'));
        add_filter('request', array($this, 'rockside_map_regional_singular_request'), 11);
        add_action('parse_query', array($this, 'normalize_regional_main_query'), 1);
        add_filter('redirect_canonical', array($this, 'disable_regional_home_canonical'), 10, 2);
    }

    public function disable_regional_home_canonical($redirect_url, $requested_url) {
        if (is_admin()) {
            return $redirect_url;
        }

        $request_uri = '/';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
        }

        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $path = trim($path, '/');

        // Не даємо WordPress канонікалізувати регіональну головну
        if ($path === 'kyiv' || $path === 'lviv') {
            return false;
        }

        return $redirect_url;
    }

    private function parse_regional_path($path) {
        $path = trim((string) $path, '/');

        $result = array(
            'region' => 'kharkiv',
            'lang'   => '',
            'rest'   => '',
        );

        if ($path === '') {
            return $result;
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

        if (empty($segments)) {
            return $result;
        }

        $known_regions = array('kyiv', 'lviv');
        $known_langs   = array('ru', 'uk');

        // Вариант 1: /kyiv/ru/...
        if (!empty($segments[0]) && in_array(strtolower($segments[0]), $known_regions, true)) {
            $result['region'] = strtolower($segments[0]);

            if (!empty($segments[1]) && in_array(strtolower($segments[1]), $known_langs, true)) {
                $result['lang'] = strtolower($segments[1]);
                $result['rest'] = implode('/', array_slice($segments, 2));
            } else {
                $result['rest'] = implode('/', array_slice($segments, 1));
            }

            return $result;
        }

        // Вариант 2: /ru/kyiv/...
        if (!empty($segments[0]) && in_array(strtolower($segments[0]), $known_langs, true)) {
            $result['lang'] = strtolower($segments[0]);

            if (!empty($segments[1]) && in_array(strtolower($segments[1]), $known_regions, true)) {
                $result['region'] = strtolower($segments[1]);
                $result['rest'] = implode('/', array_slice($segments, 2));
            } else {
                $result['rest'] = implode('/', array_slice($segments, 1));
            }

            return $result;
        }

        // Без явного языка и без региона
        $result['rest'] = implode('/', $segments);

        return $result;
    }

    public function parse_switcher_path_context($path) {
        return $this->parse_regional_path($path);
    }

    public function normalize_regional_main_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $base_page_id = (int) $query->get('rockside_base_page_id');
        if ($base_page_id <= 0) {
            return;
        }

        // Если грузим linked page — убираем конфликтующие vars
        if ($query->get('page_id')) {
            $query->set('pagename', '');
            $query->set('name', '');
            $query->set('attachment', '');
            $query->set('attachment_id', '');
            $query->set('error', '');
        }

        // Если грузим linked post — тоже чистим конфликтующие vars
        if ($query->get('p')) {
            $query->set('name', '');
            $query->set('pagename', '');
            $query->set('attachment', '');
            $query->set('attachment_id', '');
            $query->set('error', '');
        }
    }

    public function register_rewrite() {
        add_rewrite_tag('%' . self::QUERY_VAR . '%', '([^&]+)');

        /**
         * WOODMART PORTFOLIO / PROJECT CAT
         * Не делаем региональные копии, а просто разрешаем открывать
         * одинаковый контент через /kyiv/... и /lviv/...
         */

        // Archive: /kyiv/portfolio/ , /lviv/portfolio/
        add_rewrite_rule(
            '^(kyiv|lviv)/portfolio/?$',
            'index.php?post_type=portfolio&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        // Single: /kyiv/portfolio/some-project/ , /lviv/portfolio/some-project/
        add_rewrite_rule(
            '^(kyiv|lviv)/portfolio/([^/]+)/?$',
            'index.php?post_type=portfolio&name=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        // Taxonomy: /kyiv/project-cat/fontany/ , /lviv/project-cat/fontany/
        add_rewrite_rule(
            '^(kyiv|lviv)/project-cat/([^/]+)/?$',
            'index.php?taxonomy=project-cat&term=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(kyiv|lviv)/product/(.+)/?$',
            'index.php?post_type=product&name=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(kyiv|lviv)/product-category/(.+)/?$',
            'index.php?product_cat=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(kyiv|lviv)/(.+?)/?$',
            'index.php?pagename=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(kyiv|lviv)/([^/]+)/?$',
            'index.php?name=$matches[2]&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public function register_query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'rockside_base_page_id';
        return $vars;
    }

    public function rockside_map_home_request($query_vars) {
        if (is_admin()) {
            return $query_vars;
        }

        // Поиск не трогаем
        if (!empty($_GET['s'])) {
            return $query_vars;
        }

        $request_uri = '/';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
        }

        $parts = wp_parse_url($request_uri);
        $path  = isset($parts['path']) ? trim($parts['path'], '/') : '';

        // Определяем регион сначала из path, потом из region_id
        $region = 'kharkiv';

        if ($path === 'kyiv' || strpos($path, 'kyiv/') === 0) {
            $region = 'kyiv';
        } elseif ($path === 'lviv' || strpos($path, 'lviv/') === 0) {
            $region = 'lviv';
        } elseif (!empty($_GET['region_id'])) {
            $candidate = sanitize_key(wp_unslash($_GET['region_id']));
            if ($this->is_valid_region($candidate)) {
                $region = $candidate;
            }
        }

        if ($region === 'kharkiv') {
            return $query_vars;
        }

        // Обрабатываем только главную:
        // /
        // /?region_id=kyiv
        // /?region_id=lviv
        // /kyiv/
        // /lviv/
        $is_home_root = ($path === '');
        $is_home_regional_path = ($path === 'kyiv' || $path === 'lviv');

        if (!$is_home_root && !$is_home_regional_path) {
            return $query_vars;
        }

        $base_page_id = (int) get_option('page_on_front');
        if (!$base_page_id) {
            return $query_vars;
        }

        $linked_page_id = 0;

        if ($region === 'kyiv') {
            $linked_page_id = (int) get_post_meta($base_page_id, '_rockside_kyiv_page_id', true);
        } elseif ($region === 'lviv') {
            $linked_page_id = (int) get_post_meta($base_page_id, '_rockside_lviv_page_id', true);
        }

        $target_page_id = $linked_page_id ?: $base_page_id;

        return array(
            'page_id' => $target_page_id,
            'rockside_base_page_id' => $base_page_id,
            self::QUERY_VAR => $region,
        );
    }

    public function capture_region_from_request($wp) {
        $path = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        }

        $parsed = $this->parse_regional_path($path);
        $region = $parsed['region'];

        // region_id имеет приоритет только если в path нет явного региона
        if (
            $region === 'kharkiv' &&
            !empty($_GET['region_id'])
        ) {
            $candidate = sanitize_key(wp_unslash($_GET['region_id']));
            if ($this->is_valid_region($candidate)) {
                $region = $candidate;
            }
        }

        $this->set_current_region($region);
    }

    public function set_current_region($region) {
        if (!$this->is_valid_region($region)) {
            $region = 'kharkiv';
        }

        $GLOBALS['rockside_current_region'] = $region;
    }

    public function get_current_region() {
        return isset($GLOBALS['rockside_current_region']) ? $GLOBALS['rockside_current_region'] : 'kharkiv';
    }

    public function get_regions() {
        return $this->regions;
    }

    public function is_valid_region($region) {
        return isset($this->regions[$region]);
    }

    public function get_region_slug($region = null) {
        $region = $region ?: $this->get_current_region();
        return isset($this->regions[$region]['slug']) ? $this->regions[$region]['slug'] : '';
    }

    public function build_regional_url($url, $region = null) {
        $region = $region ?: $this->get_current_region();

        $parts = wp_parse_url($url);
        if (!$parts) {
            return $url;
        }

        $path = isset($parts['path']) ? trim($parts['path'], '/') : '';
        $query = isset($parts['query']) ? $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? $parts['fragment'] : '';

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

        $known_regions = array('kyiv', 'lviv');
        $known_langs   = array('ru', 'uk');

        $lang = '';
        $rest_segments = $segments;

        // Полностью очищаем начало URL от повторяющихся lang/region префиксов.
        while (!empty($rest_segments)) {
            $first = strtolower($rest_segments[0]);

            if (in_array($first, $known_langs, true)) {
                if ($lang === '') {
                    $lang = $first;
                }
                array_shift($rest_segments);
                continue;
            }

            if (in_array($first, $known_regions, true)) {
                array_shift($rest_segments);
                continue;
            }

            break;
        }

        $new_segments = array();

        // На сайте язык идет первым.
        if ($lang !== '') {
            $new_segments[] = $lang;
        }

        // Для неосновного региона добавляем региональный префикс.
        if ($region !== 'kharkiv') {
            $slug = $this->get_region_slug($region);
            if ($slug !== '') {
                $new_segments[] = $slug;
            }
        }

        if (!empty($rest_segments)) {
            $new_segments = array_merge($new_segments, $rest_segments);
        }

        $new_path = '/' . implode('/', $new_segments);
        $new_path = preg_replace('#//+#', '/', $new_path);

        $rebuilt = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $new_path;

        if ($query !== '') {
            parse_str($query, $query_args);
            unset($query_args['region_id']);

            if (!empty($query_args)) {
                $rebuilt = add_query_arg($query_args, $rebuilt);
            }
        }

        if ($fragment !== '') {
            $rebuilt .= '#' . $fragment;
        }

        return $rebuilt;
    }

    public function get_region_price_meta_key($region) {
        switch ($region) {
            case 'kyiv':
                return '_price_kyiv';
            case 'lviv':
                return '_price_lviv';
            default:
                return '';
        }
    }

    public function get_region_hide_meta_key($region) {
        switch ($region) {
            case 'kyiv':
                return '_hide_in_kyiv';
            case 'lviv':
                return '_hide_in_lviv';
            default:
                return '';
        }
    }

    public function rockside_map_regional_singular_request($query_vars) {
        if (is_admin()) {
            return $query_vars;
        }

        if (!empty($_GET['s'])) {
            return $query_vars;
        }

        $request_uri = '/';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
        }

        $parts = wp_parse_url($request_uri);
        $path  = isset($parts['path']) ? trim($parts['path'], '/') : '';

        $region = 'kharkiv';
        if ($path === 'kyiv' || strpos($path, 'kyiv/') === 0) {
            $region = 'kyiv';
        } elseif ($path === 'lviv' || strpos($path, 'lviv/') === 0) {
            $region = 'lviv';
        }

        if ($region === 'kharkiv') {
            return $query_vars;
        }

        if ($path === '' || $path === 'kyiv' || $path === 'lviv') {
            return $query_vars;
        }

        $clean_path = preg_replace('#^(kyiv|lviv)(/|$)#', '', $path);
        $clean_path = trim((string) $clean_path, '/');

        if ($clean_path === '') {
            return $query_vars;
        }

        // ---------- WOODMART PORTFOLIO / PROJECT CAT ----------
        // Не делаем региональную подмену page/post для портфолио и его категорий.
        if (
            $clean_path === 'portfolio' ||
            strpos($clean_path, 'portfolio/') === 0 ||
            $clean_path === 'project-cat' ||
            strpos($clean_path, 'project-cat/') === 0
        ) {
            return $query_vars;
        }

        // ---------- PAGE ----------
        $base_page = get_page_by_path($clean_path, OBJECT, 'page');

        if ($base_page && !empty($base_page->ID)) {
            $base_page_id = (int) $base_page->ID;
            $linked_page_id = 0;

            if ($region === 'kyiv') {
                $linked_page_id = (int) get_post_meta($base_page_id, '_rockside_kyiv_page_id', true);
            } elseif ($region === 'lviv') {
                $linked_page_id = (int) get_post_meta($base_page_id, '_rockside_lviv_page_id', true);
            }

            $target_page_id = $linked_page_id ?: $base_page_id;

            return array(
                'page_id' => $target_page_id,
                'rockside_base_page_id' => $base_page_id,
                self::QUERY_VAR => $region,
            );
        }

        // ---------- POST ----------
        $base_post = get_page_by_path($clean_path, OBJECT, 'post');

        if ($base_post && !empty($base_post->ID)) {
            $base_post_id = (int) $base_post->ID;
            $linked_post_id = 0;

            if ($region === 'kyiv') {
                $linked_post_id = (int) get_post_meta($base_post_id, '_rockside_kyiv_page_id', true);
            } elseif ($region === 'lviv') {
                $linked_post_id = (int) get_post_meta($base_post_id, '_rockside_lviv_page_id', true);
            }

            $target_post_id = $linked_post_id ?: $base_post_id;

            return array(
                'p' => $target_post_id,
                'post_type' => 'post',
                'rockside_base_page_id' => $base_post_id,
                self::QUERY_VAR => $region,
            );
        }

        return $query_vars;
    }
}