<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminPsFreeShippingTeaserController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function postProcess(): void
    {
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
            $this->confirmations[] = $this->l('Settings updated.');
        }

        parent::postProcess();
    }

    public function initContent(): void
    {
        $this->content = $this->renderConfigForm();
        parent::initContent();
    }

    private function renderConfigForm(): string
    {
        $helper                        = new HelperForm();
        $helper->table                 = $this->table;
        $helper->name_controller       = $this->controller_name;
        $helper->token                 = Tools::getAdminTokenLite($this->controller_name);
        $helper->currentIndex          = AdminController::$currentIndex;
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
                    'title' => $this->l('Free Shipping Teaser Settings'),
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
}
