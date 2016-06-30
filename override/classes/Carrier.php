<?php

class Carrier extends CarrierCore
{
    public function delete()
    {
        if (!parent::delete()) {
            return false;
        }
        Carrier::cleanPositions();
        return (Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'cart_rule_carrier WHERE id_carrier = '.(int)$this->id) &&
            Db::getInstance()->delete('module_carrier', 'id_reference = '.(int)$this->id_reference) &&
            $this->deleteTaxRulesGroup(Shop::getShops(true, null, true)));
    }
}
