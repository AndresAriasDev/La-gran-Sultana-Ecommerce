<?php

namespace Sultana\CommerceCore\Modules\Coupons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountCoupons
{
    public const ENDPOINT = 'cupones';

    public static function register(): void
    {
        add_action( 'init', [ self::class, 'register_endpoint' ] );
        add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
        add_filter( 'woocommerce_account_menu_items', [ self::class, 'add_account_menu_item' ], 13 );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ self::class, 'render_account_endpoint' ] );
    }

    public static function register_endpoint(): void
    {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        $option_key = 'scc_account_coupons_endpoint_version';
        $version    = ( defined( 'SCC_VERSION' ) ? SCC_VERSION : '1' ) . '-account-coupons-v1';

        if ( get_option( $option_key ) === $version ) {
            return;
        }

        flush_rewrite_rules();
        update_option( $option_key, $version, false );
    }

    public static function add_account_menu_item( array $items ): array
    {
        if ( isset( $items[ self::ENDPOINT ] ) ) {
            return $items;
        }

        $new_items = [];

        foreach ( $items as $endpoint => $label ) {
            if ( 'customer-logout' === $endpoint ) {
                $new_items[ self::ENDPOINT ] = __( 'Cupones', 'sultana-commerce-core' );
            }

            $new_items[ $endpoint ] = $label;
        }

        return $new_items;
    }

    public static function render_account_endpoint(): void
    {
        wc_get_template(
            'myaccount/coupons.php',
            [
                'coupons' => self::get_available_coupons_for_user( get_current_user_id() ),
            ],
            '',
            self::get_plugin_template_path()
        );
    }

    public static function get_available_coupons_for_user( int $user_id ): array
    {
        if ( $user_id <= 0 || ! class_exists( '\WC_Coupon' ) ) {
            return [];
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user instanceof \WP_User ) {
            return [];
        }

        $coupons = [];

        foreach ( self::get_native_coupons() as $coupon ) {
            if ( ! $coupon instanceof \WC_Coupon ) {
                continue;
            }

            if ( ! self::is_coupon_published( $coupon ) || self::is_coupon_expired( $coupon ) ) {
                continue;
            }

            if ( ! self::coupon_matches_user_email( $coupon, (string) $user->user_email ) ) {
                continue;
            }

            $coupons[] = self::prepare_coupon_data( $coupon );
        }

        return $coupons;
    }

    public static function user_has_available_coupons( int $user_id ): bool
    {
        if ( $user_id <= 0 || ! class_exists( '\WC_Coupon' ) ) {
            return false;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user instanceof \WP_User ) {
            return false;
        }

        foreach ( self::get_native_coupons() as $coupon ) {
            if ( ! $coupon instanceof \WC_Coupon ) {
                continue;
            }

            if ( ! self::is_coupon_published( $coupon ) || self::is_coupon_expired( $coupon ) ) {
                continue;
            }

            if ( ! self::coupon_matches_user_email( $coupon, (string) $user->user_email ) ) {
                continue;
            }

            return true;
        }

        return false;
    }

    public static function has_public_available_coupons(): bool
    {
        if ( ! class_exists( '\WC_Coupon' ) ) {
            return false;
        }

        foreach ( self::get_native_coupons() as $coupon ) {
            if ( ! $coupon instanceof \WC_Coupon ) {
                continue;
            }

            if ( ! self::is_coupon_published( $coupon ) || self::is_coupon_expired( $coupon ) ) {
                continue;
            }

            if ( self::coupon_has_email_restrictions( $coupon ) ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function get_native_coupons(): array
    {
        if ( function_exists( 'wc_get_coupons' ) ) {
            $coupons = wc_get_coupons(
                [
                    'status'  => 'publish',
                    'limit'   => -1,
                    'orderby' => 'date',
                    'order'   => 'DESC',
                ]
            );

            return is_array( $coupons ) ? $coupons : [];
        }

        $posts = get_posts(
            [
                'post_type'      => 'shop_coupon',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]
        );

        return array_map(
            static function ( $post_id ) {
                return new \WC_Coupon( absint( $post_id ) );
            },
            $posts
        );
    }

    private static function get_plugin_template_path(): string
    {
        return defined( 'SCC_PLUGIN_PATH' ) ? trailingslashit( SCC_PLUGIN_PATH ) . 'templates/' : '';
    }

    private static function is_coupon_published( \WC_Coupon $coupon ): bool
    {
        $post_id = $coupon->get_id();

        return $post_id > 0 && 'publish' === get_post_status( $post_id );
    }

    private static function is_coupon_expired( \WC_Coupon $coupon ): bool
    {
        $expires = $coupon->get_date_expires();

        return $expires && $expires->getTimestamp() < time();
    }

    private static function coupon_matches_user_email( \WC_Coupon $coupon, string $user_email ): bool
    {
        $user_email = strtolower( sanitize_email( $user_email ) );

        if ( '' === $user_email || ! method_exists( $coupon, 'get_email_restrictions' ) ) {
            return '' !== $user_email;
        }

        $restrictions = $coupon->get_email_restrictions();
        $restrictions = is_array( $restrictions ) ? array_filter( array_map( 'trim', $restrictions ) ) : [];

        if ( empty( $restrictions ) ) {
            return true;
        }

        foreach ( $restrictions as $restriction ) {
            if ( self::email_matches_restriction( $user_email, (string) $restriction ) ) {
                return true;
            }
        }

        return false;
    }

    private static function coupon_has_email_restrictions( \WC_Coupon $coupon ): bool
    {
        if ( ! method_exists( $coupon, 'get_email_restrictions' ) ) {
            return false;
        }

        $restrictions = $coupon->get_email_restrictions();
        $restrictions = is_array( $restrictions ) ? array_filter( array_map( 'trim', $restrictions ) ) : [];

        return ! empty( $restrictions );
    }

    private static function email_matches_restriction( string $user_email, string $restriction ): bool
    {
        $restriction = strtolower( trim( $restriction ) );

        if ( '' === $restriction ) {
            return false;
        }

        if ( $user_email === $restriction ) {
            return true;
        }

        if ( false === strpos( $restriction, '*' ) ) {
            return false;
        }

        $pattern = '/^' . str_replace( '\*', '.*', preg_quote( $restriction, '/' ) ) . '$/i';

        return 1 === preg_match( $pattern, $user_email );
    }

    private static function prepare_coupon_data( \WC_Coupon $coupon ): array
    {
        $expires = $coupon->get_date_expires();

        return [
            'id'                 => $coupon->get_id(),
            'code'               => $coupon->get_code(),
            'description'        => $coupon->get_description(),
            'discount_type'      => $coupon->get_discount_type(),
            'discount_type_name' => self::get_discount_type_label( $coupon->get_discount_type() ),
            'amount'             => $coupon->get_amount(),
            'amount_html'        => self::format_coupon_amount( $coupon ),
            'expires'            => $expires ? $expires->date_i18n( get_option( 'date_format' ) ) : '',
        ];
    }

    private static function get_discount_type_label( string $discount_type ): string
    {
        $types = function_exists( 'wc_get_coupon_types' ) ? wc_get_coupon_types() : [];

        return isset( $types[ $discount_type ] )
            ? (string) $types[ $discount_type ]
            : $discount_type;
    }

    private static function format_coupon_amount( \WC_Coupon $coupon ): string
    {
        $amount = (float) $coupon->get_amount();

        if ( 'percent' === $coupon->get_discount_type() ) {
            return sprintf(
                /* translators: %s: coupon percentage amount. */
                __( '%s%%', 'sultana-commerce-core' ),
                wc_format_decimal( $amount, 0 )
            );
        }

        return function_exists( 'wc_price' )
            ? wc_price( $amount )
            : (string) $amount;
    }
}
