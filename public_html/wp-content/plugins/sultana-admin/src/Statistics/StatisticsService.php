<?php

namespace Sultana\Admin\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use Sultana\Admin\Customers\CustomerMetrics;
use Sultana\Admin\Core\Router;
use Throwable;
use WP_User;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StatisticsService
{
    private const PERIODS = [ 'today', 'week', 'month' ];
    private const OPERATIONAL_ORDER_STATUSES = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled' ];

    public function dashboard( string $period ): array
    {
        $period = in_array( $period, self::PERIODS, true ) ? $period : 'month';
        $range  = $this->period_range( $period );

        $current_orders  = $this->order_totals_for_range( $range['start'], $range['end'] );
        $previous_orders = $this->order_totals_for_range( $range['previous_start'], $range['previous_end'] );
        $new_customers   = $this->registered_customers_for_range( $range['start'], $range['end'] );
        $sales_trend     = $this->sales_trend( $period, $range['start'], $range['end'] );
        $top_products    = $this->top_products( $range['start'], $range['end'] );
        $order_statuses  = $this->order_status_distribution( $range['start'], $range['end'] );
        $best_customers  = $this->best_customers( $range['start'], $range['end'] );

        return [
            'period'          => $period,
            'periods'         => $this->period_options( $period ),
            'range_label'     => $range['label'],
            'metrics'         => [
                [
                    'key'   => 'sales',
                    'label' => __( 'Ventas', 'sultana-admin' ),
                    'value' => $this->format_money( $current_orders['sales'] ),
                    'icon'  => 'piggy-bank',
                    'trend' => $this->comparison_percent( $current_orders['sales'], $previous_orders['sales'] ),
                ],
                [
                    'key'   => 'orders',
                    'label' => __( 'Pedidos', 'sultana-admin' ),
                    'value' => number_format_i18n( $current_orders['orders'] ),
                    'icon'  => 'package-check',
                    'trend' => null,
                ],
                [
                    'key'   => 'customers',
                    'label' => __( 'Clientes nuevos', 'sultana-admin' ),
                    'value' => number_format_i18n( $new_customers ),
                    'icon'  => 'user',
                    'trend' => null,
                ],
            ],
            'sales_trend'     => $sales_trend,
            'top_products'    => $top_products,
            'order_statuses'  => $order_statuses,
            'best_customers'  => $best_customers,
            'valid_statuses'  => CustomerMetrics::VALID_ORDER_STATUSES,
            'data_sources'    => [
                'orders'    => $this->order_data_source(),
                'trend'     => $this->order_data_source(),
                'products'  => $this->product_data_source(),
                'statuses'  => $this->order_data_source(),
                'customers_rank' => $this->customer_rank_data_source(),
                'customers' => 'WP_User_Query:user_registered',
            ],
            'prepared_blocks' => [
                'sales_trend',
                'top_products',
                'low_selling_products',
                'order_statuses',
                'best_customers',
            ],
            'error'           => '',
        ];
    }

    private function period_options( string $active_period ): array
    {
        $options = [
            'today' => __( 'Hoy', 'sultana-admin' ),
            'week'  => __( 'Semana', 'sultana-admin' ),
            'month' => __( 'Mes', 'sultana-admin' ),
        ];

        return array_map(
            static fn ( string $key, string $label ): array => [
                'key'       => $key,
                'label'     => $label,
                'url'       => add_query_arg( [ 'period' => $key ], Router::statistics_url() ),
                'is_active' => $active_period === $key,
            ],
            array_keys( $options ),
            $options
        );
    }

    private function period_range( string $period ): array
    {
        $timezone = wp_timezone();
        $now      = new DateTimeImmutable( 'now', $timezone );
        $today    = $now->setTime( 0, 0, 0 );

        if ( 'today' === $period ) {
            $start          = $today;
            $end            = $today->modify( '+1 day' );
            $previous_start = $start->modify( '-1 day' );
            $previous_end   = $start;
            $label          = wp_date( get_option( 'date_format' ), $start->getTimestamp(), $timezone );
        } elseif ( 'week' === $period ) {
            $start_of_week = absint( get_option( 'start_of_week', 1 ) );
            $current_day   = (int) $today->format( 'w' );
            $days_back     = ( $current_day - $start_of_week + 7 ) % 7;
            $start          = $today->modify( '-' . $days_back . ' days' );
            $end            = $start->modify( '+7 days' );
            $previous_start = $start->modify( '-7 days' );
            $previous_end   = $start;
            $label          = $this->range_label( $start, $end, $timezone );
        } else {
            $start          = $today->modify( 'first day of this month' );
            $end            = $start->modify( '+1 month' );
            $previous_start = $start->modify( '-1 month' );
            $previous_end   = $start;
            $label          = wp_date( 'F Y', $start->getTimestamp(), $timezone );
        }

        return [
            'start'          => $start,
            'end'            => $end,
            'previous_start' => $previous_start,
            'previous_end'   => $previous_end,
            'label'          => $label,
        ];
    }

    private function range_label( DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $timezone ): string
    {
        $end_inclusive = $end->modify( '-1 day' );

        return sprintf(
            '%s - %s',
            wp_date( get_option( 'date_format' ), $start->getTimestamp(), $timezone ),
            wp_date( get_option( 'date_format' ), $end_inclusive->getTimestamp(), $timezone )
        );
    }

    private function order_totals_for_range( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $statuses = array_map( static fn ( string $status ): string => 'wc-' . $status, CustomerMetrics::VALID_ORDER_STATUSES );

        try {
            if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
                $table = $wpdb->prefix . 'wc_order_stats';

                return $this->order_totals_from_sql(
                    "SELECT COUNT(order_id) AS orders_count, COALESCE(SUM(total_sales), 0) AS sales_total
                    FROM {$table}
                    WHERE status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s",
                    $statuses,
                    $start,
                    $end
                );
            }

            if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
                $table = $wpdb->prefix . 'wc_orders';

                return $this->order_totals_from_sql(
                    "SELECT COUNT(id) AS orders_count, COALESCE(SUM(total_amount), 0) AS sales_total
                    FROM {$table}
                    WHERE type = 'shop_order'
                    AND status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s",
                    $statuses,
                    $start,
                    $end
                );
            }

            return $this->order_totals_from_posts( $statuses, $start, $end );
        } catch ( Throwable $exception ) {
            return [
                'orders' => 0,
                'sales'  => 0.0,
            ];
        }
    }

    private function order_totals_from_sql( string $sql_template, array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = str_replace( 'IN (%s)', 'IN (' . $status_placeholders . ')', $sql_template );
        $row                 = $wpdb->get_row(
            $wpdb->prepare(
                $sql,
                array_merge(
                    $statuses,
                    [
                        $this->mysql_gmt( $start ),
                        $this->mysql_gmt( $end ),
                    ]
                )
            ),
            ARRAY_A
        );

        return [
            'orders' => absint( $row['orders_count'] ?? 0 ),
            'sales'  => (float) ( $row['sales_total'] ?? 0 ),
        ];
    }

    private function order_totals_from_posts( array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = $wpdb->prepare(
            "SELECT COUNT(posts.ID) AS orders_count, COALESCE(SUM(total_meta.meta_value + 0), 0) AS sales_total
            FROM {$wpdb->posts} posts
            LEFT JOIN {$wpdb->postmeta} total_meta ON total_meta.post_id = posts.ID AND total_meta.meta_key = '_order_total'
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_placeholders})
            AND posts.post_date_gmt >= %s
            AND posts.post_date_gmt < %s",
            array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
        );
        $row                 = $wpdb->get_row( $sql, ARRAY_A );

        return [
            'orders' => absint( $row['orders_count'] ?? 0 ),
            'sales'  => (float) ( $row['sales_total'] ?? 0 ),
        ];
    }

    private function registered_customers_for_range( DateTimeImmutable $start, DateTimeImmutable $end ): int
    {
        try {
            $query = new WP_User_Query(
                [
                    'role__in'    => [ 'customer' ],
                    'fields'      => 'ID',
                    'number'      => 1,
                    'count_total' => true,
                    'date_query'  => [
                        [
                            'column'    => 'user_registered',
                            'after'     => $this->mysql_gmt( $start ),
                            'before'    => $this->mysql_gmt( $end->modify( '-1 second' ) ),
                            'inclusive' => true,
                        ],
                    ],
                ]
            );
        } catch ( Throwable $exception ) {
            return 0;
        }

        return absint( $query->get_total() );
    }

    private function sales_trend( string $period, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        $bucket = $this->trend_bucket_config( $period, $start, $end );
        $rows   = $this->sales_trend_rows( $start, $end, $bucket['seconds'] );
        $points = [];
        $max    = 0.0;

        for ( $index = 0; $index < $bucket['count']; $index++ ) {
            $value = (float) ( $rows[ $index ] ?? 0 );
            $max   = max( $max, $value );

            $points[] = [
                'label'      => $bucket['labels'][ $index ] ?? '',
                'axis_label' => $bucket['axis_labels'][ $index ] ?? '',
                'value'      => $value,
            ];
        }

        return [
            'points' => $points,
            'max'    => $max,
            'empty'  => $max <= 0.0,
        ];
    }

    private function trend_bucket_config( string $period, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        $timezone = wp_timezone();
        $labels   = [];
        $axis     = [];

        if ( 'today' === $period ) {
            for ( $index = 0; $index < 24; $index++ ) {
                $point_time = $start->modify( '+' . $index . ' hours' );
                $labels[]   = $this->compact_time_label( $point_time, $timezone );
                $axis[]     = in_array( $index, [ 0, 6, 12, 18, 23 ], true ) ? wp_date( 'g a', $point_time->getTimestamp(), $timezone ) : '';
            }

            return [
                'seconds'     => HOUR_IN_SECONDS,
                'count'       => 24,
                'labels'      => $labels,
                'axis_labels' => $axis,
            ];
        }

        $days = max( 1, (int) ceil( ( $end->getTimestamp() - $start->getTimestamp() ) / DAY_IN_SECONDS ) );

        for ( $index = 0; $index < $days; $index++ ) {
            $point_time = $start->modify( '+' . $index . ' days' );
            $labels[]   = $this->compact_date_label( $point_time );

            if ( 'week' === $period ) {
                $axis[] = wp_date( 'D', $point_time->getTimestamp(), $timezone );
            } else {
                $axis[] = in_array( $index, [ 0, 14, $days - 1 ], true ) ? wp_date( 'j', $point_time->getTimestamp(), $timezone ) : '';
            }
        }

        return [
            'seconds'     => DAY_IN_SECONDS,
            'count'       => $days,
            'labels'      => $labels,
            'axis_labels' => $axis,
        ];
    }

    private function sales_trend_rows( DateTimeImmutable $start, DateTimeImmutable $end, int $bucket_seconds ): array
    {
        global $wpdb;

        $statuses = array_map( static fn ( string $status ): string => 'wc-' . $status, CustomerMetrics::VALID_ORDER_STATUSES );

        try {
            if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
                $table = $wpdb->prefix . 'wc_order_stats';

                return $this->bucketed_sales_from_sql(
                    "SELECT FLOOR((UNIX_TIMESTAMP(date_created_gmt) - UNIX_TIMESTAMP(%s)) / %d) AS bucket_index, COALESCE(SUM(total_sales), 0) AS sales_total
                    FROM {$table}
                    WHERE status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s
                    GROUP BY bucket_index",
                    $statuses,
                    $start,
                    $end,
                    $bucket_seconds
                );
            }

            if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
                $table = $wpdb->prefix . 'wc_orders';

                return $this->bucketed_sales_from_sql(
                    "SELECT FLOOR((UNIX_TIMESTAMP(date_created_gmt) - UNIX_TIMESTAMP(%s)) / %d) AS bucket_index, COALESCE(SUM(total_amount), 0) AS sales_total
                    FROM {$table}
                    WHERE type = 'shop_order'
                    AND status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s
                    GROUP BY bucket_index",
                    $statuses,
                    $start,
                    $end,
                    $bucket_seconds
                );
            }

            return $this->bucketed_sales_from_posts( $statuses, $start, $end, $bucket_seconds );
        } catch ( Throwable $exception ) {
            return [];
        }
    }

    private function bucketed_sales_from_sql( string $sql_template, array $statuses, DateTimeImmutable $start, DateTimeImmutable $end, int $bucket_seconds ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = str_replace( 'IN (%s)', 'IN (' . $status_placeholders . ')', $sql_template );
        $rows                = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                array_merge(
                    [
                        $this->mysql_gmt( $start ),
                        $bucket_seconds,
                    ],
                    $statuses,
                    [
                        $this->mysql_gmt( $start ),
                        $this->mysql_gmt( $end ),
                    ]
                )
            ),
            ARRAY_A
        );

        return $this->bucket_rows_to_map( $rows );
    }

    private function bucketed_sales_from_posts( array $statuses, DateTimeImmutable $start, DateTimeImmutable $end, int $bucket_seconds ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = $wpdb->prepare(
            "SELECT FLOOR((UNIX_TIMESTAMP(posts.post_date_gmt) - UNIX_TIMESTAMP(%s)) / %d) AS bucket_index, COALESCE(SUM(total_meta.meta_value + 0), 0) AS sales_total
            FROM {$wpdb->posts} posts
            LEFT JOIN {$wpdb->postmeta} total_meta ON total_meta.post_id = posts.ID AND total_meta.meta_key = '_order_total'
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_placeholders})
            AND posts.post_date_gmt >= %s
            AND posts.post_date_gmt < %s
            GROUP BY bucket_index",
            array_merge(
                [
                    $this->mysql_gmt( $start ),
                    $bucket_seconds,
                ],
                $statuses,
                [
                    $this->mysql_gmt( $start ),
                    $this->mysql_gmt( $end ),
                ]
            )
        );

        return $this->bucket_rows_to_map( $wpdb->get_results( $sql, ARRAY_A ) );
    }

    private function bucket_rows_to_map( $rows ): array
    {
        $map = [];

        foreach ( is_array( $rows ) ? $rows : [] as $row ) {
            $bucket = isset( $row['bucket_index'] ) ? (int) $row['bucket_index'] : -1;

            if ( $bucket < 0 ) {
                continue;
            }

            $map[ $bucket ] = (float) ( $row['sales_total'] ?? 0 );
        }

        return $map;
    }

    private function top_products( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $orders_table = $wpdb->prefix . 'wc_order_stats';

        if ( ! $this->table_exists( $lookup_table ) || ! $this->table_exists( $orders_table ) ) {
            return [];
        }

        $statuses            = array_map( static fn ( string $status ): string => 'wc-' . $status, CustomerMetrics::VALID_ORDER_STATUSES );
        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = $wpdb->prepare(
            "SELECT
                CASE
                    WHEN variation_post.post_parent > 0 THEN variation_post.post_parent
                    WHEN product_post.post_parent > 0 THEN product_post.post_parent
                    ELSE lookup.product_id
                END AS product_id,
                COALESCE(SUM(lookup.product_qty), 0) AS units_sold,
                COALESCE(SUM(lookup.product_net_revenue), 0) AS revenue
            FROM {$lookup_table} lookup
            INNER JOIN {$orders_table} stats ON stats.order_id = lookup.order_id
            LEFT JOIN {$wpdb->posts} product_post ON product_post.ID = lookup.product_id
            LEFT JOIN {$wpdb->posts} variation_post ON variation_post.ID = lookup.variation_id
            WHERE lookup.product_id > 0
            AND stats.status IN ({$status_placeholders})
            AND stats.date_created_gmt >= %s
            AND stats.date_created_gmt < %s
            GROUP BY product_id
            ORDER BY units_sold DESC, revenue DESC
            LIMIT 5",
            array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
        );
        $rows                = $wpdb->get_results( $sql, ARRAY_A );

        if ( empty( $rows ) || ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $product_ids = array_map( 'absint', wp_list_pluck( $rows, 'product_id' ) );
        $product_posts = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => [ 'publish', 'private', 'draft', 'pending', 'future', 'trash' ],
                'post__in'       => $product_ids,
                'posts_per_page' => count( $product_ids ),
                'orderby'        => 'post__in',
                'fields'         => 'ids',
            ]
        );
        $product_map = [];

        foreach ( $product_posts as $product_id ) {
            $product = wc_get_product( absint( $product_id ) );

            if ( $product && method_exists( $product, 'get_id' ) ) {
                $product_map[ $product->get_id() ] = $product;
            }
        }

        $items = [];

        foreach ( $rows as $row ) {
            $product_id = absint( $row['product_id'] ?? 0 );
            $product    = $product_map[ $product_id ] ?? null;

            if ( ! $product ) {
                continue;
            }

            $items[] = [
                'id'         => $product_id,
                'name'       => $product->get_name(),
                'image'      => $this->product_image_url( $product ),
                'units'      => (float) ( $row['units_sold'] ?? 0 ),
                'units_text' => sprintf(
                    /* translators: %s: sold units. */
                    __( '%s vendidos', 'sultana-admin' ),
                    number_format_i18n( (float) ( $row['units_sold'] ?? 0 ) )
                ),
                'revenue'    => $this->format_money( (float) ( $row['revenue'] ?? 0 ) ),
                'url'        => add_query_arg( 'product_id', $product_id, Router::products_url() ),
            ];
        }

        return $items;
    }

    private function product_image_url( $product ): string
    {
        $image_id = method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0;
        $url      = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

        if ( ! $url && function_exists( 'wc_placeholder_img_src' ) ) {
            $url = wc_placeholder_img_src( 'thumbnail' );
        }

        return is_string( $url ) ? $url : '';
    }

    private function order_status_distribution( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        $counts = array_fill_keys( self::OPERATIONAL_ORDER_STATUSES, 0 );
        $rows   = $this->order_status_rows( $start, $end );

        foreach ( $rows as $row ) {
            $status = $this->normalize_order_status( (string) ( $row['status'] ?? '' ) );

            if ( ! isset( $counts[ $status ] ) ) {
                continue;
            }

            $counts[ $status ] = absint( $row['orders_count'] ?? 0 );
        }

        $items = [];

        foreach ( self::OPERATIONAL_ORDER_STATUSES as $status ) {
            $items[] = [
                'status' => $status,
                'label'  => $this->order_status_label( $status ),
                'count'  => $counts[ $status ],
            ];
        }

        return [
            'items' => $items,
            'empty' => array_sum( $counts ) <= 0,
        ];
    }

    private function order_status_rows( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $statuses = array_map( static fn ( string $status ): string => 'wc-' . $status, self::OPERATIONAL_ORDER_STATUSES );

        try {
            if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
                $table = $wpdb->prefix . 'wc_order_stats';

                return $this->order_status_rows_from_sql(
                    "SELECT status, COUNT(order_id) AS orders_count
                    FROM {$table}
                    WHERE status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s
                    GROUP BY status",
                    $statuses,
                    $start,
                    $end
                );
            }

            if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
                $table = $wpdb->prefix . 'wc_orders';

                return $this->order_status_rows_from_sql(
                    "SELECT status, COUNT(id) AS orders_count
                    FROM {$table}
                    WHERE type = 'shop_order'
                    AND status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s
                    GROUP BY status",
                    $statuses,
                    $start,
                    $end
                );
            }

            return $this->order_status_rows_from_posts( $statuses, $start, $end );
        } catch ( Throwable $exception ) {
            return [];
        }
    }

    private function order_status_rows_from_sql( string $sql_template, array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = str_replace( 'IN (%s)', 'IN (' . $status_placeholders . ')', $sql_template );

        return $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
            ),
            ARRAY_A
        );
    }

    private function order_status_rows_from_posts( array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = $wpdb->prepare(
            "SELECT posts.post_status AS status, COUNT(posts.ID) AS orders_count
            FROM {$wpdb->posts} posts
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_placeholders})
            AND posts.post_date_gmt >= %s
            AND posts.post_date_gmt < %s
            GROUP BY posts.post_status",
            array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    private function best_customers( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        $rows = $this->best_customer_rows( $start, $end );

        if ( empty( $rows ) ) {
            return [];
        }

        $customer_ids = array_values( array_unique( array_filter( array_map( static fn ( array $row ): int => absint( $row['customer_id'] ?? 0 ), $rows ) ) ) );

        if ( empty( $customer_ids ) ) {
            return [];
        }

        $users = get_users(
            [
                'include' => $customer_ids,
                'fields'  => 'all',
            ]
        );
        $user_map = [];

        foreach ( $users as $user ) {
            if ( $user instanceof WP_User ) {
                $user_map[ $user->ID ] = $user;
            }
        }

        $items = [];

        foreach ( $rows as $row ) {
            $customer_id = absint( $row['customer_id'] ?? 0 );
            $user        = $user_map[ $customer_id ] ?? null;

            if ( ! $user ) {
                continue;
            }

            $orders_count = absint( $row['orders_count'] ?? 0 );
            $items[]      = [
                'id'          => $customer_id,
                'name'        => $user->display_name ?: $user->user_login,
                'orders'      => $orders_count,
                'orders_text' => sprintf(
                    /* translators: %s: order count. */
                    _n( '%s pedido', '%s pedidos', $orders_count, 'sultana-admin' ),
                    number_format_i18n( $orders_count )
                ),
                'total'       => $this->format_money( (float) ( $row['sales_total'] ?? 0 ) ),
                'url'         => Router::customer_url( $customer_id ),
            ];
        }

        return $items;
    }

    private function best_customer_rows( DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $statuses = array_map( static fn ( string $status ): string => 'wc-' . $status, CustomerMetrics::VALID_ORDER_STATUSES );

        try {
            if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
                $table          = $wpdb->prefix . 'wc_order_stats';
                $customer_table = $wpdb->prefix . 'wc_customer_lookup';

                if ( ! $this->table_exists( $customer_table ) ) {
                    return [];
                }

                return $this->best_customer_rows_from_sql(
                    "SELECT customer.user_id AS customer_id, COUNT(stats.order_id) AS orders_count, COALESCE(SUM(stats.total_sales), 0) AS sales_total
                    FROM {$table} stats
                    INNER JOIN {$customer_table} customer ON customer.customer_id = stats.customer_id
                    WHERE customer.user_id > 0
                    AND stats.status IN (%s)
                    AND stats.date_created_gmt >= %s
                    AND stats.date_created_gmt < %s
                    GROUP BY customer.user_id
                    ORDER BY sales_total DESC, orders_count DESC
                    LIMIT 5",
                    $statuses,
                    $start,
                    $end
                );
            }

            if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
                $table = $wpdb->prefix . 'wc_orders';

                return $this->best_customer_rows_from_sql(
                    "SELECT customer_id, COUNT(id) AS orders_count, COALESCE(SUM(total_amount), 0) AS sales_total
                    FROM {$table}
                    WHERE type = 'shop_order'
                    AND customer_id > 0
                    AND status IN (%s)
                    AND date_created_gmt >= %s
                    AND date_created_gmt < %s
                    GROUP BY customer_id
                    ORDER BY sales_total DESC, orders_count DESC
                    LIMIT 5",
                    $statuses,
                    $start,
                    $end
                );
            }

            return $this->best_customer_rows_from_posts( $statuses, $start, $end );
        } catch ( Throwable $exception ) {
            return [];
        }
    }

    private function best_customer_rows_from_sql( string $sql_template, array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = str_replace( 'IN (%s)', 'IN (' . $status_placeholders . ')', $sql_template );

        return $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
            ),
            ARRAY_A
        );
    }

    private function best_customer_rows_from_posts( array $statuses, DateTimeImmutable $start, DateTimeImmutable $end ): array
    {
        global $wpdb;

        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql                 = $wpdb->prepare(
            "SELECT customer_meta.meta_value AS customer_id, COUNT(posts.ID) AS orders_count, COALESCE(SUM(total_meta.meta_value + 0), 0) AS sales_total
            FROM {$wpdb->posts} posts
            INNER JOIN {$wpdb->postmeta} customer_meta ON customer_meta.post_id = posts.ID AND customer_meta.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} total_meta ON total_meta.post_id = posts.ID AND total_meta.meta_key = '_order_total'
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_placeholders})
            AND posts.post_date_gmt >= %s
            AND posts.post_date_gmt < %s
            AND customer_meta.meta_value > 0
            GROUP BY customer_meta.meta_value
            ORDER BY sales_total DESC, orders_count DESC
            LIMIT 5",
            array_merge( $statuses, [ $this->mysql_gmt( $start ), $this->mysql_gmt( $end ) ] )
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    private function normalize_order_status( string $status ): string
    {
        return preg_replace( '/^wc-/', '', sanitize_key( $status ) );
    }

    private function order_status_label( string $status ): string
    {
        $labels = [
            'pending'    => __( 'Pendiente', 'sultana-admin' ),
            'processing' => __( 'Procesando', 'sultana-admin' ),
            'on-hold'    => __( 'En espera', 'sultana-admin' ),
            'completed'  => __( 'Completado', 'sultana-admin' ),
            'cancelled'  => __( 'Cancelado', 'sultana-admin' ),
        ];

        return $labels[ $status ] ?? $status;
    }

    private function comparison_percent( float $current, float $previous ): ?array
    {
        if ( $previous <= 0.0 || $current === $previous ) {
            return null;
        }

        $percent = ( ( $current - $previous ) / $previous ) * 100;

        return [
            'direction' => $percent > 0 ? 'up' : 'down',
            'label'     => sprintf( '%s%s%%', $percent > 0 ? '+' : '', number_format_i18n( round( $percent ) ) ),
        ];
    }

    private function order_data_source(): string
    {
        global $wpdb;

        if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
            return 'woocommerce_lookup:wc_order_stats';
        }

        if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
            return 'woocommerce_hpos:wc_orders';
        }

        return 'wordpress_legacy:posts/postmeta';
    }

    private function product_data_source(): string
    {
        global $wpdb;

        if ( $this->table_exists( $wpdb->prefix . 'wc_order_product_lookup' ) && $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) ) {
            return 'woocommerce_lookup:wc_order_product_lookup+wc_order_stats';
        }

        return 'unavailable';
    }

    private function customer_rank_data_source(): string
    {
        global $wpdb;

        if ( $this->table_exists( $wpdb->prefix . 'wc_order_stats' ) && $this->table_exists( $wpdb->prefix . 'wc_customer_lookup' ) ) {
            return 'woocommerce_lookup:wc_order_stats+wc_customer_lookup+wp_users';
        }

        if ( $this->uses_hpos() && $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
            return 'woocommerce_hpos:wc_orders+wp_users';
        }

        return 'wordpress_legacy:posts/postmeta+wp_users';
    }

    private function mysql_gmt( DateTimeImmutable $date ): string
    {
        return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
    }

    private function format_money( float $amount ): string
    {
        return function_exists( 'wc_price' ) ? wc_price( $amount ) : number_format_i18n( $amount, 2 );
    }

    private function compact_time_label( DateTimeImmutable $date, DateTimeZone $timezone ): string
    {
        $label = strtolower( wp_date( 'g:i a', $date->getTimestamp(), $timezone ) );

        return str_replace( [ 'am', 'pm' ], [ 'a. m.', 'p. m.' ], $label );
    }

    private function compact_date_label( DateTimeImmutable $date ): string
    {
        $months = [
            1  => __( 'ene.', 'sultana-admin' ),
            2  => __( 'feb.', 'sultana-admin' ),
            3  => __( 'mar.', 'sultana-admin' ),
            4  => __( 'abr.', 'sultana-admin' ),
            5  => __( 'may.', 'sultana-admin' ),
            6  => __( 'jun.', 'sultana-admin' ),
            7  => __( 'jul.', 'sultana-admin' ),
            8  => __( 'ago.', 'sultana-admin' ),
            9  => __( 'sept.', 'sultana-admin' ),
            10 => __( 'oct.', 'sultana-admin' ),
            11 => __( 'nov.', 'sultana-admin' ),
            12 => __( 'dic.', 'sultana-admin' ),
        ];

        $month = (int) $date->format( 'n' );

        return sprintf(
            '%d %s %s',
            (int) $date->format( 'j' ),
            $months[ $month ] ?? $date->format( 'M' ),
            $date->format( 'Y' )
        );
    }

    private function table_exists( string $table ): bool
    {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    private function uses_hpos(): bool
    {
        return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}
