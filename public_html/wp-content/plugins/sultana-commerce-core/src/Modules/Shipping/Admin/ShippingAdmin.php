<?php

namespace Sultana\CommerceCore\Modules\Shipping\Admin;

use Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingAdmin
{
    private const MENU_SLUG = 'scc-shipping-settings';
    private const SAVE_ACTION = 'scc_save_shipping_settings';
    private const NONCE_ACTION = 'scc_shipping_settings';
    private const NONCE_NAME = 'scc_shipping_settings_nonce';

    public static function register(): void
    {
        add_action( 'admin_menu', [ self::class, 'register_menu' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ self::class, 'save_settings' ] );
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __( 'Sultana Shipping', 'sultana-commerce-core' ),
            __( 'Sultana Shipping', 'sultana-commerce-core' ),
            'manage_woocommerce',
            self::MENU_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    public static function render_page(): void
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para administrar envíos.', 'sultana-commerce-core' ) );
        }

        $repository = new ShippingSettingsRepository();
        $repository->ensure_defaults();

        $express       = array_replace_recursive( ShippingSettingsRepository::default_express_granada_settings(), $repository->express_granada_settings() );
        $store_pickup  = array_replace( ShippingSettingsRepository::default_store_pickup_settings(), $repository->store_pickup_settings() );
        $rates         = $repository->cargotrans_rates();
        $municipalities = $repository->cargotrans_municipalities();

        uasort(
            $municipalities,
            static function ( $first, $second ): int {
                $first_label  = is_array( $first ) ? (string) ( $first['label'] ?? '' ) : (string) $first;
                $second_label = is_array( $second ) ? (string) ( $second['label'] ?? '' ) : (string) $second;

                return strcasecmp( $first_label, $second_label );
            }
        );

        $schedule = self::first_schedule_range( $express['schedule'] ?? [] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Configuración de envíos', 'sultana-commerce-core' ); ?></h1>

            <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Configuración de envíos guardada.', 'sultana-commerce-core' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

                <h2><?php esc_html_e( 'Configuración general', 'sultana-commerce-core' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="scc-express-cost"><?php esc_html_e( 'Costo de Envío Express Granada', 'sultana-commerce-core' ); ?></label>
                            </th>
                            <td>
                                <input id="scc-express-cost" class="regular-text" type="number" min="0" step="0.01" name="express[cost]" value="<?php echo esc_attr( (string) ( $express['cost'] ?? 50 ) ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="scc-express-max-weight"><?php esc_html_e( 'Peso máximo Express', 'sultana-commerce-core' ); ?></label>
                            </th>
                            <td>
                                <input id="scc-express-max-weight" class="regular-text" type="number" min="0" step="0.01" name="express[max_weight]" value="<?php echo esc_attr( (string) ( $express['max_weight'] ?? 5 ) ); ?>">
                                <p class="description"><?php esc_html_e( 'Peso en kilogramos.', 'sultana-commerce-core' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Horario de atención', 'sultana-commerce-core' ); ?></th>
                            <td>
                                <label>
                                    <?php esc_html_e( 'Inicio', 'sultana-commerce-core' ); ?>
                                    <input type="time" name="express[schedule_from]" value="<?php echo esc_attr( $schedule['from'] ); ?>">
                                </label>
                                <label style="margin-left:1rem;">
                                    <?php esc_html_e( 'Fin', 'sultana-commerce-core' ); ?>
                                    <input type="time" name="express[schedule_to]" value="<?php echo esc_attr( $schedule['to'] ); ?>">
                                </label>
                                <p class="description"><?php esc_html_e( 'Se aplica de lunes a sábado para Envío Express Granada.', 'sultana-commerce-core' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="scc-store-pickup-branch"><?php esc_html_e( 'Nombre de la sucursal para Retiro en tienda', 'sultana-commerce-core' ); ?></label>
                            </th>
                            <td>
                                <input id="scc-store-pickup-branch" class="regular-text" type="text" name="store_pickup[branch_name]" value="<?php echo esc_attr( (string) ( $store_pickup['branch_name'] ?? 'Granada' ) ); ?>">
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Tarifas Cargotrans', 'sultana-commerce-core' ); ?></h2>
                <?php foreach ( $rates as $route_key => $route ) : ?>
                    <h3><?php echo esc_html( (string) ( $route['label'] ?? $route_key ) ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Peso mínimo', 'sultana-commerce-core' ); ?></th>
                                <th><?php esc_html_e( 'Peso máximo', 'sultana-commerce-core' ); ?></th>
                                <th><?php esc_html_e( 'Precio', 'sultana-commerce-core' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( (array) ( $route['brackets'] ?? [] ) as $index => $bracket ) : ?>
                                <tr>
                                    <td>
                                        <input type="number" min="0" step="0.01" name="rates[<?php echo esc_attr( (string) $route_key ); ?>][brackets][<?php echo esc_attr( (string) $index ); ?>][min]" value="<?php echo esc_attr( (string) ( $bracket['min'] ?? 0 ) ); ?>">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.01" name="rates[<?php echo esc_attr( (string) $route_key ); ?>][brackets][<?php echo esc_attr( (string) $index ); ?>][max]" value="<?php echo esc_attr( (string) ( $bracket['max'] ?? 0 ) ); ?>">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.01" name="rates[<?php echo esc_attr( (string) $route_key ); ?>][brackets][<?php echo esc_attr( (string) $index ); ?>][cost]" value="<?php echo esc_attr( (string) ( $bracket['cost'] ?? 0 ) ); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th colspan="2">
                                    <label for="scc-extra-kg-<?php echo esc_attr( (string) $route_key ); ?>"><?php esc_html_e( 'Kilo adicional', 'sultana-commerce-core' ); ?></label>
                                </th>
                                <td>
                                    <input id="scc-extra-kg-<?php echo esc_attr( (string) $route_key ); ?>" type="number" min="0" step="0.01" name="rates[<?php echo esc_attr( (string) $route_key ); ?>][extra_kg]" value="<?php echo esc_attr( (string) ( $route['extra_kg'] ?? 0 ) ); ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach; ?>

                <h2><?php esc_html_e( 'Municipios', 'sultana-commerce-core' ); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Municipio', 'sultana-commerce-core' ); ?></th>
                            <th><?php esc_html_e( 'Ruta asignada', 'sultana-commerce-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $municipalities as $municipality_key => $municipality ) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( self::municipality_label( (string) $municipality_key, $municipality ) ); ?>
                                </td>
                                <td>
                                    <select name="municipalities[<?php echo esc_attr( (string) $municipality_key ); ?>][route]">
                                        <?php foreach ( $rates as $route_key => $route ) : ?>
                                            <option value="<?php echo esc_attr( (string) $route_key ); ?>" <?php selected( self::municipality_route( $municipality ), (string) $route_key ); ?>>
                                                <?php echo esc_html( (string) ( $route['label'] ?? $route_key ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button( __( 'Guardar configuración de envíos', 'sultana-commerce-core' ) ); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings(): void
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos para administrar envíos.', 'sultana-commerce-core' ) );
        }

        check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

        $repository = new ShippingSettingsRepository();
        $repository->ensure_defaults();

        self::save_express_settings( $repository );
        self::save_store_pickup_settings( $repository );
        self::save_cargotrans_rates( $repository );
        self::save_municipalities( $repository );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'             => self::MENU_SLUG,
                    'settings-updated' => 'true',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private static function save_express_settings( ShippingSettingsRepository $repository ): void
    {
        $posted = isset( $_POST['express'] ) && is_array( $_POST['express'] )
            ? wp_unslash( $_POST['express'] )
            : [];

        $settings = array_replace_recursive( ShippingSettingsRepository::default_express_granada_settings(), $repository->express_granada_settings() );
        $from     = self::sanitize_time( (string) ( $posted['schedule_from'] ?? '08:00' ), '08:00' );
        $to       = self::sanitize_time( (string) ( $posted['schedule_to'] ?? '17:00' ), '17:00' );

        $settings['cost']       = self::sanitize_decimal( $posted['cost'] ?? $settings['cost'] );
        $settings['max_weight'] = self::sanitize_decimal( $posted['max_weight'] ?? $settings['max_weight'] );
        $settings['schedule']   = [
            1 => [ [ 'from' => $from, 'to' => $to ] ],
            2 => [ [ 'from' => $from, 'to' => $to ] ],
            3 => [ [ 'from' => $from, 'to' => $to ] ],
            4 => [ [ 'from' => $from, 'to' => $to ] ],
            5 => [ [ 'from' => $from, 'to' => $to ] ],
            6 => [ [ 'from' => $from, 'to' => $to ] ],
        ];

        update_option( ShippingSettingsRepository::EXPRESS_OPTION, $settings, false );
    }

    private static function save_store_pickup_settings( ShippingSettingsRepository $repository ): void
    {
        $posted = isset( $_POST['store_pickup'] ) && is_array( $_POST['store_pickup'] )
            ? wp_unslash( $_POST['store_pickup'] )
            : [];

        $settings = array_replace( ShippingSettingsRepository::default_store_pickup_settings(), $repository->store_pickup_settings() );
        $branch   = sanitize_text_field( (string) ( $posted['branch_name'] ?? $settings['branch_name'] ) );

        $settings['branch_name'] = '' !== $branch ? $branch : 'Granada';

        update_option( ShippingSettingsRepository::STORE_PICKUP_OPTION, $settings, false );
    }

    private static function save_cargotrans_rates( ShippingSettingsRepository $repository ): void
    {
        $posted = isset( $_POST['rates'] ) && is_array( $_POST['rates'] )
            ? wp_unslash( $_POST['rates'] )
            : [];

        $rates = $repository->cargotrans_rates();

        foreach ( $rates as $route_key => $route ) {
            if ( ! isset( $posted[ $route_key ] ) || ! is_array( $posted[ $route_key ] ) ) {
                continue;
            }

            $posted_route = $posted[ $route_key ];
            $brackets     = [];

            foreach ( (array) ( $route['brackets'] ?? [] ) as $index => $bracket ) {
                $posted_bracket = isset( $posted_route['brackets'][ $index ] ) && is_array( $posted_route['brackets'][ $index ] )
                    ? $posted_route['brackets'][ $index ]
                    : [];

                $brackets[] = [
                    'min'  => self::sanitize_decimal( $posted_bracket['min'] ?? $bracket['min'] ?? 0 ),
                    'max'  => self::sanitize_decimal( $posted_bracket['max'] ?? $bracket['max'] ?? 0 ),
                    'cost' => self::sanitize_decimal( $posted_bracket['cost'] ?? $bracket['cost'] ?? 0 ),
                ];
            }

            $last_bracket = end( $brackets );

            $rates[ $route_key ]['brackets']   = $brackets;
            $rates[ $route_key ]['extra_kg']   = self::sanitize_decimal( $posted_route['extra_kg'] ?? $route['extra_kg'] ?? 0 );
            $rates[ $route_key ]['max_weight'] = is_array( $last_bracket ) ? (float) $last_bracket['max'] : (float) ( $route['max_weight'] ?? 0 );
        }

        update_option( ShippingSettingsRepository::RATES_OPTION, $rates, false );
    }

    private static function save_municipalities( ShippingSettingsRepository $repository ): void
    {
        $posted = isset( $_POST['municipalities'] ) && is_array( $_POST['municipalities'] )
            ? wp_unslash( $_POST['municipalities'] )
            : [];

        $municipalities = $repository->cargotrans_municipalities();
        $routes         = array_keys( $repository->cargotrans_rates() );

        foreach ( $municipalities as $municipality_key => $municipality ) {
            $posted_route = isset( $posted[ $municipality_key ]['route'] )
                ? sanitize_key( (string) $posted[ $municipality_key ]['route'] )
                : self::municipality_route( $municipality );

            if ( in_array( $posted_route, $routes, true ) ) {
                $municipalities[ $municipality_key ] = [
                    'label' => self::municipality_label( (string) $municipality_key, $municipality ),
                    'route' => $posted_route,
                ];
            }
        }

        update_option( ShippingSettingsRepository::MUNICIPALITIES_OPTION, $municipalities, false );
    }

    private static function first_schedule_range( array $schedule ): array
    {
        foreach ( $schedule as $ranges ) {
            if ( isset( $ranges[0] ) && is_array( $ranges[0] ) ) {
                return [
                    'from' => self::sanitize_time( (string) ( $ranges[0]['from'] ?? '08:00' ), '08:00' ),
                    'to'   => self::sanitize_time( (string) ( $ranges[0]['to'] ?? '17:00' ), '17:00' ),
                ];
            }
        }

        return [
            'from' => '08:00',
            'to'   => '17:00',
        ];
    }

    private static function municipality_label( string $key, $municipality ): string
    {
        if ( is_array( $municipality ) ) {
            return (string) ( $municipality['label'] ?? $key );
        }

        return ucwords( str_replace( '-', ' ', $key ) );
    }

    private static function municipality_route( $municipality ): string
    {
        if ( is_array( $municipality ) ) {
            return (string) ( $municipality['route'] ?? '' );
        }

        return (string) $municipality;
    }

    private static function sanitize_time( string $value, string $fallback ): string
    {
        return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
    }

    private static function sanitize_decimal( $value ): float
    {
        $value = str_replace( ',', '.', sanitize_text_field( (string) $value ) );

        return max( 0, round( (float) $value, 2 ) );
    }
}
