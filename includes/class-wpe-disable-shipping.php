<?php
/**
 * Versandarten deaktivieren Modul
 *
 * Ermöglicht das Deaktivieren bestimmter Versandarten pro Produkt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Disable_Shipping {

    /**
     * Konstruktor
     */
    public function __construct() {
        // Backend: Meta-Box hinzufügen
        add_action( 'add_meta_boxes_product', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Frontend: Versandarten filtern
        add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), 100, 2 );
    }

    /**
     * Meta-Box zum Produkt-Editor hinzufügen (Seitenleiste)
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_disable_shipping_meta_box',
            __( 'Versandarten deaktivieren', 'woo-product-extras' ),
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
        wp_nonce_field( 'wpe_save_disabled_shipping', 'wpe_disabled_shipping_nonce' );

        // Gespeicherte Werte laden
        $saved_disabled_methods = get_post_meta( $post->ID, '_disabled_shipping_methods', true );
        if ( ! is_array( $saved_disabled_methods ) ) {
            $saved_disabled_methods = array();
        }

        // Alle aktiven Versandmethoden abrufen
        $all_shipping_methods = $this->get_all_shipping_methods();

        ?>
        <div class="wpe-meta-box-content">
            <p class="description">
                <?php esc_html_e( 'Wählen Sie die Versandarten, die für dieses Produkt NICHT verfügbar sein sollen:', 'woo-product-extras' ); ?>
            </p>

            <?php if ( empty( $all_shipping_methods ) ) : ?>
                <p><em><?php esc_html_e( 'Keine Versandarten gefunden. Bitte richten Sie zuerst Versandzonen in WooCommerce ein.', 'woo-product-extras' ); ?></em></p>
            <?php else : ?>
                <div class="wpe-shipping-methods-list" style="max-height: 200px; overflow-y: auto; margin-top: 10px;">
                    <?php foreach ( $all_shipping_methods as $method_id => $method_info ) : ?>
                        <label style="display: block; margin-bottom: 8px; padding: 5px; background: #f9f9f9; border-radius: 3px;">
                            <input type="checkbox" 
                                   name="disabled_shipping_methods[]" 
                                   value="<?php echo esc_attr( $method_id ); ?>"
                                   <?php checked( in_array( $method_id, $saved_disabled_methods, true ) ); ?>>
                            <strong><?php echo esc_html( $method_info['title'] ); ?></strong>
                            <br>
                            <small style="color: #666; margin-left: 22px;">
                                <?php echo esc_html( $method_info['zone'] ); ?>
                            </small>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Alle verfügbaren Versandmethoden abrufen
     */
    private function get_all_shipping_methods() {
        $all_methods = array();

        // Versandzonen abrufen
        $shipping_zones = WC_Shipping_Zones::get_zones();

        foreach ( $shipping_zones as $zone ) {
            $zone_name = $zone['zone_name'];
            
            foreach ( $zone['shipping_methods'] as $method ) {
                $rate_id = $method->get_rate_id();
                $all_methods[ $rate_id ] = array(
                    'title' => $method->get_title(),
                    'zone'  => $zone_name
                );
            }
        }

        // Auch "Rest der Welt" Zone berücksichtigen (Zone 0)
        $zone_zero = new WC_Shipping_Zone( 0 );
        $zone_zero_methods = $zone_zero->get_shipping_methods();
        
        foreach ( $zone_zero_methods as $method ) {
            $rate_id = $method->get_rate_id();
            $all_methods[ $rate_id ] = array(
                'title' => $method->get_title(),
                'zone'  => __( 'Rest der Welt', 'woo-product-extras' )
            );
        }

        return $all_methods;
    }

    /**
     * Meta-Box Daten speichern
     */
    public function save_meta_box( $post_id ) {
        // Sicherheitsprüfungen
        if ( ! isset( $_POST['wpe_disabled_shipping_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wpe_disabled_shipping_nonce'], 'wpe_save_disabled_shipping' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        // Ausgewählte Versandmethoden speichern
        if ( isset( $_POST['disabled_shipping_methods'] ) && is_array( $_POST['disabled_shipping_methods'] ) ) {
            $disabled_methods = array_map( 'sanitize_text_field', $_POST['disabled_shipping_methods'] );
            update_post_meta( $post_id, '_disabled_shipping_methods', $disabled_methods );
        } else {
            // Wenn keine ausgewählt, Meta-Feld löschen
            delete_post_meta( $post_id, '_disabled_shipping_methods' );
        }
    }

    /**
     * Versandarten im Checkout basierend auf Produkteinstellungen filtern
     */
    public function filter_shipping_methods( $rates, $package ) {
        // Array für alle zu deaktivierenden Methoden
        $methods_to_disable = array();

        // Durch alle Produkte im Warenkorb loopen
        foreach ( $package['contents'] as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $disabled_for_product = get_post_meta( $product_id, '_disabled_shipping_methods', true );

            if ( is_array( $disabled_for_product ) && ! empty( $disabled_for_product ) ) {
                $methods_to_disable = array_merge( $methods_to_disable, $disabled_for_product );
            }
        }

        // Duplikate entfernen
        $methods_to_disable = array_unique( $methods_to_disable );

        // Markierte Versandarten aus der Liste entfernen
        foreach ( $methods_to_disable as $method_id ) {
            if ( isset( $rates[ $method_id ] ) ) {
                unset( $rates[ $method_id ] );
            }
        }

        return $rates;
    }
}
