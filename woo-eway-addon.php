<?php
/**
 * Plugin Name:         Addon for Eway and WooCommerce
 * Plugin URI :         Addon for Eway and WooCommerce
 * Description:         Addon for Eway and WooCommerce allows you to accept payments on your Woocommerce store. It accpets credit card payments and processes them securely with your merchant account.
 * Version:             2.0.3
 * WC requires at least:2.3
 * WC tested up to:     3.8.1
 * Requires at least:   4.0+
 * Tested up to:        5.3.2
 * Contributors:        wp_estatic
 * Author:              Estatic Infotech Pvt Ltd
 * Author URI:          http://estatic-infotech.com/
 * License:             GPLv3
 * @package WooCommerce
 * @category Woocommerce Payment Gateway
 */
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    deactivate_plugins(plugin_basename(__FILE__));
    add_action('load-plugins.php', function() {
        add_filter('gettext', 'change_eway_text', 99, 3);
    });

    /**
     * 
     * @param type $translated_text
     * @param type $untranslated_text
     * @param type $domain
     * @return string
     */
    function change_eway_text($translated_text, $untranslated_text, $domain) {
        $old = array(
            "Plugin <strong>activated</strong>.",
            "Selected plugins <strong>activated</strong>."
        );

        $new = "Please activate <b>Woocommerce</b> Plugin to use WooCommerce eWay Addon plugin";

        if (in_array($untranslated_text, $old, true)) {
            $translated_text = $new;
            remove_filter(current_filter(), __FUNCTION__, 99);
        }
        return $translated_text;
    }

    return FALSE;
}

add_action('plugins_loaded', 'init_eway_gateway_class');

/**
 * 
 * @return boolean
 */
