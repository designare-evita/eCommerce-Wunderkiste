<?php
/**
 * Plugin Name: WooCommerce Product Extras
 * Plugin URI: https://example.com/woo-product-extras
 * Description: Erweiterte Produktoptionen für WooCommerce - Preis auf Anfrage, Versandarten pro Produkt, Zubehör Tab & Image Resizer
 * Version: 1.1.0
 * Author: Ihr Name
 * Author URI: https://example.com
 * Text Domain: woo-product-extras
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Sicherheit: Direkten Zugriff verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin-Konstanten definieren
define( 'WPE_VERSION', '1.1.0' );
define( 'WPE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Hauptklasse des Plugins
 */
class WooCommerce_Product_Extras {

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Singleton-Pattern
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialisierung
     */
    public function init() {
        // Prüfen ob WooCommerce aktiv ist
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Textdomain laden
        load_plugin_textdomain( 'woo-product-extras', false, dirname( WPE_PLUGIN_BASENAME ) . '/languages' );

        // Admin-Seite laden
        if ( is_admin() ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-admin.php';
            new WPE_Admin();
        }

        // Module laden basierend auf Einstellungen
        $this->load_modules();
    }

    /**
     * Module laden
     */
    private function load_modules() {
        $options = get_option( 'wpe_options', array() );

        // Preis auf Anfrage Modul
        if ( ! empty( $options['enable_price_on_request'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-price-on-request.php';
            new WPE_Price_On_Request();
        }

        // Versandarten deaktivieren Modul
        if ( ! empty( $options['enable_disable_shipping'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-disable-shipping.php';
            new WPE_Disable_Shipping();
        }

        // Zubehör/Accessories Modul
        if ( ! empty( $options['enable_product_accessories'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-product-accessories.php';
            new WPE_Product_Accessories();
        }

        // Image Resizer Modul (kein WooCommerce benötigt)
        if ( ! empty( $options['enable_image_resizer'] ) ) {
            require_once WPE_PLUGIN_DIR . 'includes/class-wpe-image-resizer.php';
            new WPE_Image_Resizer();
        }
    }

    /**
     * WooCommerce nicht installiert Hinweis
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'WooCommerce Product Extras benötigt WooCommerce. Bitte installieren und aktivieren Sie WooCommerce.', 'woo-product-extras' ); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        // Standard-Optionen setzen
        $default_options = array(
            'enable_price_on_request'    => 0,
            'enable_disable_shipping'    => 0,
            'enable_product_accessories' => 0,
            'enable_image_resizer'       => 0,
            'price_on_request_css'       => "/* Preis auf Anfrage Styling */\n.price-on-request {\n    color: #e74c3c;\n    font-weight: bold;\n    font-size: 1.1em;\n}"
        );

        if ( ! get_option( 'wpe_options' ) ) {
            add_option( 'wpe_options', $default_options );
        }
    }

    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        // Hier können Aufräumarbeiten durchgeführt werden
    }
}

// Plugin starten
WooCommerce_Product_Extras::get_instance();
