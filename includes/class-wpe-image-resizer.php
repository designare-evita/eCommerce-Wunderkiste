<?php
/**
 * Image Resizer Modul
 *
 * Skaliert Bilder auf maximal 800px Breite/Höhe mit hoher Qualität
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Image_Resizer {

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

        $form_fields['wpe_resize'] = array(
            'label' => __( 'Skalierung', 'woo-product-extras' ),
            'input' => 'html',
            'html'  => '
                <button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post->ID ) . '" data-nonce="' . wp_create_nonce( 'wpe_resize_' . $post->ID ) . '">
                    ' . esc_html__( 'Auf 800px skalieren', 'woo-product-extras' ) . '
                </button>
                <p class="description" style="margin-top:5px;">' . esc_html__( 'Überschreibt das Originalbild (max. 800px Breite/Höhe).', 'woo-product-extras' ) . '</p>
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
                var status = button.siblings('.wpe-resize-status');
                
                // Für Listenansicht: Status-Element ist Geschwister
                if (status.length === 0) {
                    status = button.parent().find('.wpe-resize-status');
                }
                
                var attachmentId = button.data('id');
                var nonce = button.data('nonce');

                if (!confirm('<?php echo esc_js( __( 'Möchtest du dieses Bild wirklich permanent auf 800px verkleinern? Das Original wird überschrieben.', 'woo-product-extras' ) ); ?>')) {
                    return;
                }

                button.prop('disabled', true);
                status.text('<?php echo esc_js( __( 'Arbeite...', 'woo-product-extras' ) ); ?>').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpe_resize_image',
                        attachment_id: attachmentId,
                        security: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            status.text('<?php echo esc_js( __( 'Erledigt!', 'woo-product-extras' ) ); ?> ' + response.data).css('color', 'green');
                        } else {
                            status.text('<?php echo esc_js( __( 'Fehler:', 'woo-product-extras' ) ); ?> ' + (response.data || '<?php echo esc_js( __( 'Unbekannt', 'woo-product-extras' ) ); ?>')).css('color', 'red');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        status.text('<?php echo esc_js( __( 'Server Fehler.', 'woo-product-extras' ) ); ?>').css('color', 'red');
                        button.prop('disabled', false);
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
        // Werte: 82 = WP-Standard, 88-90 = Kompromiss, 92-95 = Hohe Qualität
        $editor->set_quality( 92 );

        $size = $editor->get_size();
        if ( $size['width'] <= 800 && $size['height'] <= 800 ) {
            wp_send_json_error( __( 'Bereits klein genug.', 'woo-product-extras' ) );
        }

        $resized = $editor->resize( 800, 800, false );
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

        wp_send_json_success( __( 'Skaliert auf 800px (92% Qualität).', 'woo-product-extras' ) );
    }

    /**
     * Spalten-Überschrift in der Listenansicht hinzufügen
     */
    public function add_list_column( $columns ) {
        $columns['wpe_resizer'] = __( 'Resizer', 'woo-product-extras' );
        return $columns;
    }

    /**
     * Spalten-Inhalt (Button) in der Listenansicht
     */
    public function fill_list_column( $column_name, $post_id ) {
        if ( 'wpe_resizer' !== $column_name ) {
            return;
        }

        if ( wp_attachment_is_image( $post_id ) ) {
            echo '<button type="button" class="button button-small wpe-resize-trigger" data-id="' . esc_attr( $post_id ) . '" data-nonce="' . wp_create_nonce( 'wpe_resize_' . $post_id ) . '">800px</button>';
            echo '<span class="wpe-resize-status" style="display:block; font-size:11px; margin-top:2px;"></span>';
        }
    }

    /**
     * CSS für Spaltenbreite
     */
    public function list_css() {
        echo '<style>.column-wpe_resizer { width: 100px; }</style>';
    }
}
