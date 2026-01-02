<?php
/**
 * Preis auf Anfrage Modul
 *
 * Ermöglicht das Verstecken von Preisen und Anzeigen von "Preis auf Anfrage"
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Price_On_Request {

    /**
     * Konstruktor
     */
    public function __construct() {
        // Backend: Meta-Box hinzufügen
        add_action( 'add_meta_boxes_product', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Frontend: Preis und Warenkorb anpassen
        add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_display' ), 10, 2 );
        add_filter( 'woocommerce_is_purchasable', array( $this, 'hide_add_to_cart' ), 10, 2 );
        add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_loop_add_to_cart' ), 10, 2 );

        // Custom CSS im Frontend ausgeben
        add_action( 'wp_head', array( $this, 'output_custom_css' ) );
    }

    /**
     * Meta-Box zum Produkt-Editor hinzufügen (Seitenleiste)
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_price_on_request_meta_box',
            __( 'Preis auf Anfrage', 'woo-product-extras' ),
            array( $this, 'render_meta_box' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Inhalt der Meta-Box rendern
     */
    public function render_meta_box( $post ) {
        // Nonce-Feld für Sicherheit
        wp_nonce_field( 'wpe_save_price_on_request', 'wpe_price_on_request_nonce' );

        $is_price_on_request = get_post_meta( $post->ID, '_price_on_request', true );
        $custom_text = get_post_meta( $post->ID, '_price_on_request_text', true );
        
        if ( empty( $custom_text ) ) {
            $custom_text = __( 'Preis auf Anfrage', 'woo-product-extras' );
        }
        ?>
        <div class="wpe-meta-box-content">
            <p>
                <label>
                    <input type="checkbox" 
                           name="_price_on_request" 
                           value="yes" 
                           <?php checked( 'yes', $is_price_on_request ); ?>>
                    <?php esc_html_e( 'Aktivieren', 'woo-product-extras' ); ?>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e( 'Versteckt den Preis und den Warenkorb-Button.', 'woo-product-extras' ); ?>
            </p>
            
            <p style="margin-top: 15px;">
                <label for="wpe_price_on_request_text">
                    <strong><?php esc_html_e( 'Anzeigetext:', 'woo-product-extras' ); ?></strong>
                </label>
                <input type="text" 
                       name="_price_on_request_text" 
                       id="wpe_price_on_request_text"
                       value="<?php echo esc_attr( $custom_text ); ?>"
                       class="widefat"
                       placeholder="<?php esc_attr_e( 'Preis auf Anfrage', 'woo-product-extras' ); ?>">
            </p>
            <p class="description">
                <?php esc_html_e( 'Text der statt dem Preis angezeigt wird.', 'woo-product-extras' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Meta-Box Daten speichern
     */
    public function save_meta_box( $post_id ) {
        // Sicherheitsprüfungen
        if ( ! isset( $_POST['wpe_price_on_request_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wpe_price_on_request_nonce'], 'wpe_save_price_on_request' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        // Werte speichern
        $price_on_request = isset( $_POST['_price_on_request'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_price_on_request', $price_on_request );

        // Custom Text speichern
        if ( isset( $_POST['_price_on_request_text'] ) ) {
            $custom_text = sanitize_text_field( $_POST['_price_on_request_text'] );
            update_post_meta( $post_id, '_price_on_request_text', $custom_text );
        }
    }

    /**
     * Preis im Frontend ersetzen
     */
    public function change_price_display( $price, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            $custom_text = get_post_meta( $product->get_id(), '_price_on_request_text', true );
            
            if ( empty( $custom_text ) ) {
                $custom_text = __( 'Preis auf Anfrage', 'woo-product-extras' );
            }

            return '<span class="price-on-request">' . esc_html( $custom_text ) . '</span>';
        }
        return $price;
    }

    /**
     * Warenkorb-Button auf Produktseite entfernen
     */
    public function hide_add_to_cart( $is_purchasable, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            return false;
        }
        return $is_purchasable;
    }

    /**
     * Warenkorb-Button in Shop/Archiv-Seiten entfernen
     */
    public function remove_loop_add_to_cart( $html, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            return '';
        }
        return $html;
    }

    /**
     * Custom CSS im Frontend ausgeben
     */
    public function output_custom_css() {
        $options = get_option( 'wpe_options', array() );
        
        if ( ! empty( $options['price_on_request_css'] ) ) {
            echo "\n<style id=\"wpe-price-on-request-css\">\n";
            echo wp_strip_all_tags( $options['price_on_request_css'] );
            echo "\n</style>\n";
        } else {
            // Standard-CSS falls nichts definiert
            echo "\n<style id=\"wpe-price-on-request-css\">\n";
            echo ".price-on-request { color: #e74c3c; font-weight: bold; }\n";
            echo "</style>\n";
        }
    }
}
