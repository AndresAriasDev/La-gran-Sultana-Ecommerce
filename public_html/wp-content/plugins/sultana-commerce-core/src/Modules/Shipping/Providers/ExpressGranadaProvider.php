<?php

namespace Sultana\CommerceCore\Modules\Shipping\Providers;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Sultana\CommerceCore\Modules\Shipping\Contracts\ShippingProviderInterface;
use Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository;
use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExpressGranadaProvider implements ShippingProviderInterface
{
    private ShippingSettingsRepository $settings;

    public function __construct( ShippingSettingsRepository $settings )
    {
        $this->settings = $settings;
    }

    public function get_rates( ShippingContext $context ): array
    {
        $settings     = $this->settings->express_granada_settings();
        $municipality = ShippingSettingsRepository::normalize_location_key( $context->municipality() );
        $target       = ShippingSettingsRepository::normalize_location_key( (string) ( $settings['municipality'] ?? 'granada' ) );
        $max_weight   = (float) ( $settings['max_weight'] ?? 0 );

        if ( empty( $settings['enabled'] ) || $municipality !== $target ) {
            return [];
        }

        if ( $max_weight > 0 && $context->weight() > $max_weight ) {
            return [];
        }

        return [
            [
                'id'    => 'scc_express_granada',
                'label' => __( 'Envío Express Granada centro y alrededores', 'sultana-commerce-core' ),
                'cost'  => (float) ( $settings['cost'] ?? 50 ),
                'meta'  => [
                    __( 'Entrega', 'sultana-commerce-core' ) => __( 'Motorizado local', 'sultana-commerce-core' ),
                ],
            ],
        ];
    }

    private function is_open_now( array $settings ): bool
    {
        try {
            $timezone = new DateTimeZone( (string) ( $settings['timezone'] ?? 'America/Managua' ) );
            $now      = new DateTimeImmutable( 'now', $timezone );
        } catch ( Exception $exception ) {
            return false;
        }

        $day      = (int) $now->format( 'N' );
        $time     = $now->format( 'H:i' );
        $schedule = $settings['schedule'][ $day ] ?? [];

        if ( ! is_array( $schedule ) ) {
            return false;
        }

        foreach ( $schedule as $range ) {
            $from = (string) ( $range['from'] ?? '' );
            $to   = (string) ( $range['to'] ?? '' );

            if ( $from <= $time && $time <= $to ) {
                return true;
            }
        }

        return false;
    }
}
