<?php
/**
 * Order Recovery Module
 *
 * Handles failed payments and abandoned payment processes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPE_Order_Recovery {

    /**
     * Constructor
     */
    public function __construct() {
        // Scenario A: Check after 1 hour on "Pending Payment"
        add_action( 'woocommerce_order_status_pending', array( $this, 'schedule_pending_check' ), 10, 2 );
        add_action( 'wpe_check_pending_order_event', array( $this, 'check_pending_order_status' ) );

        // Scenario B: Immediate email on "Failed" status
        add_action( 'woocommerce_order_status_failed', array( $this, 'send_recovery_email_immediately' ), 10, 2 );

        // Scenario C: Manual button in order overview
        add_filter( 'woocommerce_order_actions', array( $this, 'add_custom_order_action' ) );
        add_action( 'woocommerce_order_action_wpe_send_payment_link', array( $this, 'process_custom_order_action' ) );

        // Customize email text (optional, to make it friendlier)
        add_action( 'woocommerce_email_before_order_table', array( $this, 'add_custom_email_message' ), 10, 4 );
    }

    /**
     * SCENARIO A: Schedule cron job (1 hour)
     */
    public function schedule_pending_check( $order_id, $order ) {
        if ( ! wp_next_scheduled( 'wpe_check_pending_order_event', array( $order_id ) ) ) {
            // Schedule event in 1 hour (3600 seconds)
            wp_schedule_single_event( time() + 3600, 'wpe_check_pending_order_event', array( $order_id ) );
        }
    }

    /**
     * SCENARIO A: Execute cron job
     * + Admin info email
     */
    public function check_pending_order_status( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check if order is STILL "pending"
        if ( $order->has_status( 'pending' ) ) {
            // 1. Send the "Customer Invoice / Order Details" email (contains payment link)
            WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );

            // 2. Add note to order
            $order->add_order_note( __( '[eCommerce Wunderkiste] Automatic reminder email sent after 1 hour.', 'ecommerce-wunderkiste' ) );

            // 3. Send admin info email
            $this->send_admin_info_mail( $order, 'pending_recovery' );
        }
    }

    /**
     * SCENARIO B: Immediate on "Failed"
     * + Admin info email
     */
    public function send_recovery_email_immediately( $order_id, $order ) {
        // 1. Send email to customer (payment request)
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );

        // 2. Add system note
        $order->add_order_note( __( '[eCommerce Wunderkiste] Immediate email sent due to failed payment.', 'ecommerce-wunderkiste' ) );

        // 3. Send admin info email
        $this->send_admin_info_mail( $order, 'failed_recovery' );
    }

    /**
     * HELPER: Send admin notification
     */
    private function send_admin_info_mail( $order, $type ) {
        $to = get_option( 'admin_email' );
        $order_id = $order->get_id();
        $edit_link = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

        if ( 'failed_recovery' === $type ) {
            /* translators: %s: order ID */
            $subject = sprintf( __( '[Admin Info] Payment FAILED: Order #%s', 'ecommerce-wunderkiste' ), $order_id );
            /* translators: %s: order ID */
            $message_intro = sprintf( __( 'the payment for order #%s has officially failed (Status: Failed).', 'ecommerce-wunderkiste' ), $order_id );
        } else {
            // pending_recovery
            /* translators: %s: order ID */
            $subject = sprintf( __( '[Admin Info] Payment STILL PENDING: Order #%s', 'ecommerce-wunderkiste' ), $order_id );
            /* translators: %s: order ID */
            $message_intro = sprintf( __( 'order #%s has been unpaid for 1 hour (Status: Pending).', 'ecommerce-wunderkiste' ), $order_id );
        }

        /* translators: %1$s: message intro, %2$s: edit link */
        $message = sprintf(
            __( "Hello Admin,\n\n%1\$s\n\nThe system has automatically sent a payment link to the customer.\n\nOrder link: %2\$s", 'ecommerce-wunderkiste' ),
            $message_intro,
            $edit_link
        );

        wp_mail( $to, $subject, $message );
    }

    /**
     * SCENARIO C: Register button
     */
    public function add_custom_order_action( $actions ) {
        $actions['wpe_send_payment_link'] = __( '➔ Send payment link via email (eCommerce Wunderkiste)', 'ecommerce-wunderkiste' );
        return $actions;
    }

    /**
     * SCENARIO C: Execute button
     */
    public function process_custom_order_action( $order ) {
        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order->get_id() );
        $order->add_order_note( __( '[eCommerce Wunderkiste] Payment link sent manually.', 'ecommerce-wunderkiste' ), false, true );
    }

    /**
     * Customize email content: Yellow box with button
     */
    public function add_custom_email_message( $order, $sent_to_admin, $plain_text, $email ) {
        // Only in the "Customer Invoice" email (payment request)
        if ( 'customer_invoice' === $email->id && ! $sent_to_admin ) {

            // Get payment link
            $pay_url = $order->get_checkout_payment_url();

            echo '<div style="background:#fff3cd; color:#856404; padding:20px; border:1px solid #ffeeba; border-radius:5px; margin-bottom:20px; text-align:center;">';

            echo '<p style="font-size: 16px; margin-top:0;"><strong>' . esc_html__( 'Your order was unfortunately not successful.', 'ecommerce-wunderkiste' ) . '</strong></p>';

            echo '<p>' . esc_html__( 'It looks like the payment process was interrupted. No worries, your order is saved.', 'ecommerce-wunderkiste' ) . '</p>';

            echo '<p>' . esc_html__( 'You can complete the payment here:', 'ecommerce-wunderkiste' ) . '</p>';

            // The button
            echo '<a href="' . esc_url( $pay_url ) . '" style="background-color:#d9534f; color:#ffffff; display:inline-block; padding:12px 24px; text-decoration:none; border-radius:4px; font-weight:bold; margin: 10px 0;">' . esc_html__( '➔ Pay now', 'ecommerce-wunderkiste' ) . '</a>';

            echo '<p style="margin-bottom:0; font-size: 12px; color: #856404;">' . esc_html__( 'You can find your order details below.', 'ecommerce-wunderkiste' ) . '</p>';

            echo '</div>';
        }
    }
}
