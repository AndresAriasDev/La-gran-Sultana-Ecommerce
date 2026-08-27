<?php

namespace Sultana\CommerceCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TEMPORAL 2026-08: checkout performance instrumentation.
 *
 * Remove this class and its call sites after measuring production checkout latency.
 */
class CheckoutPerformanceLogger
{
    private static float $request_start = 0.0;
    private static array $markers = [];

    public static function register(): void
    {
        add_action( 'woocommerce_before_checkout_process', [ self::class, 'mark_checkout_start' ], 0, 0 );
        add_action( 'woocommerce_checkout_order_created', [ self::class, 'mark_order_created_start' ], 0, 0 );
        add_action( 'woocommerce_checkout_order_created', [ self::class, 'log_order_created_duration' ], PHP_INT_MAX, 0 );
        add_action( 'woocommerce_checkout_order_processed', [ self::class, 'mark_order_processed_start' ], 0, 0 );
        add_action( 'woocommerce_checkout_order_processed', [ self::class, 'log_order_processed_duration' ], PHP_INT_MAX, 0 );
        add_filter( 'woocommerce_payment_successful_result', [ self::class, 'log_gateway_result_start' ], PHP_INT_MIN, 2 );
        add_filter( 'woocommerce_payment_successful_result', [ self::class, 'log_gateway_result_duration' ], PHP_INT_MAX, 2 );
        add_action( 'woocommerce_reduce_order_stock', [ self::class, 'mark_reduce_order_stock_start' ], 0, 1 );
        add_action( 'woocommerce_reduce_order_stock', [ self::class, 'log_reduce_order_stock_duration' ], PHP_INT_MAX, 1 );
        add_action( 'woocommerce_order_status_changed', [ self::class, 'mark_order_status_changed_start' ], 0, 4 );
        add_action( 'woocommerce_order_status_changed', [ self::class, 'log_order_status_changed_duration' ], PHP_INT_MAX, 4 );
        add_action( 'woocommerce_payment_complete', [ self::class, 'mark_payment_complete_start' ], 0, 1 );
        add_action( 'woocommerce_payment_complete', [ self::class, 'log_payment_complete_duration' ], PHP_INT_MAX, 1 );
        add_filter( 'pre_wp_mail', [ self::class, 'mark_wp_mail_start' ], PHP_INT_MIN, 2 );
        add_action( 'wp_mail_succeeded', [ self::class, 'log_wp_mail_succeeded' ], PHP_INT_MAX, 1 );
        add_action( 'wp_mail_failed', [ self::class, 'log_wp_mail_failed' ], PHP_INT_MAX, 1 );
        add_action( 'shutdown', [ self::class, 'log_shutdown_total' ], PHP_INT_MAX );
    }

    public static function enabled(): bool
    {
        return function_exists( 'wp_doing_ajax' )
            && wp_doing_ajax()
            && isset( $_GET['wc-ajax'] )
            && 'checkout' === (string) wp_unslash( $_GET['wc-ajax'] );
    }

    public static function start(): float
    {
        return microtime( true );
    }

    public static function log_duration( string $label, float $start, array $context = [] ): void
    {
        if ( ! self::enabled() ) {
            return;
        }

        self::log( $label, ( microtime( true ) - $start ) * 1000, $context );
    }

    public static function log( string $label, float $duration_ms, array $context = [] ): void
    {
        if ( ! self::enabled() ) {
            return;
        }

        $message = sprintf(
            '[SCC Checkout Perf] %s | %.2f ms',
            $label,
            $duration_ms
        );

        if ( ! empty( $context ) ) {
            $message .= ' | ' . wp_json_encode( $context );
        }

        error_log( $message );
    }

    public static function mark_checkout_start(): void
    {
        if ( ! self::enabled() ) {
            return;
        }

        self::$request_start = self::start();
        self::log( 'checkout:start', 0.0 );
    }

    public static function mark_order_created_start(): void
    {
        self::mark( 'order_created' );
    }

    public static function log_order_created_duration(): void
    {
        self::log_since_mark( 'hook:woocommerce_checkout_order_created', 'order_created' );
    }

    public static function mark_order_processed_start(): void
    {
        self::mark( 'order_processed' );
    }

    public static function log_order_processed_duration(): void
    {
        self::log_since_mark( 'hook:woocommerce_checkout_order_processed', 'order_processed' );
        self::log_total_checkout_duration();
        self::mark( 'after_order_processed' );
    }

    public static function log_gateway_result_start( $result, int $order_id )
    {
        self::log_since_mark(
            'checkout:after_order_processed_until_payment_result',
            'after_order_processed',
            [
                'order_id' => $order_id,
            ]
        );
        self::mark( 'payment_successful_result' );

        return $result;
    }

