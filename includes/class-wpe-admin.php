<?php
/**
 * Admin class for eCommerce Wunderkiste
 *
 * Manages settings page and plugin options
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'plugin_action_links_' . WPE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Extras', 'ecommerce-wunderkiste' ),
            __( 'Product Extras', 'ecommerce-wunderkiste' ),
            'manage_woocommerce',
            'woo-product-extras',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wpe_settings_group',
            'wpe_options',
            array( $this, 'sanitize_options' )
        );

        // Main section
        add_settings_section(
            'wpe_main_section',
            __( 'Enable Modules', 'ecommerce-wunderkiste' ),
            array( $this, 'main_section_callback' ),
            'woo-product-extras'
        );

        // Price on Request
        add_settings_field(
            'enable_price_on_request',
            __( 'Price on Request', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_price_on_request',
                'description' => __( 'Allows displaying "Price on Request" instead of the price for individual products.', 'ecommerce-wunderkiste' )
            )
        );

        // Disable Shipping
        add_settings_field(
            'enable_disable_shipping',
            __( 'Disable Shipping Methods', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_disable_shipping',
                'description' => __( 'Allows disabling specific shipping methods per product.', 'ecommerce-wunderkiste' )
            )
        );

        // Accessories
        add_settings_field(
            'enable_product_accessories',
            __( 'Accessories Tab', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_product_accessories',
                'description' => __( 'Adds an "Accessories" tab to products to link related products.', 'ecommerce-wunderkiste' )
            )
        );

        // Image Resizer
        add_settings_field(
            'enable_image_resizer',
            __( 'Image Resizer 800px/1200px', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_image_resizer',
                'description' => __( 'Adds a button in the media library to resize images to max. 800px or 1200px.', 'ecommerce-wunderkiste' )
            )
        );

        // Order Recovery
        add_settings_field(
            'enable_order_recovery',
            __( 'Order Recovery (Payment Failure)', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_order_recovery',
                'description' => __( 'Enables scenarios A, B and C: Email after 1h on pending, instant email on failure, manual button.', 'ecommerce-wunderkiste' )
            )
        );

        // Tiered Pricing
        add_settings_field(
            'enable_tiered_pricing',
            __( 'Tiered Pricing', 'ecommerce-wunderkiste' ),
            array( $this, 'checkbox_field_callback' ),
            'woo-product-extras',
            'wpe_main_section',
            array(
                'id'          => 'enable_tiered_pricing',
                'description' => __( 'Enables tiered pricing per product (quantity discounts).', 'ecommerce-wunderkiste' )
            )
        );

        // CSS Section
        add_settings_section(
            'wpe_css_section',
            __( 'Custom CSS', 'ecommerce-wunderkiste' ),
            array( $this, 'css_section_callback' ),
            'woo-product-extras'
        );

        // Custom CSS for Price on Request
        add_settings_field(
            'price_on_request_css',
            __( 'Price on Request CSS', 'ecommerce-wunderkiste' ),
            array( $this, 'textarea_field_callback' ),
            'woo-product-extras',
            'wpe_css_section',
            array(
                'id'          => 'price_on_request_css',
                'description' => __( 'CSS for the "Price on Request" display. Use the class .price-on-request', 'ecommerce-wunderkiste' ),
                'rows'        => 10
            )
        );
    }

    /**
     * Main section description
     */
    public function main_section_callback() {
        echo '<p>' . esc_html__( 'Enable the desired modules. Settings will be displayed per product in the sidebar.', 'ecommerce-wunderkiste' ) . '</p>';
    }

    /**
     * CSS section description
     */
    public function css_section_callback() {
        echo '<p>' . esc_html__( 'Customize the appearance of features with your own CSS.', 'ecommerce-wunderkiste' ) . '</p>';
    }

    /**
     * Render checkbox field
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
     * Render textarea field
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
     * Sanitize options
     */
    public function sanitize_options( $input ) {
        $sanitized = array();

        // Checkboxes
        $sanitized['enable_price_on_request']    = ! empty( $input['enable_price_on_request'] ) ? 1 : 0;
        $sanitized['enable_disable_shipping']    = ! empty( $input['enable_disable_shipping'] ) ? 1 : 0;
        $sanitized['enable_product_accessories'] = ! empty( $input['enable_product_accessories'] ) ? 1 : 0;
        $sanitized['enable_image_resizer']       = ! empty( $input['enable_image_resizer'] ) ? 1 : 0;
        $sanitized['enable_order_recovery']      = ! empty( $input['enable_order_recovery'] ) ? 1 : 0;
        $sanitized['enable_tiered_pricing']      = ! empty( $input['enable_tiered_pricing'] ) ? 1 : 0;

        // CSS (with wp_strip_all_tags for security, but allowing CSS syntax)
        if ( isset( $input['price_on_request_css'] ) ) {
            $sanitized['price_on_request_css'] = wp_strip_all_tags( $input['price_on_request_css'] );
        }

        return $sanitized;
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_woo-product-extras' !== $hook ) {
            return;
        }

        // CodeMirror for CSS editor
        wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
        wp_enqueue_script( 'wp-theme-plugin-editor' );
        wp_enqueue_style( 'wp-codemirror' );

        // Custom admin script
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
     * Add settings link to plugin page
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=woo-product-extras' ) . '">' . __( 'Settings', 'ecommerce-wunderkiste' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render settings page
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
                submit_button( __( 'Save Settings', 'ecommerce-wunderkiste' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Instructions', 'ecommerce-wunderkiste' ); ?></h2>
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h3><?php esc_html_e( 'Price on Request', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'After activation, a "Price on Request" box appears in the sidebar of each product. Enable the checkbox to replace the price with "Price on Request" and remove the add to cart button.', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'Disable Shipping Methods', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'After activation, a box with all available shipping methods appears in the sidebar of each product. Select the shipping methods that should NOT be available for this product.', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'Accessories Tab', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'After activation, an "Accessories / Related Products" box appears in the sidebar of each product. Search for products and select the desired ones. An "Accessories" tab with linked products will then appear in the frontend.', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'Image Resizer 800px/1200px', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'After activation, buttons appear in the media library (list view and single view) to resize images to a maximum of 800px or 1200px. The original image is overwritten. Quality: 92% (high quality).', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'Order Recovery', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'Fully automatic: Sends an email when a payment has been pending for 1 hour, or immediately on failure. Also adds a manual "Send Payment Link" button in the order overview.', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'Tiered Pricing', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'Define individual prices based on order quantity. The price table is automatically displayed on the product page.', 'ecommerce-wunderkiste' ); ?></p>

                <h3><?php esc_html_e( 'CSS Customization', 'ecommerce-wunderkiste' ); ?></h3>
                <p><?php esc_html_e( 'Use the following CSS class for individual styles:', 'ecommerce-wunderkiste' ); ?></p>
                <code>.price-on-request { color: #e74c3c; font-weight: bold; }</code>
            </div>
        </div>
        <?php
    }
}
