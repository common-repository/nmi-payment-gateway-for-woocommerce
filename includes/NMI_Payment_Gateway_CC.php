<?php
//namespace Egooty\Plugins\NMI\Includes\Gateways;

/**
 * Class NMI_Payment_Gateway_CC
 *
 * NMI_Payment_Gateway_CC is a class that implement the NMI cc gate way
 * to WC_Payment_Gateway_CC
 *
 * @package  WooNmiPaymentGateway
 * @author   Mudassar Ali <sahil_bwp@yahoo.com>
 * @version  $Revision: 1.0 $
 * @access   public
 * @copyright   Copyright 2019 eGooty
 * @link     http://egooty.com/
 *
 */
class NMI_Payment_Gateway_CC extends WC_Payment_Gateway_CC
{
    /**
     *  Woo gate way id
     * @var string
     */
    const ID = 'woo-nmi-payment-gateway';
    //const WC_STATUS = 'wc-estimate';


    /**
     * NMI_Payment_Gateway_eCheck constructor.11
     */

    public function __construct() {
        global $woocommerce;
        
        $this->id			= self::ID;
        $this->method_title = __( 'Network Merchants Inc - CC', 'woo-nmi-payment-gateway' );
        $this->icon = apply_filters('woo_nmi_payment_cc_icon', '');
        $this->has_fields = TRUE;

        $this->nmi = 'https://secure.networkmerchants.com/api/transact.php';

        $this->supports = array(
            'products',
            'default_credit_card_form',
            'refunds',
            'tokenization',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_payment_method_change'
        );

        $default_card_type_options = array(
            'VISA' => 'VISA',
            'MC' => 'MasterCard',
            'AMEX' => 'American Express',
            'DISC' => 'Discover',
            'DC' => 'Diner\'s Club',
            'JCB' => 'JCB Card'
        );

        $this->card_type_options = apply_filters('woo_nmi_payment_cc_card_types', $default_card_type_options);

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->username = $this->get_option('username');
        $this->password = $this->get_option('password');
        $this->descriptor = $this->get_option('descriptor');
        $this->descriptor_url = $this->get_option('descriptor_url');
        $this->processor_id = $this->get_option('processor_id');
        $this->dup_seconds = $this->get_option('dup_seconds');
        $this->provider = $this->get_option('provider');
        $this->transtype = $this->get_option('transtype');
        $this->cardtypes = $this->get_option('cardtypes');
        $this->saved_cards = 'yes' === $this->get_option('saved_cards');
        $this->receipt = 'yes' === $this->get_option('receipt');
        $this->debug = $this->get_option('debug');

        if ($this->provider == '') {
            $this->provider = 'nmi';
        }

        if ($this->transtype == '') {
            $this->transtype = 'sale';
        }

        // Logs
        if ($this->debug == 'yes') {
            $this->log = new WC_Logger();
        }

        // Actions
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancelled_subscription'), 10, 2);
        }

