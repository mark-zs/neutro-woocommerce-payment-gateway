<?php

// Exit if accessed directly
defined('ABSPATH') or die('');

class NeutroPG_Callback_Handler {

    private static $instance;

    public static function get_instance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'neutro_handle_callback'));
    }

    public function neutro_handle_callback() {
        if (!isset($_GET['neutro_order']) || !isset($_GET['nonce'])) {
            return;
        }

        $order_id = is_numeric($_GET['neutro_order']) ? intval($_GET['neutro_order']) : 0;
        $nonce = sanitize_text_field($_GET['nonce']);

        $order = wc_get_order($order_id);

        // not an order
        if (!($order instanceof WC_Order)) {
            return;
        }

        $order_nonce = self::get_order_nonce($order_id);

        // the nonce is incorrect
        if ($nonce != $order_nonce) {
            return;
        }

        var_dump($_GET);
        die;
    }

    public static function get_order_nonce($order_id) {
        $nonce = get_post_meta($order_id, '_neutro_nonce', true);
        if (!$nonce) {
            $nonce = wp_generate_password(43, false);
            update_post_meta($order_id, '_neutro_nonce', $nonce);
        }
        return $nonce;
    }
}

NeutroPG_Callback_Handler::get_instance();