<?php

namespace Sultana\CommerceCore\Core;

use Sultana\CommerceCore\Modules\Accounts\AccountRegistration;
use Sultana\CommerceCore\Modules\Accounts\AccountLogin;
use Sultana\CommerceCore\Modules\Accounts\AccountPasswordReset;
use Sultana\CommerceCore\Modules\Accounts\AccountAccess;
use Sultana\CommerceCore\Modules\Accounts\ProfileAvatar;
use Sultana\CommerceCore\Modules\Checkout\CheckoutAccounts;
use Sultana\CommerceCore\Modules\Combos\ComboProducts;
use Sultana\CommerceCore\Modules\Coupons\AccountCoupons;
use Sultana\CommerceCore\Modules\Emails\OrderStatusEmails;
use Sultana\CommerceCore\Modules\HomePromotions\HomePromotions;
use Sultana\CommerceCore\Modules\Reviews\ProductReviews;
use Sultana\CommerceCore\Modules\Wishlist\Wishlist;
use Sultana\CommerceCore\Modules\Shipping\Shipping;
use Sultana\CommerceCore\Modules\Shipping\Admin\ShippingAdmin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap
{
    public static function init(): void
    {
        add_action( 'plugins_loaded', [ self::class, 'load' ] );
    }

    public static function load(): void
    {
        require_once SCC_PLUGIN_PATH . 'src/Core/StoreBranding.php';
        require_once SCC_PLUGIN_PATH . 'src/Core/TemplateLoader.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Accounts/AccountLogin.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Accounts/AccountRegistration.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Accounts/AccountPasswordReset.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Accounts/AccountAccess.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Accounts/ProfileAvatar.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Checkout/CheckoutAccounts.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/ComboStockService.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/ComboComponentService.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/ProductCombo.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/ComboOrderService.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/Admin/ComboProductsAdmin.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Combos/ComboProducts.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Coupons/AccountCoupons.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Emails/EmailRenderer.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Emails/OrderStatusEmails.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/HomePromotions/HomePromotions.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Reviews/ProductReviews.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Wishlist/Wishlist.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Contracts/ShippingProviderInterface.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/ValueObjects/ShippingContext.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Repositories/ShippingSettingsRepository.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Providers/CargotransProvider.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Providers/ExpressGranadaProvider.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Providers/StorePickupProvider.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/ShippingEngine.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Shipping.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Admin/ShippingAdmin.php';

        AccountLogin::register();
        AccountRegistration::register();
        AccountPasswordReset::register();
        AccountAccess::register();
        ProfileAvatar::register();
        CheckoutAccounts::register();
        ComboProducts::register();
        AccountCoupons::register();
        OrderStatusEmails::register();
        HomePromotions::register();
        ProductReviews::register();
        Wishlist::register();
        Shipping::register();
        ShippingAdmin::register();
    }
}
