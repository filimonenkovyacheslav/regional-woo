<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Popup {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_footer', array($this, 'render_popup'));
    }

    private function is_popup_enabled() {
        return get_option('rockside_region_popup_enabled', 'yes') === 'yes';
    }

    private function get_enabled_regions_for_popup() {
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

    public function register_assets() {
        if (!$this->is_popup_enabled()) {
            return;
        }

        wp_register_style('rockside-region-popup', false, array(), ROCKSIDE_REGIONAL_VERSION);

        $inline_css = '
.rockside-region-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 99998;
    display: none;
}
.rockside-region-popup {
    position: fixed;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: calc(100% - 32px);
    max-width: 420px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 18px 50px rgba(0,0,0,.22);
    padding: 24px;
    z-index: 99999;
    display: none;
}
.rockside-region-popup.is-visible,
.rockside-region-popup-overlay.is-visible {
    display: block;
}
.rockside-region-popup__title {
    font-size: 24px;
    line-height: 1.2;
    margin: 0 0 10px;
    font-weight: 700;
}
.rockside-region-popup__text {
    margin: 0 0 18px;
    color: #444;
    font-size: 15px;
    line-height: 1.5;
}
.rockside-region-popup__field {
    margin: 0 0 18px;
}
.rockside-region-popup__label {
    display: block;
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 600;
    color: #111;
}
.rockside-region-popup__select {
    width: 100%;
    min-height: 44px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
    padding: 0 12px;
    font-size: 15px;
}
.rockside-region-popup__actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}
.rockside-region-popup__btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 44px;
    border-radius: 8px;
    padding: 0 16px;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid #111;
    background: #111;
    color: #fff;
    cursor: pointer;
}
.rockside-region-popup__btn--secondary {
    background: #fff;
    color: #111;
}
.rockside-region-popup {
    position: fixed !important;
}
.rockside-region-popup__close {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    left: auto !important;
    bottom: auto !important;
    width: 32px !important;
    height: 32px !important;
    min-width: 32px !important;
    min-height: 32px !important;
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border: 0 !important;
    border-radius: 6px !important;
    background: #f3f3f3 !important;
    color: #111 !important;
    text-decoration: none !important;
    box-shadow: none !important;
    transform: none !important;
    float: none !important;
    z-index: 100000 !important;
    cursor: pointer !important;
    overflow: hidden !important;
}
.rockside-region-popup__close:hover {
    background: #e9e9e9 !important;
}
.rockside-region-popup__close-icon {
    display: block !important;
    width: 100% !important;
    text-align: center !important;
    font-size: 22px !important;
    line-height: 32px !important;
    font-weight: 400 !important;
    color: #111 !important;
    pointer-events: none !important;
}
@media (max-width: 767px) {
    .rockside-region-popup {
        padding: 20px;
    }
    .rockside-region-popup__title {
        font-size: 21px;
    }
}';

        wp_add_inline_style('rockside-region-popup', $inline_css);
        wp_enqueue_style('rockside-region-popup');

        wp_register_script('rockside-region-popup', false, array(), ROCKSIDE_REGIONAL_VERSION, true);

        $enabled_regions = $this->get_enabled_regions_for_popup();
        $recommended_region = $this->get_recommended_region();

        if (!isset($enabled_regions[$recommended_region])) {
            $keys = array_keys($enabled_regions);
            $recommended_region = !empty($keys) ? $keys[0] : 'kharkiv';
        }

        $config = array(
            'enabled' => $this->is_popup_enabled(),
            'recommendedRegion' => $recommended_region,
            'storageKey' => 'rockside_region_popup_closed',
            'regionUrls' => $this->get_region_urls(),
        );

        wp_add_inline_script(
            'rockside-region-popup',
            'window.rocksideRegionPopupConfig = ' . wp_json_encode($config) . ';',
            'before'
        );

        $inline_js = "
