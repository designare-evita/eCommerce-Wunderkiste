<?php
/**
 * Image Resizer Module
 *
 * Resizes images to max 800px or 1200px width/height with high quality
 * Version: 2.4 - Mit Live-Update und Bulk-Actions
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

        // Bulk Actions
        add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );

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
            $current_size = '<span class="wpe-current-size" style="color:#666; font-size:12px;">' .
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
     * Add Bulk Actions to Media Library
     */
    public function add_bulk_actions( $bulk_actions ) {
        $bulk_actions['wpe_bulk_800'] = __( '🖼️ Resize to 800px', 'ecommerce-wunderkiste' );
        $bulk_actions['wpe_bulk_1200'] = __( '🖼️ Resize to 1200px', 'ecommerce-wunderkiste' );
        return $bulk_actions;
    }

    /**
     * JavaScript for AJAX functionality
     */
    public function admin_footer_script() {
        $screen = get_current_screen();
        if ( ! $screen || ( $screen->base !== 'upload' && $screen->base !== 'post' && $screen->id !== 'attachment' ) ) {
            return;
        }

        $bulk_nonce = wp_create_nonce( 'wpe_bulk_resize' );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Single Image Resize
            $(document).on('click', '.wpe-resize-trigger', function(e) {
                e.preventDefault();
                var button = $(this);
                var container = button.closest('.wpe-resize-container, td, .setting, .compat-field-wpe_resize');
                var status = container.find('.wpe-resize-status');
                var sizeDisplay = container.find('.wpe-current-size');

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
                status.text('⏳ ' + <?php echo wp_json_encode( __( 'Working...', 'ecommerce-wunderkiste' ) ); ?>).css('color', '#2271b1').show();

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
                            status.text('✓ ' + <?php echo wp_json_encode( __( 'Done:', 'ecommerce-wunderkiste' ) ); ?> + ' ' + response.data.dimensions).css('color', 'green');
                            
                            // Update size display
                            if (sizeDisplay.length) {
                                sizeDisplay.html(<?php echo wp_json_encode( __( 'Current:', 'ecommerce-wunderkiste' ) ); ?> + ' <strong>' + response.data.dimensions + '</strong>');
                            }
                            
                            // Re-enable buttons after 3 seconds
                            setTimeout(function() {
                                container.find('.wpe-resize-trigger').prop('disabled', false);
                                status.fadeOut();
                            }, 3000);
                        } else {
                            status.text('✗ ' + (response.data || <?php echo wp_json_encode( __( 'Unknown error', 'ecommerce-wunderkiste' ) ); ?>)).css('color', 'red');
                            container.find('.wpe-resize-trigger').prop('disabled', false);
                        }
                    },
                    error: function() {
                        status.text('✗ ' + <?php echo wp_json_encode( __( 'Server error.', 'ecommerce-wunderkiste' ) ); ?>).css('color', 'red');
                        container.find('.wpe-resize-trigger').prop('disabled', false);
                    }
                });
            });

            // Bulk Action Handler
            $(document).on('click', '#doaction, #doaction2', function(e) {
                var action = $(this).prev('select').val();
                if (action !== 'wpe_bulk_800' && action !== 'wpe_bulk_1200') return;
                
                e.preventDefault();
                var size = action === 'wpe_bulk_800' ? 800 : 1200;
                var checked = $('input[name="media[]"]:checked');
                
                if (checked.length === 0) {
                    alert(<?php echo wp_json_encode( __( 'Please select at least one image.', 'ecommerce-wunderkiste' ) ); ?>);
                    return;
                }
                
                if (!confirm(<?php echo wp_json_encode( __( 'Resize selected images to', 'ecommerce-wunderkiste' ) ); ?> + ' ' + size + 'px? ' + <?php echo wp_json_encode( __( 'Originals will be overwritten!', 'ecommerce-wunderkiste' ) ); ?>)) {
                    return;
                }
                
                var ids = [];
                checked.each(function() { ids.push($(this).val()); });
                
                // Progress Modal
                var modal = $('<div id="wpe-bulk-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;display:flex;align-items:center;justify-content:center;">' +
                    '<div style="background:#fff;padding:30px;border-radius:8px;max-width:500px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,0.3);">' +
                    '<h2 style="margin:0 0 20px;color:#1d2327;">🖼️ ' + <?php echo wp_json_encode( __( 'Resizing images...', 'ecommerce-wunderkiste' ) ); ?> + '</h2>' +
                    '<div class="wpe-progress-bar" style="background:#ddd;height:24px;border-radius:12px;overflow:hidden;margin-bottom:15px;">' +
                    '<div class="wpe-progress-fill" style="background:linear-gradient(90deg,#2271b1,#135e96);height:100%;width:0%;transition:width 0.3s;"></div></div>' +
                    '<div class="wpe-progress-text" style="text-align:center;font-size:14px;color:#50575e;">0 / ' + ids.length + '</div>' +
                    '<div class="wpe-progress-log" style="max-height:200px;overflow-y:auto;margin-top:15px;font-size:12px;background:#f6f7f7;padding:10px;border-radius:4px;"></div>' +
                    '</div></div>');
                $('body').append(modal);
                
                var processed = 0, success = 0, errors = 0;
                var progressFill = modal.find('.wpe-progress-fill');
                var progressText = modal.find('.wpe-progress-text');
                var progressLog = modal.find('.wpe-progress-log');
                
                function processNext(index) {
                    if (index >= ids.length) {
                        // Done
                        progressText.html('<strong style="color:#00a32a;">✓ ' + <?php echo wp_json_encode( __( 'Done!', 'ecommerce-wunderkiste' ) ); ?> + '</strong> ' + success + ' ' + <?php echo wp_json_encode( __( 'successful', 'ecommerce-wunderkiste' ) ); ?> + ', ' + errors + ' ' + <?php echo wp_json_encode( __( 'errors', 'ecommerce-wunderkiste' ) ); ?>);
                        setTimeout(function() {
                            modal.fadeOut(300, function() { 
                                $(this).remove(); 
                                location.reload(); 
                            });
                        }, 2000);
                        return;
                    }
                    
                    var id = ids[index];
                    var row = $('input[name="media[]"][value="' + id + '"]').closest('tr');
                    var title = row.find('.title a').text() || row.find('.column-title strong').text() || 'ID: ' + id;
                    
                    $.post(ajaxurl, {
                        action: 'wpe_resize_image',
                        attachment_id: id,
                        target_size: size,
                        security: '<?php echo esc_js( $bulk_nonce ); ?>',
                        is_bulk: true
                    }, function(r) {
                        processed++;
                        var percent = Math.round((processed / ids.length) * 100);
                        progressFill.css('width', percent + '%');
                        progressText.text(processed + ' / ' + ids.length);
                        
                        if (r.success) {
                            success++;
                            progressLog.prepend('<div style="color:#00a32a;margin-bottom:3px;">✓ ' + title + ' → ' + r.data.dimensions + '</div>');
                        } else {
                            errors++;
                            progressLog.prepend('<div style="color:#d63638;margin-bottom:3px;">✗ ' + title + ': ' + (r.data || 'Error') + '</div>');
                        }
                        
                        // Next image after short delay
                        setTimeout(function() { processNext(index + 1); }, 200);
                    }).fail(function() {
                        processed++;
                        errors++;
                        progressLog.prepend('<div style="color:#d63638;margin-bottom:3px;">✗ ' + title + ': Connection error</div>');
                        setTimeout(function() { processNext(index + 1); }, 200);
                    });
                }
                
                processNext(0);
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
        $is_bulk = isset( $_POST['is_bulk'] ) && $_POST['is_bulk'];

        // Validate allowed sizes
        if ( ! in_array( $target_size, $this->allowed_sizes, true ) ) {
            $target_size = 800;
        }

        // Verify nonce - different for single vs bulk
        if ( $is_bulk ) {
            check_ajax_referer( 'wpe_bulk_resize', 'security' );
        } else {
            check_ajax_referer( 'wpe_resize_' . $attachment_id, 'security' );
        }

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( __( 'No permission.', 'ecommerce-wunderkiste' ) );
        }

        // Check if it's an image
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( __( 'Not an image.', 'ecommerce-wunderkiste' ) );
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
        
        wp_send_json_success( array(
            'dimensions' => $new_size['width'] . ' × ' . $new_size['height'] . ' px',
            'width'      => $new_size['width'],
            'height'     => $new_size['height'],
        ) );
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
            $metadata = wp_get_attachment_metadata( $post_id );
            
            // Show current size
            $current_size = '';
            if ( $metadata && isset( $metadata['width'] ) ) {
                $current_size = '<span class="wpe-current-size" style="font-size:11px;color:#666;display:block;margin-bottom:4px;">' . 
                    $metadata['width'] . '×' . $metadata['height'] . '</span>';
            }
            
            echo $current_size;
            echo '<div class="wpe-resize-container" style="display: flex; gap: 4px; flex-wrap: wrap;">';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="800" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Resize to 800px', 'ecommerce-wunderkiste' ) . '">800</button>';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="1200" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Resize to 1200px', 'ecommerce-wunderkiste' ) . '">1200</button>';
            echo '</div>';
            echo '<span class="wpe-resize-status" style="display:block; font-size:11px; margin-top:2px;"></span>';
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

    /**
     * CSS for column width
     */
    public function list_css() {
        echo '<style>
            .column-wpe_resizer { width: 120px; }
            .wpe-resize-container .button { min-width: 45px; padding: 0 8px; }
            .wpe-current-size strong { color: #1d2327; }
        </style>';
    }
}
