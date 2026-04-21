<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/src/TeaserBuilder.php';
require_once __DIR__ . '/src/ThresholdFinder.php';

use PrestaShop\Module\FreeShippingTeaser\ThresholdFinder;
use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;

class Ps_freeshippingteaser extends Module
{
    public function __construct()
    {
        $this->name          = 'ps_freeshippingteaser';
        $this->tab           = 'front_office_features';
        $this->version       = '1.0.0';
        $this->author        = 'Previn Kalisetty Appadu';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('Free Shipping Teaser');
        $this->description = $this->l('Displays a real-time free shipping progress teaser in cart areas.');
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installTab()
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
        return $this->uninstallTab()
            && parent::uninstall()
            && Configuration::deleteByName('FST_FREE_SHIPPING_AMOUNT')
            && Configuration::deleteByName('FST_TEASER_TEXT')
            && Configuration::deleteByName('FST_SUCCESS_TEXT');
    }

    private function installTab(): bool
    {
        $parentId = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        if (!$parentId) {
            $parentId = 0;
        }

        $tab             = new Tab();
        $tab->class_name = 'AdminPsFreeShippingTeaser';
        $tab->module     = $this->name;
        $tab->id_parent  = $parentId;
        $tab->active     = 1;
        $tab->icon       = 'local_shipping';
        $tab->name       = [];

        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Free Shipping Teaser';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName('AdminPsFreeShippingTeaser');
        if ($tabId) {
            $tab = new Tab($tabId);
            return (bool) $tab->delete();
        }

        return true;
    }

    private bool $miniCartRendered = false;

    /** Request-scoped threshold cache — avoids repeated DB queries across hook calls. */
    private bool  $thresholdLoaded  = false;
    private ?float $cachedThreshold = null;

    private function getThreshold(): ?float
    {
        if (!$this->thresholdLoaded) {
            $this->cachedThreshold = (new ThresholdFinder())->find();
            $this->thresholdLoaded = true;
        }
        return $this->cachedThreshold;
    }

    public function hookDisplayHeader(): string
    {
        $this->context->controller->addCSS($this->_path . 'views/css/freeshippingteaser.css');
        $this->context->controller->addJS($this->_path . 'views/js/freeshippingteaser.js');

        return '';
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        // Reserved for future admin assets.
    }

    public function hookDisplayShoppingCartFooter(array $params): string
    {
        return $this->renderTeaser();
    }

    public function hookDisplayBeforeCarrier(array $params): string
    {
        return $this->renderTeaser();
    }

    /**
     * Primary hook for the header mini-cart area.
     * If this fires, suppress hookDisplayTop so we don't double-render.
     */
    public function hookDisplayNav2Column(array $params): string
    {
        $this->miniCartRendered = true;

        return $this->renderTeaser();
    }

    /** Fallback mini-cart hook for themes that don't wire displayNav2Column. */
    public function hookDisplayTop(array $params): string
    {
        if ($this->miniCartRendered) {
            return '';
        }

        return $this->renderTeaser();
    }

    private function renderTeaser(): string
    {
        $threshold = $this->getThreshold();

        if ($threshold === null) {
            return '';
        }

        $cart         = $this->context->cart;
        $currency     = $this->context->currency;
        $currencySign = ($currency !== null) ? $currency->sign : '';
        $cartTotal    = ($cart !== null) ? (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS) : 0.0;
        $teaserText   = (string) Configuration::get('FST_TEASER_TEXT');
        $successText  = (string) Configuration::get('FST_SUCCESS_TEXT');

        $data = (new TeaserBuilder())->build(
            $threshold,
            $cartTotal,
            $currencySign,
            $teaserText,
            $successText
        );

        $this->context->smarty->assign([
            'fst_qualified'    => $data['qualified'],
            'fst_percent'      => $data['percent'],
            'fst_teaser_text'  => $data['teaser_text'],
            'fst_success_text' => $data['success_text'],
            'fst_ajax_url'     => $this->context->link->getModuleLink($this->name, 'ajax'),
            'fst_threshold'    => $threshold,
            'fst_currency'     => $currencySign,
            'fst_teaser_tpl'   => $teaserText,
            'fst_success_tpl'  => $successText,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/teaser.tpl');
    }
}
