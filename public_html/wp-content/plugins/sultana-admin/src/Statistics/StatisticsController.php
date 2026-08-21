<?php

namespace Sultana\Admin\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StatisticsController
{
    public static function prepare_screen(): array
    {
        $period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'month';
        $service = new StatisticsService();

        return $service->dashboard( $period );
    }
}
