<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Plugin {
    private static $instance = null;

    /** @var Rockside_Regional_Region_Context */
    private $context;

    /** @var Rockside_Regional_Pricing */
    private $pricing;

    /** @var Rockside_Regional_SEO */
    private $seo;

    /** @var Rockside_Regional_Inventory_Feed */
    private $feed;

    /** @var Rockside_Regional_Admin */
    private $admin;

    /** @var Rockside_Regional_Cache */
    private $cache;

    /** @var Rockside_Regional_Switcher */
    private $switcher;

    /** @var Rockside_Regional_Popup */
    private $popup;

    /** @var Rockside_Regional_Output_Fix */
    private $output_fix;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        $context = new Rockside_Regional_Region_Context();
        $context->register_rewrite();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function boot() {
        $this->context = new Rockside_Regional_Region_Context();
        $this->pricing = new Rockside_Regional_Pricing($this->context);
        $this->seo     = new Rockside_Regional_SEO($this->context);
        $this->feed    = new Rockside_Regional_Inventory_Feed($this->context);
        $this->admin   = new Rockside_Regional_Admin($this->context);
        $this->cache   = new Rockside_Regional_Cache($this->context);
        $this->switcher = new Rockside_Regional_Switcher($this->context);
        $this->popup = new Rockside_Regional_Popup($this->context);
        $this->output_fix = new Rockside_Regional_Output_Fix($this->context);

        $this->context->boot();
        $this->pricing->boot();
        $this->seo->boot();
        $this->feed->boot();
        $this->admin->boot();
        $this->cache->boot();
        $this->switcher->boot();
        $this->popup->boot();
        $this->output_fix->boot();
    }
}