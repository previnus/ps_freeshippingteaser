<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

class Ps_freeshippingteaserAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        $this->ajax = true;
        parent::initContent();
    }

    public function displayAjax(): void
    {
        $token = Tools::getValue('token');

        if (!$token || $token !== Tools::getToken(false)) {
            http_response_code(403);
            $this->ajaxRender(json_encode(['error' => 'Invalid token']));

            return;
        }

        $finder    = new \PrestaShop\Module\FreeShippingTeaser\ThresholdFinder();
        $threshold = $finder->find();

        if ($threshold === null) {
            $this->ajaxRender(json_encode(['threshold' => null]));

            return;
        }

        $cart      = $this->context->cart;
        $currency  = $this->context->currency;
        $cartTotal = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);

        $builder = new \PrestaShop\Module\FreeShippingTeaser\TeaserBuilder();
        $data    = $builder->build(
            $threshold,
            $cartTotal,
            $currency->sign,
            (string) Configuration::get('FST_TEASER_TEXT'),
            (string) Configuration::get('FST_SUCCESS_TEXT')
        );

        $this->ajaxRender(json_encode($data));
    }
}
