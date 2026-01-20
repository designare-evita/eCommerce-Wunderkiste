<?php
/**
 * Product Accessories Module
 *
 * Adds an "Accessories" tab to products with product linking
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Product_Accessories {

    /**
     * Meta key for accessory products
     */
    const META_KEY = '_wpe_accessory_products';

    /**
     * Constructor
     */
    public function __construct() {
        // Backend: Add meta box
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post_product', array( $this, 'save_meta_box' ) );

        // Backend: Admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Backend: AJAX product search
        add_action( 'wp_ajax_wpe_search_products', array( $this, 'ajax_search_products' ) );

        // Frontend: Add tab
        add_filter( 'woocommerce_product_tabs', array( $this, 'add_accessories_tab' ) );

        // Frontend: Styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
    }

    /**
     * Add meta box to product editor
     */
    public function add_meta_box() {
        add_meta_box(
            'wpe_accessories_box',
            __( '🔗 Accessories / Related Products', 'ecommerce-wunderkiste' ),
            array( $this, 'render_meta_box' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render meta box
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'wpe_accessories_nonce', 'wpe_accessories_nonce_field' );

        $accessory_ids = get_post_meta( $post->ID, self::META_KEY, true );
        $accessory_ids = is_array( $accessory_ids ) ? $accessory_ids : array();

        ?>
        <div class="wpe-accessories-wrapper">
            <div class="wpe-accessories-search">
                <input type="text"
                       id="wpe-accessory-search"
                       class="widefat"
                       placeholder="<?php esc_attr_e( 'Search product...', 'ecommerce-wunderkiste' ); ?>"
                       autocomplete="off">
                <div id="wpe-search-results" class="wpe-search-results"></div>
            </div>

            <div id="wpe-selected-accessories" class="wpe-selected-accessories">
                <?php
                if ( ! empty( $accessory_ids ) ) {
                    foreach ( $accessory_ids as $product_id ) {
                        $product = wc_get_product( $product_id );
                        if ( $product ) {
                            $this->render_selected_product( $product );
                        }
                    }
                }
                ?>
            </div>

            <input type="hidden"
                   name="wpe_accessory_ids"
                   id="wpe-accessory-ids"
                   value="<?php echo esc_attr( implode( ',', $accessory_ids ) ); ?>">

            <p class="description" style="margin-top: 10px;">
                <?php esc_html_e( 'Select products to display as accessories.', 'ecommerce-wunderkiste' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render single selected product
     */
    private function render_selected_product( $product ) {
        $thumb = $product->get_image( array( 30, 30 ) );
        $title = $product->get_name();
        $sku   = $product->get_sku();
        $id    = $product->get_id();

        ?>
        <div class="wpe-selected-item" data-id="<?php echo esc_attr( $id ); ?>">
            <span class="wpe-remove-item" title="<?php esc_attr_e( 'Remove', 'ecommerce-wunderkiste' ); ?>">×</span>
            <?php echo wp_kses_post( $thumb ); ?>
            <span class="wpe-item-title">
                <?php echo esc_html( $title ); ?>
                <?php if ( $sku ) : ?>
                    <small>(<?php echo esc_html( $sku ); ?>)</small>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }

    /**
     * Save meta box
     */
    public function save_meta_box( $post_id ) {
        // Check nonce
        if ( ! isset( $_POST['wpe_accessories_nonce_field'] ) ) {
            return;
        }

        // Sanitize and verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['wpe_accessories_nonce_field'] ) );
        if ( ! wp_verify_nonce( $nonce, 'wpe_accessories_nonce' ) ) {
            return;
        }

        // Skip auto-save
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save accessory IDs
        $accessory_ids = array();
        if ( ! empty( $_POST['wpe_accessory_ids'] ) ) {
            $ids = explode( ',', sanitize_text_field( wp_unslash( $_POST['wpe_accessory_ids'] ) ) );
            $accessory_ids = array_filter( array_map( 'intval', $ids ) );
        }

        update_post_meta( $post_id, self::META_KEY, $accessory_ids );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        global $post_type;

        if ( 'product' !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        // Inline styles
        wp_add_inline_style( 'woocommerce_admin_styles', $this->get_admin_styles() );

        // Inline script
        wp_add_inline_script( 'jquery', $this->get_admin_script() );
    }

    /**
     * Admin CSS
     */
    private function get_admin_styles() {
        return '
            .wpe-accessories-wrapper {
                margin: -6px -12px -12px;
                padding: 12px;
            }
            .wpe-accessories-search {
                position: relative;
                margin-bottom: 10px;
            }
            #wpe-accessory-search {
                padding: 8px 10px;
                font-size: 13px;
            }
            .wpe-search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #ddd;
                border-top: none;
                max-height: 250px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                box-shadow: 0 3px 5px rgba(0,0,0,0.1);
            }
            .wpe-search-results.active {
                display: block;
            }
            .wpe-search-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 10px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
                font-size: 12px;
            }
            .wpe-search-item:hover {
                background: #f0f6fc;
            }
            .wpe-search-item img {
                width: 30px;
                height: 30px;
                object-fit: cover;
                border-radius: 3px;
            }
            .wpe-search-item-title {
                flex: 1;
                line-height: 1.3;
            }
            .wpe-search-item-title small {
                display: block;
                color: #888;
                font-size: 11px;
            }
            .wpe-search-item-sku {
                color: #888;
                font-size: 11px;
            }
            .wpe-selected-accessories {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .wpe-selected-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 6px 8px;
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                font-size: 12px;
            }
            .wpe-selected-item img {
                width: 30px;
                height: 30px;
                object-fit: cover;
                border-radius: 3px;
            }
            .wpe-item-title {
                flex: 1;
                line-height: 1.3;
            }
            .wpe-item-title small {
                color: #888;
                font-size: 11px;
            }
            .wpe-remove-item {
                cursor: pointer;
                color: #a00;
                font-size: 18px;
                font-weight: bold;
                line-height: 1;
                padding: 0 4px;
            }
            .wpe-remove-item:hover {
                color: #dc3232;
            }
            .wpe-no-results {
                padding: 12px;
                color: #888;
                text-align: center;
                font-size: 12px;
            }
            .wpe-loading {
                padding: 12px;
                text-align: center;
                color: #888;
            }
        ';
    }

    /**
     * Admin JavaScript
     */
    private function get_admin_script() {
        $nonce = wp_create_nonce( 'wpe_search_products' );
        $search_text = esc_js( __( 'Searching...', 'ecommerce-wunderkiste' ) );
        $no_results_text = esc_js( __( 'No products found', 'ecommerce-wunderkiste' ) );
        $remove_text = esc_js( __( 'Remove', 'ecommerce-wunderkiste' ) );

        return "
        jQuery(document).ready(function($) {
            var searchTimeout;
            var searchInput = $('#wpe-accessory-search');
            var searchResults = $('#wpe-search-results');
            var selectedContainer = $('#wpe-selected-accessories');
            var hiddenInput = $('#wpe-accessory-ids');
            var currentPostId = $('#post_ID').val();

            // Product search
            searchInput.on('input', function() {
                var query = $(this).val();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    searchResults.removeClass('active').empty();
                    return;
                }

                searchResults.addClass('active').html('<div class=\"wpe-loading\">{$search_text}</div>');

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpe_search_products',
                            query: query,
                            exclude: currentPostId,
                            selected: hiddenInput.val(),
                            nonce: '{$nonce}'
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var html = '';
                                $.each(response.data, function(i, product) {
                                    html += '<div class=\"wpe-search-item\" data-id=\"' + product.id + '\">';
                                    html += '<img src=\"' + product.thumb + '\" alt=\"\">';
                                    html += '<span class=\"wpe-search-item-title\">' + product.title;
                                    if (product.sku) {
                                        html += '<small>(' + product.sku + ')</small>';
                                    }
                                    html += '</span>';
                                    html += '</div>';
                                });
                                searchResults.html(html);
                            } else {
                                searchResults.html('<div class=\"wpe-no-results\">{$no_results_text}</div>');
                            }
                        }
                    });
                }, 300);
            });

            // Select product
            searchResults.on('click', '.wpe-search-item', function() {
                var item = $(this);
                var id = item.data('id');
                var title = item.find('.wpe-search-item-title').html();
                var thumb = item.find('img').attr('src');

                // Already selected?
                if (selectedContainer.find('[data-id=\"' + id + '\"]').length > 0) {
                    return;
                }

                // Add to list
                var html = '<div class=\"wpe-selected-item\" data-id=\"' + id + '\">';
                html += '<span class=\"wpe-remove-item\" title=\"{$remove_text}\">×</span>';
                html += '<img src=\"' + thumb + '\" alt=\"\">';
                html += '<span class=\"wpe-item-title\">' + title + '</span>';
                html += '</div>';

                selectedContainer.append(html);
                updateHiddenInput();

                // Reset search
                searchInput.val('');
                searchResults.removeClass('active').empty();
            });

            // Remove product
            selectedContainer.on('click', '.wpe-remove-item', function() {
                $(this).closest('.wpe-selected-item').remove();
                updateHiddenInput();
            });

            // Update hidden input
            function updateHiddenInput() {
                var ids = [];
                selectedContainer.find('.wpe-selected-item').each(function() {
                    ids.push($(this).data('id'));
                });
                hiddenInput.val(ids.join(','));
            }

            // Click outside closes dropdown
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wpe-accessories-search').length) {
                    searchResults.removeClass('active');
                }
            });
        });
        ";
    }

    /**
     * AJAX: Product search
     */
    public function ajax_search_products() {
        check_ajax_referer( 'wpe_search_products', 'nonce' );

        $query    = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        $exclude  = isset( $_POST['exclude'] ) ? intval( $_POST['exclude'] ) : 0;
        $selected = isset( $_POST['selected'] ) ? sanitize_text_field( wp_unslash( $_POST['selected'] ) ) : '';

        // Already selected IDs
        $selected_ids = array_filter( array_map( 'intval', explode( ',', $selected ) ) );
        $exclude_ids  = array_merge( array( $exclude ), $selected_ids );

        // Search products
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'post__not_in'   => $exclude_ids,
            's'              => $query,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        // First normal search
        $products_query = new WP_Query( $args );

        $results = array();

        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $product = wc_get_product( get_the_ID() );

                if ( $product ) {
                    $thumb_id  = $product->get_image_id();
                    $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

                    $results[] = array(
                        'id'    => $product->get_id(),
                        'title' => $product->get_name(),
                        'sku'   => $product->get_sku(),
                        'thumb' => $thumb_url,
                    );
                }
            }
            wp_reset_postdata();
        }

        // SKU search as fallback
        if ( empty( $results ) ) {
            $sku_args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'post__not_in'   => $exclude_ids,
                'meta_query'     => array(
                    array(
                        'key'     => '_sku',
                        'value'   => $query,
                        'compare' => 'LIKE',
                    ),
                ),
            );

            $sku_query = new WP_Query( $sku_args );

            if ( $sku_query->have_posts() ) {
                while ( $sku_query->have_posts() ) {
                    $sku_query->the_post();
                    $product = wc_get_product( get_the_ID() );

                    if ( $product ) {
                        $thumb_id  = $product->get_image_id();
                        $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

                        $results[] = array(
                            'id'    => $product->get_id(),
                            'title' => $product->get_name(),
                            'sku'   => $product->get_sku(),
                            'thumb' => $thumb_url,
                        );
                    }
                }
                wp_reset_postdata();
            }
        }

        wp_send_json_success( $results );
    }

    /**
     * Frontend: Add accessories tab
     */
    public function add_accessories_tab( $tabs ) {
        global $product;

        if ( ! $product ) {
            return $tabs;
        }

        $accessory_ids = get_post_meta( $product->get_id(), self::META_KEY, true );

        if ( empty( $accessory_ids ) || ! is_array( $accessory_ids ) ) {
            return $tabs;
        }

        // Check if at least one product exists
        $has_valid_products = false;
        foreach ( $accessory_ids as $id ) {
            $acc_product = wc_get_product( $id );
            if ( $acc_product && $acc_product->is_visible() ) {
                $has_valid_products = true;
                break;
            }
        }

        if ( ! $has_valid_products ) {
            return $tabs;
        }

        $tabs['accessories'] = array(
            'title'    => __( 'Accessories', 'ecommerce-wunderkiste' ),
            'priority' => 25,
            'callback' => array( $this, 'render_accessories_tab' ),
        );

        return $tabs;
    }

    /**
     * Frontend: Render tab content
     */
    public function render_accessories_tab() {
        global $product;

        $accessory_ids = get_post_meta( $product->get_id(), self::META_KEY, true );

        if ( empty( $accessory_ids ) || ! is_array( $accessory_ids ) ) {
            return;
        }

        echo '<div class="wpe-accessories-list">';
        echo '<h3>' . esc_html__( 'Matching Accessories', 'ecommerce-wunderkiste' ) . '</h3>';

        echo '<ul class="products columns-4">';

        foreach ( $accessory_ids as $accessory_id ) {
            $accessory = wc_get_product( $accessory_id );

            if ( ! $accessory || ! $accessory->is_visible() ) {
                continue;
            }

            // Use WooCommerce product template
            $post_object = get_post( $accessory_id );
            setup_postdata( $GLOBALS['post'] =& $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

            wc_get_template_part( 'content', 'product' );
        }

        wp_reset_postdata();

        echo '</ul>';
        echo '</div>';
    }

    /**
     * Frontend styles
     */
    public function enqueue_frontend_styles() {
        if ( ! is_product() ) {
            return;
        }

        $css = '
            .wpe-accessories-list {
                margin-top: 20px;
            }
            .wpe-accessories-list h3 {
                margin-bottom: 20px;
            }
            .wpe-accessories-list .products {
                margin: 0;
                padding: 0;
            }
        ';

        wp_add_inline_style( 'woocommerce-general', $css );
    }
}
