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
     * Hilfsfunktion: Sprache abrufen
     */
    private function get_language() {
        $options = get_option( 'wpe_options', array() );
        return isset( $options['plugin_language'] ) && $options['plugin_language'] === 'en' ? 'en' : 'de';
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
        $order = wc_get_order( $order_id ); 

        if ( ! $order ) return;

        // Wir prüfen, ob die Bestellung IMMER NOCH "pending" ist
        if ( $order->has_status( 'pending' ) ) {
            // 1. Sende die "Customer Invoice / Order Details" Mail (enthält Zahlungslink)
            WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
            
            // 2. Notiz an der Bestellung hinterlassen
            $order->add_order_note( '[Wunderkiste] Automatische Erinnerungs-Mail nach 1 Std. gesendet.' );

            // 3. Info-Mail an Admin senden
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

        // 3. Info-Mail an Admin senden
        $this->send_admin_info_mail( $order, 'failed_recovery' );
    }

    /**
     * HILFSFUNKTION: Admin Benachrichtigung senden
     */
    private function send_admin_info_mail( $order, $type ) {
        $to = get_option( 'admin_email' ); 
        $order_id = $order->get_id();
        $edit_link = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
        $lang = $this->get_language();

        if ( 'failed_recovery' === $type ) {
            if ( $lang === 'en' ) {
                $subject = sprintf( '[Admin Info] Payment FAILED: Order #%s', $order_id );
                $message_intro = sprintf( 'the payment for order #%s has officially failed (Status: Failed).', $order_id );
            } else {
                $subject = sprintf( '[Admin Info] Zahlung FEHLGESCHLAGEN: Bestellung #%s', $order_id );
                $message_intro = sprintf( 'die Zahlung für Bestellung #%s ist offiziell fehlgeschlagen (Status: Failed).', $order_id );
            }
        } else {
            // pending_recovery
            if ( $lang === 'en' ) {
                $subject = sprintf( '[Admin Info] Payment STILL PENDING: Order #%s', $order_id );
                $message_intro = sprintf( 'the order #%s has been unpaid for 1 hour (Status: Pending).', $order_id );
            } else {
                $subject = sprintf( '[Admin Info] Zahlung NOCH AUSSTEHEND: Bestellung #%s', $order_id );
                $message_intro = sprintf( 'die Bestellung #%s ist seit 1 Stunde unbezahlt (Status: Pending).', $order_id );
            }
        }

        if ( $lang === 'en' ) {
            $message = sprintf( 
                "Hello Admin,\n\n%s\n\nThe system has automatically sent the customer a payment link.\n\nLink to order: %s", 
                $message_intro,
                $edit_link
            );
        } else {
            $message = sprintf( 
                "Hallo Admin,\n\n%s\n\nDas System hat dem Kunden automatisch einen Link zur Zahlung gesendet.\n\nLink zur Bestellung: %s", 
                $message_intro,
                $edit_link
            );
        }
        
        wp_mail( $to, $subject, $message );
    }

    /**
     * SZENARIO C: Button registrieren
     */
    public function add_custom_order_action( $actions ) {
        $lang = $this->get_language();
        
        if ( $lang === 'en' ) {
            $actions['wpe_send_payment_link'] = '➔ Send payment link via Email (Wunderkiste)';
        } else {
            $actions['wpe_send_payment_link'] = '➔ Zahlungslink per E-Mail senden (Wunderkiste)';
        }
        return $actions;
    }

    /**
     * SZENARIO C: Button ausführen
     */
    public function process_custom_order_action( $order ) {
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order->get_id() );
        $order->add_order_note( '[Wunderkiste] Payment link sent manually.', false, true );
    }

    /**
     * E-Mail Inhalt anpassen: Gelbe Box mit Button
     */
    public function add_custom_email_message( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'customer_invoice' === $email->id && ! $sent_to_admin ) {
            
            $pay_url = $order->get_checkout_payment_url();
            $shop_name = get_bloginfo( 'name' );
            $lang = $this->get_language();

            echo '<div style="background:#fff3cd; color:#856404; padding:20px; border:1px solid #ffeeba; border-radius:5px; margin-bottom:20px; text-align:center;">';
            
            echo '<p style="font-size: 16px; margin-top:0;"><strong>';
            if ( $lang === 'en' ) {
                printf( 'Your order at %s was unfortunately not successful.', esc_html( $shop_name ) );
            } else {
                printf( 'Deine Bestellung bei %s war leider nicht erfolgreich.', esc_html( $shop_name ) );
            }
            echo '</strong></p>';
            
            echo '<p>';
            if ( $lang === 'en' ) {
                echo 'It looks like the payment process was interrupted. No worries, your order is saved.';
            } else {
                echo 'Es sieht so aus, als wäre der Zahlungsvorgang unterbrochen worden. Keine Sorge, deine Bestellung ist gespeichert.';
            }
            echo '</p>';
            
            echo '<p>';
            if ( $lang === 'en' ) {
                echo 'You can retry the payment directly here:';
            } else {
                echo 'Du kannst die Zahlung hier direkt nachholen:';
            }
            echo '</p>';

            // Der Button
            $btn_text = ( $lang === 'en' ) ? '➔ Pay now' : '➔ Jetzt bezahlen';
            
            echo '<a href="' . esc_url( $pay_url ) . '" style="background-color:#d9534f; color:#ffffff; display:inline-block; padding:12px 24px; text-decoration:none; border-radius:4px; font-weight:bold; margin: 10px 0;">' . esc_html( $btn_text ) . '</a>';

            echo '<p style="margin-bottom:0; font-size: 12px; color: #856404;">';
            if ( $lang === 'en' ) {
                echo 'The details of your order can be found below.';
            } else {
                echo 'Die Details zu deiner Bestellung findest du unten.';
            }
            echo '</p>';
            
            echo '</div>';
        }
    }
}
