<?php
/**
 * Price on Request Module
 *
 * Allows hiding prices and displaying "Price on Request"
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Price_On_Request {

    /**
     * Constructor
     */
    public function __construct() {
        // Backend: Add meta box
        add_action( 'add_meta_boxes_product', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Frontend: Modify price and cart
        add_filter( 'woocommerce_get_price_html', array( $this, 'change_price_display' ), 10, 2 );
        add_filter( 'woocommerce_is_purchasable', array( $this, 'hide_add_to_cart' ), 10, 2 );
        add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_loop_add_to_cart' ), 10, 2 );

        // Output custom CSS in frontend
        add_action( 'wp_head', array( $this, 'output_custom_css' ) );
    }

    /**
     * Add meta box to product editor (sidebar)
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_price_on_request_meta_box',
            __( 'Price on Request', 'ecommerce-wunderkiste' ),
            array( $this, 'render_meta_box' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box( $post ) {
        // Nonce field for security
        wp_nonce_field( 'wpe_save_price_on_request', 'wpe_price_on_request_nonce' );

        $is_price_on_request = get_post_meta( $post->ID, '_price_on_request', true );
        $custom_text = get_post_meta( $post->ID, '_price_on_request_text', true );

        if ( empty( $custom_text ) ) {
            $custom_text = __( 'Price on Request', 'ecommerce-wunderkiste' );
        }
        ?>
        <div class="wpe-meta-box-content">
            <p>
                <label>
                    <input type="checkbox"
                           name="_price_on_request"
                           value="yes"
                           <?php checked( 'yes', $is_price_on_request ); ?>>
                    <?php esc_html_e( 'Enable', 'ecommerce-wunderkiste' ); ?>
                </label>
            </p>
            <p class="description">
                <?php esc_html_e( 'Hides the price and the add to cart button.', 'ecommerce-wunderkiste' ); ?>
            </p>

            <p style="margin-top: 15px;">
                <label for="wpe_price_on_request_text">
                    <strong><?php esc_html_e( 'Display text:', 'ecommerce-wunderkiste' ); ?></strong>
                </label>
                <input type="text"
                       name="_price_on_request_text"
                       id="wpe_price_on_request_text"
                       value="<?php echo esc_attr( $custom_text ); ?>"
                       class="widefat"
                       placeholder="<?php esc_attr_e( 'Price on Request', 'ecommerce-wunderkiste' ); ?>">
            </p>
            <p class="description">
                <?php esc_html_e( 'Text displayed instead of the price.', 'ecommerce-wunderkiste' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box( $post_id ) {
        // Security checks
        if ( ! isset( $_POST['wpe_price_on_request_nonce'] ) ) {
            return;
        }

        // Sanitize and verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['wpe_price_on_request_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'wpe_save_price_on_request' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        // Save values
        $price_on_request = isset( $_POST['_price_on_request'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_price_on_request', $price_on_request );

        // Save custom text
        if ( isset( $_POST['_price_on_request_text'] ) ) {
            $custom_text = sanitize_text_field( wp_unslash( $_POST['_price_on_request_text'] ) );
            update_post_meta( $post_id, '_price_on_request_text', $custom_text );
        }
    }

    /**
     * Replace price in frontend
     */
    public function change_price_display( $price, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            $custom_text = get_post_meta( $product->get_id(), '_price_on_request_text', true );

            if ( empty( $custom_text ) ) {
                $custom_text = __( 'Price on Request', 'ecommerce-wunderkiste' );
            }

            return '<span class="price-on-request">' . esc_html( $custom_text ) . '</span>';
        }
        return $price;
    }

    /**
     * Remove add to cart button on product page
     */
    public function hide_add_to_cart( $is_purchasable, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            return false;
        }
        return $is_purchasable;
    }

    /**
     * Remove add to cart button on shop/archive pages
     */
    public function remove_loop_add_to_cart( $html, $product ) {
        if ( 'yes' === get_post_meta( $product->get_id(), '_price_on_request', true ) ) {
            return '';
        }
        return $html;
    }

    /**
     * Output custom CSS in frontend
     */
    public function output_custom_css() {
        $options = get_option( 'wpe_options', array() );

        if ( ! empty( $options['price_on_request_css'] ) ) {
            echo "\n<style id=\"wpe-price-on-request-css\">\n";
            echo esc_html( $options['price_on_request_css'] );
            echo "\n</style>\n";
        } else {
            // Default CSS if nothing defined
            echo "\n<style id=\"wpe-price-on-request-css\">\n";
            echo ".price-on-request { color: #e74c3c; font-weight: bold; }\n";
            echo "</style>\n";
        }
    }
}
