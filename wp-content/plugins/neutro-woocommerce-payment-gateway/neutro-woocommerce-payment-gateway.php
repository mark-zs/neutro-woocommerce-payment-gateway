<?php
/**
 * Plugin Name:       Neutro payments
 * Plugin URI:        https://www.neutro.net/
 * Description:       Neutro is an easy checkout option which has an impact on the world.
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Neutro Ltd
 * Developer:         neutroltd
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       neutro-payments
 * Domain Path:       /languages
 */

// Exit if accessed directly
defined('ABSPATH') or die('');

/**
 * Check if WooCommerce is active
 **/
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    function nwpg_error_notice() {
        ?>
        <div class="error notice">
            <p><?php _e('Neutro WooCommerce payment gateway: Woocommerce has not been installed!', 'neutro-woocommerce-payment-gateway'); ?></p>
        </div>
        <?php
    }

    add_action('admin_notices', 'nwpg_error_notice');
    return;
}

define('NWPG_ROOT', untrailingslashit(plugin_dir_path(__FILE__)));
define('NWPG_BASE_URL', untrailingslashit(plugin_dir_url(__FILE__)));

require_once(NWPG_ROOT . '/inc/callback-handler.php');
require_once(NWPG_ROOT . '/inc/class-neutro-woocommerce-payment-gateway.php');

/**
 * register neutro payment gateway
 *
 * @param array $gateways
 *
 * @return array
 */
function nwpg_register_payment_gateway($gateways) {
    $gateways[] = 'WC_Neutro_Payment_Gateway';
    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'nwpg_register_payment_gateway');

function neutro_custom_button_text($button_html) {
    // $button_html = str_replace( 'Place order', 'Pay with Neutro', $button_html );
    $button_html = str_replace(' value="', 'data-pay-with-neutro="Pay with Neutro" value="', $button_html);
    return $button_html;
}

add_filter('woocommerce_order_button_html', 'neutro_custom_button_text');

function neutro_scripts() {
    // for checkout page only
    if (!is_checkout()) {
        return;
    }
    ?>
    <script type="text/javascript" async defer>
        (function ($) {
            'use strict';

            $(document).ready(function () {
                function correctBtnText() {
                    var paymentMethod = $('input[name="payment_method"]:checked').val();
                    var $btnPlaceOrder = $('#place_order');
                    // console.log(paymentMethod);
                    if (paymentMethod === 'neutro') {
                        // console.log($btnPlaceOrder.attr('data-pay-with-neutro'));
                        $btnPlaceOrder.text($btnPlaceOrder.attr('data-pay-with-neutro'));
                    } else {
                        $btnPlaceOrder.text($btnPlaceOrder.attr('data-value'));
                    }
                }

                $(document.body).on('updated_checkout', function () {
                    correctBtnText();
                });

                $(document.body).on('payment_method_selected', function () {
                    correctBtnText();
                });
            });

        })(jQuery);
    </script>
    <?php
}

add_action('wp_footer', 'neutro_scripts');
