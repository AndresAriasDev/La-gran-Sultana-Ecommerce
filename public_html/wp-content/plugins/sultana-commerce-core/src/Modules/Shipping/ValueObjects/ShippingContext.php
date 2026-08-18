<?php

namespace Sultana\CommerceCore\Modules\Shipping\ValueObjects;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShippingContext
{
    private array $package;
    private float $weight;
    private string $department;
    private string $municipality;

    public function __construct( array $package, float $weight, string $department, string $municipality )
    {
        $this->package      = $package;
        $this->weight       = $weight;
        $this->department   = $department;
        $this->municipality = $municipality;
    }

    public function package(): array
    {
        return $this->package;
    }

    public function weight(): float
    {
        return $this->weight;
    }

    public function department(): string
    {
        return $this->department;
    }

    public function municipality(): string
    {
        return $this->municipality;
    }
}
