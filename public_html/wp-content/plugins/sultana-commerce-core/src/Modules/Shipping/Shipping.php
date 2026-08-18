<?php

namespace Sultana\CommerceCore\Modules\Shipping;

use Sultana\CommerceCore\Modules\Shipping\Providers\CargotransProvider;
use Sultana\CommerceCore\Modules\Shipping\Providers\ExpressGranadaProvider;
use Sultana\CommerceCore\Modules\Shipping\Providers\StorePickupProvider;
use Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository;
use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;
use WC_Product;
use WC_Shipping_Rate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shipping
{
    public static function register(): void
    {
        add_action( 'init', [ self::class, 'ensure_settings' ] );
        add_filter( 'woocommerce_package_rates', [ self::class, 'replace_package_rates' ], 100, 2 );
        add_filter( 'woocommerce_cart_no_shipping_available_html', [ self::class, 'missing_municipality_shipping_message' ] );
        add_filter( 'woocommerce_no_shipping_available_html', [ self::class, 'missing_municipality_shipping_message' ] );
        add_filter( 'woocommerce_cart_shipping_packages', [ self::class, 'apply_gift_shipping_destination_to_packages' ], 20 );
        add_filter( 'woocommerce_cart_shipping_method_full_label', [ self::class, 'add_shipping_method_description' ], 10, 2 );
        add_filter( 'woocommerce_shipping_chosen_method', [ self::class, 'choose_default_shipping_method' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_checkout_refresh_script' ] );
    }

    public static function activate(): void
    {
        self::ensure_settings();
    }

    public static function ensure_settings(): void
    {
        ( new ShippingSettingsRepository() )->ensure_defaults();
    }

    public static function replace_package_rates( array $rates, array $package ): array
    {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $rates;
        }

        $repository = new ShippingSettingsRepository();
        $engine     = new ShippingEngine(
            [
                new CargotransProvider( $repository ),
                new ExpressGranadaProvider( $repository ),
                new StorePickupProvider( $repository ),
            ]
        );

        $context = self::build_context( $package );

        if ( self::is_missing_municipality( $context->municipality() ) ) {
            return [];
        }

        $engine_rates = $engine->calculate_rates( $context );
        $engine_rates = self::filter_gift_shipping_rates( $engine_rates, $context );
        $engine_rates = self::sort_rates_for_context( $engine_rates, $context );
        $custom_rates = [];

        foreach ( $engine_rates as $rate ) {
            $shipping_rate = new WC_Shipping_Rate(
                $rate['id'],
                $rate['label'],
                $rate['cost'],
                [],
                $rate['id']
            );

            foreach ( $rate['meta'] ?? [] as $key => $value ) {
                $shipping_rate->add_meta_data( $key, $value );
            }

            $custom_rates[ $rate['id'] ] = $shipping_rate;
        }

        return $custom_rates;
    }

    public static function apply_gift_shipping_destination_to_packages( array $packages ): array
    {
        $destination = self::gift_shipping_destination();

        if ( empty( $destination['state'] ) && empty( $destination['city'] ) ) {
            return $packages;
        }

        foreach ( $packages as $index => $package ) {
            $packages[ $index ]['destination']['country']  = 'NI';
            $packages[ $index ]['destination']['state']    = sanitize_text_field( (string) ( $destination['state'] ?? '' ) );
            $packages[ $index ]['destination']['city']     = sanitize_text_field( (string) ( $destination['city'] ?? '' ) );
            $packages[ $index ]['destination']['postcode'] = '';
        }

        return $packages;
    }

    public static function add_shipping_method_description( string $label, $method ): string
    {
        $gift = self::gift_context();

        if ( self::shipping_rate_matches( $method, 'scc_express_granada' ) ) {
            $description = empty( $gift['owner_name'] )
                ? __( 'Disponible para Granada centro y barrios aledaños. Después de confirmar tu pago, enviaremos tu pedido con motorizado en horario laboral de 9:00 a. m. a 5:00 p. m.', 'sultana-commerce-core' )
                : __( 'Enviaremos el pedido con motorizado en horario laboral de 9:00 a. m. a 5:00 p. m.', 'sultana-commerce-core' );

            return sprintf(
                '%s <small class="scc-shipping-method-description">%s</small>',
                $label,
                esc_html( $description )
            );
        }

        if ( self::shipping_rate_matches( $method, 'scc_cargotrans' ) ) {
            $description = empty( $gift['owner_name'] )
                ? __( 'Te enviaremos tu pedido a la dirección registrada mediante Cargotrans.', 'sultana-commerce-core' )
                : sprintf(
                    /* translators: %s: gift recipient name. */
                    __( 'Enviaremos el pedido a la dirección asociada a %s mediante Cargotrans.', 'sultana-commerce-core' ),
                    sanitize_text_field( (string) $gift['owner_name'] )
                );

            return sprintf(
                '%s <small class="scc-shipping-method-description">%s</small>',
                $label,
                esc_html( $description )
            );
        }

        if ( self::shipping_rate_matches( $method, 'scc_store_pickup' ) ) {
            $repository  = new ShippingSettingsRepository();
            $settings    = array_replace( ShippingSettingsRepository::default_store_pickup_settings(), $repository->store_pickup_settings() );
            $branch_name = sanitize_text_field( (string) ( $settings['branch_name'] ?? 'Granada' ) );

            return sprintf(
                '%s <small class="scc-shipping-method-description">%s</small>',
                $label,
                esc_html(
                    sprintf(
                        /* translators: %s: store pickup branch name. */
                        __( 'Recoge personalmente tu pedido en nuestra tienda de %s. No incluye ningún costo de envío.', 'sultana-commerce-core' ),
                        $branch_name
                    )
                )
            );
        }

        return $label;
    }

    public static function choose_default_shipping_method( $chosen_method, array $available_methods ): string
    {
        $chosen_method = (string) $chosen_method;

        if ( [] === $available_methods || self::chosen_method_is_available( $chosen_method, $available_methods ) ) {
            return $chosen_method;
        }

        $granada_rates_are_available = '' !== self::find_available_rate_id( $available_methods, 'scc_express_granada' )
            && '' !== self::find_available_rate_id( $available_methods, 'scc_store_pickup' );

        if ( self::customer_destination_is_granada() || $granada_rates_are_available ) {
            foreach ( self::granada_shipping_priority() as $method_id ) {
                $rate_id = self::find_available_rate_id( $available_methods, $method_id );

                if ( '' !== $rate_id ) {
                    return $rate_id;
                }
            }
        }

        $cargotrans_rate_id = self::find_available_rate_id( $available_methods, 'scc_cargotrans' );

        if ( '' !== $cargotrans_rate_id && ! self::customer_municipality_is_granada() ) {
            return $cargotrans_rate_id;
        }

        return (string) array_key_first( $available_methods );
    }

    public static function missing_municipality_shipping_message( string $message ): string
    {
        if ( self::customer_municipality_is_missing() ) {
            return esc_html__( 'Selecciona un municipio para calcular el envío.', 'sultana-commerce-core' );
        }

        return $message;
    }

    public static function enqueue_checkout_refresh_script(): void
    {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
            return;
        }

        $gift = self::gift_context();

        wp_add_inline_script(
            'wc-checkout',
            sprintf(
                'window.sccCheckoutHasGift=%s;jQuery(function($){$(document.body).on("change", "#billing_state, #billing_city, #shipping_state, #shipping_city", function(){ if(window.sccCheckoutHasGift){return;} $(document.body).trigger("update_checkout"); });});',
                ! empty( $gift['owner_id'] ) ? 'true' : 'false'
            )
        );
    }

    private static function build_context( array $package ): ShippingContext
    {
        $destination = $package['destination'] ?? [];
        $gift_destination = self::gift_shipping_destination();

        if ( ! empty( $gift_destination['state'] ) || ! empty( $gift_destination['city'] ) ) {
            $destination['state'] = $gift_destination['state'] ?? '';
            $destination['city']  = $gift_destination['city'] ?? '';
        }

        return new ShippingContext(
            $package,
            self::calculate_package_weight( $package ),
            sanitize_text_field( $destination['state'] ?? '' ),
            sanitize_text_field( $destination['city'] ?? '' )
        );
    }

    private static function filter_gift_shipping_rates( array $rates, ShippingContext $context ): array
    {
        $gift = self::gift_context();

        if ( empty( $gift['owner_id'] ) ) {
            return $rates;
        }

        $department   = ShippingSettingsRepository::normalize_location_key( $context->department() );
        $municipality = ShippingSettingsRepository::normalize_location_key( $context->municipality() );
        $allowed_id   = ( 'granada' === $department && 'granada' === $municipality )
            ? 'scc_express_granada'
            : 'scc_cargotrans';

        return array_values(
            array_filter(
                $rates,
                static function ( array $rate ) use ( $allowed_id ): bool {
                    return $allowed_id === (string) ( $rate['id'] ?? '' );
                }
            )
        );
    }

    private static function sort_rates_for_context( array $rates, ShippingContext $context ): array
    {
        $department   = ShippingSettingsRepository::normalize_location_key( $context->department() );
        $municipality = ShippingSettingsRepository::normalize_location_key( $context->municipality() );

        if ( 'granada' !== $department || 'granada' !== $municipality ) {
            return $rates;
        }

        $priority = array_flip( self::granada_shipping_priority() );

        uasort(
            $rates,
            static function ( array $left, array $right ) use ( $priority ): int {
                $left_id  = (string) ( $left['id'] ?? '' );
                $right_id = (string) ( $right['id'] ?? '' );

                return ( $priority[ $left_id ] ?? PHP_INT_MAX ) <=> ( $priority[ $right_id ] ?? PHP_INT_MAX );
            }
        );

        return array_values( $rates );
    }

    /**
     * @return array<int, string>
     */
    private static function granada_shipping_priority(): array
    {
        return [
            'scc_express_granada',
            'scc_store_pickup',
            'scc_cargotrans',
        ];
    }

    private static function gift_shipping_destination(): array
    {
        $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

        if ( ! class_exists( $wishlist_class ) || ! method_exists( $wishlist_class, 'get_cart_gift_shipping_destination' ) ) {
            return [];
        }

        $destination = $wishlist_class::get_cart_gift_shipping_destination();

        return is_array( $destination ) ? $destination : [];
    }

    private static function gift_context(): array
    {
        $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

        if ( ! class_exists( $wishlist_class ) || ! method_exists( $wishlist_class, 'get_cart_gift_notice_context' ) ) {
            return [];
        }

        $gift = $wishlist_class::get_cart_gift_notice_context();

        return is_array( $gift ) ? $gift : [];
    }

    private static function customer_municipality_is_missing(): bool
    {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $woocommerce = WC();

        if ( ! $woocommerce || ! $woocommerce->customer ) {
            return false;
        }

        $municipality = $woocommerce->customer->get_shipping_city() ?: $woocommerce->customer->get_billing_city();

        return self::is_missing_municipality( (string) $municipality );
    }

    private static function customer_municipality_is_granada(): bool
    {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $woocommerce = WC();

        if ( ! $woocommerce || ! $woocommerce->customer ) {
            return false;
        }

        $municipality = $woocommerce->customer->get_shipping_city() ?: $woocommerce->customer->get_billing_city();

        return 'granada' === ShippingSettingsRepository::normalize_location_key( (string) $municipality );
    }

    private static function customer_destination_is_granada(): bool
    {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        $woocommerce = WC();

        if ( ! $woocommerce || ! $woocommerce->customer ) {
            return false;
        }

        $department   = $woocommerce->customer->get_shipping_state() ?: $woocommerce->customer->get_billing_state();
        $municipality = $woocommerce->customer->get_shipping_city() ?: $woocommerce->customer->get_billing_city();

        return 'granada' === ShippingSettingsRepository::normalize_location_key( (string) $department )
            && 'granada' === ShippingSettingsRepository::normalize_location_key( (string) $municipality );
    }

    private static function chosen_method_is_available( string $chosen_method, array $available_methods ): bool
    {
        if ( '' === $chosen_method || isset( $available_methods[ $chosen_method ] ) ) {
            return '' !== $chosen_method;
        }

        foreach ( $available_methods as $method ) {
            if ( self::shipping_rate_matches( $method, $chosen_method ) ) {
                return true;
            }
        }

        return false;
    }

    private static function find_available_rate_id( array $available_methods, string $method_id ): string
    {
        foreach ( $available_methods as $rate_id => $method ) {
            if ( $method_id === (string) $rate_id || self::shipping_rate_matches( $method, $method_id ) ) {
                return (string) $rate_id;
            }
        }

        return '';
    }

    private static function shipping_rate_matches( $method, string $method_id ): bool
    {
        if ( ! is_object( $method ) ) {
            return false;
        }

        if ( method_exists( $method, 'get_id' ) && $method_id === (string) $method->get_id() ) {
            return true;
        }

        if ( method_exists( $method, 'get_method_id' ) && $method_id === (string) $method->get_method_id() ) {
            return true;
        }

        return false;
    }

    private static function is_missing_municipality( string $municipality ): bool
    {
        $key = ShippingSettingsRepository::normalize_location_key( $municipality );

        if ( '' === $key ) {
            return true;
        }

        return in_array(
            $key,
            [
                'selecciona-un-municipio',
                'selecciona-municipio',
                'seleccione-un-municipio',
                'municipio',
                'select-a-city',
            ],
            true
        );
    }

    private static function calculate_package_weight( array $package ): float
    {
        $weight = 0.0;

        foreach ( $package['contents'] ?? [] as $cart_item ) {
            $product  = $cart_item['data'] ?? null;
            $quantity = (int) ( $cart_item['quantity'] ?? 0 );

            if ( ! $product instanceof WC_Product || $quantity <= 0 ) {
                continue;
            }

            $product_weight = (float) $product->get_weight();

            if ( $product_weight <= 0 ) {
                continue;
            }

            $weight += (float) wc_get_weight( $product_weight, 'kg' ) * $quantity;
        }

        return round( $weight, 3 );
    }
}
