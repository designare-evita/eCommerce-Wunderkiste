<?php
/**
 * Admin-Klasse für WooCommerce Product Extras
 *
 * Verwaltet die Einstellungsseite und Plugin-Optionen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Admin {

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'plugin_action_links_' . WPE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Extras', 'woo-product-extras' ),
            __( 'Product Extras', 'woo-product-extras' ),
            'manage_woocommerce',
            'woo-product-extras',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Einstellungen registrieren
     */
    public function register_settings() {
        register_setting(
            'wpe_settings_group',
            'wpe_options',
            array( $this, 'sanitize_options' )
        );

        // Hauptbereich
        add_settings_section(
            'wpe_main_section',
            __( 'Module & Einstellungen', 'woo-product-extras' ),
            array( $this, 'main_section_callback' ),
            'woo-product-extras'
        );

        // NEU: Sprachauswahl
        add_settings_field(
            'plugin_language',
            __( 'Sprache für E-Mails', 'woo-product-extras' ),
            array( $this, 'select_language_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'plugin_language',
                'description' => __( 'Wählen Sie die Sprache für die Order Recovery E-Mails.', 'woo-product-extras' )
            )
        );

        // Preis auf Anfrage aktivieren
        add_settings_field(
            'enable_price_on_request',
            __( 'Preis auf Anfrage', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_price_on_request',
                'description' => __( 'Ermöglicht es, bei einzelnen Produkten "Preis auf Anfrage" anzuzeigen statt des Preises.', 'woo-product-extras' )
            )
        );

        // Versandarten deaktivieren aktivieren
        add_settings_field(
            'enable_disable_shipping',
            __( 'Versandarten deaktivieren', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_disable_shipping',
                'description' => __( 'Ermöglicht es, bestimmte Versandarten pro Produkt zu deaktivieren.', 'woo-product-extras' )
            )
        );

        // Zubehör/Accessories aktivieren
        add_settings_field(
            'enable_product_accessories',
            __( 'Zubehör Tab', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_product_accessories',
                'description' => __( 'Fügt einen "Zubehör" Tab bei Produkten hinzu, um passende Produkte zu verknüpfen.', 'woo-product-extras' )
            )
        );

        // Image Resizer aktivieren
        add_settings_field(
            'enable_image_resizer',
            __( 'Image Resizer 800px/1200px', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_image_resizer',
                'description' => __( 'Fügt einen Button in der Mediathek hinzu, um Bilder auf max. 800px oder 1200px zu skalieren.', 'woo-product-extras' )
            )
        );

        // Order Recovery aktivieren
        add_settings_field(
            'enable_order_recovery',
            __( 'Order Recovery (Zahlungsabbruch)', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_order_recovery',
                'description' => __( 'Aktiviert Szenarien A, B und C: Mail nach 1h bei Abbruch, Sofort-Mail bei Fehler, Manueller Button.', 'woo-product-extras' )
            )
        );

        // Staffelpreise aktivieren
        add_settings_field(
            'enable_tiered_pricing',
            __( 'Staffelpreise', 'woo-product-extras' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_tiered_pricing',
                'description' => __( 'Aktiviert Preisstaffelungen pro Produkt (Mengenrabatte).', 'woo-product-extras' )
            )
        );

        // CSS Bereich
        add_settings_section(
            'wpe_css_section',
            __( 'Custom CSS', 'woo-product-extras' ),
            array( $this, 'css_section_callback' ),
            'woo-product-extras'
        );

        // Custom CSS für Preis auf Anfrage
        add_settings_field(
            'price_on_request_css',
            __( 'Preis auf Anfrage CSS', 'woo-product-extras' ),
            array( $this, 'textarea_field_callback' ),
            'woo-product-extras',
            'wpe_css_section',
            array(
                'id'          => 'price_on_request_css',
                'description' => __( 'CSS für die "Preis auf Anfrage" Anzeige. Nutzen Sie die Klasse .price-on-request', 'woo-product-extras' ),
                'rows'        => 10
            )
        );
    }

    /**
     * Hauptbereich Beschreibung
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__( 'Aktivieren Sie die gewünschten Module und Einstellungen.', 'woo-product-extras' ) . '</p>';
    }

    /**
     * CSS Bereich Beschreibung
     */
    public function css_section_callback() {
        echo '<p>' . esc_html__( 'Passen Sie das Aussehen der Funktionen mit eigenem CSS an.', 'woo-product-extras' ) . '</p>';
    }

    /**
     * Checkbox-Feld rendern
     */
    public function checkbox_field_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : 0;
        ?>
        <label>
            <input type="checkbox" 
                   name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]" 
                   value="1" 
                   <?php checked( 1, $value ); ?>>
            <?php echo esc_html( $args['description'] ); ?>
        </label>
        <?php
    }

    /**
     * NEU: Auswahlfeld für Sprache rendern
     */
    public function select_language_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        // Standard: 'de' (Deutsch)
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : 'de';
        ?>
        <select name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]">
            <option value="de" <?php selected( 'de', $value ); ?>>Deutsch</option>
            <option value="en" <?php selected( 'en', $value ); ?>>English</option>
        </select>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Textarea-Feld rendern
     */
    public function textarea_field_callback( $args ) {
        $options = get_option( 'wpe_options', array() );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
        $rows    = isset( $args['rows'] ) ? $args['rows'] : 5;
        ?>
        <textarea name="wpe_options[<?php echo esc_attr( $args['id'] ); ?>]" 
                  id="<?php echo esc_attr( $args['id'] ); ?>"
                  rows="<?php echo esc_attr( $rows ); ?>"
                  class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php
    }

    /**
     * Optionen bereinigen
     */
    public function sanitize_options( $input ) {
        $sanitized = array();

        // Checkboxen
        $sanitized['enable_price_on_request']    = ! empty( $input['enable_price_on_request'] ) ? 1 : 0;
        $sanitized['enable_disable_shipping']    = ! empty( $input['enable_disable_shipping'] ) ? 1 : 0;
        $sanitized['enable_product_accessories'] = ! empty( $input['enable_product_accessories'] ) ? 1 : 0;
        $sanitized['enable_image_resizer']       = ! empty( $input['enable_image_resizer'] ) ? 1 : 0;
        $sanitized['enable_order_recovery']      = ! empty( $input['enable_order_recovery'] ) ? 1 : 0;
        $sanitized['enable_tiered_pricing']      = ! empty( $input['enable_tiered_pricing'] ) ? 1 : 0;

        // NEU: Sprache
        $sanitized['plugin_language'] = ( isset( $input['plugin_language'] ) && $input['plugin_language'] === 'en' ) ? 'en' : 'de';

        // CSS
        if ( isset( $input['price_on_request_css'] ) ) {
            $sanitized['price_on_request_css'] = wp_strip_all_tags( $input['price_on_request_css'] );
        }

        return $sanitized;
    }

    /**
     * Admin-Scripts und Styles laden
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_woo-product-extras' !== $hook ) {
            return;
        }

        wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
        wp_enqueue_script( 'wp-theme-plugin-editor' );
        wp_enqueue_style( 'wp-codemirror' );

        wp_add_inline_script( 'wp-theme-plugin-editor', "
            jQuery(document).ready(function($) {
                if ($('#price_on_request_css').length) {
                    wp.codeEditor.initialize($('#price_on_request_css'), {
                        codemirror: {
                            mode: 'css',
                            lineNumbers: true,
                            indentUnit: 4,
                            tabSize: 4
                        }
                    });
                }
            });
        " );
    }

    /**
     * Settings-Link
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=woo-product-extras' ) . '">' . __( 'Einstellungen', 'woo-product-extras' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors(); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'wpe_settings_group' );
                do_settings_sections( 'woo-product-extras' );
                submit_button( __( 'Einstellungen speichern', 'woo-product-extras' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Anleitung', 'woo-product-extras' ); ?></h2>
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h3><?php esc_html_e( 'Sprache', 'woo-product-extras' ); ?></h3>
                <p><?php esc_html_e( 'Wählen Sie oben "Deutsch" oder "English", um die Sprache der automatischen E-Mails zu ändern.', 'woo-product-extras' ); ?></p>
            </div>
        </div>
        <?php
    }
}
