<?php
/**
 * Plugin Name: eCommerce Wunderkiste
 * Plugin URI: https://designare.at
 * Description: Extended product options for WooCommerce - Price on Request, Shipping Methods per Product, Accessories Tab, Image Resizer, Order Recovery & Tiered Pricing
 * Version: 1.1.0
 * Author: Michael Kanda
 * Author URI: https://designare.at
 * Text Domain: ecommerce-wunderkiste
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Define plugin constants
define( 'WPE_VERSION', '1.1.0' );
define( 'WPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class WooCommerce_Product_Extras {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialization
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Load admin page
        if ( is_admin() ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-admin.php';
            new WPE_Admin();
        }

        // Load modules based on settings
        $this->load_modules();
    }

    /**
     * Load modules
     */
    private function load_modules() {
        $options = get_option( 'wpe_options', array() );

        // Price on Request module
        if ( ! empty( $options['enable_price_on_request'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-price-on-request.php';
            new WPE_Price_On_Request();
        }

        // Disable Shipping module
        if ( ! empty( $options['enable_disable_shipping'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-disable-shipping.php';
            new WPE_Disable_Shipping();
        }

        // Accessories module
        if ( ! empty( $options['enable_product_accessories'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-product-accessories.php';
            new WPE_Product_Accessories();
        }

        // Image Resizer module (no WooCommerce required)
        if ( ! empty( $options['enable_image_resizer'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-image-resizer.php';
            new WPE_Image_Resizer();
        }

        // Order Recovery module
        if ( ! empty( $options['enable_order_recovery'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-order-recovery.php';
            new WPE_Order_Recovery();
        }

        // Tiered Pricing module
        if ( ! empty( $options['enable_tiered_pricing'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-tiered-pricing.php';
            new WPE_Tiered_Pricing();
        }
    }

    /**
     * WooCommerce not installed notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'eCommerce Wunderkiste requires WooCommerce. Please install and activate WooCommerce.', 'ecommerce-wunderkiste' ); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'enable_price_on_request'    => 0,
            'enable_disable_shipping'    => 0,
            'enable_product_accessories' => 0,
            'enable_image_resizer'       => 0,
            'enable_order_recovery'      => 0,
            'enable_tiered_pricing'      => 0,
            'price_on_request_css'       => "/* Price on Request Styling */\n.price-on-request {\n    color: #e74c3c;\n    font-weight: bold;\n    font-size: 1.1em;\n}"
        );

        if ( ! get_option( 'wpe_options' ) ) {
            add_option( 'wpe_options', $default_options );
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup can be done here
    }
}

// Start plugin
WooCommerce_Product_Extras::get_instance();
