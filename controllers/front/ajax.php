<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/src/TeaserBuilder.php';
require_once dirname(__DIR__, 2) . '/src/ThresholdFinder.php';

use PrestaShop\Module\FreeShippingTeaser\ThresholdFinder;
use PrestaShop\Module\FreeShippingTeaser\TeaserBuilder;

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

        $finder    = new ThresholdFinder();
        $threshold = $finder->find();

        if ($threshold === null) {
            $this->ajaxRender(json_encode(['threshold' => null]));

            return;
        }

        $cart = $this->context->cart;
        if ($cart === null) {
            $this->ajaxRender(json_encode(['threshold' => null]));
            return;
        }
        $currency     = $this->context->currency;
        $currencySign = ($currency !== null) ? $currency->sign : '';
        // Ex-tax product subtotal — matches server-side comparison in renderTeaser().
        $cartTotal    = (float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);

        $builder = new TeaserBuilder();
        $data    = $builder->build(
            $threshold,
            $cartTotal,
            $currencySign,
            (string) Configuration::get('FST_TEASER_TEXT'),
            (string) Configuration::get('FST_SUCCESS_TEXT')
        );

        $this->ajaxRender(json_encode($data));
    }
}
