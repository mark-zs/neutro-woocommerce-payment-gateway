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
        if (!isset($_GET['neutro_payment']) || !isset($_GET['nonce']) || !isset($_GET['status'])) {
            return;
        }

        // only extract order_id and nonce from the return URL, other fields must be queried from database
        $order_id = is_numeric($_GET['merchantTransactionId']) ? intval($_GET['merchantTransactionId']) : 0;
        $nonce = sanitize_text_field($_GET['nonce']);

        $order = wc_get_order($order_id);

        // not an order
        if (!($order instanceof WC_Order)) {
            return;
        }

        $order_nonce = self::get_order_nonce($order_id);

        // the nonce does not match
        if ($nonce != $order_nonce) {
            return;
        }

        /**
         * @var WC_Neutro_Payment_Gateway $neutro_pg
         */
        $neutro_pg = WC_Payment_Gateways::instance()->get_available_payment_gateways()['neutro'];

        // verify the order
        $status = $neutro_pg->verify_order_payment($order_id);

        $accepted_statuses = array('executed', 'cancelled', 'failed', 'rejected');
        if (!in_array($status, $accepted_statuses)) {
            return;
        }

        $neutroSinglePaymentId = get_post_meta($order_id, '_neutroSinglePaymentId', true);
        $note = sprintf('neutroSinglePaymentId = %s. ', $neutroSinglePaymentId);

        // successful
        if ($status == 'executed') {
            $order->update_status('processing', $note);

            wp_safe_redirect($order->get_checkout_order_received_url());
            die;
        }

        // cancelled
        if ($status == 'cancelled') {
            $order->update_status('cancelled', $note);
            ?>
            <p>Your order has been cancelled.</p>
            <p>Redirecting to home page in 5 seconds...</p>
            <script type="text/javascript">
                (function () {
                    setTimeout(function () {
                        window.location.href = '<?php echo home_url(); ?>';
                    }, 2000);
                })();
            </script>
            <?php
            die;
        }

        // failed
        if ($status == 'failed') {
            // $order->update_status('failed', $note);
            ?>
            <p>The payment has been failed. Please try again</p>
            <p>Redirecting to the checkout page in 5 seconds or click
                <a href="<?php echo $order->get_checkout_payment_url(); ?>">here</a>...</p>
            <script type="text/javascript">
                (function () {
                    setTimeout(function () {
                        window.location.href = '<?php echo $order->get_checkout_payment_url(); ?>';
                    }, 2000);
                })();
            </script>
            <?php
            die;
        }

        // rejected
        if ($status == 'rejected') {
            $note = 'Payment rejected. ' . $note;
            $order->update_status('failed', $note);
            ?>
            <p>The payment has been rejected. Please contact the site administrator for help.</p>
            <p>Redirecting to home page in 5 seconds...</p>
            <script type="text/javascript">
                (function () {
                    setTimeout(function () {
                        window.location.href = '<?php echo home_url(); ?>';
                    }, 2000);
                })();
            </script>
            <?php
            die;
        }
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