    public static function log_gateway_result_duration( $result, int $order_id )
    {
        self::log_since_mark(
            'hook:woocommerce_payment_successful_result',
            'payment_successful_result',
            [
                'order_id' => $order_id,
            ]
        );

        return $result;
    }

    public static function mark_reduce_order_stock_start( $order ): void
    {
        self::mark( self::order_hook_key( 'reduce_order_stock', $order ) );
    }

    public static function log_reduce_order_stock_duration( $order ): void
    {
        self::log_since_mark(
            'hook:woocommerce_reduce_order_stock',
            self::order_hook_key( 'reduce_order_stock', $order ),
            self::order_context( $order )
        );
    }

    public static function mark_order_status_changed_start( int $order_id, string $from, string $to, $order ): void
    {
        self::mark( self::status_hook_key( $order_id, $from, $to ) );
    }

    public static function log_order_status_changed_duration( int $order_id, string $from, string $to, $order ): void
    {
        self::log_since_mark(
            'hook:woocommerce_order_status_changed',
            self::status_hook_key( $order_id, $from, $to ),
            [
                'order_id' => $order_id,
                'from'     => $from,
                'to'       => $to,
            ]
        );
    }

    public static function mark_payment_complete_start( int $order_id ): void
    {
        self::mark( 'payment_complete:' . $order_id );
    }

    public static function log_payment_complete_duration( int $order_id ): void
    {
        self::log_since_mark(
            'hook:woocommerce_payment_complete',
            'payment_complete:' . $order_id,
            [
                'order_id' => $order_id,
            ]
        );
    }

    public static function mark_wp_mail_start( $return, array $atts )
    {
        if ( ! self::enabled() ) {
            return $return;
        }

        self::$markers['wp_mail'][] = [
            'start'   => self::start(),
            'context' => [
                'subject'  => sanitize_text_field( (string) ( $atts['subject'] ?? '' ) ),
                'to_count' => self::mail_recipient_count( $atts['to'] ?? [] ),
            ],
        ];

        return $return;
    }

    public static function log_wp_mail_succeeded( array $mail_data ): void
    {
        self::log_wp_mail_result( 'mail:any_wp_mail_succeeded', true );
    }

    public static function log_wp_mail_failed( \WP_Error $error ): void
    {
        self::log_wp_mail_result(
            'mail:any_wp_mail_failed',
            false,
            [
                'error_codes' => $error->get_error_codes(),
            ]
        );
    }

    public static function log_shutdown_total(): void
    {
        if ( ! self::enabled() || self::$request_start <= 0 ) {
            return;
        }

        self::log_duration( 'checkout:total_until_shutdown', self::$request_start );
    }

    private static function mark( string $key ): void
    {
        if ( ! self::enabled() ) {
            return;
        }

        self::$markers[ $key ] = self::start();
    }

    private static function log_since_mark( string $label, string $key, array $context = [] ): void
    {
        if ( ! self::enabled() || empty( self::$markers[ $key ] ) ) {
            return;
        }

        self::log_duration( $label, self::$markers[ $key ], $context );
        unset( self::$markers[ $key ] );
    }

    private static function log_total_checkout_duration(): void
    {
        if ( ! self::enabled() || self::$request_start <= 0 ) {
            return;
        }

        self::log_duration( 'checkout:total_until_order_processed', self::$request_start );
    }

    private static function log_wp_mail_result( string $label, bool $sent, array $extra_context = [] ): void
    {
        if ( ! self::enabled() || empty( self::$markers['wp_mail'] ) || ! is_array( self::$markers['wp_mail'] ) ) {
            return;
        }

        $entry = array_pop( self::$markers['wp_mail'] );

        if ( ! is_array( $entry ) || empty( $entry['start'] ) ) {
            return;
        }

        $context = is_array( $entry['context'] ?? null ) ? $entry['context'] : [];
        $context = array_merge(
            $context,
            [
                'sent' => $sent,
            ],
            $extra_context
        );

        self::log_duration( $label, (float) $entry['start'], $context );
    }

    private static function mail_recipient_count( $to ): int
    {
        if ( is_array( $to ) ) {
            return count( $to );
        }

        return '' === trim( (string) $to ) ? 0 : count( array_filter( array_map( 'trim', explode( ',', (string) $to ) ) ) );
    }

    private static function order_hook_key( string $prefix, $order ): string
    {
        return $prefix . ':' . self::order_id( $order );
    }

    private static function status_hook_key( int $order_id, string $from, string $to ): string
    {
        return 'order_status_changed:' . $order_id . ':' . $from . ':' . $to;
    }

    private static function order_context( $order ): array
    {
        $order_id = self::order_id( $order );

        return $order_id > 0
            ? [
                'order_id' => $order_id,
            ]
            : [];
    }

    private static function order_id( $order ): int
    {
        return is_object( $order ) && is_callable( [ $order, 'get_id' ] )
            ? absint( $order->get_id() )
            : absint( $order );
    }
}
