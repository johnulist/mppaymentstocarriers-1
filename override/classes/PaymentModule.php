<?php

class PaymentModule extends PaymentModuleCore
{
    public function install()
    {
        if (!Module::isEnabled('mppaymentstocarriers') || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return parent::install();
        }

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
        if (!Module::isEnabled('mppaymentstocarriers') || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return parent::uninstall();
        }

        if (!Db::getInstance()->delete('module_country', '`id_module` = '.(int) $this->id)
            || !Db::getInstance()->delete('module_currency', '`id_module` = '.(int) $this->id)
            || !Db::getInstance()->delete('module_group', '`id_module` = '.(int) $this->id)
            || !Db::getInstance()->delete('module_carrier', 'id_module` = '.(int) $this->id)) {
            return false;
        }

        return parent::uninstall();
    }

    public function addCheckboxCarrierRestrictionsForModule(array $shops = array())
    {

        if (!Module::isEnabled('mppaymentstocarriers') || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return parent::addCheckboxCountryRestrictionsForModule($shops);
        }

        if (!$shops) {
            $shops = Shop::getShops(true, null, true);
        }

        $carriers = Carrier::getCarriers((int) Context::getContext()->language->id);
        $carrierIds = array();
        foreach ($carriers as $carrier) {
            $carrierIds[] = $carrier['id_reference'];
        }

        foreach ($shops as $shop) {
            foreach ($carrierIds as $idCarrier) {
                if (!Db::getInstance()->insert(
                    'module_carrier',
                    array(
                        'id_module' => (int) $this->id,
                        'id_shop' => (int) $shop,
                        'id_reference' => (int) $idCarrier,
                    )
                )) {
                    return false;
                }
            }
        }

        return true;
    }
}
