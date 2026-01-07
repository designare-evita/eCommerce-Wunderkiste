<?php
/**
 * Image Resizer Modul
 *
 * Skaliert Bilder auf maximal 800px oder 1200px Breite/Höhe mit hoher Qualität
 * Version: 2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Image_Resizer {

    /**
     * Erlaubte Zielgrößen
     */
    private $allowed_sizes = array( 800, 1200 );

    /**
     * Konstruktor
     */
    public function __construct() {
        // Button im "Attachment Details" Modal (Einzelansicht)
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_resize_button' ), 10, 2 );

        // JavaScript für AJAX
        add_action( 'admin_footer', array( $this, 'admin_footer_script' ) );

        // AJAX Handler
        add_action( 'wp_ajax_wpe_resize_image', array( $this, 'ajax_resize_image' ) );

        // Spalte in der Listenansicht
        add_filter( 'manage_upload_columns', array( $this, 'add_list_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'fill_list_column' ), 10, 2 );

        // CSS für Spaltenbreite
        add_action( 'admin_head', array( $this, 'list_css' ) );
    }

    /**
     * Button im Attachment Details Modal hinzufügen
     */
    public function add_resize_button( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        // Aktuelle Bildgröße ermitteln
        $metadata = wp_get_attachment_metadata( $post->ID );
        $current_size = '';
        if ( $metadata && isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
            $current_size = '<span style="color:#666; font-size:12px;">' . 
                sprintf( __( 'Aktuell: %d × %d px', 'woo-product-extras' ), $metadata['width'], $metadata['height'] ) . 
                '</span><br>';
        }

        $nonce = wp_create_nonce( 'wpe_resize_' . $post->ID );

        $form_fields['wpe_resize'] = array(
            'label' => __( 'Skalierung', 'woo-product-extras' ),
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
                <p class="description" style="margin-top:5px;">' . esc_html__( 'Überschreibt das Originalbild (92% Qualität).', 'woo-product-extras' ) . '</p>
                <span class="wpe-resize-status" style="color: #2271b1; font-weight: bold; display: none;"></span>
            ',
        );
        return $form_fields;
    }

    /**
     * JavaScript für AJAX-Funktionalität
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
                
                // Fallback für Listenansicht
                if (status.length === 0) {
                    status = button.parent().find('.wpe-resize-status');
                }
                if (status.length === 0) {
                    status = button.parent().parent().find('.wpe-resize-status');
                }
                
                var attachmentId = button.data('id');
                var targetSize = button.data('size') || 800;
                var nonce = button.data('nonce');

                if (!confirm('<?php echo esc_js( __( 'Möchtest du dieses Bild wirklich permanent auf ', 'woo-product-extras' ) ); ?>' + targetSize + '<?php echo esc_js( __( 'px verkleinern? Das Original wird überschrieben.', 'woo-product-extras' ) ); ?>')) {
                    return;
                }

                // Alle Buttons in diesem Container deaktivieren
                container.find('.wpe-resize-trigger').prop('disabled', true);
                button.prop('disabled', true);
                status.text('<?php echo esc_js( __( 'Arbeite...', 'woo-product-extras' ) ); ?>').css('color', '#2271b1').show();

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
                            status.text('✗ ' + (response.data || '<?php echo esc_js( __( 'Unbekannt', 'woo-product-extras' ) ); ?>')).css('color', 'red');
                            container.find('.wpe-resize-trigger').prop('disabled', false);
                        }
                    },
                    error: function() {
                        status.text('✗ <?php echo esc_js( __( 'Server Fehler.', 'woo-product-extras' ) ); ?>').css('color', 'red');
                        container.find('.wpe-resize-trigger').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX Handler für Bild-Skalierung
     */
    public function ajax_resize_image() {
        $attachment_id = intval( $_POST['attachment_id'] );
        $target_size = isset( $_POST['target_size'] ) ? intval( $_POST['target_size'] ) : 800;
        
        // Erlaubte Größen validieren
        if ( ! in_array( $target_size, $this->allowed_sizes ) ) {
            $target_size = 800;
        }
        
        check_ajax_referer( 'wpe_resize_' . $attachment_id, 'security' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( __( 'Keine Berechtigung.', 'woo-product-extras' ) );
        }

        $path = get_attached_file( $attachment_id );
        if ( ! $path || ! file_exists( $path ) ) {
            wp_send_json_error( __( 'Datei nicht gefunden.', 'woo-product-extras' ) );
        }

        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            wp_send_json_error( __( 'Bildfehler.', 'woo-product-extras' ) );
        }

        // QUALITÄT SETZEN - 92 für hohe Qualität bei akzeptabler Dateigröße
        $editor->set_quality( 92 );

        $size = $editor->get_size();
        if ( $size['width'] <= $target_size && $size['height'] <= $target_size ) {
            wp_send_json_error( 
                sprintf( __( 'Bereits %dpx oder kleiner.', 'woo-product-extras' ), $target_size ) 
            );
        }

        $resized = $editor->resize( $target_size, $target_size, false );
        if ( is_wp_error( $resized ) ) {
            wp_send_json_error( __( 'Resize-Fehler.', 'woo-product-extras' ) );
        }

        $saved = $editor->save( $path );
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( __( 'Speicherfehler.', 'woo-product-extras' ) );
        }

        // Metadaten aktualisieren (wichtig für korrekte Anzeige in Mediathek)
        $metadata = wp_generate_attachment_metadata( $attachment_id, $path );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // Neue Größe für Feedback ermitteln
        $new_size = $editor->get_size();
        $feedback = sprintf( '%d×%dpx (92%% Qual.)', $new_size['width'], $new_size['height'] );

        wp_send_json_success( $feedback );
    }

    /**
     * Spalten-Überschrift in der Listenansicht hinzufügen
     */
    public function add_list_column( $columns ) {
        $columns['wpe_resizer'] = __( 'Resizer', 'woo-product-extras' );
        return $columns;
    }

    /**
     * Spalten-Inhalt (Buttons) in der Listenansicht
     */
    public function fill_list_column( $column_name, $post_id ) {
        if ( 'wpe_resizer' !== $column_name ) {
            return;
        }

        if ( wp_attachment_is_image( $post_id ) ) {
            $nonce = wp_create_nonce( 'wpe_resize_' . $post_id );
            echo '<div class="wpe-resize-container" style="display: flex; gap: 4px; flex-wrap: wrap;">';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="800" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Auf 800px skalieren', 'woo-product-extras' ) . '">800</button>';
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-size="1200" data-nonce="' . esc_attr( $nonce ) . '" title="' . esc_attr__( 'Auf 1200px skalieren', 'woo-product-extras' ) . '">1200</button>';
            echo '</div>';
            echo '<span class="wpe-resize-status" style="display:block; font-size:11px; margin-top:2px;"></span>';
        }
    }

    /**
     * CSS für Spaltenbreite
     */
    public function list_css() {
        echo '<style>
            .column-wpe_resizer { width: 120px; }
            .wpe-resize-container .button { min-width: 45px; padding: 0 8px; }
        </style>';
    }
}