        if (!$this->is_valid_for_use()) $this->enabled = false;

    }

    /**
     * cancelled_subscription function.
     *
     * @param float $amount_to_charge The amount to charge.
     * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
     */
    public function cancelled_subscription($order)
    {

        //$profile_id = self::get_subscriptions_nmi_id( $order );

        $profile_id = get_post_meta($order->parent_id, 'NMI Subscriber ID', true);

        // Make sure a subscriptions status is active with NMI
        $woo_nmi_payment_args['username'] = $this->username;
        $woo_nmi_payment_args['password'] = $this->password;
        $woo_nmi_payment_args['recurring'] = 'delete_subscription';
        $woo_nmi_payment_args['subscription_id'] = $profile_id;

        $name_value_pairs = array();
        foreach ($woo_nmi_payment_args as $key => $value) {
            $name_value_pairs[] = $key . '=' . urlencode($value);
        }
        $gateway_values = implode('&', $name_value_pairs);

        $provider = $this->provider;

        $nmi_adr = $this->$provider . '?';

        $response = wp_remote_post($nmi_adr . $gateway_values, array('sslverify' => false, 'timeout' => 60));

        if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
            parse_str($response['body'], $response);

            if ($response['response'] == '1') {
                $order->add_order_note(__('Subscription cancelled with NMI', 'woo-nmi-payment-gateway'));
            } else {
                $order->add_order_note(__('There was error cancelling the Subscription with NMI', 'woo-nmi-payment-gateway'));
            }

        }

    }

    /**
     * Returns a NMI Subscription ID/Recurring Payment Profile ID based on a user ID and subscription key
     *
     * @since 1.1
     */
    public static function get_subscriptions_nmi_id($order)
    {

        $profile_id = get_post_meta($order->id, 'NMI Subscriber ID', true);

        return $profile_id;
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    function is_valid_for_use()
    {
        if (!in_array(get_option('woocommerce_currency'), array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP'))) return false;

        return true;
    }

    /**
     * get_icon function.
     *
     * @access public
     * @return string
     */
    function get_icon()
    {
        global $woocommerce;

        $icon = '';
        if ($this->icon) {
            // default behavior
            $icon = '<img src="' . $this->force_ssl($this->icon) . '" alt="' . $this->title . '" />';
        } elseif ($this->cardtypes) {
            // display icons for the selected card types
            $icon = '';
            foreach ($this->cardtypes as $cardtype) {
                if (file_exists(plugin_dir_path(__FILE__) . '/assets/images/card-' . strtolower($cardtype) . '.png')) {
                    $icon .= '<img src="' . $this->force_ssl(plugins_url('/assets/images/card-' . strtolower($cardtype) . '.png', __FILE__)) . '" alt="' . strtolower($cardtype) . '" />';
                }
            }
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    private function force_ssl($url)
    {

        if ('yes' == get_option('woocommerce_force_ssl_checkout')) {
            $url = str_replace('http:', 'https:', $url);
        }

        return $url;
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options()
    {

        ?>
        <h3><?php _e('Network Merchants Inc', 'woo-nmi-payment-gateway'); ?></h3>
        <p><?php _e('Network Merchants Inc - CC works by processing the Credit Card Payments on your site.', 'woo-nmi-payment-gateway'); ?></p>
        <table class="form-table">
            <?php
            if ($this->is_valid_for_use()) :

                // Generate the HTML For the settings form.
                $this->generate_settings_html();

            else :

                ?>
                <div class="inline error"><p>
                        <strong><?php _e('Gateway Disabled', 'woo-nmi-payment-gateway'); ?></strong>: <?php _e('NMI does not support your store currency.', 'woo-nmi-payment-gateway'); ?>
                    </p></div>
            <?php

            endif;
            ?>
        </table><!--/.form-table-->
        <?php
    } // End admin_options()

    /**
     *  Initialise Gateway Settings Form Fields
     */
    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-nmi-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Network Merchants Inc - CC', 'woo-nmi-payment-gateway'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'title' => array(
                'title' => __('Title', 'woo-nmi-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-nmi-payment-gateway'),
                'default' => __('Credit Card', 'woo-nmi-payment-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woo-nmi-payment-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woo-nmi-payment-gateway'),
                'default' => __("Pay via NMI; you can pay with your credit card.", 'woo-nmi-payment-gateway'),
                'desc_tip' => true,
            ),
            'username' => array(
                'title' => __('UserName', 'woo-nmi-payment-gateway'),
                'type' => 'text',
                'description' => __('Please enter your UserName; this is needed in order to take payment.', 'woo-nmi-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'password' => array(
                'title' => __('Password', 'woo-nmi-payment-gateway'),
                'type' => 'text',
                'description' => __('Please enter your Password; this is needed in order to take payment.', 'woo-nmi-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'descriptor' => array(
                'title' => __('Descriptor', 'woo-nmi-payment-gateway'),
                'type' => 'text',
                'description' => __('Set payment descriptor on supported processors.', 'woo-nmi-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'descriptor_url' => array(
                'title' => __('Descriptor URL', 'woo-nmi-payment-gateway'),
                'type' => 'text',
                'description' => __('Set payment descriptor url on supported processors.', 'woo-nmi-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),


            'transtype' => array(
                'title' => __('Transaction Type', 'woo-nmi-payment-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'sale' => 'Sale (Authorize and Capture)',
                    'auth' => 'Authorize Only'
                ),
                'description' => __('Select your Transaction Type.', 'woo-nmi-payment-gateway'),
                'default' => 'sale',
                'desc_tip' => true,
            ),
            'provider' => array(
                'title' => __('Payment Gateway', 'woo-nmi-payment-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'nmi' => 'Network Merchants Inc',
                ),
                'description' => __('Select your Merchant Account Provider.', 'woo-nmi-payment-gateway'),
                'default' => 'nmi',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug log', 'woo-nmi-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woo-nmi-payment-gateway'),
                'default' => 'no',
                'description' => sprintf(__('Log NMI requests, inside %s', 'woo-nmi-payment-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path(self::ID) . '</code>'),
            )
        );
    } // End init_form_fields()

    /**
     * There are no payment fields for nmi, but we want to show the description if set.
     **/
    function payment_fields()
    {
        $user = wp_get_current_user();
        $display_tokenization = $this->supports('tokenization') && is_checkout() && $this->saved_cards /*&& $user->ID*/
        ;

        if ($user->ID) {
            $user_email = get_user_meta($user->ID, 'billing_email', true);
            $user_email = $user_email ? $user_email : $user->user_email;
        } else {
            $user_email = '';
        }

        if (is_add_payment_method_page()) {
            $pay_button_text = __('Add Card', 'woo-nmi-payment-gateway');
        } else {
            $pay_button_text = '';
        }

        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        if ($display_tokenization) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
        wp_enqueue_script('wc-credit-card-form');
        //$this->form();
        echo '<fieldset id="woo-nmi-cc-form" class="wc-credit-card-form wc-payment-form">
      <p class="form-row form-row-wide">
        <label for="woo-nmi-card-number">' . esc_html__('Card Number', 'woo-nmi-payment-gateway') . ' <span class="required">*</span></label>
        <input id="woo-nmi-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" name="woo-nmi-card-number" />
      </p>
      <p class="form-row form-row-first">
        <label for="woo-nmi-card-expiry">' . esc_html__('Expiry (MM/YY)', 'woo-nmi-payment-gateway') . ' <span class="required">*</span></label>
        <input id="woo-nmi-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" maxlength="7" autocomplete="off" placeholder="MM / YY" name="woo-nmi-card-expiry" />
      </p>
      <p class="form-row form-row-last">
        <label for="woo-nmi-card-cvc">' . esc_html__('Card Code', 'woo-nmi-payment-gateway') . ' <span class="required">*</span></label>
        <input id="woo-nmi-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" maxlength="4" placeholder="CVC" name="woo-nmi-card-cvc" style="width:100px"  />
      </p>
      <div class="clear"></div>
    </fieldset>';

        if ($display_tokenization) {
            $this->save_payment_method_checkbox();
        }

    }

    public function validate_fields()
    {
        global $woocommerce;
        
        $token_id =  (isset($_POST['wc-woo-nmi-payment-token'])) ? wc_clean($_POST['wc-woo-nmi-payment-token']) : null;
        $card_number =  (isset($_POST['woo-nmi-card-number'])) ? wc_clean($_POST['woo-nmi-card-number']) : null;
        $card_expiry =  (isset($_POST['woo-nmi-card-expiry'])) ? wc_clean($_POST['woo-nmi-card-expiry']) : null;
        $card_cvc =  (isset($_POST['woo-nmi-card-cvc'])) ? wc_clean($_POST['woo-nmi-card-cvc']) : null;
        
        
        if ($token_id && 'new' !== $token_id) {
           // $token_id = wc_clean($_POST['wc-woo-nmi-payment-token']);
            $token = WC_Payment_Tokens::get($token_id);
            if ($token->get_user_id() !== get_current_user_id()) {
                // Optionally display a notice with `wc_add_notice`
                wc_add_notice(__('There was error processing payment using token please use the card details to continue the checkout.', 'woo-nmi-payment-gateway'), $notice_type = 'error');
            }
        } else {
            if (!$this->is_empty_credit_card($card_number)) {
                wc_add_notice('<strong>Credit Card Number</strong> ' . __('is a required field.', 'woo-nmi-payment-gateway'), 'error');
            } elseif (!$this->is_valid_credit_card($card_number)) {
                wc_add_notice('<strong>Credit Card Number</strong> ' . __('is not a valid credit card number.', 'woo-nmi-payment-gateway'), 'error');
            }

            if (!$this->is_empty_expire_date($card_expiry)) {
                wc_add_notice('<strong>Card Expiry Date</strong> ' . __('is a required field.', 'woo-nmi-payment-gateway'), 'error');
            } elseif (!$this->is_valid_expire_date($card_expiry)) {
                wc_add_notice('<strong>Card Expiry Date</strong> ' . __('is not a valid expiry date.', 'woo-nmi-payment-gateway'), 'error');
            }

            if (!$this->is_empty_ccv_nmber($card_cvc)) {
                wc_add_notice('<strong>CCV Number</strong> ' . __('is a required field.', 'woo-nmi-payment-gateway'), 'error');
            }

        }

    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id)
    {
        global $woocommerce;
        
        $payment_method =  (isset($_POST['wc-woo-nmi-new-payment-method'])) ? wc_clean($_POST['wc-woo-nmi-new-payment-method']) : null;
        $token_id =  (isset($_POST['wc-woo-nmi-payment-token'])) ? wc_clean($_POST['wc-woo-nmi-payment-token']) : null;
        $card_number =  (isset($_POST['woo-nmi-card-number'])) ? wc_clean($_POST['woo-nmi-card-number']) : null;
        $card_expiry =  (isset($_POST['woo-nmi-card-expiry'])) ? wc_clean($_POST['woo-nmi-card-expiry']) : null;
        $card_cvc =  (isset($_POST['woo-nmi-card-cvc'])) ? wc_clean($_POST['woo-nmi-card-cvc']) : null;
        $descriptor =  (!empty($_POST['descriptor']) && isset($_POST['descriptor'])) ? wc_clean($_POST['descriptor']) : null;
        $descriptor_url =  (!empty($_POST['descriptor_url']) && isset($_POST['descriptor_url'])) ? wc_clean($_POST['descriptor_url']) : null;
        $processor_id =  (!empty($_POST['processor_id']) && isset($_POST['processor_id'])) ? wc_clean($_POST['processor_id']) : null;

        $order = new WC_Order($order_id);

        $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($card_number));
        $ccexp_expiry = str_replace(' ', '', $card_expiry);
        $month = substr($ccexp_expiry, 0, 2);
        $year = substr($ccexp_expiry, 3, 4);
        $provider = $this->provider;
        $cardtype = $this->getCardType($credit_card);

        $nmi_adr = $this->$provider . '?';

        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'nmipay');

        if ($this->debug == 'yes') {
            $this->log->add(self::ID, $order_id . ' - CC Processing Started');
        }

        if ($payment_method && count($tokens) == 0) {

            $woo_nmi_payment_args['customer_vault'] = 'add_customer';
            $woo_nmi_payment_args['ccnumber'] = $credit_card;
            $woo_nmi_payment_args['cvv'] = $card_cvc;
            $woo_nmi_payment_args['ccexp'] = $month . '/' . $year;

            $last_four_digits = substr($woo_nmi_payment_args['ccnumber'], -4);

            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - Token Count: ' . count($tokens) . ' Token Param: ' . $payment_method);
            }

        } else if ($payment_method && count($tokens) > 0) {

            $token = WC_Payment_Tokens::get(get_user_meta(get_current_user_id(), 'nmi_cc_token_id', true));

            $woo_nmi_payment_args['customer_vault'] = 'update_customer';
            $woo_nmi_payment_args['customer_vault_id'] = $token->get_token();
            $woo_nmi_payment_args['ccnumber'] = $credit_card;
            $woo_nmi_payment_args['cvv'] = $card_cvc;
            $woo_nmi_payment_args['ccexp'] = $month . '/' . $year;

            $last_four_digits = substr($woo_nmi_payment_args['ccnumber'], -4);

            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - Custom Saved Token : ' . $token->get_id() . ' Token Param: ' . $payment_method);
            }

        } else if ($token_id && 'new' !== $token_id) {

            //$token_id = wc_clean($_POST['wc-woo-nmi-payment-token']);
            $token = WC_Payment_Tokens::get($token_id);
            $woo_nmi_payment_args['customer_vault'] = 'update_customer';
            $woo_nmi_payment_args['customer_vault_id'] = $token->get_token();

            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - Token : ' . $token_id . ' Token Param: ' . $payment_method);
            }

        } else {

            $woo_nmi_payment_args['ccnumber'] = $credit_card;
            $woo_nmi_payment_args['cvv'] = $card_cvc;
            $woo_nmi_payment_args['ccexp'] = $month . '/' . $year;

            $last_four_digits = substr($credit_card, -4);

            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - New Card with no Tokenisation ');
            }

        }

        if ($this->receipt) {
            $woo_nmi_payment_args['customer_receipt'] = $this->receipt;
        }


        // Processing subscription
        if (function_exists('wcs_order_contains_subscription') || function_exists('wcs_is_subscription')) {

            if (wcs_order_contains_subscription($order_id) || wcs_is_subscription($order_id)) {

                $woo_nmi_payment_args['type'] = $this->transtype;
                $woo_nmi_payment_args['payment'] = 'creditcard';
                $woo_nmi_payment_args['ipaddress'] = $_SERVER['REMOTE_ADDR'];
                $woo_nmi_payment_args['username'] = $this->username;
                $woo_nmi_payment_args['password'] = $this->password;
                $woo_nmi_payment_args['currency'] = get_woocommerce_currency();

                $woo_nmi_payment_args['orderid'] = $order_id . '-' . time();

                $woo_nmi_payment_args = $this->set_billing_and_shipping_address($woo_nmi_payment_args, $order);

                $woo_nmi_payment_args['email'] = $order->get_billing_email();

                $woo_nmi_payment_args['invoice'] = $order->order_key;

                $AmountInput = number_format($order->order_total, 2, '.', '');

                $woo_nmi_payment_args['amount'] = $AmountInput;

                if (in_array($order->billing_country, array('US', 'CA'))) {
                    $order->billing_phone = str_replace(array('( ', '-', ' ', ' )', '.'), '', $order->get_billing_phone());
                    $woo_nmi_payment_args['phone'] = $order->billing_phone;
                } else {
                    $woo_nmi_payment_args['phone'] = $order->get_billing_phone();
                }
                //var_dump($order->get_total_tax());die;
                // Tax
                $woo_nmi_payment_args['tax'] = $order->get_total_tax();

                // Cart Contents
                $item_loop = 0;
                if (sizeof($order->get_items()) > 0) {
                    foreach ($order->get_items() as $item) {
                        if ($item->get_quantity() > 0) {

                            $item_loop++;

                            $item_name = $item->get_name();
                            $item_meta = strip_tags(wc_display_item_meta($item, array(
                                'before' => "",
                                'separator' => ", ",
                                'after' => "",
                                'echo' => false,
                                'autop' => false,
                            )));
                            if ($item_meta) {
                                $item_name .= ' (' . $item_meta . ')';
                            }

                            $product = $item->get_product();

                            $sku = $product->get_sku();
                            if (empty($product->get_sku()) || $product->get_sku() == '') {
                                $sku = 'N/A';
                            }

                            $woo_nmi_payment_args['item_product_code_' . $item_loop] = $sku;
                            $woo_nmi_payment_args['item_tax_amount_' . $item_loop] = $item->get_total_tax();
                            $woo_nmi_payment_args['item_description_' . $item_loop] = $item_name;
                            $woo_nmi_payment_args['item_unit_cost_' . $item_loop] = $order->get_item_subtotal($item, false);
                            $woo_nmi_payment_args['item_quantity_' . $item_loop] = $item->get_quantity();
                            $item_total_amount = $order->get_item_subtotal($item, false) * $item->get_quantity();
                            $woo_nmi_payment_args['item_total_amount_' . $item_loop] = $item_total_amount;

                        }
                    }
                }

                // Discount
                if ($order->get_total_discount() > 0) {
                    $woo_nmi_payment_args['discount_amount'] = number_format($order->get_total_discount(), 2, '.', '');
                }

                // Shipping Cost item - nmi only allows shipping per item, we want to send shipping for the order
                if ($order->get_total_shipping() > 0) {
                    $woo_nmi_payment_args['shipping'] = number_format($order->get_total_shipping(), 2, '.', '');
                }


                $subscriptions = wcs_get_subscriptions_for_order($order);

                $subscription = array_pop($subscriptions);

                if (!empty($subscription)) {

                    $order_items = $order->get_items();

                    $unconverted_periods = array(
                        'billing_period' => $subscription->billing_period,
                        'trial_period' => $subscription->trial_period,
                    );

                    $converted_periods = array();

                    // Convert period strings into PayPay's format
                    foreach ($unconverted_periods as $key => $period) {
                        switch (strtolower($period)) {
                            case 'day':
                                $converted_periods[$key] = 'day';
                                break;
                            case 'week':
                                $converted_periods[$key] = 'week';
                                break;
                            case 'year':
                                $converted_periods[$key] = 'year';
                                break;
                            case 'month':
                            default:
                                $converted_periods[$key] = 'month';
                                break;
                        }
                    }

                    $sign_up_fee = $subscription->get_sign_up_fee();
                    $price_per_period = $subscription->get_total();
                    $subscription_interval = $subscription->billing_interval;
                    $start_timestamp = $subscription->get_time('start');
                    $trial_end_timestamp = $subscription->get_time('trial_end');
                    $next_payment_timestamp = $subscription->get_time('next_payment');

                    $is_synced_subscription = WC_Subscriptions_Synchroniser::subscription_contains_synced_product($subscription->id);

                    if ($is_synced_subscription) {
                        $length_from_timestamp = $next_payment_timestamp;
                    } elseif ($trial_end_timestamp > 0) {
                        $length_from_timestamp = $trial_end_timestamp;
                    } else {
                        $length_from_timestamp = $start_timestamp;
                    }

                    $subscription_length = wcs_estimate_periods_between($length_from_timestamp, $subscription->get_time('end'), $subscription->billing_period);

                    $subscription_installments = $subscription_length / $subscription_interval;

                    $initial_payment = ($is_payment_change) ? 0 : $order->get_total();

                    if ($initial_payment == '0.00') {
                        $initial_payment = '0.01';
                    }

                    if ($subscription_trial_length > 0) {

                        $trial_until = wcs_calculate_paypal_trial_periods_until($next_payment_timestamp);

                        $subscription_trial_length = $trial_until['first_trial_length'];
                        $converted_periods['trial_period'] = $trial_until['first_trial_period'];

                        $dateformat = "Ymd";
                        $todayDate = date($dateformat);
                        $startdate = date($dateformat, strtotime(date($dateformat, strtotime($todayDate)) . " +" . $subscription_trial_length . ' ' . $converted_periods['trial_period']));

                        $woo_nmi_payment_args['plan_payments'] = $subscription_installments;

                        $woo_nmi_payment_args['amount'] = $initial_payment;

                        $woo_nmi_payment_args['plan_amount'] = $price_per_period;

                        if ($converted_periods['billing_period'] == 'day') {
                            $woo_nmi_payment_args['day_frequency'] = $subscription_interval;
                        } else if ($converted_periods['billing_period'] == 'week') {
                            $woo_nmi_payment_args['day_frequency'] = $subscription_interval * 7;
                        } else if ($converted_periods['billing_period'] == 'year') {
                            $woo_nmi_payment_args['month_frequency'] = $subscription_interval * 12;
                            $timestamp = strtotime($startdate);
                            $day = date('d', $timestamp);
                            $woo_nmi_payment_args['day_of_month'] = $day;
                        } else {
                            $woo_nmi_payment_args['month_frequency'] = $subscription_interval;
                            $timestamp = strtotime($startdate);
                            $day = date('d', $timestamp);
                            $woo_nmi_payment_args['day_of_month'] = $day;
                        }

                    } else {
                        $dateformat = "Ymd";
                        $startdate = date($dateformat);

                        $woo_nmi_payment_args['plan_payments'] = $subscription_installments;

                        $woo_nmi_payment_args['amount'] = $initial_payment;

                        $woo_nmi_payment_args['plan_amount'] = $price_per_period;

                        if ($converted_periods['billing_period'] == 'day') {
                            $woo_nmi_payment_args['day_frequency'] = $subscription_interval;
                            $startdate = date($dateformat, strtotime(date($dateformat, strtotime($startdate)) . ' +1 day'));
                        } else if ($converted_periods['billing_period'] == 'week') {
                            $woo_nmi_payment_args['day_frequency'] = $subscription_interval * 7;
                            $startdate = date($dateformat, strtotime(date($dateformat, strtotime($startdate)) . ' +1 week'));
                        } else if ($converted_periods['billing_period'] == 'year') {
                            $woo_nmi_payment_args['month_frequency'] = $subscription_interval * 12;
                            $startdate = date($dateformat, strtotime(date($dateformat, strtotime($startdate)) . ' +1 year'));
                            $timestamp = strtotime($startdate);
                            $day = date('d', $timestamp);
                            $woo_nmi_payment_args['day_of_month'] = $day;
                        } else {
                            $woo_nmi_payment_args['month_frequency'] = $subscription_interval;
                            $timestamp = strtotime($startdate);
                            $day = date('d', $timestamp);
                            $woo_nmi_payment_args['day_of_month'] = $day;
                            $startdate = date($dateformat, strtotime(date($dateformat, strtotime($startdate)) . ' +1 month'));
                        }

                    }

                    $woo_nmi_payment_args['start_date'] = $startdate;

                    $woo_nmi_payment_args['recurring'] = 'add_subscription';

                    $woo_nmi_payment_args['billing_method'] = 'recurring';

                }

            } else {
                $woo_nmi_payment_args['type'] = $this->transtype;
                $woo_nmi_payment_args['payment'] = 'creditcard';
                $woo_nmi_payment_args['ipaddress'] = $_SERVER['REMOTE_ADDR'];
                $woo_nmi_payment_args['username'] = $this->username;
                $woo_nmi_payment_args['password'] = $this->password;
                $woo_nmi_payment_args['currency'] = get_woocommerce_currency();

                $woo_nmi_payment_args['orderid'] = $order_id . '-' . time();

                $woo_nmi_payment_args = $this->set_billing_and_shipping_address($woo_nmi_payment_args, $order);

                $woo_nmi_payment_args['email'] = $order->get_billing_email();

                $woo_nmi_payment_args['invoice'] = $order->order_key;

                $AmountInput = number_format($order->order_total, 2, '.', '');

                $woo_nmi_payment_args['amount'] = $AmountInput;

                if (in_array($order->billing_country, array('US', 'CA'))) {
                    $order->billing_phone = str_replace(array('( ', '-', ' ', ' )', '.'), '', $order->get_billing_phone());
                    $woo_nmi_payment_args['phone'] = $order->billing_phone;
                } else {
                    $woo_nmi_payment_args['phone'] = $order->get_billing_phone();
                }
                //var_dump($order->get_total_tax());die;
                // Tax
                $woo_nmi_payment_args['tax'] = $order->get_total_tax();

                // Cart Contents
                $item_loop = 0;
                if (sizeof($order->get_items()) > 0) {
                    foreach ($order->get_items() as $item) {
                        if ($item->get_quantity() > 0) {

                            $item_loop++;

                            $item_name = $item->get_name();
                            $item_meta = strip_tags(wc_display_item_meta($item, array(
                                'before' => "",
                                'separator' => ", ",
                                'after' => "",
                                'echo' => false,
                                'autop' => false,
                            )));
                            if ($item_meta) {
                                $item_name .= ' (' . $item_meta . ')';
                            }

                            $product = $item->get_product();
                            $sku = $product->get_sku();
                            if (empty($product->get_sku()) || $product->get_sku() == '') {
                                $sku = 'N/A';
                            }

                            $woo_nmi_payment_args['item_product_code_' . $item_loop] = $sku;
                            $woo_nmi_payment_args['item_tax_amount_' . $item_loop] = $item->get_total_tax();
                            $woo_nmi_payment_args['item_description_' . $item_loop] = $item_name;
                            $woo_nmi_payment_args['item_unit_cost_' . $item_loop] = $order->get_item_subtotal($item, false);
                            $woo_nmi_payment_args['item_quantity_' . $item_loop] = $item->get_quantity();
                            $item_total_amount = $order->get_item_subtotal($item, false) * $item->get_quantity();
                            $woo_nmi_payment_args['item_total_amount_' . $item_loop] = $item_total_amount;
                        }
                    }
                }

                // Discount
                if ($order->get_total_discount() > 0) {
                    $woo_nmi_payment_args['discount_amount'] = number_format($order->get_total_discount(), 2, '.', '');
                }

                // Shipping Cost item - nmi only allows shipping per item, we want to send shipping for the order
                if ($order->get_total_shipping() > 0) {
                    $woo_nmi_payment_args['shipping'] = number_format($order->get_total_shipping(), 2, '.', '');
                }
            }

            // Processing standard
        } else {
            $woo_nmi_payment_args['type'] = $this->transtype;
            $woo_nmi_payment_args['payment'] = 'creditcard';
            $woo_nmi_payment_args['ipaddress'] = $_SERVER['REMOTE_ADDR'];
            $woo_nmi_payment_args['username'] = $this->username;
            $woo_nmi_payment_args['password'] = $this->password;
            $woo_nmi_payment_args['currency'] = get_woocommerce_currency();

            $woo_nmi_payment_args['orderid'] = $order_id . '-' . time();

            $woo_nmi_payment_args = $this->set_billing_and_shipping_address($woo_nmi_payment_args, $order);

            $woo_nmi_payment_args['email'] = $order->get_billing_email();

            $woo_nmi_payment_args['invoice'] = $order->order_key;

            $AmountInput = number_format($order->order_total, 2, '.', '');

            $woo_nmi_payment_args['amount'] = $AmountInput;

            if (in_array($order->billing_country, array('US', 'CA'))) {
                $order->billing_phone = str_replace(array('( ', '-', ' ', ' )', '.'), '', $order->get_billing_phone());
                $woo_nmi_payment_args['phone'] = $order->billing_phone;
            } else {
                $woo_nmi_payment_args['phone'] = $order->get_billing_phone();
            }
            //var_dump($order->get_total_tax());die;
            // Tax
            $woo_nmi_payment_args['tax'] = $order->get_total_tax();

            // Cart Contents
            $item_loop = 0;
            if (sizeof($order->get_items()) > 0) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_quantity() > 0) {

                        $item_loop++;

                        $item_name = $item->get_name();
                        $item_meta = strip_tags(wc_display_item_meta($item, array(
                            'before' => "",
                            'separator' => ", ",
                            'after' => "",
                            'echo' => false,
                            'autop' => false,
                        )));
                        if ($item_meta) {
                            $item_name .= ' (' . $item_meta . ')';
                        }

                        $product = $item->get_product();
                        $sku = $product->get_sku();
                        if (empty($product->get_sku()) || $product->get_sku() == '') {
                            $sku = 'N/A';
                        }

                        $woo_nmi_payment_args['item_product_code_' . $item_loop] = $sku;
                        $woo_nmi_payment_args['item_tax_amount_' . $item_loop] = $item->get_total_tax();
                        $woo_nmi_payment_args['item_description_' . $item_loop] = $item_name;
                        $woo_nmi_payment_args['item_unit_cost_' . $item_loop] = $order->get_item_subtotal($item, false);
                        $woo_nmi_payment_args['item_quantity_' . $item_loop] = $item->get_quantity();
                        $item_total_amount = $order->get_item_subtotal($item, false) * $item->get_quantity();
                        $woo_nmi_payment_args['item_total_amount_' . $item_loop] = $item_total_amount;


                    }
                }
            }

            // Discount
            if ($order->get_total_discount() > 0) {
                $woo_nmi_payment_args['discount_amount'] = number_format($order->get_total_discount(), 2, '.', '');
            }

            // Shipping Cost item - nmi only allows shipping per item, we want to send shipping for the order
            if ($order->get_total_shipping() > 0) {
                $woo_nmi_payment_args['shipping'] = number_format($order->get_total_shipping(), 2, '.', '');
            }

        }

        if (!empty($woo_nmi_payment_args['discount_amount'])) {

            $item_loop++;

            $woo_nmi_payment_args['item_product_code_' . $item_loop] = 'N/A';
            $woo_nmi_payment_args['item_tax_amount_' . $item_loop] = 0;
            $woo_nmi_payment_args['item_description_' . $item_loop] = 'Discount';
            $woo_nmi_payment_args['item_unit_cost_' . $item_loop] = -$woo_nmi_payment_args['discount_amount'];
            $woo_nmi_payment_args['item_quantity_' . $item_loop] = 1;
            $woo_nmi_payment_args['item_total_amount_' . $item_loop] = -$woo_nmi_payment_args['discount_amount'];
        }


        $woo_nmi_payment_args['merchant_defined_field_1'] = get_bloginfo('name');

        if ($descriptor) {
            $woo_nmi_payment_args['descriptor'] = $descriptor;
        } else {
            $woo_nmi_payment_args['descriptor'] = $this->descriptor;
        }

        if ($descriptor_url) {
            $woo_nmi_payment_args['descriptor_url'] = $descriptor_url;
        } else {
            $woo_nmi_payment_args['descriptor_url'] = $this->descriptor_url;
        }

        if ($processor_id) {
            $woo_nmi_payment_args['processor_id'] = $processor_id;
        } else {
            $woo_nmi_payment_args['processor_id'] = $this->processor_id;
        }

        if (!empty($this->dup_seconds) && (int)$this->dup_seconds > 0) {
            $woo_nmi_payment_args['dup_seconds'] = $this->dup_seconds;
        }


        //wp_mail('sahil.bwp@gmail.com','NMI TEST', print_r($woo_nmi_payment_args,true) );

        $name_value_pairs = array();
        foreach ($woo_nmi_payment_args as $key => $value) {
            $name_value_pairs[] = $key . '=' . urlencode($value);
        }
        $gateway_values = implode('&', $name_value_pairs);

        $response = wp_remote_post($nmi_adr . $gateway_values, array('sslverify' => false, 'timeout' => 60));

        if ($this->debug == 'yes') {
            $woo_nmi_payment_args['ccnumber'] = "XXXX-XXXX-XXXX-" . $last_four_digits;
            $woo_nmi_payment_args['cvv'] = "XXX";
            $this->log->add(self::ID, $order_id . ' - NMI CC Order Request: ' . print_r($woo_nmi_payment_args, true));
        }

        if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
            parse_str($response['body'], $response);

            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - NMI CC Order Response: ' . print_r($response, true));
            }

            if ($response['response'] == '1') {
                // Payment completed
                $order->add_order_note(sprintf(__('The NMI Payment transaction is successful. The Transaction Id is %s. %s Card with last digits %s and expiry %s', 'woo-nmi-payment-gateway'), $response["transactionid"], $cardtype, $last_four_digits, $ccexp_expiry));
                $order->payment_complete($response["transactionid"]);

                if (isset($response['subscription_id'])) {
                    update_post_meta($order_id, 'NMI Subscriber ID', $response['subscription_id']);
                    WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
                }

                update_post_meta($order_id, 'NMI Transaction ID', $response['transactionid']);

                if (isset($response['customer_vault_id']) && $payment_method && count($tokens) == 0) {
                    // Build the token
                    $token = new WC_Payment_Token_CC();
                    $token->set_token($response['customer_vault_id']); // Token comes from payment processor
                    $token->set_gateway_id(self::ID);
                    $token->set_last4($last_four_digits);
                    $token->set_expiry_year('20' . substr($ccexp_expiry, 3, 7));
                    $token->set_expiry_month($month);
                    $token->set_card_type($cardtype);
                    $token->set_user_id(get_current_user_id());
                    // Save the new token to the database
                    $token->save();

                    update_user_meta(get_current_user_id(), 'nmi_cc_token_id', $token->get_id());

                    if ($this->debug == 'yes') {
                        $this->log->add(self::ID, $order_id . ' - New Token Saved : ' . $token->get_id());
                    }

                } else if (isset($response['customer_vault_id']) && $payment_method && count($tokens) > 0) {

                    $token_id = get_user_meta(get_current_user_id(), 'nmi_cc_token_id', true);
                    $token = WC_Payment_Tokens::get($token_id);
                    $token->set_token($response['customer_vault_id']); // Token comes from payment processor
                    $token->set_gateway_id(self::ID);
                    $token->set_last4($last_four_digits);
                    $token->set_expiry_year('20' . substr($ccexp_expiry, 3, 7));
                    $token->set_expiry_month($month);
                    $token->set_card_type($cardtype);
                    $token->set_user_id(get_current_user_id());
                    // Save the new token to the database
                    $token->save();

                    update_user_meta(get_current_user_id(), 'nmi_cc_token_id', $token->get_id());

                    if ($this->debug == 'yes') {
                        $this->log->add(self::ID, $order_id . ' - Old Token Deleted : ' . $token_id . ' - New Token Saved : ' . $token->get_id());
                    }

                }

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );

            } else {

                if (strpos($response['responsetext'], 'Invalid Customer Vault Id') !== false) {// Build the token
                    $token_id = get_user_meta(get_current_user_id(), 'nmi_cc_token_id', true);
                    if (!empty($token_id)) {
                        $token = new WC_Payment_Tokens();
                        $token->delete($token_id);
                    }
                    if ($this->debug == 'yes') {
                        $this->log->add(self::ID, $order_id . ' - Token Deleted : ' . $token_id . ' - because of error : ' . $response['responsetext']);
                    }
                    return array(
                        'result' => 'success',
                        'redirect' => $order->get_checkout_payment_url()
                    );
                } else {
                    $order->add_order_note(sprintf(__('Transaction Failed. %s-%s', 'woo-nmi-payment-gateway'), $response['response_code'], $response['responsetext']));
                    wc_add_notice(sprintf(__('Transaction Failed. %s-%s', 'woo-nmi-payment-gateway'), $response['response_code'], $response['responsetext']), $notice_type = 'error');
                    if ($this->debug == 'yes') {
                        $this->log->add(self::ID, $order_id . ' - ' . sprintf(__('Transaction Failed. %s-%s', 'woo-nmi-payment-gateway'), $response['response_code'], $response['responsetext']));
                    }
                }
            }
        } else {
            $order->add_order_note(sprintf(__('Gateway Error. Please Notify the Store Owner about this error. %s', 'woo-nmi-payment-gateway'), print_r($response, TRUE)));
            wc_add_notice(__('Gateway Error. Please Notify the Store Owner about this error. %s', 'woo-nmi-payment-gateway'), $notice_type = 'error');
            if ($this->debug == 'yes') {
                $this->log->add(self::ID, $order_id . ' - ' . sprintf(__('Gateway Error. Please Notify the Store Owner about this error. %s', 'woo-nmi-payment-gateway'), print_r($response, TRUE)));
            }
        }
    }

    /**
     * Set Billing and Shipping Address
     * @param    int $order_id
     * @param    array $woo_nmi_payment_args
     * @return array $woo_nmi_payment_args
     */
    function set_billing_and_shipping_address($woo_nmi_payment_args, $order)
    {

        $woo_nmi_payment_args['firstname'] = $order->get_billing_first_name();
        $woo_nmi_payment_args['lastname'] = $order->get_billing_last_name();
        $woo_nmi_payment_args['company'] = $order->get_billing_company();
        $woo_nmi_payment_args['address1'] = $order->get_billing_address_1();
        $woo_nmi_payment_args['address2'] = $order->get_billing_address_2();
        $woo_nmi_payment_args['city'] = $order->get_billing_city();
        $woo_nmi_payment_args['state'] = $order->get_billing_state();
        $woo_nmi_payment_args['zip'] = $order->get_billing_postcode();
        $woo_nmi_payment_args['country'] = $order->get_billing_country();

        $ship_name = $order->get_shipping_first_name();

        if (empty($ship_name)) {
            $woo_nmi_payment_args['shipping_firstname'] = $order->get_billing_first_name();
            $woo_nmi_payment_args['shipping_lastname'] = $order->get_billing_last_name();
            $woo_nmi_payment_args['shipping_company'] = $order->get_billing_company();
            $woo_nmi_payment_args['shipping_address1'] = $order->get_billing_address_1();
            $woo_nmi_payment_args['shipping_address2'] = $order->get_billing_address_2();
            $woo_nmi_payment_args['shipping_city'] = $order->get_billing_city();
            $woo_nmi_payment_args['shipping_state'] = $order->get_billing_state();
            $woo_nmi_payment_args['shipping_zip'] = $order->get_billing_postcode();
            $woo_nmi_payment_args['shipping_country'] = $order->get_billing_country();
        } else {
            $woo_nmi_payment_args['shipping_firstname'] = $order->get_shipping_first_name();
            $woo_nmi_payment_args['shipping_lastname'] = $order->get_shipping_last_name();
            $woo_nmi_payment_args['shipping_company'] = $order->get_shipping_company();
            $woo_nmi_payment_args['shipping_address1'] = $order->get_shipping_address_1();
            $woo_nmi_payment_args['shipping_address2'] = $order->get_shipping_address_2();
            $woo_nmi_payment_args['shipping_city'] = $order->get_shipping_city();
            $woo_nmi_payment_args['shipping_state'] = $order->get_shipping_state();
            $woo_nmi_payment_args['shipping_zip'] = $order->get_shipping_postcode();
            $woo_nmi_payment_args['shipping_country'] = $order->get_shipping_country();
        }

        return $woo_nmi_payment_args;

    }

    /**
     * Process a refund if supported
     * @param    int $order_id
     * @param    float $amount
     * @param    string $reason
     * @return    bool|wp_error True or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if (!$order || !$order->get_transaction_id()) {
            return false;
        }

        $provider = $this->provider;

        $nmi_adr = $this->$provider . '?';

        if (!is_null($amount)) {
            $woo_nmi_payment_args['type'] = 'refund';
            $woo_nmi_payment_args['username'] = $this->username;
            $woo_nmi_payment_args['password'] = $this->password;
            $woo_nmi_payment_args['transactionid'] = $order->get_transaction_id();
            $woo_nmi_payment_args['amount'] = number_format($amount, 2, '.', '');
        }

        $name_value_pairs = array();
        foreach ($woo_nmi_payment_args as $key => $value) {
            $name_value_pairs[] = $key . '=' . urlencode($value);
        }
        $gateway_values = implode('&', $name_value_pairs);

        $response = wp_remote_post($nmi_adr . $gateway_values, array('sslverify' => false, 'timeout' => 60));

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['body'])) {
            return new WP_Error('nmi-error', __('Empty NMI response.', 'woo-nmi-payment-gateway'));
        }

        parse_str($response['body'], $response);

        if ($response['response'] == '1') {
            $order->add_order_note(sprintf(__('Refund %s - Refund ID: %s', 'woo-nmi-payment-gateway'), $response['responsetext'], $response['transactionid']));
            return true;
        } else if ($response['response'] == '2') {
            $order->add_order_note(__('Transaction Declined', 'woo-nmi-payment-gateway'));
            return true;
        } else if ($response['response'] == '3') {
            $order->add_order_note(__('Error in transaction data or system error.', 'woo-nmi-payment-gateway'));
            return true;
        }

        return false;
    }

    /**
     * Add payment method via account screen.
     * We don't store the token locally, but to the NMI API.
     * @since 3.0.0
     */
    public function add_payment_method()
    {

        $card_number =  (isset($_POST['woo-nmi-card-number'])) ? wc_clean($_POST['woo-nmi-card-number']) : null;
        $card_expiry =  (isset($_POST['woo-nmi-card-expiry'])) ? wc_clean($_POST['woo-nmi-card-expiry']) : null;
        $card_cvc =  (isset($_POST['woo-nmi-card-cvc'])) ? wc_clean($_POST['woo-nmi-card-cvc']) : null;

        $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($card_number));
        $ccexp_expiry = str_replace(' ', '', $card_expiry);
        $month = substr($ccexp_expiry, 0, 2);
        $year = substr($ccexp_expiry, 3, 4);
        $provider = $this->provider;

        $cardtype = $this->getCardType($credit_card);

        $nmi_adr = $this->$provider . '?';

        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), self::ID);

        if (count($tokens) == 0) {

            $woo_nmi_payment_args['customer_vault'] = 'add_customer';
            $woo_nmi_payment_args['ccnumber'] = $credit_card;
            $woo_nmi_payment_args['cvv'] = $card_cvc;
            $woo_nmi_payment_args['ccexp'] = $month . '/' . $year;

            $last_four_digits = substr($woo_nmi_payment_args['ccnumber'], -4);


        } else if (count($tokens) > 0) {

            $token = WC_Payment_Tokens::get(get_user_meta(get_current_user_id(), 'nmi_cc_token_id', true));

            $woo_nmi_payment_args['customer_vault'] = 'update_customer';
            $woo_nmi_payment_args['customer_vault_id'] = $token->get_token();
            $woo_nmi_payment_args['ccnumber'] = $credit_card;
            $woo_nmi_payment_args['cvv'] = $card_cvc;
            $woo_nmi_payment_args['ccexp'] = $month . '/' . $year;

            $last_four_digits = substr($woo_nmi_payment_args['ccnumber'], -4);

        }
        $woo_nmi_payment_args['username'] = $this->username;
        $woo_nmi_payment_args['password'] = $this->password;

        $name_value_pairs = array();
        foreach ($woo_nmi_payment_args as $key => $value) {
            $name_value_pairs[] = $key . '=' . urlencode($value);
        }
        $gateway_values = implode('&', $name_value_pairs);

        $response = wp_remote_post($nmi_adr . $gateway_values, array('sslverify' => false, 'timeout' => 60));

        if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
            parse_str($response['body'], $response);
            if ($response['response'] == '1') {

                if (count($tokens) == 0) {
                    // Build the token
                    $token = new WC_Payment_Token_CC();
                    $token->set_token($response['customer_vault_id']); // Token comes from payment processor
                    $token->set_gateway_id(self::ID);
                    $token->set_last4($last_four_digits);
                    $token->set_expiry_year('20' . substr($ccexp_expiry, 3, 7));
                    $token->set_expiry_month($month);
                    $token->set_card_type($cardtype);
                    $token->set_user_id(get_current_user_id());
                    // Save the new token to the database
                    $token->save();

                    update_user_meta(get_current_user_id(), 'nmi_cc_token_id', $token->get_id());

                } else if (count($tokens) > 0) {

                    $token_id = get_user_meta(get_current_user_id(), 'nmi_cc_token_id', true);
                    $token = WC_Payment_Tokens::get($token_id);
                    $token->set_token($response['customer_vault_id']); // Token comes from payment processor
                    $token->set_gateway_id(self::ID);
                    $token->set_last4($last_four_digits);
                    $token->set_expiry_year('20' . substr($ccexp_expiry, 3, 7));
                    $token->set_expiry_month($month);
                    $token->set_card_type($cardtype);
                    $token->set_user_id(get_current_user_id());
                    // Save the new token to the database
                    $token->save();

                    update_user_meta(get_current_user_id(), 'nmi_cc_token_id', $token->get_id());

                }

                return array(
                    'result' => 'success',
                    'redirect' => wc_get_endpoint_url('payment-methods'),
                );

            } else {
                wc_add_notice(sprintf(__('Transaction Failed. %s-%s', 'woo-nmi-payment-gateway'), $response['response_code'], $response['responsetext']), $notice_type = 'error');
                return;
            }
        } else {
            wc_add_notice(__('PLease make sure you have entered the Credit Card details.' . print_r($response, TRUE), 'woo-nmi-payment-gateway'), $notice_type = 'error');
            return;
        }

    }

    /*
    * Check whether the card number number is empty
    */
    private function is_empty_credit_card($credit_card)
    {

        if (empty($credit_card)) {
            return false;
        }

        return true;
    }

    /*
    * Check whether the card number number is valid
    */
    private function is_valid_credit_card($credit_card)
    {

        $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($credit_card));

        $number = preg_replace('/[^0-9]+/', '', $credit_card);
        $strlen = strlen($number);
        $sum = 0;
        if ($strlen < 13) {
            return false;
        }
        for ($i = 0; $i < $strlen; $i++) {
            $digit = substr($number, $strlen - $i - 1, 1);

            if ($i % 2 == 1) {

                $sub_total = $digit * 2;

                if ($sub_total > 9) {
                    $sub_total = 1 + ($sub_total - 10);
                }
            } else {
                $sub_total = $digit;
            }
            $sum += $sub_total;
        }

        if ($sum > 0 AND $sum % 10 == 0) {
            return true;
        }

        return false;
    }

    /*
    * Check expiry date is empty
    */
    private function is_empty_expire_date($ccexp_expiry)
    {

        $ccexp_expiry = str_replace(array(' / ', '/', ' '), '', $ccexp_expiry);

        if (is_numeric($ccexp_expiry) && (strlen($ccexp_expiry) == 4)) {
            return true;
        }

        return false;
    }

    /*
    * Check expiry date is valid
    */
    private function is_valid_expire_date($ccexp_expiry)
    {

        $month = $year = '';
        $month = substr($ccexp_expiry, 0, 2);
        $year = substr($ccexp_expiry, 5, 7);
        $year = '20' . $year;

        if ($month > 12) {
            return false;
        }

        if (date("Y-m-d", strtotime($year . "-" . $month . "-01")) > date("Y-m-d")) {
            return true;
        }

        return false;
    }

    /*
    * Check whether the ccv number is empty
    */
    private function is_empty_ccv_nmber($ccv_number)
    {

        $length = strlen($ccv_number);

        return is_numeric($ccv_number) AND $length > 2 AND $length < 5;

    }

    private function getCardType($CCNumber)
    {

        $creditcardTypes = array(
            array('Name' => 'AMEX', 'cardLength' => array(15), 'cardPrefix' => array('34', '37')),
            array('Name' => 'Maestro', 'cardLength' => array(12, 13, 14, 15, 16, 17, 18, 19), 'cardPrefix' => array('5018', '5020', '5038', '6304', '6759', '6761', '6763')),
            array('Name' => 'MasterCard', 'cardLength' => array(16), 'cardPrefix' => array('51', '52', '53', '54', '55')),
            array('Name' => 'VISA', 'cardLength' => array(13, 16), 'cardPrefix' => array('4')),
            array('Name' => 'Discover', 'cardLength' => array(13, 16), 'cardPrefix' => array('6011', '65')),
            array('Name' => 'JCB', 'cardLength' => array(16), 'cardPrefix' => array('3528', '3529', '353', '354', '355', '356', '357', '358')),
            array('Name' => 'Diners', 'cardLength' => array(14), 'cardPrefix' => array('300', '301', '302', '303', '304', '305', '36')),
            array('Name' => 'Diners', 'cardLength' => array(16), 'cardPrefix' => array('54', '55')),
            array('Name' => 'Diners', 'cardLength' => array(14), 'cardPrefix' => array('300', '305'))
        );

        $CCNumber = trim($CCNumber);
        $type = 'VISA-SSL';
        foreach ($creditcardTypes as $card) {
            if (!in_array(strlen($CCNumber), $card['cardLength'])) {
                continue;
            }
            $prefixes = '/^(' . implode('|', $card['cardPrefix']) . ')/';
            if (preg_match($prefixes, $CCNumber) == 1) {
                $type = $card['Name'];
                break;
            }
        }
        return $type;
    }
}