function init_eway_gateway_class() {

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links_eway');

    /**
     * 
     * @param type $links
     * @return type
     */
    function add_action_links_eway($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_eway_ei') . '" title="' . esc_attr(__('View WooCommerce Settings', 'woocommerce')) . '">' . __('Settings', 'woocommerce') . '</a>',
        );
        return array_merge($links, $action_links);
    }

    if (class_exists('WC_Payment_Gateway')) {

        class WC_Gateway_Eway_EI extends WC_Payment_Gateway {

            function __construct() {
                $this->id = 'eway';
                $this->icon = null;
                $this->has_fields = true;
                $this->method_title = 'Eway';

                $this->init_form_fields();
                $this->init_settings();
                $this->supports = array('default_credit_card_form', 'products', 'refunds');
                $this->eway_api_key = $this->get_option('eway_api');
                $this->eway_api_password = $this->get_option('eway_passwrod');

                $this->eway_mode = $this->get_option('eway_mode') === 'sandbox' ? true : false;

                $this->title = $this->get_option('title');
                if (empty($this->title)) {
                    $this->title = 'Eway - Credit Card';
                }

                $getway_description = $this->get_option('description');
                if (!empty($getway_description)) {
                    $this->method_description = $getway_description;
                } else {
                    $this->method_description = 'Woo Eway Addon allows you to accept payments on your Woocommerce store. It accpets credit card payments and processes them securely with your merchant account.';
                }

                if (is_admin()) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                }
            }

            /**
             * 
             * @return boolean
             */
            public function is_available() {

                if ($this->enabled == "yes") {

                    if (!$this->eway_mode && is_checkout()) {
                        return false;
                    }
                    // Required fields check
                    if ($this->eway_api_key || $this->eway_api_password || $this->eway_customer_id) {
                        return true;
                    }
                }
                return false;
            }

            /**
             * initial form load in Admin
             */
            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable / Disable', 'eway'),
                        'label' => __('Enable eWay(using credit card)', 'eway'),
                        'type' => 'checkbox',
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title' => __('Title', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('Payment title the customer will see during the checkout process.', 'eway'),
                        'default' => __('Eway', 'eway'),
                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Display this description on checkout page.', 'woocommerce'),
                        'default' => $this->method_description,
                        'desc_tip' => true,
                        'css' => 'width: 100% !important;max-width: 400px;',
                    ),
                    'eway_customer_id' => array(
                        'title' => __('eWay Customer ID', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_api' => array(
                        'title' => __('eWay Rapid API key', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_passwrod' => array(
                        'title' => __('eWay Rapid Password', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_mode' => array(
                        'title' => __('eWay Mode', 'eway'),
                        'type' => 'select',
                        'description' => '',
                        'default' => 'sandbox',
                        'options' => array(
                            'sandbox' => __('Sandbox', 'eway'),
                            'live' => __('Live', 'eway'),
                        ),
                    ),
                    'show_accepted' => array(
                        'title' => __('Show Accepted Card Icons', 'eway'),
                        'type' => 'select',
                        'class' => 'chosen_select',
                        'css' => 'width: 350px;',
                        'desc_tip' => __('Select the mode to accept.', 'eway'),
                        'options' => array(
                            'yes' => 'Yes',
                            'no' => 'No',
                        ),
                        'default' => 'yes',
                    ), 'eway_cardtypes' => array(
                        'title' => __('Accepted Card Types', 'eway'),
                        'type' => 'multiselect',
                        'class' => 'chosen_select',
                        'css' => 'width: 350px;',
                        'desc_tip' => __('Add/Remove credit card types to accept.', 'eway'),
                        'options' => array(
                            'mastercard' => 'MasterCard',
                            'visa' => 'Visa',
                            'dinersclub' => 'Dinners Club',
                            'amex' => 'AMEX',
                            'discover' => 'Discover',
                            'jcb' => 'JCB'
                        ),
                        'default' => array('mastercard' => 'MasterCard',
                            'visa' => 'Visa',
                            'discover' => 'Discover',
                            'amex' => 'AMEX'),
                    ),
                );
            }

            /**
             * 
             * @param type $number
             * @return string
             */
            function get_card_type($number) {
                $number = preg_replace('/[^\d]/', '', $number);

                if (preg_match('/^3[47][0-9]{13}$/', $number)) {
                    $card = 'amex';
                } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
                    $card = 'dinersclub';
                } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
                    $card = 'discover';
                } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
                    $card = 'jcb';
                } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
                    $card = 'mastercard';
                } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
                    $card = 'visa';
                } else {
                    $card = 'unknown';
                }

                return $card;
            }

            /**
             * 
             * @return type
             */
            public function get_icon() {

                if ($this->get_option('show_accepted') == 'yes') {

                    $get_cardtypes = $this->get_option('eway_cardtypes');
                    $icons = "";
                    foreach ($get_cardtypes as $val) {
                        $cardimage = plugins_url('images/' . $val . '.png', __FILE__);
                        $icons .= '<img src="' . $cardimage . '" alt="' . $val . '" />';
                    }
                } else {
                    $icons = "";
                }
                return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
            }

            /**
             * 
             * @global type $woocommerce
             * @param type $order_id
             * @return type
             */
            public function process_payment($order_id) {
                global $woocommerce;
                $customer_order = wc_get_order($order_id);

                require('vendor/autoload.php');
                //require_once 'lib/eway-rapid-php-master/include_eway.php';
                if ($this->eway_mode == true) {
                    define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/AccessCode/');
                } else {
                    define('MODE_PRODUCTION', 'https://api.ewaypayments.com/AccessCode/');
                }

                $apiKey = $this->eway_api_key;
                $apiPassword = $this->eway_api_password;

                if ($this->eway_mode == true) {
                    $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                } else {
                    $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                }

                $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);

                $cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ', '', $_POST['ei_eway-card-number'])));
                if (!in_array($cardtype, $this->get_option('eway_cardtypes'))) {
                    wc_add_notice('Merchant do not accept/support payments using ' . ucwords($cardtype) . ' card', $notice_type = 'error');
                    return array(
                        'result' => 'success',
                        'redirect' => wc_get_checkout_url(),
                    );
                    die;
                }
                $card_num = sanitize_text_field(str_replace(' ', '', $_POST['ei_eway-card-number']));
                $exp_date = explode("/", sanitize_text_field($_POST['ei_eway-card-expiry']));
                $exp_month = str_replace(' ', '', $exp_date[0]);
                $exp_year = str_replace(' ', '', $exp_date[1]);
                $cvc = sanitize_text_field($_POST['ei_eway-card-cvc']);
                $currency = get_woocommerce_currency();

                $order = wc_get_order($order_id);
                $items = $order->get_items();
                $allItems = array();
                foreach ($items as $item) {
                    $product_qty = $item['qty'];
                    $product_variation_id = $item['variation_id'];
                    if ($product_variation_id) {
                        $product = new WC_Product($item['variation_id']);
                    } else {
                        $product = new WC_Product($item['product_id']);
                    }
                    $sku = $product->get_sku();
                    $description = $product->get_description();

                    $data['SKU'] = $sku;
                    $data['Description'] = $description;
                    $data['Quantity'] = $product_qty;
                    $data['UnitCost'] = '';
                    $data['Tax'] = $customer_order->get_total_tax();
                    array_push($allItems, $data);
                }

                $transaction = [
                    'Customer' => [
                        'Reference' => rand(),
                        'Title' => '',
                        'FirstName' => $customer_order->billing_first_name,
                        'LastName' => $customer_order->billing_last_name,
                        'CompanyName' => $customer_order->billing_company_name,
                        'JobDescription' => 'Product Purchase',
                        'Street1' => $customer_order->billing_address_1,
                        'Street2' => $customer_order->billing_address_2,
                        'City' => $customer_order->billing_city,
                        'State' => $customer_order->billing_state,
                        'PostalCode' => $customer_order->billing_postcode,
                        'Country' => $customer_order->billing_country,
                        'Phone' => $customer_order->billing_phone,
                        'Mobile' => $customer_order->billing_phone,
                        'Email' => $customer_order->billing_email,
                        "Url" => "http://www.ewaypayments.com",
                        'CardDetails' => [
                            'Name' => $customer_order->billing_first_name,
                            'Number' => $card_num,
                            'ExpiryMonth' => $exp_month,
                            'ExpiryYear' => $exp_year,
                            'CVN' => $cvc,
                        ]
                    ],
                    'ShippingAddress' => [
                        'ShippingMethod' => \Eway\Rapid\Enum\ShippingMethod::NEXT_DAY,
                        'FirstName' => $customer_order->shipping_first_name,
                        'LastName' => $customer_order->shipping_last_name,
                        'Street1' => $customer_order->shipping_address_1,
                        'Street2' => $customer_order->shipping_address_2,
                        'City' => $customer_order->shipping_city,
                        'State' => $customer_order->shipping_state,
                        'Country' => $customer_order->shipping_country,
                        'PostalCode' => $customer_order->shipping_postcode,
                        'Phone' => $customer_order->shipping_phone,
                    ],
                    'Items' => $allItems,
                    'Options' => [],
                    'Payment' => [
                        'TotalAmount' => floatval($customer_order->order_total * 100),
                        'InvoiceNumber' => 'Inv ' . $order_id,
                        'InvoiceDescription' => 'Individual Invoice Description',
                        'InvoiceReference' => $order_id,
                        'CurrencyCode' => $currency,
                    ],
                    'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
                    'Capture' => true,
                ];

                $response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);
                if ($response->TransactionStatus == true) {
                    add_post_meta($order_id, '_transaction_id', $response->TransactionID);
                    $customer_order->add_order_note(__('eWay payment completed.', 'eway'));
                    $customer_order->payment_complete();
                    $woocommerce->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($customer_order),
                    );
                    die;
                } else {
                    $k = "";
                    foreach ($response->getErrors() as $error) {
                        $k .= \Eway\Rapid::getMessage($error) . "<br>";
                    }
                    wc_add_notice('Error Processing Checkout, please check the errors ' . $k . "<br>", 'error');
                    return array(
                        'result' => 'success',
                        'redirect' => wc_get_checkout_url(),
                    );
                    die;
                }
            }

            /* Start of credit card form */

            public function payment_fields() {
                echo apply_filters('wc_eway_description', wpautop(wp_kses_post(wptexturize(trim($this->method_description)))));
                $this->form();
            }

            /**
             * 
             * @param type $name
             * @return type
             */
            public function field_name($name) {
                return $this->supports('tokenization') ? '' : ' name="ei_' . esc_attr($this->id . '-' . $name) . '" ';
            }

            public function form() {
                wp_enqueue_script('wc-credit-card-form');
                $fields = array();
                $cvc_field = '<p class="form-row form-row-last">
	<label for="ei_eway-card-cvc">' . __('Card Code', 'woocommerce') . ' <span class="required">*</span></label>
	<input id="ei_eway-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' />
</p>';
                $default_fields = array(
                    'card-number-field' => '<p class="form-row form-row-wide">
	<label for="ei_eway-card-number">' . __('Card Number', 'woocommerce') . ' <span class="required">*</span></label>
	<input id="ei_eway-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
</p>',
                    'card-expiry-field' => '<p class="form-row form-row-first">
<label for="ei_eway-card-expiry">' . __('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>
<input id="ei_eway-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
</p>',
                    'card-cvc-field' => $cvc_field
                );

                $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
                ?>

                <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
                    <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
                    <?php
                    foreach ($fields as $field) {
                        echo $field;
                    }
                    ?>
                    <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
                    <div class="clear"></div>
                </fieldset>
                <?php
            }

            /**
             * 
             * @global type $woocommerce
             * @param type $order_id
             * @param type $amount
             * @param type $reason
             * @return boolean
             * @throws Exception
             */
            public function process_refund($order_id, $amount = NULL, $reason = '') {
                global $woocommerce;
                $customer_order = wc_get_order($order_id);
                require('vendor/autoload.php');
                $transaction_id = get_post_meta($order_id, '_transaction_id', true);

                if ($amount > 0) {
                    if ($this->eway_mode == true) {
                        define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    } else {
                        define('MODE_SANDBOX', 'https://api.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    }

                    $apiKey = $this->eway_api_key;
                    $apiPassword = $this->eway_api_password;

                    if ($this->eway_mode == true) {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                    } else {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                    }
                    $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);
                    $refund = [
                        'Refund' => [
                            'TransactionID' => $transaction_id,
                            'TotalAmount' => $amount * 100
                        ],
                    ];
                    if ($refund) {
                        $repoch = $refund->created;
                        $rdt = new DateTime($repoch);
                        $rtimestamp = $rdt->format('Y-m-d H:i:s e');
                        $response = $client->refund($refund);
                        if ($response->TransactionStatus == true) {
                            $customer_order->add_order_note(__('Eway Refund completed at. ' . $rtimestamp . ' with Refund ID = ' . $response->TransactionID, 'eway'));
                            return true;
                        } else {
                            if ($response->getErrors()) {
                                foreach ($response->getErrors() as $error) {
                                    throw new Exception(__("Error: " . \Eway\Rapid::getMessage($error)));
                                }
                            } else {
                                throw new Exception(__('Sorry, your refund failed'));
                            }
                            return false;
                        }
                    }
                } else {
                    if ($this->eway_mode == true) {
                        define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    } else {
                        define('MODE_SANDBOX', 'https://api.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    }

                    $apiKey = $this->eway_api_key;
                    $apiPassword = $this->eway_api_password;

                    if ($this->eway_mode == true) {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                    } else {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                    }
                    $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);
                    $order_total = get_post_meta($order_id, '_order_total', true);
                    $refund = [
                        'Refund' => [
                            'TransactionID' => $transaction_id,
                            'TotalAmount' => $order_total * 100
                        ],
                    ];
                    if ($refund) {
                        $repoch = $refund->created;
                        $rdt = new DateTime($repoch);
                        $rtimestamp = $rdt->format('Y-m-d H:i:s e');
                        $response = $client->refund($refund);

                        if ($response->TransactionStatus == true) {
                            $customer_order->add_order_note(__('Eway Refund completed at. ' . $rtimestamp . ' with Refund ID = ' . $response->TransactionID, 'eway'));
                            return true;
                        } else {
                            if ($response->getErrors()) {
                                foreach ($response->getErrors() as $error) {
                                    throw new Exception(__("Error: " . \Eway\Rapid::getMessage($error)));
                                }
                            } else {
                                throw new Exception(__('Sorry, your refund failed'));
                            }
                            return false;
                        }
                    }
                }
            }

        }

    } else {

        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', 'ewet_activate_error');
        }
        if (!is_ssl()) {
            add_action('admin_notices', 'ewet_sslerror');
        }

        deactivate_plugins(plugin_basename(__FILE__));
        return FALSE;
    }
}

/**
 * Activate error
 */
function ewet_activate_error() {
    $html = '<div class="error">';
    $html .= '<p>';
    $html .= __('Please activate <b>Woocommerce</b> Plugin to use this plugin');
    $html .= '</p>';
    $html .= '</div>';
    echo $html;
}

/**
 * ssl Error
 */
function ewet_sslerror() {
    $html = '<div class="error">';
    $html .= '<p>';
    $html .= __('Please use <b>ssl</b> and activate Force secure checkout to use this plugin');
    $html .= '</p>';
    $html .= '</div>';
    echo $html;
}

/**
 * 
 * @param array $methods
 * @return string
 */
function ewet_add_eway_gateway_class($methods) {
    $methods[] = 'WC_Gateway_Eway_EI';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'ewet_add_eway_gateway_class');

/**
 * ewet_add_custom_js_eway
 */
function ewet_add_custom_js_eway() {
    wp_enqueue_script('jquery-cc-eway', plugin_dir_url(__FILE__) . 'js/cc.custom_eway.js', array('jquery'), '1.0', True);
}

add_action('wp_enqueue_scripts', 'ewet_add_custom_js_eway');
