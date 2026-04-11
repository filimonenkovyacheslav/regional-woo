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
        add_shortcode('rockside_region_switcher', array($this, 'render_shortcode'));
        add_shortcode('rockside_region_switcher_mobile', array($this, 'render_mobile_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }

    public function register_assets() {
        wp_register_style('rockside-region-switcher', false, array(), ROCKSIDE_REGIONAL_VERSION);

        $inline_css = '
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

        wp_add_inline_style('rockside-region-switcher', $inline_css);
        wp_enqueue_style('rockside-region-switcher');

        wp_register_script('rockside-region-switcher', false, array(), ROCKSIDE_REGIONAL_VERSION, true);

        $inline_js = <<<'JS'
            (function () {
                function parseUrlContext() {
                    var path = window.location.pathname || '/';
                    path = path.replace(/^\/+|\/+$/g, '');

                    var result = {
                        region: 'kharkiv',
                        lang: '',
                        rest: ''
                    };

                    var segments = path ? path.split('/').filter(Boolean) : [];
                    var knownRegions = ['kyiv', 'lviv'];
                    var knownLangs = ['ru', 'uk'];

                    if (segments[0] && knownLangs.indexOf(segments[0].toLowerCase()) !== -1) {
                        result.lang = segments[0].toLowerCase();

                        if (segments[1] && knownRegions.indexOf(segments[1].toLowerCase()) !== -1) {
                            result.region = segments[1].toLowerCase();
                            result.rest = segments.slice(2).join('/');
                        } else {
                            result.rest = segments.slice(1).join('/');
                        }

                        return result;
                    }

                    if (segments[0] && knownRegions.indexOf(segments[0].toLowerCase()) !== -1) {
                        result.region = segments[0].toLowerCase();

                        if (segments[1] && knownLangs.indexOf(segments[1].toLowerCase()) !== -1) {
                            result.lang = segments[1].toLowerCase();
                            result.rest = segments.slice(2).join('/');
                        } else {
                            result.rest = segments.slice(1).join('/');
                        }

                        return result;
                    }

                    var params = new URLSearchParams(window.location.search);
                    var regionId = params.get('region_id');
                    if (regionId === 'kyiv' || regionId === 'lviv' || regionId === 'kharkiv') {
                        result.region = regionId;
                    }

                    result.rest = segments.join('/');
                    return result;
                }

                function buildRegionalUrl(path) {
                    var context = parseUrlContext();
                    var origin = window.location.origin;
                    var cleanPath = (path || '').replace(/^\/+|\/+$/g, '');
                    var segments = [];

                    if (context.lang) {
                        segments.push(context.lang);
                    }

                    if (context.region && context.region !== 'kharkiv') {
                        segments.push(context.region);
                    }

                    if (cleanPath) {
                        segments.push(cleanPath);
                    }

                    return origin + '/' + segments.join('/') + '/';
                }

                function syncRegionSwitcher() {
                    var context = parseUrlContext();
                    var region = context.region;

                    document.querySelectorAll('.rockside-region-switcher').forEach(function (wrapper) {
                        wrapper.setAttribute('data-current-region', region);

                        var select = wrapper.querySelector('.rockside-region-switcher-select');
                        if (!select) {
                            return;
                        }

                        var hasOption = Array.prototype.some.call(select.options, function (option) {
                            return option.value === region;
                        });

                        if (hasOption) {
                            select.value = region;
                            select.setAttribute('data-current-region', region);
                        }
                    });
                }

                function syncHomeLinks() {
                    var regionalHomeUrl = buildRegionalUrl('');

                    var selectors = [
                        '.wd-logo',
                        '.custom-logo-link',
                        '.site-logo a',
                        '.woodmart-logo a',
                        '.woocommerce-breadcrumb a:first-child',
                        '.wd-breadcrumbs a:first-child',
                        '.rank-math-breadcrumb a:first-child',
                        '.yoast-breadcrumb a:first-child',
                        '.breadcrumb a:first-child',
                        '.breadcrumbs a:first-child'
                    ];

                    document.querySelectorAll(selectors.join(',')).forEach(function (link) {
                        if (!link || !link.href) {
                            return;
                        }

                        try {
                            var parsed = new URL(link.href, window.location.origin);
                            var path = parsed.pathname.replace(/\/+$/, '/') || '/';

                            if (parsed.origin === window.location.origin && path === '/') {
                                link.href = regionalHomeUrl;
                            }
                        } catch (e) {}
                    });
                }

                function syncCartCheckoutLinks(root) {
                    var scope = root && root.querySelectorAll ? root : document;
                    var cartUrl = buildRegionalUrl('cart-2');
                    var checkoutUrl = buildRegionalUrl('checkout-2');

                    scope.querySelectorAll('a.elementor-button--view-cart, a[href*="/cart-2/"], a[href*="/cart-2?"]').forEach(function (link) {
                        if (!link || !link.href) {
                            return;
                        }

                        try {
                            var parsed = new URL(link.href, window.location.origin);

                            if (parsed.search && parsed.search.indexOf('remove_item=') !== -1) {
                                link.href = cartUrl.replace(/\/$/, '') + parsed.search;
                            } else {
                                link.href = cartUrl;
                            }
                        } catch (e) {
                            link.href = cartUrl;
                        }
                    });

                    scope.querySelectorAll('a.elementor-button--checkout, a.checkout-button, a[href*="/checkout-2/"], a[href*="/checkout-2?"]').forEach(function (link) {
                        if (!link || !link.href) {
                            return;
                        }

                        link.href = checkoutUrl;
                    });
                }

                function syncAll(root) {
                    syncRegionSwitcher();
                    syncHomeLinks();
                    syncCartCheckoutLinks(root || document);
                }

                function installObservers() {
                    if (!document.body || typeof MutationObserver === 'undefined') {
                        return;
                    }

                    var observer = new MutationObserver(function (mutations) {
                        mutations.forEach(function (mutation) {
                            mutation.addedNodes.forEach(function (node) {
                                if (!node || node.nodeType !== 1) {
                                    return;
                                }

                                if (
                                    node.matches &&
                                    (
                                        node.matches('.widget_shopping_cart_content') ||
                                        node.matches('.cart-widget-side') ||
                                        node.matches('.wd-shopping-cart') ||
                                        node.matches('.woocommerce-mini-cart') ||
                                        node.matches('.wc-proceed-to-checkout') ||
                                        node.querySelector('.widget_shopping_cart_content, .cart-widget-side, .woocommerce-mini-cart, .wc-proceed-to-checkout')
                                    )
                                ) {
                                    syncAll(node);
                                }
                            });
                        });
                    });

                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }

                document.addEventListener('change', function (e) {
                    if (!e.target.matches('.rockside-region-switcher-select')) {
                        return;
                    }

                    var selected = e.target.value;
                    if (!selected) {
                        return;
                    }

                    var option = e.target.options[e.target.selectedIndex];
                    var targetUrl = option.getAttribute('data-url');
                    if (!targetUrl) {
                        return;
                    }

                    try {
                        document.cookie = 'rockside_region=' + selected + '; path=/; max-age=' + (60 * 60 * 24 * 30) + '; SameSite=Lax';
                    } catch (err) {}

                    window.location.href = targetUrl;
                });

                document.addEventListener('DOMContentLoaded', function () {
                    syncAll(document);
                    installObservers();
                });

                window.addEventListener('pageshow', function () {
                    syncAll(document);
                });

                window.addEventListener('popstate', function () {
                    syncAll(document);
                });

                if (window.jQuery) {
                    jQuery(document.body).on(
                        'wc_fragments_loaded wc_fragments_refreshed added_to_cart removed_from_cart updated_wc_div updated_cart_totals updated_checkout',
                        function () {
                            syncAll(document);
                        }
                    );
                }

                setTimeout(function () { syncAll(document); }, 100);
                setTimeout(function () { syncAll(document); }, 500);
                setTimeout(function () { syncAll(document); }, 1200);
            })();
            JS;

        wp_add_inline_script('rockside-region-switcher', $inline_js);
        wp_enqueue_script('rockside-region-switcher');
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'label' => 'Ваш регіон',
            'show_label' => 'yes',
            'class' => '',
        ), $atts, 'rockside_region_switcher');

        return $this->render_switcher_markup($atts['label'], $atts['show_label'] === 'yes', $atts['class']);
    }

    public function render_mobile_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'label' => 'Регіон',
            'show_label' => 'no',
            'class' => 'rockside-region-switcher--mobile',
        ), $atts, 'rockside_region_switcher_mobile');

        return $this->render_switcher_markup($atts['label'], $atts['show_label'] === 'yes', $atts['class']);
    }

    private function render_switcher_markup($label, $show_label, $extra_class = '') {
        $current_region = $this->context->get_current_region();
        $options = $this->get_switcher_options();
        $classes = trim('rockside-region-switcher ' . $extra_class);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-current-region="<?php echo esc_attr($current_region); ?>">
            <?php if ($show_label) : ?>
                <label class="rockside-region-switcher-label" for="rockside-region-switcher-select"><?php echo esc_html($label); ?></label>
            <?php endif; ?>

            <select id="rockside-region-switcher-select" class="rockside-region-switcher-select" aria-label="Вибір регіону">
                <?php foreach ($options as $region_key => $option) : ?>
                    <option
                        value="<?php echo esc_attr($region_key); ?>"
                        data-url="<?php echo esc_url($option['url']); ?>"
                        <?php selected($region_key, $current_region); ?>
                    >
                        <?php echo esc_html($option['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_switcher_options() {
        $regions = $this->context->get_regions();
        $options = array();
        $request_url = $this->get_current_request_url();

        foreach ($regions as $region_key => $region) {
            $options[$region_key] = array(
                'label' => $region['label'],
                'url'   => $this->build_target_url_for_region($request_url, $region_key),
            );
        }

        return $options;
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

        // Поиск оставляем через query string
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

        // На сайте язык должен идти первым
        if ($lang !== '') {
            $new_segments[] = $lang;
        }

        // Для неосновного региона добавляем региональный префикс
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