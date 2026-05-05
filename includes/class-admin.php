<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rockside_Regional_Admin {
    /** @var Rockside_Regional_Region_Context */
    private $context;

    public function __construct(Rockside_Regional_Region_Context $context) {
        $this->context = $context;
    }

    public function boot() {
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        add_action('woocommerce_product_options_pricing', array($this, 'render_simple_product_fields'));
        add_action('woocommerce_variation_options_pricing', array($this, 'render_variation_fields'), 10, 3);
        add_action('woocommerce_admin_process_product_object', array($this, 'save_simple_product_fields'));
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_fields'), 10, 2);

        add_action('save_post_product', array($this, 'save_product_region_meta_fallback'), 20, 3);

        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_page', array($this, 'save_page_region_meta'));
        add_action('save_post_post', array($this, 'save_page_region_meta'));

        add_action('product_cat_add_form_fields', array($this, 'render_product_cat_add_fields'));
        add_action('product_cat_edit_form_fields', array($this, 'render_product_cat_edit_fields'));
        add_action('created_product_cat', array($this, 'save_product_cat_region_meta'));
        add_action('edited_product_cat', array($this, 'save_product_cat_region_meta'));
    }

    private function find_base_content_for_regional_item($post_id, $post_type) {
        $items = get_posts(array(
            'post_type'      => $post_type,
            'post_status'    => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post__not_in'   => array($post_id),
        ));

        if (empty($items)) {
            return null;
        }

        foreach ($items as $base_id) {
            $kyiv_linked  = (int) get_post_meta($base_id, '_rockside_kyiv_page_id', true);
            $lviv_linked  = (int) get_post_meta($base_id, '_rockside_lviv_page_id', true);
            $odesa_linked = (int) get_post_meta($base_id, '_rockside_odesa_page_id', true);

            if ($kyiv_linked === (int) $post_id) {
                return array(
                    'base_id' => (int) $base_id,
                    'region'  => 'kyiv',
                );
            }

            if ($lviv_linked === (int) $post_id) {
                return array(
                    'base_id' => (int) $base_id,
                    'region'  => 'lviv',
                );
            }

            if ($odesa_linked === (int) $post_id) {
                return array(
                    'base_id' => (int) $base_id,
                    'region'  => 'odesa',
                );
            }
        }

        return null;
    }

    public function register_settings_page() {
        add_options_page(
            'Rockside Regions',
            'Rockside Regions',
            'manage_options',
            'rockside-regions',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('rockside_regions_settings', 'rockside_region_popup_enabled', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_yes_no'),
            'default' => 'yes',
        ));

        register_setting('rockside_regions_settings', 'rockside_primary_feed_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_feed_url'),
            'default' => '',
        ));

        register_setting('rockside_regions_settings', 'rockside_enabled_switcher_regions', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_enabled_switcher_regions'),
            'default' => array(),
        ));
    }

    public function sanitize_yes_no($value) {
        return $value === 'no' ? 'no' : 'yes';
    }

    public function sanitize_feed_url($value) {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return esc_url_raw($value);
    }

    public function sanitize_enabled_switcher_regions($value) {
        if (!is_array($value)) {
            return array();
        }

        $allowed = array('kharkiv', 'kyiv', 'lviv', 'odesa');
        $clean = array();

        foreach ($value as $region) {
            $region = sanitize_key($region);

            if (in_array($region, $allowed, true)) {
                $clean[] = $region;
            }
        }

        return array_values(array_unique($clean));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $enabled_regions = get_option('rockside_enabled_switcher_regions', array());
        if (!is_array($enabled_regions)) {
            $enabled_regions = array();
        }
        ?>
        <div class="wrap">
            <h1>Rockside Regions</h1>
            <form method="post" action="options.php">
                <?php settings_fields('rockside_regions_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Увімкнути popup рекомендації регіону</th>
                        <td>
                            <input type="hidden" name="rockside_region_popup_enabled" value="no">
                            <label>
                                <input
                                    type="checkbox"
                                    name="rockside_region_popup_enabled"
                                    value="yes"
                                    <?php checked(get_option('rockside_region_popup_enabled', 'yes'), 'yes'); ?>
                                >
                                Показувати popup вибору регіону на першому візиті.
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Регіони у перемикачі на фронті</th>
                        <td>
                            <fieldset>
                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="rockside_enabled_switcher_regions[]" value="kharkiv" <?php checked(in_array('kharkiv', $enabled_regions, true)); ?>>
                                    Харків
                                </label>

                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="rockside_enabled_switcher_regions[]" value="kyiv" <?php checked(in_array('kyiv', $enabled_regions, true)); ?>>
                                    Київ
                                </label>

                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="rockside_enabled_switcher_regions[]" value="lviv" <?php checked(in_array('lviv', $enabled_regions, true)); ?>>
                                    Львів
                                </label>

                                <label style="display:block; margin-bottom:6px;">
                                    <input type="checkbox" name="rockside_enabled_switcher_regions[]" value="odesa" <?php checked(in_array('odesa', $enabled_regions, true)); ?>>
                                    Одеса
                                </label>
                            </fieldset>

                            <p class="description">
                                Оберіть регіони, які треба показувати в перемикачі на фронті.
                                Якщо нічого не обрано — у перемикачі буде тільки Харків.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Посилання на основний Google feed</th>
                        <td>
                            <input
                                type="url"
                                name="rockside_primary_feed_url"
                                value="<?php echo esc_attr(get_option('rockside_primary_feed_url', '')); ?>"
                                class="regular-text"
                                style="width: 100%; max-width: 700px;"
                                placeholder="https://rockside.ua/wp-content/uploads/woo-product-feed-pro/xml/rock-google-feed.xml"
                            >
                            <p class="description">
                                Вкажіть URL основного primary Google feed. Саме з нього буде братись список <code>g:id</code> для regional inventory feed.
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_simple_product_fields() {
        echo '<div class="options_group">';

        woocommerce_wp_text_input(array(
            'id'                => '_price_kyiv',
            'label'             => 'Ціна Київ',
            'type'              => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Регіональна ціна для Києва.',
            'value'             => $this->get_safe_scalar_meta(get_the_ID(), '_price_kyiv'),
        ));

        woocommerce_wp_text_input(array(
            'id'                => '_price_lviv',
            'label'             => 'Ціна Львів',
            'type'              => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Регіональна ціна для Львова.',
            'value'             => $this->get_safe_scalar_meta(get_the_ID(), '_price_lviv'),
        ));

        woocommerce_wp_text_input(array(
            'id'                => '_price_odesa',
            'label'             => 'Ціна Одеса',
            'type'              => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Регіональна ціна для Одеси.',
            'value'             => $this->get_safe_scalar_meta(get_the_ID(), '_price_odesa'),
        ));

        woocommerce_wp_checkbox(array(
            'id'          => '_hide_in_kyiv',
            'label'       => 'Приховати в Києві',
            'description' => 'Товар не буде доступний у Києві.',
        ));

        woocommerce_wp_checkbox(array(
            'id'          => '_hide_in_lviv',
            'label'       => 'Приховати у Львові',
            'description' => 'Товар не буде доступний у Львові.',
        ));

        woocommerce_wp_checkbox(array(
            'id'          => '_hide_in_odesa',
            'label'       => 'Приховати в Одесі',
            'description' => 'Товар не буде доступний в Одесі.',
        ));

        echo '</div>';
    }

    public function render_variation_fields($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;

        woocommerce_wp_text_input(array(
            'id'                => '_price_kyiv_' . $variation_id,
            'name'              => '_price_kyiv[' . $variation_id . ']',
            'value'             => $this->get_safe_scalar_meta($variation_id, '_price_kyiv'),
            'label'             => 'Ціна Київ',
            'type'              => 'number',
            'wrapper_class'     => 'form-row form-row-first',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Залиш порожнім, щоб використовувалась базова ціна варіації.',
        ));

        woocommerce_wp_text_input(array(
            'id'                => '_price_lviv_' . $variation_id,
            'name'              => '_price_lviv[' . $variation_id . ']',
            'value'             => $this->get_safe_scalar_meta($variation_id, '_price_lviv'),
            'label'             => 'Ціна Львів',
            'type'              => 'number',
            'wrapper_class'     => 'form-row form-row-last',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Залиш порожнім, щоб використовувалась базова ціна варіації.',
        ));

        woocommerce_wp_text_input(array(
            'id'                => '_price_odesa_' . $variation_id,
            'name'              => '_price_odesa[' . $variation_id . ']',
            'value'             => $this->get_safe_scalar_meta($variation_id, '_price_odesa'),
            'label'             => 'Ціна Одеса',
            'type'              => 'number',
            'wrapper_class'     => 'form-row form-row-full',
            'custom_attributes' => array('step' => '0.01', 'min' => '0'),
            'desc_tip'          => true,
            'description'       => 'Залиш порожнім, щоб використовувалась базова ціна варіації.',
        ));

        woocommerce_wp_checkbox(array(
            'id'            => '_hide_in_kyiv_' . $variation_id,
            'name'          => '_hide_in_kyiv[' . $variation_id . ']',
            'value'         => get_post_meta($variation_id, '_hide_in_kyiv', true),
            'label'         => 'Приховати в Києві',
            'wrapper_class' => 'form-row form-row-first',
        ));

        woocommerce_wp_checkbox(array(
            'id'            => '_hide_in_lviv_' . $variation_id,
            'name'          => '_hide_in_lviv[' . $variation_id . ']',
            'value'         => get_post_meta($variation_id, '_hide_in_lviv', true),
            'label'         => 'Приховати у Львові',
            'wrapper_class' => 'form-row form-row-last',
        ));

        woocommerce_wp_checkbox(array(
            'id'            => '_hide_in_odesa_' . $variation_id,
            'name'          => '_hide_in_odesa[' . $variation_id . ']',
            'value'         => get_post_meta($variation_id, '_hide_in_odesa', true),
            'label'         => 'Приховати в Одесі',
            'wrapper_class' => 'form-row form-row-full',
        ));
    }

    public function save_simple_product_fields($product) {
        $product_id = $product->get_id();

        $this->save_decimal_post_meta($product_id, '_price_kyiv');
        $this->save_decimal_post_meta($product_id, '_price_lviv');
        $this->save_decimal_post_meta($product_id, '_price_odesa');

        $this->save_checkbox_post_meta($product_id, '_hide_in_kyiv');
        $this->save_checkbox_post_meta($product_id, '_hide_in_lviv');
        $this->save_checkbox_post_meta($product_id, '_hide_in_odesa');
    }

    public function save_product_region_meta_fallback($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->save_checkbox_post_meta($post_id, '_hide_in_kyiv');
        $this->save_checkbox_post_meta($post_id, '_hide_in_lviv');
        $this->save_checkbox_post_meta($post_id, '_hide_in_odesa');

        $this->save_decimal_post_meta($post_id, '_price_kyiv');
        $this->save_decimal_post_meta($post_id, '_price_lviv');
        $this->save_decimal_post_meta($post_id, '_price_odesa');
    }

    public function save_variation_fields($variation_id, $index) {
        $price_kyiv = isset($_POST['_price_kyiv'][$variation_id])
            ? wc_clean(wp_unslash($_POST['_price_kyiv'][$variation_id]))
            : '';
        $price_lviv = isset($_POST['_price_lviv'][$variation_id])
            ? wc_clean(wp_unslash($_POST['_price_lviv'][$variation_id]))
            : '';
        $price_odesa = isset($_POST['_price_odesa'][$variation_id])
            ? wc_clean(wp_unslash($_POST['_price_odesa'][$variation_id]))
            : '';

        $this->update_decimal_meta($variation_id, '_price_kyiv', $price_kyiv);
        $this->update_decimal_meta($variation_id, '_price_lviv', $price_lviv);
        $this->update_decimal_meta($variation_id, '_price_odesa', $price_odesa);

        if (isset($_POST['_hide_in_kyiv'][$variation_id])) {
            update_post_meta($variation_id, '_hide_in_kyiv', 'yes');
        } else {
            delete_post_meta($variation_id, '_hide_in_kyiv');
        }

        if (isset($_POST['_hide_in_lviv'][$variation_id])) {
            update_post_meta($variation_id, '_hide_in_lviv', 'yes');
        } else {
            delete_post_meta($variation_id, '_hide_in_lviv');
        }

        if (isset($_POST['_hide_in_odesa'][$variation_id])) {
            update_post_meta($variation_id, '_hide_in_odesa', 'yes');
        } else {
            delete_post_meta($variation_id, '_hide_in_odesa');
        }
    }

    private function save_decimal_post_meta($post_id, $meta_key) {
        $value = isset($_POST[$meta_key]) ? wc_clean(wp_unslash($_POST[$meta_key])) : '';
        $this->update_decimal_meta($post_id, $meta_key, $value);
    }

    private function update_decimal_meta($post_id, $meta_key, $value) {
        if (is_array($value)) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        $value = trim((string) $value);

        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        $formatted = wc_format_decimal($value);

        if ($formatted === '' || !is_numeric($formatted)) {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $formatted);
    }

    private function save_checkbox_post_meta($post_id, $meta_key) {
        $value = isset($_POST[$meta_key]) ? wc_clean(wp_unslash($_POST[$meta_key])) : '';

        if ($value === 'yes' || $value === 'on' || $value === '1' || $value === 1) {
            update_post_meta($post_id, $meta_key, 'yes');
        } else {
            delete_post_meta($post_id, $meta_key);
        }
    }

    private function get_safe_scalar_meta($post_id, $meta_key) {
        $value = get_post_meta($post_id, $meta_key, true);

        if (is_array($value)) {
            return '';
        }

        return $value;
    }

    public function register_meta_boxes() {
        add_meta_box(
            'rockside_region_page_meta',
            'Регіональні сторінки та SEO',
            array($this, 'render_page_region_meta_box'),
            'page',
            'normal',
            'default'
        );

        add_meta_box(
            'rockside_region_post_meta',
            'Регіональні записи та SEO',
            array($this, 'render_page_region_meta_box'),
            'post',
            'normal',
            'default'
        );
    }

    public function render_page_region_meta_box($post) {
        wp_nonce_field('rockside_region_page_meta', 'rockside_region_page_meta_nonce');

        $linked_context = $this->find_base_content_for_regional_item($post->ID, $post->post_type);

        if ($linked_context) {
            $base_id = (int) $linked_context['base_id'];
            $region  = $linked_context['region'];

            $region_label = 'Харків';
            if ($region === 'kyiv') {
                $region_label = 'Київ';
            } elseif ($region === 'lviv') {
                $region_label = 'Львів';
            } elseif ($region === 'odesa') {
                $region_label = 'Одеса';
            }

            $item_label = $post->post_type === 'post' ? 'запис' : 'сторінка';
            $base_label = $post->post_type === 'post' ? 'оригінальний запис' : 'оригінальна сторінка';
            $base_title = get_the_title($base_id);

            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>Цей матеріал використовується як регіональний контент.</strong></p>';
            echo '<p>Це ' . esc_html($item_label) . ' для регіону <strong>' . esc_html($region_label) . '</strong> у '
                . esc_html($base_label) . ' <strong>' . esc_html($base_title) . ' (#' . $base_id . ')</strong>.</p>';
            echo '<p><em>Для такого контенту блок пов’язаних сторінок/записів і SEO override не застосовується. '
                . 'Редагуйте вміст цієї ' . esc_html($item_label) . ' як окремий регіональний матеріал.</em></p>';
            echo '</div>';

            return;
        }

        $kyiv_page_id  = (int) get_post_meta($post->ID, '_rockside_kyiv_page_id', true);
        $lviv_page_id  = (int) get_post_meta($post->ID, '_rockside_lviv_page_id', true);
        $odesa_page_id = (int) get_post_meta($post->ID, '_rockside_odesa_page_id', true);

        $post_type = $post->post_type;
        $items = get_posts(array(
            'post_type'      => $post_type,
            'post_status'    => array('publish', 'draft', 'private'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $title = $post_type === 'post' ? 'Пов’язані записи для регіонів' : 'Пов’язані сторінки для регіонів';
        $label_kyiv = $post_type === 'post' ? 'Запис Київ' : 'Сторінка Київ';
        $label_lviv = $post_type === 'post' ? 'Запис Львів' : 'Сторінка Львів';
        $label_odesa = $post_type === 'post' ? 'Запис Одеса' : 'Сторінка Одеса';

        echo '<p><strong>' . esc_html($title) . '</strong></p>';

        echo '<p><label>' . esc_html($label_kyiv) . '<br>';
        echo '<select name="rockside_kyiv_page_id" style="width:100%">';
        echo '<option value="">— Не вибрано —</option>';
        foreach ($items as $item) {
            if ((int) $item->ID === (int) $post->ID) {
                continue;
            }
            echo '<option value="' . esc_attr($item->ID) . '" ' . selected($kyiv_page_id, $item->ID, false) . '>' . esc_html($item->post_title) . ' (#' . (int) $item->ID . ')</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>' . esc_html($label_lviv) . '<br>';
        echo '<select name="rockside_lviv_page_id" style="width:100%">';
        echo '<option value="">— Не вибрано —</option>';
        foreach ($items as $item) {
            if ((int) $item->ID === (int) $post->ID) {
                continue;
            }
            echo '<option value="' . esc_attr($item->ID) . '" ' . selected($lviv_page_id, $item->ID, false) . '>' . esc_html($item->post_title) . ' (#' . (int) $item->ID . ')</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>' . esc_html($label_odesa) . '<br>';
        echo '<select name="rockside_odesa_page_id" style="width:100%">';
        echo '<option value="">— Не вибрано —</option>';
        foreach ($items as $item) {
            if ((int) $item->ID === (int) $post->ID) {
                continue;
            }
            echo '<option value="' . esc_attr($item->ID) . '" ' . selected($odesa_page_id, $item->ID, false) . '>' . esc_html($item->post_title) . ' (#' . (int) $item->ID . ')</option>';
        }
        echo '</select></label></p>';

        $has_kyiv_linked  = $kyiv_page_id > 0;
        $has_lviv_linked  = $lviv_page_id > 0;
        $has_odesa_linked = $odesa_page_id > 0;

        echo '<hr>';
        echo '<p><strong>SEO override</strong></p>';

        if ($has_kyiv_linked) {
            echo '<p><em>Для Києва вибрана окрема пов’язана ' . esc_html($post_type === 'post' ? 'запис' : 'сторінка') . '. SEO override для Києва не використовується.</em></p>';
        } else {
            echo '<p><label>Title Київ<br><input type="text" style="width:100%" name="rockside_kyiv_title" value="' . esc_attr(get_post_meta($post->ID, '_rockside_kyiv_title', true)) . '"></label></p>';
            echo '<p><label>Description Київ<br><textarea style="width:100%" rows="4" name="rockside_kyiv_description">' . esc_textarea(get_post_meta($post->ID, '_rockside_kyiv_description', true)) . '</textarea></label></p>';
        }

        if ($has_lviv_linked) {
            echo '<p><em>Для Львова вибрана окрема пов’язана ' . esc_html($post_type === 'post' ? 'запис' : 'сторінка') . '. SEO override для Львова не використовується.</em></p>';
        } else {
            echo '<p><label>Title Львів<br><input type="text" style="width:100%" name="rockside_lviv_title" value="' . esc_attr(get_post_meta($post->ID, '_rockside_lviv_title', true)) . '"></label></p>';
            echo '<p><label>Description Львів<br><textarea style="width:100%" rows="4" name="rockside_lviv_description">' . esc_textarea(get_post_meta($post->ID, '_rockside_lviv_description', true)) . '</textarea></label></p>';
        }

        if ($has_odesa_linked) {
            echo '<p><em>Для Одеси вибрана окрема пов’язана ' . esc_html($post_type === 'post' ? 'запис' : 'сторінка') . '. SEO override для Одеси не використовується.</em></p>';
        } else {
            echo '<p><label>Title Одеса<br><input type="text" style="width:100%" name="rockside_odesa_title" value="' . esc_attr(get_post_meta($post->ID, '_rockside_odesa_title', true)) . '"></label></p>';
            echo '<p><label>Description Одеса<br><textarea style="width:100%" rows="4" name="rockside_odesa_description">' . esc_textarea(get_post_meta($post->ID, '_rockside_odesa_description', true)) . '</textarea></label></p>';
        }
    }

    public function save_page_region_meta($post_id) {
        if (
            !isset($_POST['rockside_region_page_meta_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['rockside_region_page_meta_nonce'])),
                'rockside_region_page_meta'
            )
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $kyiv_page_id  = isset($_POST['rockside_kyiv_page_id']) ? absint(wp_unslash($_POST['rockside_kyiv_page_id'])) : 0;
        $lviv_page_id  = isset($_POST['rockside_lviv_page_id']) ? absint(wp_unslash($_POST['rockside_lviv_page_id'])) : 0;
        $odesa_page_id = isset($_POST['rockside_odesa_page_id']) ? absint(wp_unslash($_POST['rockside_odesa_page_id'])) : 0;

        if ($kyiv_page_id > 0) {
            update_post_meta($post_id, '_rockside_kyiv_page_id', $kyiv_page_id);
        } else {
            delete_post_meta($post_id, '_rockside_kyiv_page_id');
        }

        if ($lviv_page_id > 0) {
            update_post_meta($post_id, '_rockside_lviv_page_id', $lviv_page_id);
        } else {
            delete_post_meta($post_id, '_rockside_lviv_page_id');
        }

        if ($odesa_page_id > 0) {
            update_post_meta($post_id, '_rockside_odesa_page_id', $odesa_page_id);
        } else {
            delete_post_meta($post_id, '_rockside_odesa_page_id');
        }

        if ($kyiv_page_id > 0) {
            delete_post_meta($post_id, '_rockside_kyiv_title');
            delete_post_meta($post_id, '_rockside_kyiv_description');
        } else {
            update_post_meta(
                $post_id,
                '_rockside_kyiv_title',
                isset($_POST['rockside_kyiv_title']) ? sanitize_text_field(wp_unslash($_POST['rockside_kyiv_title'])) : ''
            );
            update_post_meta(
                $post_id,
                '_rockside_kyiv_description',
                isset($_POST['rockside_kyiv_description']) ? sanitize_textarea_field(wp_unslash($_POST['rockside_kyiv_description'])) : ''
            );
        }

        if ($lviv_page_id > 0) {
            delete_post_meta($post_id, '_rockside_lviv_title');
            delete_post_meta($post_id, '_rockside_lviv_description');
        } else {
            update_post_meta(
                $post_id,
                '_rockside_lviv_title',
                isset($_POST['rockside_lviv_title']) ? sanitize_text_field(wp_unslash($_POST['rockside_lviv_title'])) : ''
            );
            update_post_meta(
                $post_id,
                '_rockside_lviv_description',
                isset($_POST['rockside_lviv_description']) ? sanitize_textarea_field(wp_unslash($_POST['rockside_lviv_description'])) : ''
            );
        }

        if ($odesa_page_id > 0) {
            delete_post_meta($post_id, '_rockside_odesa_title');
            delete_post_meta($post_id, '_rockside_odesa_description');
        } else {
            update_post_meta(
                $post_id,
                '_rockside_odesa_title',
                isset($_POST['rockside_odesa_title']) ? sanitize_text_field(wp_unslash($_POST['rockside_odesa_title'])) : ''
            );
            update_post_meta(
                $post_id,
                '_rockside_odesa_description',
                isset($_POST['rockside_odesa_description']) ? sanitize_textarea_field(wp_unslash($_POST['rockside_odesa_description'])) : ''
            );
        }

        error_log(
            'ROCKSIDE SAVE LINKED CONTENT: post_id=' . $post_id .
            ' post_type=' . get_post_type($post_id) .
            ' kyiv=' . $kyiv_page_id .
            ' lviv=' . $lviv_page_id .
            ' odesa=' . $odesa_page_id
        );
    }

    public function render_product_cat_add_fields() {
        ?>
        <div class="form-field term-region-wrap">
            <label for="rockside_hide_in_kyiv">Приховати в Києві</label>
            <input type="checkbox" name="rockside_hide_in_kyiv" id="rockside_hide_in_kyiv" value="yes">
            <p>Усі товари цієї категорії будуть приховані в регіоні Київ.</p>
        </div>
        <div class="form-field term-region-wrap">
            <label for="rockside_hide_in_lviv">Приховати у Львові</label>
            <input type="checkbox" name="rockside_hide_in_lviv" id="rockside_hide_in_lviv" value="yes">
            <p>Усі товари цієї категорії будуть приховані в регіоні Львів.</p>
        </div>
        <div class="form-field term-region-wrap">
            <label for="rockside_hide_in_odesa">Приховати в Одесі</label>
            <input type="checkbox" name="rockside_hide_in_odesa" id="rockside_hide_in_odesa" value="yes">
            <p>Усі товари цієї категорії будуть приховані в регіоні Одеса.</p>
        </div>
        <?php
    }

    public function render_product_cat_edit_fields($term) {
        $hide_in_kyiv  = get_term_meta($term->term_id, '_rockside_hide_in_kyiv', true);
        $hide_in_lviv  = get_term_meta($term->term_id, '_rockside_hide_in_lviv', true);
        $hide_in_odesa = get_term_meta($term->term_id, '_rockside_hide_in_odesa', true);
        ?>
        <tr class="form-field term-region-wrap">
            <th scope="row"><label for="rockside_hide_in_kyiv">Приховати в Києві</label></th>
            <td>
                <label>
                    <input type="checkbox" name="rockside_hide_in_kyiv" id="rockside_hide_in_kyiv" value="yes" <?php checked($hide_in_kyiv, 'yes'); ?>>
                    Усі товари цієї категорії будуть приховані в регіоні Київ.
                </label>
            </td>
        </tr>
        <tr class="form-field term-region-wrap">
            <th scope="row"><label for="rockside_hide_in_lviv">Приховати у Львові</label></th>
            <td>
                <label>
                    <input type="checkbox" name="rockside_hide_in_lviv" id="rockside_hide_in_lviv" value="yes" <?php checked($hide_in_lviv, 'yes'); ?>>
                    Усі товари цієї категорії будуть приховані в регіоні Львів.
                </label>
            </td>
        </tr>
        <tr class="form-field term-region-wrap">
            <th scope="row"><label for="rockside_hide_in_odesa">Приховати в Одесі</label></th>
            <td>
                <label>
                    <input type="checkbox" name="rockside_hide_in_odesa" id="rockside_hide_in_odesa" value="yes" <?php checked($hide_in_odesa, 'yes'); ?>>
                    Усі товари цієї категорії будуть приховані в регіоні Одеса.
                </label>
            </td>
        </tr>
        <?php
    }

    public function save_product_cat_region_meta($term_id) {
        update_term_meta($term_id, '_rockside_hide_in_kyiv', isset($_POST['rockside_hide_in_kyiv']) ? 'yes' : 'no');
        update_term_meta($term_id, '_rockside_hide_in_lviv', isset($_POST['rockside_hide_in_lviv']) ? 'yes' : 'no');
        update_term_meta($term_id, '_rockside_hide_in_odesa', isset($_POST['rockside_hide_in_odesa']) ? 'yes' : 'no');
    }
}