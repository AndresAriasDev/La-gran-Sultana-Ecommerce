<?php

namespace Sultana\CommerceCore\Modules\Shipping\Providers;

use Sultana\CommerceCore\Modules\Shipping\Contracts\ShippingProviderInterface;
use Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository;
use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StorePickupProvider implements ShippingProviderInterface
{
    private ShippingSettingsRepository $settings;

    public function __construct( ShippingSettingsRepository $settings )
    {
        $this->settings = $settings;
    }

    public function get_rates( ShippingContext $context ): array
    {
        $department   = ShippingSettingsRepository::normalize_location_key( $context->department() );
        $municipality = ShippingSettingsRepository::normalize_location_key( $context->municipality() );

        if ( 'granada' !== $department || 'granada' !== $municipality ) {
            return [];
        }

        $settings    = array_replace( ShippingSettingsRepository::default_store_pickup_settings(), $this->settings->store_pickup_settings() );
        $branch_name = sanitize_text_field( (string) ( $settings['branch_name'] ?? 'Granada' ) );

        return [
            [
                'id'    => 'scc_store_pickup',
                'label' => sprintf(
                    /* translators: %s: store pickup branch name. */
                    __( 'Retiro en tienda — %s', 'sultana-commerce-core' ),
                    $branch_name
                ),
                'cost'  => 0,
                'meta'  => [
                    __( 'Entrega', 'sultana-commerce-core' ) => sprintf(
                        /* translators: %s: store pickup branch name. */
                        __( 'Recoge personalmente tu pedido en nuestra tienda de %s. No incluye ningún costo de envío.', 'sultana-commerce-core' ),
                        $branch_name
                    ),
                ],
            ],
        ];
    }
}
