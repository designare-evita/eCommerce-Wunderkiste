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
     */
    public function check_pending_order_status( $order_id ) {
        $order = wc_get_product( $order_id ); // Fallback
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) return;

        // Wir prüfen, ob die Bestellung IMMER NOCH "pending" ist
        if ( $order->has_status( 'pending' ) ) {
            // Sende die "Customer Invoice / Order Details" Mail (enthält Zahlungslink)
            WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
            
            // Notiz an der Bestellung hinterlassen
            $order->add_order_note( '[Wunderkiste] Automatische Erinnerungs-Mail nach 1 Std. gesendet.' );
        }
    }

    /**
     * SZENARIO B: Sofort bei "Failed"
     */
    public function send_recovery_email_immediately( $order_id, $order ) {
        // Prüfen, ob wir das nicht gerade erst manuell gesendet haben, um Loop zu vermeiden
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
        $order->add_order_note( '[Wunderkiste] Sofortige Mail wegen fehlgeschlagener Zahlung gesendet.' );
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
     * E-Mail Inhalt anpassen (optional)
     * Fügt einen freundlichen Text hinzu, wenn es sich um die Zahlungsaufforderung handelt
     */
    public function add_custom_email_message( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'customer_invoice' === $email->id && ! $sent_to_admin ) {
            echo '<p style="background:#fff3cd; color:#856404; padding:10px; border:1px solid #ffeeba; border-radius:3px;">';
            echo '<strong>Hoppla, da hat etwas nicht geklappt!</strong><br>';
            echo 'Es sieht so aus, als wäre der Zahlungsvorgang unterbrochen worden. ';
            echo 'Keine Sorge, Ihre Bestellung ist gespeichert. Sie können die Zahlung hier direkt nachholen:';
            echo '</p>';
        }
    }
}
