<?php

class PaymentModule extends PaymentModuleCore
{
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Insert currencies availability
        if ($this->currencies_mode == 'checkbox') {
            if (!$this->addCheckboxCurrencyRestrictionsForModule()) {
                return false;
            }
        } elseif ($this->currencies_mode == 'radio') {
            if (!$this->addRadioCurrencyRestrictionsForModule()) {
                return false;
            }
        } else {
            Tools::displayError('No currency mode for payment module');
        }

        // Insert countries availability
        $return = $this->addCheckboxCountryRestrictionsForModule();

        // Insert carrier availability
        $return &= $this->addCheckboxCarrierRestrictionsForModule();

        if (!Configuration::get('CONF_'.Tools::strtoupper($this->name).'_FIXED')) {
            Configuration::updateValue('CONF_'.Tools::strtoupper($this->name).'_FIXED', '0.2');
        }
        if (!Configuration::get('CONF_'.Tools::strtoupper($this->name).'_VAR')) {
            Configuration::updateValue('CONF_'.Tools::strtoupper($this->name).'_VAR', '2');
        }
        if (!Configuration::get('CONF_'.Tools::strtoupper($this->name).'_FIXED_FOREIGN')) {
            Configuration::updateValue('CONF_'.Tools::strtoupper($this->name).'_FIXED_FOREIGN', '0.2');
        }
        if (!Configuration::get('CONF_'.Tools::strtoupper($this->name).'_VAR_FOREIGN')) {
            Configuration::updateValue('CONF_'.Tools::strtoupper($this->name).'_VAR_FOREIGN', '2');
        }

        return $return;
    }

    public function uninstall()
    {
        if (!Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'module_country` WHERE `id_module` = '.(int)$this->id)
            || !Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'module_currency` WHERE `id_module` = '.(int)$this->id)
            || !Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'module_group` WHERE `id_module` = '.(int)$this->id)
            || !Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'module_carrier` WHERE `id_module` = '.(int)$this->id)) {
            return false;
        }

        return parent::uninstall();
    }

    public function addCheckboxCarrierRestrictionsForModule(array $shops = array())
    {
        if (!$shops) {
            $shops = Shop::getShops(true, null, true);
        }

        $carriers = Carrier::getCarriers((int) Context::getContext()->language->id);
        $carrierIds = array();
        foreach ($carriers as $carrier) {
            $carrierIds[] = $carrier['id_reference'];
        }

        foreach ($shops as $s) {
            foreach ($carrierIds as $idCarrier) {
                if (!Db::getInstance()->execute(
                    'INSERT INTO `'._DB_PREFIX_.'module_carrier` (`id_module`, `id_shop`, `id_reference`)
    VALUES ('.(int) $this->id.', "'.(int) $s.'", '.(int) $idCarrier.')')) {
                    return false;
                }
            }
        }

        return true;
    }
}
