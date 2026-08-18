<?php

namespace Sultana\CommerceCore\Modules\Shipping\Contracts;

use Sultana\CommerceCore\Modules\Shipping\ValueObjects\ShippingContext;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ShippingProviderInterface
{
    /**
     * @return array<int, array{id:string,label:string,cost:float,meta?:array<string,string>}>
     */
    public function get_rates( ShippingContext $context ): array;
}
