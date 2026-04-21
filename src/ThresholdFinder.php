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

        return $this->findFromCarrierRules();
    }

    private function findFromCarrierRules(): ?float
    {
        $sql = new \DbQuery();
        $sql->select('MIN(rp.delimiter1)');
        $sql->from('range_price', 'rp');
        $sql->innerJoin('carrier', 'c', 'c.id_carrier = rp.id_carrier');
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
}
