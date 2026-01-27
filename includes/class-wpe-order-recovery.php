<?php
/**
 * Order Recovery Modul
 *
 * Behandelt fehlgeschlagene Zahlungen und abgebrochene Zahlungsvorgänge.
 * 
 * ÄNDERUNGEN:
 * - Hook geändert von 'woocommerce_order_status_pending' zu 'woocommerce_checkout_order_created'
 *   (Der alte Hook feuerte nicht bei initialer Bestellerstellung, nur bei Statuswechsel)
 * - Zusätzlicher Hook 'woocommerce_thankyou' als Fallback
 * - Kontaktadresse: geizhals@schuberth.at
 * - Debug-Logging hinzugefügt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Order_Recovery {

    /**
     * Kontakt-E-Mail für Kundenanfragen
     */
    private $contact_email = 'geizhals@schuberth.at';

    public function __construct() {
        // Szenario A: Check nach 1 Stunde bei "Zahlung ausstehend"
        // GEÄNDERT: Nutze checkout_order_created statt order_status_pending
        add_action( 'woocommerce_checkout_order_created', array( $this, 'schedule_pending_check_on_create' ), 10, 1 );
        
        // Fallback: Auch bei thankyou-Page prüfen (falls Bestellung dort erst finalisiert wird)
        add_action( 'woocommerce_thankyou', array( $this, 'schedule_pending_check_on_thankyou' ), 10, 1 );
        
        // Cron-Event Handler
        add_action( 'wpe_check_pending_order_event', array( $this, 'check_pending_order_status' ) );

        // Szenario B: Sofort-Mail bei Status "Fehlgeschlagen"
        add_action( 'woocommerce_order_status_failed', array( $this, 'send_recovery_email_immediately' ), 10, 2 );

        // Szenario C: Manueller Button in der Bestellübersicht
        add_filter( 'woocommerce_order_actions', array( $this, 'add_custom_order_action' ) );
        add_action( 'woocommerce_order_action_wpe_send_payment_link', array( $this, 'process_custom_order_action' ) );

        // E-Mail Text anpassen
        add_action( 'woocommerce_email_before_order_table', array( $this, 'add_custom_email_message' ), 10, 4 );
        
        // Cron-Intervall registrieren (falls noch nicht vorhanden)
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
    }

    /**
     * Hilfsfunktion: Sprache abrufen
     */
    private function get_language() {
        $options = get_option( 'wpe_options', array() );
        return isset( $options['plugin_language'] ) && $options['plugin_language'] === 'en' ? 'en' : 'de';
    }

    /**
     * Cron-Intervall hinzufügen (optional, für zukünftige Nutzung)
     */
    public function add_cron_interval( $schedules ) {
        $schedules['wpe_hourly'] = array(
            'interval' => 3600,
            'display'  => 'Einmal pro Stunde (WPE)',
        );
        return $schedules;
    }

    /**
     * SZENARIO A (NEU): Cronjob bei Bestellerstellung einplanen
     */
    public function schedule_pending_check_on_create( $order ) {
        if ( ! $order ) {
            return;
        }
        
        $order_id = $order->get_id();
        
        // Nur planen wenn Status pending oder on-hold ist
        if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
            $this->log_debug( 'Order #' . $order_id . ' - Status ist nicht pending/on-hold, kein Cron geplant.' );
            return;
        }
        
        $this->schedule_pending_check( $order_id );
    }

    /**
     * SZENARIO A (Fallback): Cronjob bei Thankyou-Page einplanen
     */
    public function schedule_pending_check_on_thankyou( $order_id ) {
        if ( ! $order_id ) {
            return;
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        // Nur planen wenn Status pending ist und noch kein Event existiert
        if ( ! $order->has_status( 'pending' ) ) {
            return;
        }
        
        $this->schedule_pending_check( $order_id );
    }

    /**
     * Hilfsfunktion: Cron-Event planen
     */
    private function schedule_pending_check( $order_id ) {
        // Prüfen ob bereits geplant
        if ( wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) ) ) {
            $this->log_debug( 'Order #' . $order_id . ' - Cron bereits geplant.' );
            return;
        }
        
        // Event in 1 Stunde (3600 Sekunden) einplanen
        $scheduled_time = time() + 3600;
        $result = wp_schedule_single_event( $scheduled_time, 'wpe_check_pending_order_event', array( $order_id ) );
        
        if ( $result ) {
            $this->log_debug( 'Order #' . $order_id . ' - Cron geplant für ' . date( 'Y-m-d H:i:s', $scheduled_time ) );
        } else {
            $this->log_debug( 'Order #' . $order_id . ' - FEHLER: Cron konnte nicht geplant werden!' );
        }
    }

    /**
     * SZENARIO A: Cronjob ausführen
     */
    public function check_pending_order_status( $order_id ) {
        $order = wc_get_order( $order_id ); 

        if ( ! $order ) {
            $this->log_debug( 'Order #' . $order_id . ' - Bestellung nicht gefunden.' );
            return;
        }

        $current_status = $order->get_status();
        $this->log_debug( 'Order #' . $order_id . ' - Cron ausgeführt. Aktueller Status: ' . $current_status );

        // Prüfen ob die Bestellung IMMER NOCH "pending" ist
        if ( $order->has_status( 'pending' ) ) {
            // 1. Sende die "Customer Invoice / Order Details" Mail (enthält Zahlungslink)
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            if ( isset( $emails['WC_Email_Customer_Invoice'] ) ) {
                $emails['WC_Email_Customer_Invoice']->trigger( $order_id );
                $this->log_debug( 'Order #' . $order_id . ' - Erinnerungs-Mail an Kunden gesendet.' );
            } else {
                $this->log_debug( 'Order #' . $order_id . ' - FEHLER: WC_Email_Customer_Invoice nicht verfügbar!' );
            }
            
            // 2. Notiz an der Bestellung hinterlassen
            $order->add_order_note( '[Wunderkiste] Automatische Erinnerungs-Mail nach 1 Std. gesendet.' );

            // 3. Info-Mail an Admin senden
            $this->send_admin_info_mail( $order, 'pending_recovery' );
        } else {
            $this->log_debug( 'Order #' . $order_id . ' - Status ist nicht mehr pending (' . $current_status . '), keine Mail gesendet.' );
        }
    }

    /**
     * SZENARIO B: Sofort bei "Failed"
     */
    public function send_recovery_email_immediately( $order_id, $order ) {
        $this->log_debug( 'Order #' . $order_id . ' - Status auf FAILED gewechselt.' );
        
        // 1. Mail an Kunden senden (Zahlungsaufforderung)
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if ( isset( $emails['WC_Email_Customer_Invoice'] ) ) {
            $emails['WC_Email_Customer_Invoice']->trigger( $order_id );
            $this->log_debug( 'Order #' . $order_id . ' - Sofort-Mail an Kunden gesendet.' );
        }
        
        // 2. Notiz im System hinterlegen
        $order->add_order_note( '[Wunderkiste] Sofortige Mail wegen fehlgeschlagener Zahlung gesendet.' );

        // 3. Info-Mail an Admin senden
        $this->send_admin_info_mail( $order, 'failed_recovery' );
        
        // 4. Geplantes Pending-Event löschen (falls vorhanden)
        $timestamp = wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wpe_check_pending_order_event', array( $order_id ) );
            $this->log_debug( 'Order #' . $order_id . ' - Geplantes Pending-Event gelöscht.' );
        }
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
            $contact_email = $this->contact_email;

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
            
            // Kontaktinformation hinzufügen
            echo '<p style="margin-top: 15px; font-size: 12px; color: #856404; border-top: 1px solid #ffeeba; padding-top: 10px;">';
            if ( $lang === 'en' ) {
                printf( 'Questions? Contact us at: <a href="mailto:%s" style="color:#856404;">%s</a>', esc_attr( $contact_email ), esc_html( $contact_email ) );
            } else {
                printf( 'Fragen? Kontaktiere uns unter: <a href="mailto:%s" style="color:#856404;">%s</a>', esc_attr( $contact_email ), esc_html( $contact_email ) );
            }
            echo '</p>';
            
            echo '</div>';
        }
    }

    /**
     * Debug-Logging Hilfsfunktion
     */
    private function log_debug( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( '[WPE Order Recovery] ' . $message );
        }
    }
}
