<?php

namespace Sultana\CommerceCore\Modules\Shipping;

use Sultana\CommerceCore\Modules\Shipping\Contracts\ShippingProviderInterface;
use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingEngine
{
    /**
     * @var array<int, ShippingProviderInterface>
     */
    private array $providers;

    public function __construct( array $providers )
    {
        $this->providers = $providers;
    }

    /**
     * @return array<int, array{id:string,label:string,cost:float,meta?:array<string,string>}>
     */
    public function calculate_rates( ShippingContext $context ): array
    {
        $rates = [];

        foreach ( $this->providers as $provider ) {
            foreach ( $provider->get_rates( $context ) as $rate ) {
                $rates[] = $rate;
            }
        }

        return $rates;
    }
}
