<?php

namespace Sultana\CommerceCore\Modules\Emails;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AsyncAdminOrderEmails
{
    private const ACTION = 'scc_send_async_admin_new_order_email';
    private const GROUP = 'scc-emails';
    private const SCHEDULED_META_KEY = '_scc_async_admin_new_order_email_scheduled';
    private const SENT_META_KEY = '_scc_async_admin_new_order_email_sent';

    private static bool $sending_async_new_order = false;
    private static bool $async_mail_succeeded = false;
    private static array $async_mail_errors = [];

    public static function register(): void
    {
        add_filter( 'woocommerce_email_enabled_new_order', [ self::class, 'maybe_disable_sync_new_order_email' ], 20, 3 );
        add_filter( 'woocommerce_payment_successful_result', [ self::class, 'schedule_new_order_email' ], 20, 2 );
        add_action( self::ACTION, [ self::class, 'send_new_order_email' ], 10, 1 );
    }

    public static function maybe_disable_sync_new_order_email( bool $enabled, $order = null, $email = null ): bool
    {
        if ( self::order_meta_is_yes( $order, self::SENT_META_KEY ) ) {
            return false;
        }

        if ( self::$sending_async_new_order ) {
            return $enabled;
        }

        if ( self::is_checkout_ajax() && self::action_scheduler_is_available() ) {
            return false;
        }

        return $enabled;
    }

    public static function schedule_new_order_email( $result, int $order_id )
    {
        if ( self::should_schedule_from_result( $result ) ) {
            self::schedule_order( $order_id );
        }

        return $result;
    }

    public static function send_new_order_email( int $order_id ): void
    {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof WC_Order ) {
            return;
        }

        if ( 'yes' === $order->get_meta( self::SENT_META_KEY, true ) ) {
            return;
        }

        $email = self::new_order_email();

        if ( ! $email || ! is_callable( [ $email, 'trigger' ] ) ) {
            throw new \RuntimeException( 'WC_Email_New_Order is not available.' );
        }

        self::$sending_async_new_order = true;
        self::$async_mail_succeeded = false;
        self::$async_mail_errors = [];

        add_action( 'wp_mail_succeeded', [ self::class, 'mark_async_mail_succeeded' ], PHP_INT_MAX, 1 );
        add_action( 'wp_mail_failed', [ self::class, 'mark_async_mail_failed' ], PHP_INT_MAX, 1 );

        try {
            $email->trigger( $order->get_id(), $order );
        } finally {
            remove_action( 'wp_mail_succeeded', [ self::class, 'mark_async_mail_succeeded' ], PHP_INT_MAX );
            remove_action( 'wp_mail_failed', [ self::class, 'mark_async_mail_failed' ], PHP_INT_MAX );
            self::$sending_async_new_order = false;
        }

        if ( ! self::$async_mail_succeeded ) {
            throw new \RuntimeException(
                'Async new order email was not sent.' . ( self::$async_mail_errors ? ' Errors: ' . implode( ',', self::$async_mail_errors ) : '' )
            );
        }

        $order->update_meta_data( self::SENT_META_KEY, 'yes' );
        $order->save();
    }

    public static function mark_async_mail_succeeded( array $mail_data ): void
    {
        if ( self::$sending_async_new_order ) {
            self::$async_mail_succeeded = true;
        }
    }

    public static function mark_async_mail_failed( \WP_Error $error ): void
    {
        if ( self::$sending_async_new_order ) {
            self::$async_mail_errors = array_merge( self::$async_mail_errors, $error->get_error_codes() );
        }
    }

    private static function schedule_order( int $order_id ): void
    {
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof WC_Order || ! self::action_scheduler_is_available() ) {
            return;
        }

        if ( 'yes' === $order->get_meta( self::SENT_META_KEY, true ) || 'yes' === $order->get_meta( self::SCHEDULED_META_KEY, true ) ) {
            return;
        }

        $args = [
            'order_id' => $order->get_id(),
        ];

        if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION, $args, self::GROUP ) ) {
            return;
        }

        $action_id = as_enqueue_async_action( self::ACTION, $args, self::GROUP, true );

        if ( $action_id ) {
            $order->update_meta_data( self::SCHEDULED_META_KEY, 'yes' );
            $order->save();
        }
    }

    private static function should_schedule_from_result( $result ): bool
    {
        return self::is_checkout_ajax()
            && self::action_scheduler_is_available()
            && is_array( $result )
            && 'success' === (string) ( $result['result'] ?? '' );
    }

    private static function new_order_email()
    {
        if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
            return null;
        }

        $emails = WC()->mailer()->get_emails();

        return is_array( $emails ) ? ( $emails['WC_Email_New_Order'] ?? null ) : null;
    }

    private static function is_checkout_ajax(): bool
    {
        return function_exists( 'wp_doing_ajax' )
            && wp_doing_ajax()
            && isset( $_GET['wc-ajax'] )
            && 'checkout' === (string) wp_unslash( $_GET['wc-ajax'] );
    }

    private static function action_scheduler_is_available(): bool
    {
        return function_exists( 'as_enqueue_async_action' );
    }

    private static function order_id( $order ): int
    {
        return is_object( $order ) && is_callable( [ $order, 'get_id' ] )
            ? absint( $order->get_id() )
            : absint( $order );
    }

    private static function order_meta_is_yes( $order, string $meta_key ): bool
    {
        if ( ! $order instanceof WC_Order ) {
            $order_id = self::order_id( $order );
            $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        }

        return $order instanceof WC_Order && 'yes' === $order->get_meta( $meta_key, true );
    }
}
