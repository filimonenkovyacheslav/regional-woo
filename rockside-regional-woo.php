<?php
/**
 * Plugin Name: Rockside Regional WooCommerce
 * Description: Регіональні URL, ціни, видимість товарів та canonical для WooCommerce (Харків / Київ / Львів).
 * Version: 0.1.0
 * Author: Viacheslav
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ROCKSIDE_REGIONAL_VERSION', '0.1.0');
define('ROCKSIDE_REGIONAL_FILE', __FILE__);
define('ROCKSIDE_REGIONAL_PATH', plugin_dir_path(__FILE__));
define('ROCKSIDE_REGIONAL_URL', plugin_dir_url(__FILE__));

require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-plugin.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-region-context.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-pricing.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-seo.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-inventory-feed.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-admin.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-cache.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-switcher.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-output-fix.php';
require_once ROCKSIDE_REGIONAL_PATH . 'includes/class-popup.php';

register_activation_hook(__FILE__, ['Rockside_Regional_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Rockside_Regional_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    Rockside_Regional_Plugin::instance()->boot();
});