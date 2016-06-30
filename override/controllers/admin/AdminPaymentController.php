<?php

class AdminPaymentController extends AdminPaymentControllerCore
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        $idShop = Context::getContext()->shop->id;

        /* Get all modules then select only payment ones */
        $modules = Module::getModulesOnDisk(true);

        foreach ($modules as $module) {
            if ($module->tab == 'payments_gateways') {
                if ($module->id) {
                    if (!get_class($module) == 'SimpleXMLElement') {
                        $module->country = array();
                    }

                    $sql = new DbQuery();
                    $sql->select('`id_country`');
                    $sql->from('module_country');
                    $sql->where('`id_module` = '.(int) $module->id);
                    $sql->where('`id_shop` = '.(int) $idShop);
                    $countries = Db::getInstance()->executeS($sql);
                    foreach ($countries as $country) {
                        $module->country[] = $country['id_country'];
                    }

                    if (!get_class($module) == 'SimpleXMLElement') {
                        $module->currency = array();
                    }

                    $sql = new DbQuery();
                    $sql->select('`id_currency`');
                    $sql->from('module_currency');
                    $sql->where('`id_module` = '.(int) $module->id);
                    $sql->where('`id_shop` = '.(int) $idShop);
                    $currencies = Db::getInstance()->executeS($sql);
                    foreach ($currencies as $currency) {
                        $module->currency[] = $currency['id_currency'];
                    }

                    if (!get_class($module) == 'SimpleXMLElement') {
                        $module->group = array();
                    }

                    $sql = new DbQuery();
                    $sql->select('`id_group`');
                    $sql->from('module_group');
                    $sql->where('`id_module` = '.(int) $module->id);
                    $sql->where('`id_shop` = '.(int) $idShop);
                    $groups = Db::getInstance()->executeS($sql);
                    foreach ($groups as $group) {
                        $module->group[] = $group['id_group'];
                    }

                    if (!get_class($module) == 'SimpleXMLElement') {
                        $module->reference = array();
                    }
                    $sql = new DbQuery();
                    $sql->select('`id_reference`');
                    $sql->from('module_carrier');
                    $sql->where('`id_module` = '.(int) $module->id);
                    $sql->where('`id_shop` = '.(int) $idShop);
                    $carriers = Db::getInstance()->executeS($sql);
                    foreach ($carriers as $carrier) {
                        $module->reference[] = $carrier['id_reference'];
                    }
                } else {
                    $module->country = null;
                    $module->currency = null;
                    $module->group = null;
                }

                $this->payment_modules[] = $module;
            }
        }
    }

    public function initProcess()
    {
        if ($this->tabAccess['edit'] === '1') {
            if (Tools::isSubmit('submitModulecountry')) {
                $this->action = 'country';
            } elseif (Tools::isSubmit('submitModulecurrency')) {
                $this->action = 'currency';
            } elseif (Tools::isSubmit('submitModulegroup')) {
                $this->action = 'group';
            } elseif (Tools::isSubmit('submitModulereference')) {
                $this->action = 'carrier';
            }
        } else {
            $this->errors[] = Tools::displayError('You do not have permission to edit this.');
        }
    }

    protected function saveRestrictions($type)
    {
        // Delete type restrictions for active module.
        $modules = array();
        foreach ($this->payment_modules as $module) {
            if ($module->active) {
                $modules[] = (int) $module->id;
            }
        }

        $modules = array_unique($modules);

        Db::getInstance()->execute(
            '
  			DELETE FROM `'._DB_PREFIX_.'module_'.bqSQL($type).'`
 			WHERE `id_shop` = '.Context::getContext()->shop->id.'
  			AND `id_module` IN ('.implode(', ', $modules).')'
        );

        if ($type === 'carrier') {
            // Fill the new restriction selection for active module.
            $values = array();
            foreach ($this->payment_modules as $module) {
                if ($module->active && Tools::getIsset($module->name.'_reference')) {
                    foreach (Tools::getValue($module->name.'_reference') as $selected) {
                        $values[] = '('.(int) $module->id.', '.(int) Context::getContext()->shop->id.', '.(int) $selected.')';
                    }
                }
            }
            if (count($values)) {
                Db::getInstance()->execute(
                    'INSERT INTO `'._DB_PREFIX_.'module_carrier`
                    				(`id_module`, `id_shop`, `id_reference`)
 				VALUES '.implode(',', $values)
                );
            }
        } else {
            // Fill the new restriction selection for active module.
            $values = array();
            foreach ($this->payment_modules as $module) {
                if ($module->active && Tools::getIsset($module->name.'_'.$type.'')) {
                    foreach (Tools::getValue($module->name.'_'.$type.'') as $selected) {
                        $values[] = '('.(int) $module->id.', '.(int) Context::getContext()->shop->id.', '.(int) $selected.')';
                    }
                }
            }

            if (count($values)) {
                Db::getInstance()->execute(
                    '
  				INSERT INTO `'._DB_PREFIX_.'module_'.bqSQL($type).'`
  				(`id_module`, `id_shop`, `id_'.bqSQL($type).'`)
  				VALUES '.implode(',', $values)
                );
            }
        }

        Tools::redirectAdmin(self::$currentIndex.'&conf=4'.'&token='.$this->token);
    }

    public function renderView()
    {
        $this->toolbar_title = $this->l('Payment');
        unset($this->toolbar_btn['back']);

        $shopContext = (!Shop::isFeatureActive() || Shop::getContext() == Shop::CONTEXT_SHOP);
        if (!$shopContext) {
            $this->tpl_view_vars = array('shop_context' => $shopContext);
            return parent::renderView();
        }

        // link to modules page
        if (isset($this->payment_modules[0])) {
            $token_modules = Tools::getAdminToken('AdminModules'.(int)Tab::getIdFromClassName('AdminModules').(int)$this->context->employee->id);
        }

        $displayRestrictions = false;
        foreach ($this->payment_modules as $module) {
            if ($module->active) {
                $displayRestrictions = true;
                break;
            }
        }

        $lists = array(
            array('items' => Currency::getCurrencies(),
                'title' => $this->l('Currency restrictions'),
                'desc' => $this->l('Please mark each checkbox for the currency, or currencies, in which you want the payment module(s) to be available.'),
                'name_id' => 'currency',
                'identifier' => 'id_currency',
                'icon' => 'icon-money',
            ),
            array('items' => Group::getGroups($this->context->language->id),
                'title' => $this->l('Group restrictions'),
                'desc' => $this->l('Please mark each checkbox for the customer group(s), in which you want the payment module(s) to be available.'),
                'name_id' => 'group',
                'identifier' => 'id_group',
                'icon' => 'icon-group',
            ),
            array('items' =>Country::getCountries($this->context->language->id),
                'title' => $this->l('Country restrictions'),
                'desc' => $this->l('Please mark each checkbox for the country, or countries, in which you want the payment module(s) to be available.'),
                'name_id' => 'country',
                'identifier' => 'id_country',
                'icon' => 'icon-globe',
            ),
            array('items' => Carrier::getCarriers($this->context->language->id),
                'title' => $this->l('Carrier restrictions'),
                'desc' => $this->l('Please mark each checkbox for the carrier, or carrier, for which you want the payment module(s) to be available.'),
                'name_id' => 'reference',
                'identifier' => 'id_reference',
                'icon' => 'icon-truck',
            ),
        );

        foreach ($lists as $keyList => $list) {
            $list['check_list'] = array();
            foreach ($list['items'] as $keyItem => $item) {
                $idName = $list['name_id'];

                if ($idName === 'currency'
                    && Tools::strpos($list['items'][$keyItem]['name'], '('.$list['items'][$keyItem]['iso_code'].')') === false) {
                    $list['items'][$keyItem]['name'] = sprintf($this->l('%1$s (%2$s)'), $list['items'][$keyItem]['name'],
                        $list['items'][$keyItem]['iso_code']);
                }

                foreach ($this->payment_modules as $keyModule => $module) {
                    if (isset($module->$idName) && in_array($item['id_'.$idName], $module->$idName)) {
                        $list['items'][$keyItem]['check_list'][$keyModule] = 'checked';
                    } else {
                        $list['items'][$keyItem]['check_list'][$keyModule] = 'unchecked';
                    }

                    if (!isset($module->$idName)) {
                        $module->$idName = array();
                    }
                    if (!isset($module->currencies_mode)) {
                        $module->currencies_mode = '';
                    }
                    if (!isset($module->currencies)) {
                        $module->currencies = '';
                    }

                    // If is a country list and the country is limited, remove it from list
                    if ($idName == 'country'
                        && isset($module->limited_countries)
                        && !empty($module->limited_countries)
                        && is_array($module->limited_countries)
                        && !(in_array(Tools::strtoupper($item['iso_code']), array_map(array('Tools', 'strtoupper'), $module->limited_countries)))) {
                        $list['items'][$keyItem]['check_list'][$keyModule] = null;
                    }
                }
            }
            // update list
            $lists[$keyList] = $list;
        }

        $this->tpl_view_vars = array(
            'modules_list' => $this->renderModulesList(),
            'display_restrictions' => $displayRestrictions,
            'lists' => $lists,
            'ps_base_uri' => __PS_BASE_URI__,
            'payment_modules' => $this->payment_modules,
            'url_submit' => self::$currentIndex.'&token='.$this->token,
            'shop_context' => $shopContext
        );

        return AdminController::renderView();
    }
}
