<?php
declare(strict_types=1);

namespace PrestaShop\Module\FreeShippingTeaser;

class ThresholdFinder
{
    public function find(): ?float
    {
        $override = (float) \Configuration::get('FST_FREE_SHIPPING_AMOUNT');
        if ($override > 0.0) {
            return $override;
        }

        return $this->findFromCarrierRules() ?? $this->findFromCartRules();
    }

    private function findFromCarrierRules(): ?float
    {
        $idShop = (int) \Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('MIN(rp.delimiter1)');
        $sql->from('range_price', 'rp');
        $sql->innerJoin('carrier', 'c', 'c.id_carrier = rp.id_carrier');
        $sql->innerJoin(
            'carrier_shop',
            'cs',
            'cs.id_carrier = c.id_carrier AND cs.id_shop = ' . $idShop
        );
        $sql->innerJoin(
            'delivery',
            'd',
            'd.id_range_price = rp.id_range_price AND d.id_carrier = rp.id_carrier'
        );
        $sql->where('c.active = 1');
        $sql->where('c.deleted = 0');
        $sql->where('d.price = 0');
        $sql->where('rp.delimiter1 > 0');

        $result = \Db::getInstance()->getValue($sql);

        return ($result !== false && $result > 0) ? (float) $result : null;
    }

    private function findFromCartRules(): ?float
    {
        $idShop = (int) \Context::getContext()->shop->id;

        $sql = new \DbQuery();
        $sql->select('MIN(cr.minimum_amount)');
        $sql->from('cart_rule', 'cr');
        $sql->leftJoin(
            'cart_rule_shop',
            'crs',
            'crs.id_cart_rule = cr.id_cart_rule'
        );
        $sql->where('cr.active = 1');
        $sql->where('cr.free_shipping = 1');
        $sql->where('cr.minimum_amount > 0');
        $sql->where('cr.quantity > 0');
        $sql->where('(cr.date_from IS NULL OR cr.date_from <= NOW())');
        $sql->where('(cr.date_to IS NULL OR cr.date_to >= NOW())');
        $sql->where('(crs.id_shop IS NULL OR crs.id_shop = ' . $idShop . ')');

        $result = \Db::getInstance()->getValue($sql);

        return ($result !== false && $result > 0) ? (float) $result : null;
    }
}
