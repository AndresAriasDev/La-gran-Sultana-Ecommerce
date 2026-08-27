<?php

namespace Sultana\CommerceCore\Modules\Emails;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AsyncStockEmails
{
    private const ACTION = 'scc_send_async_stock_email';
    private const GROUP = 'scc-emails';
    private const TYPE_LOW_STOCK = 'low_stock';
    private const TYPE_NO_STOCK = 'no_stock';

    private static bool $sending_async_stock_email = false;
    private static bool $async_mail_succeeded = false;
    private static array $async_mail_errors = [];

    public static function register(): void
    {
        add_action( 'woocommerce_low_stock', [ self::class, 'schedule_low_stock_email' ], 0, 1 );
        add_action( 'woocommerce_no_stock', [ self::class, 'schedule_no_stock_email' ], 0, 1 );
        add_filter( 'woocommerce_email_recipient_low_stock', [ self::class, 'maybe_disable_sync_recipient' ], 20, 2 );
        add_filter( 'woocommerce_email_recipient_no_stock', [ self::class, 'maybe_disable_sync_recipient' ], 20, 2 );
        add_action( self::ACTION, [ self::class, 'send_stock_email' ], 10, 2 );
    }

    public static function schedule_low_stock_email( $product ): void
    {
        self::schedule_stock_email( self::TYPE_LOW_STOCK, $product );
    }

    public static function schedule_no_stock_email( $product ): void
    {
        self::schedule_stock_email( self::TYPE_NO_STOCK, $product );
    }

    public static function maybe_disable_sync_recipient( string $recipient, $product ): string
    {
        if ( self::should_defer_checkout_stock_email() ) {
            return '';
        }

        return $recipient;
    }

    public static function send_stock_email( string $type, int $product_id ): void
    {
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $mailer = self::mailer();
        $method = self::mailer_method( $type );

        if ( ! self::native_notification_is_enabled( $type ) ) {
            return;
        }

        if ( ! $mailer || '' === $method || ! is_callable( [ $mailer, $method ] ) ) {
            throw new \RuntimeException( 'WooCommerce stock email handler is not available.' );
        }

        self::$sending_async_stock_email = true;
        self::$async_mail_succeeded = false;
        self::$async_mail_errors = [];

        add_action( 'wp_mail_succeeded', [ self::class, 'mark_async_mail_succeeded' ], PHP_INT_MAX, 1 );
        add_action( 'wp_mail_failed', [ self::class, 'mark_async_mail_failed' ], PHP_INT_MAX, 1 );

        try {
            $mailer->{$method}( $product );
        } finally {
            remove_action( 'wp_mail_succeeded', [ self::class, 'mark_async_mail_succeeded' ], PHP_INT_MAX );
            remove_action( 'wp_mail_failed', [ self::class, 'mark_async_mail_failed' ], PHP_INT_MAX );
            self::$sending_async_stock_email = false;
        }

        if ( ! self::$async_mail_succeeded ) {
            throw new \RuntimeException(
                'Async stock email was not sent.' . ( self::$async_mail_errors ? ' Errors: ' . implode( ',', self::$async_mail_errors ) : '' )
            );
        }
    }

    public static function mark_async_mail_succeeded( array $mail_data ): void
    {
        if ( self::$sending_async_stock_email ) {
            self::$async_mail_succeeded = true;
        }
    }

    public static function mark_async_mail_failed( \WP_Error $error ): void
    {
        if ( self::$sending_async_stock_email ) {
            self::$async_mail_errors = array_merge( self::$async_mail_errors, $error->get_error_codes() );
        }
    }

    private static function schedule_stock_email( string $type, $product ): void
    {
        if ( ! self::should_defer_checkout_stock_email() || ! $product instanceof WC_Product || ! self::native_notification_is_enabled( $type ) ) {
            return;
        }

        self::remove_sync_stock_email_handler( $type );

        $args = [
            $type,
            $product->get_id(),
        ];

        if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION, $args, self::GROUP ) ) {
            return;
        }

        as_enqueue_async_action( self::ACTION, $args, self::GROUP, true );
    }

    private static function remove_sync_stock_email_handler( string $type ): void
    {
        $mailer = self::mailer();
        $hook = self::stock_hook( $type );
        $method = self::mailer_method( $type );

        if ( ! $mailer || '' === $hook || '' === $method ) {
            return;
        }

        remove_action( $hook, [ $mailer, $method ], 10 );
    }

    private static function should_defer_checkout_stock_email(): bool
    {
        return self::is_checkout_ajax()
            && self::action_scheduler_is_available()
            && ! self::$sending_async_stock_email;
    }

    private static function native_notification_is_enabled( string $type ): bool
    {
        $option = '';

        if ( self::TYPE_LOW_STOCK === $type ) {
            $option = 'woocommerce_notify_low_stock';
        }

        if ( self::TYPE_NO_STOCK === $type ) {
            $option = 'woocommerce_notify_no_stock';
        }

        return '' !== $option
            && 'yes' === get_option( $option, 'yes' )
            && '' !== trim( (string) get_option( 'woocommerce_stock_email_recipient', get_option( 'admin_email' ) ) );
    }

    private static function mailer()
    {
        return function_exists( 'WC' ) && WC()->mailer() ? WC()->mailer() : null;
    }

    private static function stock_hook( string $type ): string
    {
        if ( self::TYPE_LOW_STOCK === $type ) {
            return 'woocommerce_low_stock';
        }

        if ( self::TYPE_NO_STOCK === $type ) {
            return 'woocommerce_no_stock';
        }

        return '';
    }

    private static function mailer_method( string $type ): string
    {
        if ( self::TYPE_LOW_STOCK === $type ) {
            return 'low_stock';
        }

        if ( self::TYPE_NO_STOCK === $type ) {
            return 'no_stock';
        }

        return '';
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
}
