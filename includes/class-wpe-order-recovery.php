<?php
/**
 * Order Recovery Modul
 *
 * Behandelt fehlgeschlagene Zahlungen und abgebrochene Zahlunsgvorgänge.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Order_Recovery {

    public function __construct() {
        // Szenario A: Check nach 1 Stunde bei "Zahlung ausstehend"
        add_action( 'woocommerce_order_status_pending', array( $this, 'schedule_pending_check' ), 10, 2 );
        add_action( 'wpe_check_pending_order_event', array( $this, 'check_pending_order_status' ) );

        // Szenario B: Sofort-Mail bei Status "Fehlgeschlagen"
        add_action( 'woocommerce_order_status_failed', array( $this, 'send_recovery_email_immediately' ), 10, 2 );

        // Szenario C: Manueller Button in der Bestellübersicht
        add_filter( 'woocommerce_order_actions', array( $this, 'add_custom_order_action' ) );
        add_action( 'woocommerce_order_action_wpe_send_payment_link', array( $this, 'process_custom_order_action' ) );

        // E-Mail Text anpassen (optional, um es freundlicher zu machen)
        add_action( 'woocommerce_email_before_order_table', array( $this, 'add_custom_email_message' ), 10, 4 );
    }

    /**
     * SZENARIO A: Cronjob einplanen (1 Stunde)
     */
    public function schedule_pending_check( $order_id, $order ) {
        if ( ! wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) ) ) {
            // Event in 1 Stunde (3600 Sekunden) einplanen
            wp_schedule_single_event( time() + 3600, 'wpe_check_pending_order_event', array( $order_id ) );
        }
    }

    /**
     * SZENARIO A: Cronjob ausführen
     * + NEU: Info-Mail an Admin
     */
    public function check_pending_order_status( $order_id ) {
        $order = wc_get_order( $order_id ); // Korrekte WC Funktion

        if ( ! $order ) return;

        // Wir prüfen, ob die Bestellung IMMER NOCH "pending" ist
        if ( $order->has_status( 'pending' ) ) {
            // 1. Sende die "Customer Invoice / Order Details" Mail (enthält Zahlungslink)
            WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
            
            // 2. Notiz an der Bestellung hinterlassen
            $order->add_order_note( '[Wunderkiste] Automatische Erinnerungs-Mail nach 1 Std. gesendet.' );

            // 3. NEU: Info-Mail an Admin senden
            $this->send_admin_info_mail( $order, 'pending_recovery' );
        }
    }

    /**
     * SZENARIO B: Sofort bei "Failed"
     * + NEU: Info-Mail an Admin
     */
    public function send_recovery_email_immediately( $order_id, $order ) {
        // 1. Mail an Kunden senden (Zahlungsaufforderung)
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
        
        // 2. Notiz im System hinterlegen
        $order->add_order_note( '[Wunderkiste] Sofortige Mail wegen fehlgeschlagener Zahlung gesendet.' );

        // 3. NEU: Info-Mail an Admin senden
        $this->send_admin_info_mail( $order, 'failed_recovery' );
    }

    /**
     * HILFSFUNKTION: Admin Benachrichtigung senden
     */
    private function send_admin_info_mail( $order, $type ) {
        $to = get_option( 'admin_email' ); // Admin-Email aus Einstellungen
        $order_id = $order->get_id();
        $edit_link = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

        if ( 'failed_recovery' === $type ) {
            $subject = sprintf( __( '[Admin Info] Zahlung FEHLGESCHLAGEN: Bestellung #%s', 'woo-product-extras' ), $order_id );
            $message_intro = __( "die Zahlung für Bestellung #%s ist offiziell fehlgeschlagen (Status: Failed).", 'woo-product-extras' );
        } else {
            // pending_recovery
            $subject = sprintf( __( '[Admin Info] Zahlung NOCH AUSSTEHEND: Bestellung #%s', 'woo-product-extras' ), $order_id );
            $message_intro = __( "die Bestellung #%s ist seit 1 Stunde unbezahlt (Status: Pending).", 'woo-product-extras' );
        }

        $message = sprintf( 
            __( "Hallo Admin,\n\n%s\n\nDas System hat dem Kunden automatisch einen Link zur Zahlung gesendet.\n\nLink zur Bestellung: %s", 'woo-product-extras' ), 
            sprintf( $message_intro, $order_id ),
            $edit_link
        );
        
        wp_mail( $to, $subject, $message );
    }

    /**
     * SZENARIO C: Button registrieren
     */
    public function add_custom_order_action( $actions ) {
        $actions['wpe_send_payment_link'] = __( '➔ Zahlungslink per E-Mail senden (Wunderkiste)', 'woo-product-extras' );
        return $actions;
    }

    /**
     * SZENARIO C: Button ausführen
     */
    public function process_custom_order_action( $order ) {
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order->get_id() );
        $order->add_order_note( '[Wunderkiste] Zahlungslink manuell versendet.', false, true );
    }

    /**
     * E-Mail Inhalt anpassen: Gelbe Box mit Button und "Du"-Ansprache
     */
    public function add_custom_email_message( $order, $sent_to_admin, $plain_text, $email ) {
        // Wir wollen das nur in der "Customer Invoice" Mail (Zahlungsaufforderung)
        if ( 'customer_invoice' === $email->id && ! $sent_to_admin ) {
            
            // Zahlungslink abrufen
            $pay_url = $order->get_checkout_payment_url();

            echo '<div style="background:#fff3cd; color:#856404; padding:20px; border:1px solid #ffeeba; border-radius:5px; margin-bottom:20px; text-align:center;">';
            
            echo '<p style="font-size: 16px; margin-top:0;"><strong>' . esc_html__( 'Deine Bestellung auf Hagebaumarkt Schuberth war leider nicht erfolgreich.', 'woo-product-extras' ) . '</strong></p>';
            
            echo '<p>' . esc_html__( 'Es sieht so aus, als wäre der Zahlungsvorgang unterbrochen worden. Keine Sorge, deine Bestellung ist gespeichert.', 'woo-product-extras' ) . '</p>';
            
            echo '<p>' . esc_html__( 'Du kannst die Zahlung hier direkt nachholen:', 'woo-product-extras' ) . '</p>';

            // Der Button
            echo '<a href="' . esc_url( $pay_url ) . '" style="background-color:#d9534f; color:#ffffff; display:inline-block; padding:12px 24px; text-decoration:none; border-radius:4px; font-weight:bold; margin: 10px 0;">' . esc_html__( '➔ Jetzt bezahlen', 'woo-product-extras' ) . '</a>';

            echo '<p style="margin-bottom:0; font-size: 12px; color: #856404;">' . esc_html__( 'Die Details zu deiner Bestellung findest du unten.', 'woo-product-extras' ) . '</p>';
            
            echo '</div>';
        }
    }
}
