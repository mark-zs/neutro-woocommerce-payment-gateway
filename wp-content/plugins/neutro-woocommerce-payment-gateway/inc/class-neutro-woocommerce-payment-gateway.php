<?php

// Exit if accessed directly
defined('ABSPATH') or die('');

function nwpg_init_neutro_payment_gateway() {
    class WC_Neutro_Payment_Gateway extends WC_Payment_Gateway {
        private $api_key;
        private $instructions;
        private $active_countries;
        private $active_for_subscription_products;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id = 'neutro';
            // $this->icon = 'sample-icon.png';
            $this->has_fields = true;
            $this->method_title = 'Neutro';
            $this->method_description = 'Neutro Payment Gateway';

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->api_key = $this->get_option('api_key');
            $this->active_countries = $this->get_option('active_countries');
            $this->active_for_subscription_products = $this->get_option('active_for_subscription_products');

            // Actions.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // add_action('woocommerce_thankyou_neutro', array($this, 'thankyou_page'));
            // add_action('woocommerce_checkout_create_order', array($this, 'save_order_payment_gateway_details'), 10, 2);
        }

        /**
         * verify the order payment
         * @param int $order_id
         *
         * @return bool
         */
        public function verify_order_payment($order_id) {
            $endpoint = 'http://app1.neutro.financial/servlet/paymentStatus';
            $response = wp_remote_get($endpoint, array(
                'body' => array(
                    'apiKey' => $this->api_key,
                    'neutroSinglePaymentId' => get_post_meta($order_id, '_neutroSinglePaymentId', true),
                ),
            ));

            // var_dump($response); die;
            if (!isset($response['response']) || $response['response']['code'] != 200) {
                // var_dump($post_data, $response);
                return null;
            }

            $response = json_decode($response['body'], true);
            // var_dump($response); die;

            return $response['status'];
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment($order_id) {
            // $order = wc_get_order($order_id);

            $payment_url = $this->get_payment_url($order_id);

            // success
            if ($payment_url) {
                return array(
                    'result' => 'success',
                    'redirect' => $payment_url,
                );
            }

            return parent::process_payment($order_id);
        }

        /**
         * generate a payment url for the order
         * @param int $order_id
         *
         * @return string|null
         */
        private function get_payment_url($order_id) {
            $order = wc_get_order($order_id);

            $nonce = NeutroPG_Callback_Handler::get_order_nonce($order_id);

            $post_data = array(
                'apiKey' => $this->api_key,
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'userID' => is_user_logged_in() ? get_current_user_id() : '',
                'transactionDesc' => '',
                'transactionRef' => $order_id,
                'merchantTransactionId' => $order_id,
                'callbackUrl' => add_query_arg(array('neutro_payment' => true), home_url()),
                'nonce' => $nonce,
            );

            $endpoint = 'http://app1.neutro.financial/servlet/preparePayment';
            $response = wp_remote_post($endpoint, array(
                'body' => $post_data,
            ));

            // var_dump($response); die;
            // something wrong
            if (!isset($response['response']) || $response['response']['code'] != 200) {
                // var_dump($post_data, $response);
                return null;
            }

            $response = json_decode($response['body'], true);
            // var_dump($response); die;
            $neutroSinglePaymentId = $response['neutroSinglePaymentId'];
            $startPaymentRedirectUrl = $response['startPaymentRedirectUrl'];

            // link order with neutroSinglePaymentId
            update_post_meta($order_id, '_neutroSinglePaymentId', $neutroSinglePaymentId);

            return $startPaymentRedirectUrl;
        }

        public function is_available() {
            $is_available = parent::is_available();

            if (!is_admin()) {
                // Pay with Neutro will only display if user is in appropriate country
                if (is_array($this->active_countries) && !empty($this->active_countries)) {
                    $billing_country = sprintf('country:%s', WC()->customer->get_billing_country());
                    if (!in_array($billing_country, $this->active_countries)) {
                        return false;
                    }
                }
            }

            return $is_available;
        }

        public function supports($feature) {
            // subscriptions support
            if ($feature == 'subscriptions') {
                if ($this->active_for_subscription_products == 'yes') {
                    return true;
                }
            }

            return parent::supports($feature);
        }

        /**
         * Validate frontend fields.
         *
         * Validate payment fields on the frontend.
         *
         * @return bool
         */
        public function validate_fields() {
            return true;
        }

        /**
         * Output the "payment type" fields in checkout.
         */
        public function payment_fields() {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable bank transfer', 'woocommerce'),
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => $this->method_title,
                    'desc_tip' => true,
                    // 'css' => 'border:none;pointer-events:none',
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                    'default' => $this->method_description,
                    'desc_tip' => true,
                    // 'css' => 'border:none;pointer-events:none',
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_key' => array(
                    'title' => __('API KEY', 'neutro-woocommerce-payment-gateway'),
                    'type' => 'text',
                    // 'description' => 'API KEY',
                    'default' => '',
                ),
                'active_countries' => array(
                    'title' => 'Active countries',
                    'type' => 'countries_dropdown',
                    'default' => '',
                ),
                'active_for_subscription_products' => array(
                    'title' => 'Active for subscription products',
                    'type' => 'checkbox',
                ),
            );
        }

        public function generate_countries_dropdown_html($key, $value) {
            $allowed_countries = WC()->countries->get_shipping_countries();
            $shipping_continents = WC()->countries->get_shipping_continents();

            $field_key = $this->get_field_key($key);
            $locations = $this->settings[$key];

            ob_start();
            ?>
            <tr>
                <th>Active countries</th>
                <td>
                    <select multiple data-attribute="<?php echo $field_key; ?>" id="<?php echo $field_key; ?>" name="<?php echo $field_key; ?>[]" data-placeholder="Active countries" class="wc-shipping-zone-region-select chosen_select">
                        <?php
                        foreach ($shipping_continents as $continent_code => $continent) {
                            // echo '<option value="continent:' . esc_attr($continent_code) . '"' . wc_selected("continent:$continent_code", $locations) . '>' . esc_html($continent['name']) . '</option>';

                            $countries = array_intersect(array_keys($allowed_countries), $continent['countries']);

                            foreach ($countries as $country_code) {
                                echo '<option value="country:' . esc_attr($country_code) . '"' . wc_selected("country:$country_code", $locations) . '>' . esc_html('&nbsp;&nbsp; ' . $allowed_countries[$country_code]) . '</option>';

//                                $states = WC()->countries->get_states($country_code);
//
//                                if ($states) {
//                                    foreach ($states as $state_code => $state_name) {
//                                        echo '<option value="state:' . esc_attr($country_code . ':' . $state_code) . '"' . wc_selected("state:$country_code:$state_code", $locations) . '>' . esc_html('&nbsp;&nbsp;&nbsp;&nbsp; ' . $state_name . ', ' . $allowed_countries[$country_code]) . '</option>';
//                                    }
//                                }
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <?php $content = ob_get_clean();
            return $content;
        }

        public function process_admin_options() {
            parent::process_admin_options();
        }

        public function validate_countries_dropdown_field($key, $value) {
            return $value;
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if ($this->instructions && !$sent_to_admin && 'offline' === $order->payment_method && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }
    }
}

add_action('plugins_loaded', 'nwpg_init_neutro_payment_gateway', 11);
