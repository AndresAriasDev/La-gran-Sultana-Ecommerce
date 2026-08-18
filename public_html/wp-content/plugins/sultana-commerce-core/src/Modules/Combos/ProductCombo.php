<?php

namespace Sultana\CommerceCore\Modules\Combos;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( '\WC_Product_Simple' ) ) :

    class ProductCombo extends \WC_Product_Simple
    {
        protected $product_type = 'combo';

        public function get_type(): string
        {
            return 'combo';
        }

        public function is_in_stock(): bool
        {
            return ComboStockService::combo_is_available( $this->get_id(), 1 );
        }

        public function get_stock_status( $context = 'view' )
        {
            return $this->is_in_stock() ? 'instock' : 'outofstock';
        }

        public function is_purchasable(): bool
        {
            return parent::is_purchasable() && $this->is_in_stock();
        }

        public function managing_stock(): bool
        {
            return false;
        }

        public function backorders_allowed(): bool
        {
            return false;
        }

        public function is_on_backorder( $qty_in_cart = 0 ): bool
        {
            return false;
        }

        public function get_stock_quantity( $context = 'view' )
        {
            $max = ComboStockService::get_max_combo_quantity( $this->get_id() );

            return $max < 0 ? null : $max;
        }

        public function get_max_purchase_quantity(): int
        {
            return ComboStockService::get_max_combo_quantity( $this->get_id() );
        }

        public function has_enough_stock( $quantity ): bool
        {
            return ComboStockService::combo_is_available( $this->get_id(), wc_stock_amount( $quantity ) );
        }

        public function get_weight( $context = 'view' )
        {
            return ComboStockService::get_combo_weight( $this->get_id() );
        }

        public function needs_shipping(): bool
        {
            return ! $this->is_virtual();
        }

        public function supports( $feature ): bool
        {
            if ( in_array( $feature, [ 'ajax_add_to_cart' ], true ) ) {
                return true;
            }

            return parent::supports( $feature );
        }
    }

endif;
