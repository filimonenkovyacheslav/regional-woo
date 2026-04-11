<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Cache {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_action('init', array($this, 'handle_region_query_no_cache'), 1);
        add_action('send_headers', array($this, 'send_cache_headers'), 1);
        add_action('litespeed_control_set_nocache', array($this, 'maybe_force_litespeed_nocache'));
    }

    public function handle_region_query_no_cache() {
        if (empty($_GET['region_id'])) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }

        if (!defined('DONOTMINIFY')) {
            define('DONOTMINIFY', true);
        }
    }

    public function send_cache_headers() {
        if (!empty($_GET['region_id'])) {
            nocache_headers();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
            header('Pragma: no-cache', true);
            header('X-Rockside-Region-Mode: query-nocache', true);
            return;
        }

        $region = $this->context->get_current_region();
        header('X-Rockside-Region: ' . $region, true);
    }

    public function maybe_force_litespeed_nocache() {
        if (!empty($_GET['region_id'])) {
            do_action('litespeed_control_finalize');
        }
    }
}