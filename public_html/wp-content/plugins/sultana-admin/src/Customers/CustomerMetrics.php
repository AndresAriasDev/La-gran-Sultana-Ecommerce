<?php

namespace Sultana\Admin\Customers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerMetrics
{
    public const VALID_ORDER_STATUSES = [ 'processing', 'completed' ];
}