(function () {
    var config = window.rocksideRegionPopupConfig || {};
    if (!config.enabled) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        try {
            if (sessionStorage.getItem(config.storageKey) === '1') {
                return;
            }
        } catch (err) {}

        var popup = document.querySelector('.rockside-region-popup');
        var overlay = document.querySelector('.rockside-region-popup-overlay');

        if (!popup || !overlay) {
            return;
        }

        var select = popup.querySelector('.rockside-region-popup__select');
        var primaryBtn = popup.querySelector('.rockside-region-popup__btn--primary');

        popup.classList.add('is-visible');
        overlay.classList.add('is-visible');

        function closePopup() {
            popup.classList.remove('is-visible');
            overlay.classList.remove('is-visible');

            try {
                sessionStorage.setItem(config.storageKey, '1');
            } catch (err) {}
        }

        function updatePrimaryLink() {
            if (!select || !primaryBtn) {
                return;
            }

            var selectedRegion = select.value || 'kharkiv';
            var regionUrls = config.regionUrls || {};
            var targetUrl = regionUrls[selectedRegion] || regionUrls.kharkiv || '/';

            primaryBtn.setAttribute('href', targetUrl);
            primaryBtn.setAttribute('data-region', selectedRegion);
        }

        if (select) {
            select.addEventListener('change', updatePrimaryLink);

            if (config.recommendedRegion && select.querySelector('option[value=\"' + config.recommendedRegion + '\"]')) {
                select.value = config.recommendedRegion;
            }

            updatePrimaryLink();
        }

        document.addEventListener('click', function (e) {
            if (
                e.target.matches('.rockside-region-popup__close') ||
                e.target.matches('.rockside-region-popup-overlay') ||
                e.target.matches('.rockside-region-popup__btn--secondary')
            ) {
                closePopup();
            }

            if (e.target.matches('.rockside-region-popup__btn--primary')) {
                var selectedRegion = e.target.getAttribute('data-region') || (select ? select.value : 'kharkiv');

                try {
                    document.cookie = 'rockside_region=' + selectedRegion + '; path=/; max-age=' + (60 * 60 * 24 * 30) + '; SameSite=Lax';
                } catch (err) {}

                try {
                    sessionStorage.setItem(config.storageKey, '1');
                } catch (err) {}
            }
        });
    });
})();";

        wp_add_inline_script('rockside-region-popup', $inline_js);
        wp_enqueue_script('rockside-region-popup');
    }

    public function render_popup() {
        if (!$this->is_popup_enabled()) {
            return;
        }

        $regions = $this->get_enabled_regions_for_popup();
        if (empty($regions)) {
            return;
        }

        $recommended_region = $this->get_recommended_region();
        if (!isset($regions[$recommended_region])) {
            $keys = array_keys($regions);
            $recommended_region = !empty($keys) ? $keys[0] : 'kharkiv';
        }

        $current_region = $this->context->get_current_region();
        if (!isset($regions[$current_region])) {
            $keys = array_keys($regions);
            $current_region = !empty($keys) ? $keys[0] : 'kharkiv';
        }
        ?>
        <div class="rockside-region-popup-overlay"></div>
        <div
            class="rockside-region-popup"
            data-current-region="<?php echo esc_attr($current_region); ?>"
            data-recommended-region="<?php echo esc_attr($recommended_region); ?>"
        >
            <button type="button" class="rockside-region-popup__close" aria-label="Закрити">
                <span class="rockside-region-popup__close-icon" aria-hidden="true">×</span>
            </button>

            <h3 class="rockside-region-popup__title">Оберіть ваш регіон</h3>

            <p class="rockside-region-popup__text">
                Щоб показувати актуальні ціни та наявність, оберіть потрібний регіон.
                Ви завжди зможете змінити його пізніше у шапці сайту.
            </p>

            <div class="rockside-region-popup__field">
                <label class="rockside-region-popup__label" for="rockside-region-popup-select">Регіон</label>
                <select id="rockside-region-popup-select" class="rockside-region-popup__select">
                    <?php foreach ($regions as $region_key => $region_data) : ?>
                        <option
                            value="<?php echo esc_attr($region_key); ?>"
                            <?php selected($region_key, $recommended_region); ?>
                        >
                            <?php echo esc_html($region_data['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rockside-region-popup__actions">
                <a class="rockside-region-popup__btn rockside-region-popup__btn--primary" href="#">
                    Перейти
                </a>
            </div>
        </div>
        <?php
    }

    private function get_recommended_region() {
        if (!function_exists('geoip_detect2_get_info_from_current_ip')) {
            return 'kharkiv';
        }

        try {
            $record = geoip_detect2_get_info_from_current_ip();
            $region_name = '';

            if (!empty($record->mostSpecificSubdivision) && !empty($record->mostSpecificSubdivision->name)) {
                $region_name = mb_strtolower((string) $record->mostSpecificSubdivision->name);
            }

            $south = array(
                'odesa', 'odessa', 'mykolaiv', 'nikolaev', 'kherson'
            );

            $west = array(
                'lviv', 'lvov', 'ivano-frankivsk', 'ternopil', 'volyn',
                'lutsk', 'zakarpattia', 'uzhhorod', 'chernivtsi', 'rivne',
                'khmelnytskyi'
            );

            $center = array(
                'kyiv', 'kiev', 'vinnytsia', 'zhytomyr', 'cherkasy',
                'kirovohrad', 'kropyvnytskyi', 'poltava', 'chernihiv', 'sumy'
            );

            foreach ($south as $needle) {
                if ($region_name !== '' && strpos($region_name, $needle) !== false) {
                    return 'odesa';
                }
            }

            foreach ($west as $needle) {
                if ($region_name !== '' && strpos($region_name, $needle) !== false) {
                    return 'lviv';
                }
            }

            foreach ($center as $needle) {
                if ($region_name !== '' && strpos($region_name, $needle) !== false) {
                    return 'kyiv';
                }
            }
        } catch (Exception $e) {
            return 'kharkiv';
        }

        return 'kharkiv';
    }

    private function get_region_label($region) {
        $regions = $this->context->get_regions();
        return isset($regions[$region]['label']) ? $regions[$region]['label'] : 'Харків';
    }

    private function build_region_home_url($region) {
        return $this->build_target_url_for_region($this->get_current_request_url(), $region);
    }

    private function get_region_urls() {
        $urls = array();

        foreach ($this->get_enabled_regions_for_popup() as $region_key => $region_data) {
            $urls[$region_key] = $this->build_region_home_url($region_key);
        }

        return $urls;
    }

    private function get_current_request_url() {
        $request_uri = '/';

        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = (string) wp_unslash($_SERVER['REQUEST_URI']);
        }

        $parts = wp_parse_url($request_uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';

        $url = home_url($path);

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query_args);

            unset($query_args['region_id']);

            if (!empty($query_args)) {
                $url = add_query_arg($query_args, $url);
            }
        }

        return $url;
    }

    private function build_target_url_for_region($url, $target_region) {
        $parts = wp_parse_url($url);
        if (!$parts) {
            return $url;
        }

        $path = isset($parts['path']) ? trim($parts['path'], '/') : '';
        parse_str(isset($parts['query']) ? $parts['query'] : '', $query_args);

        if (!empty($query_args['s'])) {
            $base = home_url('/');

            if ($target_region === 'kharkiv') {
                unset($query_args['region_id']);
            } else {
                $query_args['region_id'] = $target_region;
            }

            return add_query_arg($query_args, $base);
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));

        $known_regions = array('kyiv', 'lviv', 'odesa');
        $known_langs   = array('ru', 'uk');

        $lang = '';
        $rest_segments = $segments;

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

        if ($lang !== '') {
            $new_segments[] = $lang;
        }

        if ($target_region !== 'kharkiv') {
            $slug = $this->context->get_region_slug($target_region);
            if ($slug) {
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

        unset($query_args['region_id']);

        if (!empty($query_args)) {
            $rebuilt = add_query_arg($query_args, $rebuilt);
        }

        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }
}