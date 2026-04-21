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

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitFSTModule')) {
            Configuration::updateValue(
                'FST_FREE_SHIPPING_AMOUNT',
                (float) Tools::getValue('FST_FREE_SHIPPING_AMOUNT')
            );
            Configuration::updateValue(
                'FST_TEASER_TEXT',
                Tools::getValue('FST_TEASER_TEXT')
            );
            Configuration::updateValue(
                'FST_SUCCESS_TEXT',
                Tools::getValue('FST_SUCCESS_TEXT')
            );
            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        return $output . $this->renderForm();
    }

    private function renderForm(): string
    {
        $helper                        = new HelperForm();
        $helper->table                 = $this->table;
        $helper->name_controller       = $this->name;
        $helper->token                 = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex          = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action         = 'submitFSTModule';
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->fields_value          = [
            'FST_FREE_SHIPPING_AMOUNT' => Tools::getValue(
                'FST_FREE_SHIPPING_AMOUNT',
                Configuration::get('FST_FREE_SHIPPING_AMOUNT')
            ),
            'FST_TEASER_TEXT'  => Tools::getValue(
                'FST_TEASER_TEXT',
                Configuration::get('FST_TEASER_TEXT')
            ),
            'FST_SUCCESS_TEXT' => Tools::getValue(
                'FST_SUCCESS_TEXT',
                Configuration::get('FST_SUCCESS_TEXT')
            ),
        ];

        return $helper->generateForm([[
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Free shipping amount'),
                        'name'  => 'FST_FREE_SHIPPING_AMOUNT',
                        'desc'  => $this->l('Set to 0 to auto-detect from carrier price ranges.'),
                        'size'  => 20,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Teaser text'),
                        'name'  => 'FST_TEASER_TEXT',
                        'desc'  => $this->l('Tokens: {amount}, {threshold}, {currency}'),
                        'size'  => 80,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Success text'),
                        'name'  => 'FST_SUCCESS_TEXT',
                        'desc'  => $this->l('Tokens: {amount}, {threshold}, {currency}'),
                        'size'  => 80,
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ]]);
    }

    /** Tracks whether the mini-cart teaser has already been output this request. */
    private bool $miniCartRendered = false;

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
        $finder    = new ThresholdFinder();
        $threshold = $finder->find();

        if ($threshold === null) {
            return '';
        }

        $cart = $this->context->cart;
        if ($cart === null) {
            return '';
        }
        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);

        $builder = new TeaserBuilder();
        $data    = $builder->build(
            $threshold,
            $cartTotal,
            $currency->sign,
            (string) Configuration::get('FST_TEASER_TEXT'),
            (string) Configuration::get('FST_SUCCESS_TEXT')
        );

        $this->context->smarty->assign([
            'fst_qualified'    => $data['qualified'],
            'fst_percent'      => $data['percent'],
            'fst_teaser_text'  => $data['teaser_text'],
            'fst_success_text' => $data['success_text'],
            'fst_ajax_url'     => $this->context->link->getModuleLink($this->name, 'ajax'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/teaser.tpl');
    }
}
