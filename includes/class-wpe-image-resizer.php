<?php
/**
 * Image Resizer Module
 *
 * Resizes images to max 800px or 1200px width/height with high quality
 * Version: 2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Image_Resizer {

    /**
     * Allowed target sizes
     */
    private $allowed_sizes = array( 800, 1200 );

    /**
     * Constructor
     */
    public function __construct() {
        // Button in "Attachment Details" modal (single view)
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_resize_button' ), 10, 2 );

        // JavaScript for AJAX
        add_action( 'admin_footer', array( $this, 'admin_footer_script' ) );

        // AJAX Handler
        add_action( 'wp_ajax_wpe_resize_image', array( $this, 'ajax_resize_image' ) );

        // Column in list view
        add_filter( 'manage_upload_columns', array( $this, 'add_list_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'fill_list_column' ), 10, 2 );

        // CSS for column width
        add_action( 'admin_head', array( $this, 'list_css' ) );
    }

    /**
     * Add button in Attachment Details modal
     */
    public function add_resize_button( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        // Get current image size
        $metadata = wp_get_attachment_metadata( $post->ID );
        $current_size = '';
        if ( $metadata && isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
            $current_size = '<span style="color:#666; font-size:12px;">' .
                sprintf(
                    /* translators: %1$d: width in pixels, %2$d: height in pixels */
                    esc_html__( 'Current: %1$d × %2$d px', 'ecommerce-wunderkiste' ),
                    $metadata['width'],
                    $metadata['height']
                ) .
                '</span><br>';
        }

        $nonce = wp_create_nonce( 'wpe_resize_' . $post->ID );

        $form_fields['wpe_resize'] = array(
            'label' => __( 'Resize', 'ecommerce-wunderkiste' ),
            'input' => 'html',
            'html'  => '
                ' . $current_size . '
                <div class="wpe-resize-container" style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post->ID ) . '" data-size="800" data-nonce="' . esc_attr( $nonce ) . '">
                        800px
                    </button>
                    <button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post->ID ) . '" data-size="1200" data-nonce="' . esc_attr( $nonce ) . '">
                        1200px
                    </button>
                </div>
                <p class="description" style="margin-top:5px;">' . esc_html__( 'Overwrites original image (92% quality).', 'ecommerce-wunderkiste' ) . '</p>
                <span class="wpe-resize-status" style="color: #2271b1; font-weight: bold; display: none;"></span>
            ',
        );
        return $form_fields;
    }

    /**
     * JavaScript for AJAX functionality
     */
    public function admin_footer_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.wpe-resize-trigger', function(e) {
                e.preventDefault();
                var button = $(this);
                var container = button.closest('.wpe-resize-container, td, .setting');
                var status = container.find('.wpe-resize-status').length ? container.find('.wpe-resize-status') : button.siblings('.wpe-resize-status');

                // Fallback for list view
                if (status.length === 0) {
                    status = button.parent().find('.wpe-resize-status');
                }
                if (status.length === 0) {
                    status = button.parent().parent().find('.wpe-resize-status');
                }

                var attachmentId = button.data('id');
                var targetSize = button.data('size') || 800;
                var nonce = button.data('nonce');

                if (!confirm(<?php echo wp_json_encode( __( 'Do you really want to permanently resize this image to ', 'ecommerce-wunderkiste' ) ); ?> + targetSize + <?php echo wp_json_encode( __( 'px? The original will be overwritten.', 'ecommerce-wunderkiste' ) ); ?>)) {
                    return;
                }

                // Disable all buttons in this container
                container.find('.wpe-resize-trigger').prop('disabled', true);
                button.prop('disabled', true);
                status.text(<?php echo wp_json_encode( __( 'Working...', 'ecommerce-wunderkiste' ) ); ?>).css('color', '#2271b1').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpe_resize_image',
                        attachment_id: attachmentId,
                        target_size: targetSize,
                        security: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            status.text('✓ ' + response.data).css('color', 'green');
                        } else {
                            status.text('✗ ' + (response.data || <?php echo wp_json_encode( __( 'Unknown', 'ecommerce-wunderkiste' ) ); ?>)).css('color', 'red');
                            container.find('.wpe-resize-trigger').prop('disabled', false);
                        }
                    },
                    error: function() {
                        status.text('✗ ' + <?php echo wp_json_encode( __( 'Server error.', 'ecommerce-wunderkiste' ) ); ?>).css('color', 'red');
                        container.find('.wpe-resize-trigger').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for image resizing
     */
    public function ajax_resize_image() {
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        $target_size = isset( $_POST['target_size'] ) ? intval( $_POST['target_size'] ) : 800;

        // Validate allowed sizes
        if ( ! in_array( $target_size, $this->allowed_sizes, true ) ) {
            $target_size = 800;
        }

        check_ajax_referer( 'wpe_resize_' . $attachment_id, 'security' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( __( 'No permission.', 'ecommerce-wunderkiste' ) );
        }

        $path = get_attached_file( $attachment_id );
        if ( ! $path || ! file_exists( $path ) ) {
            wp_send_json_error( __( 'File not found.', 'ecommerce-wunderkiste' ) );
        }

        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            wp_send_json_error( __( 'Image error.', 'ecommerce-wunderkiste' ) );
        }

        // SET QUALITY - 92 for high quality with acceptable file size
        $editor->set_quality( 92 );

        $size = $editor->get_size();
        if ( $size['width'] <= $target_size && $size['height'] <= $target_size ) {
            wp_send_json_error(
                sprintf(
                    /* translators: %d: target size in pixels */
                    __( 'Already %dpx or smaller.', 'ecommerce-wunderkiste' ),
                    $target_size
                )
            );
        }

        $resized = $editor->resize( $target_size, $target_size, false );
        if ( is_wp_error( $resized ) ) {
            wp_send_json_error( __( 'Resize error.', 'ecommerce-wunderkiste' ) );
        }

        $saved = $editor->save( $path );
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( __( 'Save error.', 'ecommerce-wunderkiste' ) );
        }

        // Update metadata (important for correct display in media library)
        $metadata = wp_generate_attachment_metadata( $attachment_id, $path );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // Get new size for feedback
        $new_size = $editor->get_size();
        $feedback = sprintf( '%d×%dpx (92%% qual.)', $new_size['width'], $new_size['height'] );

        wp_send_json_success( $feedback );
    }

    /**
     * Add column header in list view
     */
    public function add_list_column( $columns ) {
        $columns['wpe_resizer'] = __( 'Resizer', 'ecommerce-wunderkiste' );
        return $columns;
    }

    /**
     * Fill column content (buttons) in list view
     */
    public function fill_list_column( $column_name, $post_id ) {
        if ( 'wpe_resizer' !== $column_name ) {
            return;
        }

        if ( wp_attachment_is_image( $post_id ) ) {
            $nonce = wp_create_nonce( 'wpe_resize_' . $post_id );
            echo '<div class="wpe-resize-container" style="display: flex; gap: 4px; flex-wrap: wrap;">';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="800" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Resize to 800px', 'ecommerce-wunderkiste' ) . '">800</button>';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="1200" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Resize to 1200px', 'ecommerce-wunderkiste' ) . '">1200</button>';
            echo '</div>';
            echo '<span class="wpe-resize-status" style="display:block; font-size:11px; margin-top:2px;"></span>';
        }
    }

    /**
     * CSS for column width
     */
    public function list_css() {
        echo '<style>
            .column-wpe_resizer { width: 120px; }
            .wpe-resize-container .button { min-width: 45px; padding: 0 8px; }
        </style>';
    }
}
