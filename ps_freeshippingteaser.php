<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;
use PrestaShop\Module\FreeShippingTeaser\ThresholdFinder;

class Ps_freeshippingteaser extends Module
{
    public function __construct()
    {
        $this->name          = 'ps_freeshippingteaser';
        $this->tab           = 'front_office_features';
        $this->version       = '1.0.0';
        $this->author        = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('Free Shipping Teaser');
        $this->description = $this->l('Displays a real-time free shipping progress teaser in cart areas.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayBeforeCarrier')
            && $this->registerHook('displayNav2Column')
            && $this->registerHook('displayTop')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && Configuration::updateValue('FST_FREE_SHIPPING_AMOUNT', '0')
            && Configuration::updateValue(
                'FST_TEASER_TEXT',
                'Spend {amount} more and get FREE SHIPPING!'
            )
            && Configuration::updateValue(
                'FST_SUCCESS_TEXT',
                'Your cart total has reached {threshold} and your order ships FREE!'
            );
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName('FST_FREE_SHIPPING_AMOUNT')
            && Configuration::deleteByName('FST_TEASER_TEXT')
            && Configuration::deleteByName('FST_SUCCESS_TEXT');
    }
}
