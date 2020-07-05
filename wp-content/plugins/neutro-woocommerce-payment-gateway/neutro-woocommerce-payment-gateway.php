<?php
/**
 * Plugin Name:       Neutro payments
 * Plugin URI:        https://www.neutro.net/
 * Description:       Neutro is an easy checkout option which has an impact on the world.
 * Version:           0.9
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Neutro Ltd
 * Author URI:        https://www.neutro.net/
 * Developer:         Neutro Ltd
 * Developer URI:     https://www.neutro.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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
