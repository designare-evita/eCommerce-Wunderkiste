<?php
/**
 * Tiered Pricing Module
 *
 * Enables quantity-based prices per product.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Tiered_Pricing {

    /**
     * Constructor
     */
    public function __construct() {
        // Backend: Meta box
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Frontend: Display pricing table
        add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_pricing_table' ), 20 );

        // Frontend: Calculate price in cart
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_tiered_price' ), 10, 1 );

        // Frontend: CSS
        add_action( 'wp_head', array( $this, 'output_css' ) );
    }

    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_tiered_pricing_box',
            __( '💰 Tiered Pricing', 'ecommerce-wunderkiste' ),
            array( $this, 'render_meta_box' ),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'wpe_tiered_pricing_save', 'wpe_tiered_pricing_nonce' );

        $rules = get_post_meta( $post->ID, '_wpe_tiered_pricing_rules', true );
        if ( ! is_array( $rules ) ) {
            $rules = array();
        }
        ?>
        <div class="wpe-tiered-pricing-wrapper">
            <p class="description"><?php esc_html_e( 'Define price tiers here. Leave "Max Qty" empty for "and more".', 'ecommerce-wunderkiste' ); ?></p>

            <table class="widefat" id="wpe-pricing-rules-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Min Qty', 'ecommerce-wunderkiste' ); ?></th>
                        <th><?php esc_html_e( 'Max Qty', 'ecommerce-wunderkiste' ); ?></th>
                        <th><?php esc_html_e( 'Unit Price', 'ecommerce-wunderkiste' ); ?></th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $rules ) ) : ?>
                        <?php foreach ( $rules as $rule ) : ?>
                            <tr>
                                <td><input type="number" name="wpe_tier_min[]" value="<?php echo esc_attr( $rule['min'] ); ?>" class="widefat" min="1" step="1"></td>
                                <td><input type="number" name="wpe_tier_max[]" value="<?php echo esc_attr( $rule['max'] ); ?>" class="widefat" min="1" step="1" placeholder="∞"></td>
                                <td><input type="text" name="wpe_tier_price[]" value="<?php echo esc_attr( wc_format_localized_price( $rule['price'] ) ); ?>" class="widefat wc_input_price"></td>
                                <td><span class="button wpe-remove-row">×</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button button-primary" id="wpe-add-tier-row"><?php esc_html_e( '+ Add Tier', 'ecommerce-wunderkiste' ); ?></button>
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
     * Save data
     */
    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['wpe_tiered_pricing_nonce'] ) ) {
            return;
        }

        // Sanitize and verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['wpe_tiered_pricing_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'wpe_tiered_pricing_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        $rules = array();

        if ( isset( $_POST['wpe_tier_min'] ) && is_array( $_POST['wpe_tier_min'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $mins = array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_min'] ) );
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $maxs = isset( $_POST['wpe_tier_max'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_max'] ) ) : array();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $prices = isset( $_POST['wpe_tier_price'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wpe_tier_price'] ) ) : array();

            $count = count( $mins );
            for ( $i = 0; $i < $count; $i++ ) {
                if ( empty( $mins[ $i ] ) || empty( $prices[ $i ] ) ) {
                    continue;
                }

                $rules[] = array(
                    'min'   => absint( $mins[ $i ] ),
                    'max'   => ! empty( $maxs[ $i ] ) ? absint( $maxs[ $i ] ) : '',
                    'price' => wc_format_decimal( $prices[ $i ] ),
                );
            }

            // Sort by quantity (min ascending)
            usort( $rules, function( $a, $b ) {
                return $a['min'] - $b['min'];
            } );
        }

        update_post_meta( $post_id, '_wpe_tiered_pricing_rules', $rules );
    }

    /**
     * Frontend: Display table
     */
    public function display_pricing_table() {
        global $product;
        $rules = get_post_meta( $product->get_id(), '_wpe_tiered_pricing_rules', true );

        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return;
        }

        echo '<div class="wpe-tiered-pricing-table-container">';
        echo '<h4>' . esc_html__( 'Tiered Pricing', 'ecommerce-wunderkiste' ) . '</h4>';
        echo '<table class="wpe-tiered-pricing-table">';
        echo '<thead><tr><th>' . esc_html__( 'Quantity', 'ecommerce-wunderkiste' ) . '</th><th>' . esc_html__( 'Price per Unit', 'ecommerce-wunderkiste' ) . '</th></tr></thead>';
        echo '<tbody>';

        foreach ( $rules as $rule ) {
            $range = $rule['min'];
            if ( ! empty( $rule['max'] ) ) {
                $range .= ' - ' . $rule['max'];
            } else {
                $range .= '+';
            }

            echo '<tr>';
            echo '<td>' . esc_html( $range ) . ' ' . esc_html__( 'pcs.', 'ecommerce-wunderkiste' ) . '</td>';
            echo '<td>' . wp_kses_post( wc_price( $rule['price'] ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Frontend: Price calculation in cart
     */
    public function calculate_tiered_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];

            $rules = get_post_meta( $product_id, '_wpe_tiered_pricing_rules', true );

            if ( empty( $rules ) || ! is_array( $rules ) ) {
                continue;
            }

            $matched_price = false;

            // Find matching tier
            foreach ( $rules as $rule ) {
                $min = $rule['min'];
                $max = $rule['max'];

                if ( $quantity >= $min ) {
                    if ( empty( $max ) || $quantity <= $max ) {
                        $matched_price = $rule['price'];
                        // Continue searching for more specific rules (due to sorting, last match is most specific)
                    }
                }
            }

            if ( false !== $matched_price ) {
                $cart_item['data']->set_price( $matched_price );
            }
        }
    }

    /**
     * Simple CSS for frontend
     */
    public function output_css() {
        if ( ! is_product() ) {
            return;
        }
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
