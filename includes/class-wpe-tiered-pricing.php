<?php
/**
 * Staffelpreise Modul
 *
 * Ermöglicht mengenbasierte Preise pro Produkt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Tiered_Pricing {

    public function __construct() {
        // Backend: Meta-Box
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Frontend: Preistabelle anzeigen
        add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_pricing_table' ), 20 );

        // Frontend: Preis im Warenkorb berechnen
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_tiered_price' ), 10, 1 );

        // Frontend: CSS
        add_action( 'wp_head', array( $this, 'output_css' ) );
    }

    /**
     * Meta-Box hinzufügen
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_tiered_pricing_box',
            __( '💰 Staffelpreise', 'woo-product-extras' ),
            array( $this, 'render_meta_box' ),
            'product',
            'normal', // Größere Box unter dem Editor
            'high'
        );
    }

    /**
     * Meta-Box Inhalt rendern
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'wpe_tiered_pricing_save', 'wpe_tiered_pricing_nonce' );
        
        $rules = get_post_meta( $post->ID, '_wpe_tiered_pricing_rules', true );
        if ( ! is_array( $rules ) ) {
            $rules = array();
        }
        ?>
        <div class="wpe-tiered-pricing-wrapper">
            <p class="description"><?php esc_html_e( 'Definieren Sie hier Preisstaffeln. Lassen Sie "Max Stück" leer für "und mehr".', 'woo-product-extras' ); ?></p>
            
            <table class="widefat" id="wpe-pricing-rules-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Min Stück', 'woo-product-extras' ); ?></th>
                        <th><?php esc_html_e( 'Max Stück', 'woo-product-extras' ); ?></th>
                        <th><?php esc_html_e( 'Stückpreis (€)', 'woo-product-extras' ); ?></th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $rules ) ) : foreach ( $rules as $index => $rule ) : ?>
                        <tr>
                            <td><input type="number" name="wpe_tier_min[]" value="<?php echo esc_attr( $rule['min'] ); ?>" class="widefat" min="1" step="1"></td>
                            <td><input type="number" name="wpe_tier_max[]" value="<?php echo esc_attr( $rule['max'] ); ?>" class="widefat" min="1" step="1" placeholder="∞"></td>
                            <td><input type="text" name="wpe_tier_price[]" value="<?php echo esc_attr( wc_format_localized_price( $rule['price'] ) ); ?>" class="widefat wc_input_price"></td>
                            <td><span class="button wpe-remove-row">×</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button button-primary" id="wpe-add-tier-row"><?php esc_html_e( '+ Staffel hinzufügen', 'woo-product-extras' ); ?></button>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <script>
            jQuery(document).ready(function($) {
                $('#wpe-add-tier-row').on('click', function() {
                    var row = '<tr>' +
                        '<td><input type="number" name="wpe_tier_min[]" class="widefat" min="1" step="1"></td>' +
                        '<td><input type="number" name="wpe_tier_max[]" class="widefat" min="1" step="1" placeholder="∞"></td>' +
                        '<td><input type="text" name="wpe_tier_price[]" class="widefat wc_input_price"></td>' +
                        '<td><span class="button wpe-remove-row">×</span></td>' +
                        '</tr>';
                    $('#wpe-pricing-rules-table tbody').append(row);
                });

                $(document).on('click', '.wpe-remove-row', function() {
                    $(this).closest('tr').remove();
                });
            });
            </script>

            <style>
                #wpe-pricing-rules-table td { vertical-align: middle; }
                .wpe-remove-row { color: #a00; border-color: #a00; }
                .wpe-remove-row:hover { background: #a00; color: #fff; }
            </style>
        </div>
        <?php
    }

    /**
     * Daten speichern
     */
    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['wpe_tiered_pricing_nonce'] ) || ! wp_verify_nonce( $_POST['wpe_tiered_pricing_nonce'], 'wpe_tiered_pricing_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) return;

        $rules = array();
        
        if ( isset( $_POST['wpe_tier_min'] ) ) {
            $mins = $_POST['wpe_tier_min'];
            $maxs = $_POST['wpe_tier_max'];
            $prices = $_POST['wpe_tier_price'];

            for ( $i = 0; $i < count( $mins ); $i++ ) {
                if ( empty( $mins[$i] ) || empty( $prices[$i] ) ) continue;

                $rules[] = array(
                    'min'   => absint( $mins[$i] ),
                    'max'   => ! empty( $maxs[$i] ) ? absint( $maxs[$i] ) : '',
                    'price' => wc_format_decimal( $prices[$i] ), // WC Hilfsfunktion für Komma/Punkt
                );
            }

            // Sortieren nach Menge (Min aufsteigend)
            usort( $rules, function($a, $b) {
                return $a['min'] - $b['min'];
            });
        }

        update_post_meta( $post_id, '_wpe_tiered_pricing_rules', $rules );
    }

    /**
     * Frontend: Tabelle anzeigen
     */
    public function display_pricing_table() {
        global $product;
        $rules = get_post_meta( $product->get_id(), '_wpe_tiered_pricing_rules', true );

        if ( empty( $rules ) || ! is_array( $rules ) ) return;

        echo '<div class="wpe-tiered-pricing-table-container">';
        echo '<h4>' . esc_html__( 'Staffelpreise', 'woo-product-extras' ) . '</h4>';
        echo '<table class="wpe-tiered-pricing-table">';
        echo '<thead><tr><th>' . esc_html__( 'Menge', 'woo-product-extras' ) . '</th><th>' . esc_html__( 'Preis pro Stück', 'woo-product-extras' ) . '</th></tr></thead>';
        echo '<tbody>';

        foreach ( $rules as $rule ) {
            $range = $rule['min'];
            if ( ! empty( $rule['max'] ) ) {
                $range .= ' - ' . $rule['max'];
            } else {
                $range .= '+';
            }

            echo '<tr>';
            echo '<td>' . esc_html( $range ) . ' ' . esc_html__( 'Stk.', 'woo-product-extras' ) . '</td>';
            echo '<td>' . wc_price( $rule['price'] ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Frontend: Preisberechnung im Warenkorb
     */
    public function calculate_tiered_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            $rules = get_post_meta( $product_id, '_wpe_tiered_pricing_rules', true );

            if ( empty( $rules ) || ! is_array( $rules ) ) continue;

            $matched_price = false;

            // Passende Staffel finden
            foreach ( $rules as $rule ) {
                $min = $rule['min'];
                $max = $rule['max'];

                if ( $quantity >= $min ) {
                    if ( empty( $max ) || $quantity <= $max ) {
                        $matched_price = $rule['price'];
                        // Wir überschreiben nicht sofort, wir suchen weiter, falls es genauere Regeln gibt (durch Sortierung ist letzte passendste)
                    }
                }
            }

            if ( $matched_price !== false ) {
                $cart_item['data']->set_price( $matched_price );
            }
        }
    }

    /**
     * Einfaches CSS für das Frontend
     */
    public function output_css() {
        if ( ! is_product() ) return;
        ?>
        <style>
            .wpe-tiered-pricing-table-container { margin-bottom: 20px; }
            .wpe-tiered-pricing-table { width: 100%; max-width: 400px; border-collapse: collapse; margin-top: 10px; }
            .wpe-tiered-pricing-table th, .wpe-tiered-pricing-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .wpe-tiered-pricing-table th { background-color: #f9f9f9; }
            .wpe-tiered-pricing-table tr:nth-child(even) { background-color: #f2f2f2; }
        </style>
        <?php
    }
}
