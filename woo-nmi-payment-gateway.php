<?php
/**
 * @wordpress-plugin
 * Plugin Name:       NMI Payment Gateway for WooCommerce
 * Plugin URI:        http://egooty.com/wordpress-plugins/
 * Description:       WooCommerce Plugin for accepting payment through Network Merchants Inc (NMI) Payment Gateway.
 * Version:           1.0.3
 * Author:            Mudassar Ali <sahil_bwp@yahoo.com>
 * Author URI:        http://egooty.com/
 * Contributors:      sahilbabu

 * Requires at least: 4.5
 * Tested up to:      5.1.0
 * WC requires at least: 3.0.0
 * WC tested up to:    3.5.3
 *
 * License:           GPLv3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * @copyright         Copyright 2019 eGooty
 *
 * Text Domain:       woo-nmi-payment-gateway
 * Domain Path:       /languages/
 *
 * @link              http://egooty.com/wordpress-plugins/
 * @since             1.0.0
 * @package           WooNmiPaymentGateway
*/

/*
    This file is part of NMI Payment Gateway for WooCommerce Plugin.

    NMI Payment Gateway for WooCommerce Plugin is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    NMI Payment Gateway for WooCommerce Plugin is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with NMI Payment Gateway for WooCommerce Plugin. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Egooty\Plugins\NMI;


/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

// spl_autoload_register(__NAMESPACE__ . '\woo_nmi_payment_gateway_autoload');

/**
 * Define constants
 *
 * Currently plugin version.
 *
 */
define('WOO_NMI_PAYMENT_GATEWAY_VERSION', '1.0.1' );


class WooNmiPaymentGateway
{

    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = NULL;

    /**
     * URL to this plugin's directory.
     *
     * @type string
     */
    public $plugin_url = '';

    /**
     * Path to this plugin's directory.
     *
     * @type string
     */
    public $plugin_path = '';

    /**
     * Plugin Base Name
     *
     * @var string
     */
    public $plugin_basename = '';

    /**
     * Constructor. Intentionally left empty and public.
     *
     * @see plugin_setup()
     * @since 2012.09.12
     */
    public function __construct() {}


    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @since   2012.09.13
     * @return  object of this class
     */
    public static function get_instance(){
        NULL === self::$instance and self::$instance = new self;
        return self::$instance;
    }

    /**
     * Used for regular plugin work.
     *
     * @wp-hook plugins_loaded
     * @since   2012.09.10
     * @return  void
     */
    public static function init(){

        if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ||
            ! class_exists( 'WC_Payment_Gateway_eCheck' ) ) {
            return;
        }

        $this->plugin_url       = plugins_url( '/', __FILE__ );
        $this->plugin_path      = plugin_dir_path( __FILE__ );
        $this->plugin_basename  = plugin_basename( __FILE__ );

        $this->load_language( 'woo-nmi-payment-gateway' );

        /**
         *  Add files, classes
         */
        add_action('wp_loaded', array($this, 'load'));

        /**
         * Plugin Action links
         */
        add_action( 'plugin_action_links_' . $this->plugin_basename, array($this, 'add_action_links'));


        /**
         * Add the gateway to WooCommerce
         **/
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway') );

    }

    /**
     *  Load classes and files etc
     */
    public function load(){
        require_once($this->plugin_path. 'includes/NMI_Payment_Gateway_CC.php');

    }

    /**
     * Add the gateway to WooCommerce
     **/
    public function add_gateway( $methods ) {
        $methods[] = 'NMI_Payment_Gateway_CC';
        return $methods;
    }

    /**
     * Loads translation file.
     *
     * Accessible to other classes to load different language files (admin and
     * front-end for example).
     *
     * @wp-hook init
     * @param   string $domain
     * @since   2012.09.11
     * @return  void
     */
    public function load_language( $domain ){
        load_plugin_textdomain(
            $domain,
            FALSE,
            $this->plugin_path . 'languages'
        );
    }

    /**
     * @param $links
     * @return array
     */
    public function add_action_links($links){
        $plugin_links = array(
            '<a href="' . esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout' ) ) . '">' . __( 'Set up', 'woo-nmi-payment-gateway' ) . '</a>',
            '<a href="' . esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=nmipay' ) ) . '">' . __( 'Manage', 'woo-nmi-payment-gateway' ) . '</a>'
        );
        $links = array_merge( $plugin_links, $links );
        return $links;
    }


}

//add_action('plugins_loaded', array('WooNmiPaymentGateway', 'init'), 0);
add_action('plugins_loaded', array ( WooNmiPaymentGateway::get_instance(), 'init' ),0);


