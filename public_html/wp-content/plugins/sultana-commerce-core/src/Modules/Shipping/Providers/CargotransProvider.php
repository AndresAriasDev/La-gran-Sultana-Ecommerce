<?php

namespace Sultana\CommerceCore\Modules\Shipping\Providers;

use Sultana\CommerceCore\Modules\Shipping\Contracts\ShippingProviderInterface;
use Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository;
use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CargotransProvider implements ShippingProviderInterface
{
    private ShippingSettingsRepository $settings;

    public function __construct( ShippingSettingsRepository $settings )
    {
        $this->settings = $settings;
    }

    public function get_rates( ShippingContext $context ): array
    {
        $municipality = ShippingSettingsRepository::normalize_location_key( $context->municipality() );

        if ( '' === $municipality ) {
            return [];
        }

        $municipalities = $this->settings->cargotrans_municipalities();
        $municipality_data = $municipalities[ $municipality ] ?? '';
        $route             = is_array( $municipality_data )
            ? (string) ( $municipality_data['route'] ?? '' )
            : (string) $municipality_data;

        if ( '' === $route ) {
            return [];
        }

        $route_rates = $this->settings->cargotrans_rates()[ $route ] ?? [];
        $cost        = $this->calculate_cost( $context->weight(), $route_rates );

        if ( null === $cost ) {
            return [];
        }

        return [
            [
                'id'    => 'scc_cargotrans',
                'label' => __( 'Cargotrans', 'sultana-commerce-core' ),
                'cost'  => $cost,
                'meta'  => [
                    __( 'Origen', 'sultana-commerce-core' ) => __( 'Sucursal Granada', 'sultana-commerce-core' ),
                    __( 'Ruta', 'sultana-commerce-core' )   => sanitize_text_field( $route_rates['label'] ?? $route ),
                ],
            ],
        ];
    }

    private function calculate_cost( float $weight, array $route_rates ): ?float
    {
        $weight = max( 0.01, $weight );

        foreach ( $route_rates['brackets'] ?? [] as $bracket ) {
            $max = (float) ( $bracket['max'] ?? 0 );

            if ( $max > 0 && $weight <= $max ) {
                return (float) ( $bracket['cost'] ?? 0 );
            }
        }

        $max_weight = (float) ( $route_rates['max_weight'] ?? 0 );
        $extra_kg   = (float) ( $route_rates['extra_kg'] ?? 0 );
        $brackets   = $route_rates['brackets'] ?? [];
        $last       = is_array( $brackets ) ? end( $brackets ) : false;

        if ( $max_weight <= 0 || $extra_kg <= 0 || ! is_array( $last ) || $weight <= $max_weight ) {
            return null;
        }

        return (float) ( $last['cost'] ?? 0 ) + ( ceil( $weight - $max_weight ) * $extra_kg );
    }
}
