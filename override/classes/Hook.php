<?php

class Hook extends HookCore
{
    public static function getHookModuleExecList($hookName = null)
    {
        $context = Context::getContext();
        $idCache = 'hook_module_exec_list_'.(isset($context->shop->id) ? '_'.$context->shop->id : '').((isset($context->customer)) ? '_'.$context->customer->id : '');
        if (!Cache::isStored($idCache) || $hookName == 'displayPayment' || $hookName == 'displayPaymentEU' || $hookName == 'displayBackOfficeHeader') {
            $frontend = true;
            $groups = array();
            $useGroups = Group::isFeatureActive();
            if (isset($context->employee)) {
                $frontend = false;
            } else {
                // Get groups list
                if ($useGroups) {
                    if (isset($context->customer) && $context->customer->isLogged()) {
                        $groups = $context->customer->getGroups();
                    } elseif (isset($context->customer) && $context->customer->isLogged(true)) {
                        $groups = array((int) Configuration::get('PS_GUEST_GROUP'));
                    } else {
                        $groups = array((int) Configuration::get('PS_UNIDENTIFIED_GROUP'));
                    }
                }
            }

            // SQL Request
            $sql = new DbQuery();
            $sql->select('h.`name` as hook, m.`id_module`, h.`id_hook`, m.`name` as module');
            $sql->from('module', 'm');
            if ($hookName != 'displayBackOfficeHeader') {
                $sql->join(Shop::addSqlAssociation('module', 'm', true, 'module_shop.enable_device & '.(int) Context::getContext()->getDevice()));
                $sql->innerJoin('module_shop', 'ms', 'ms.`id_module` = m.`id_module`');
            }
            $sql->innerJoin('hook_module', 'hm', 'hm.`id_module` = m.`id_module`');
            $sql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
            if ($hookName != 'displayPayment' && $hookName != 'displayPaymentEU') {
                $sql->where('h.name != "displayPayment" AND h.name != "displayPaymentEU"');
            } elseif ($frontend) {
                if (Validate::isLoadedObject($context->country)) {
                    $sql->where('((h.`name` = "displayPayment" OR h.`name` = "displayPaymentEU") AND (SELECT `id_country` FROM `'._DB_PREFIX_.'module_country` mc WHERE mc.`id_module` = m.`id_module` AND `id_country` = '.(int) $context->country->id.' AND `id_shop` = '.(int) $context->shop->id.' LIMIT 1) = '.(int) $context->country->id.')');
                }
                if (Validate::isLoadedObject($context->currency)) {
                    $sql->where('((h.`name` = "displayPayment" OR h.`name` = "displayPaymentEU") AND (SELECT `id_currency` FROM `'._DB_PREFIX_.'module_currency` mcr WHERE mcr.`id_module` = m.`id_module` AND `id_currency` IN ('.(int) $context->currency->id.', -1, -2) LIMIT 1) IN ('.(int) $context->currency->id.', -1, -2))');
                }
                if (Validate::isLoadedObject($context->cart)) {
                    $carrier = new Carrier($context->cart->id_carrier);
                    if (Validate::isLoadedObject($carrier)) {
                        $sql->where('((h.`name` = "displayPayment" OR h.`name` = "displayPaymentEU") AND (SELECT `id_reference` FROM `'._DB_PREFIX_.'module_carrier` mcar WHERE mcar.`id_module` = m.`id_module` AND `id_reference` = '.(int) $carrier->id_reference.' AND `id_shop` = '.(int) $context->shop->id.' LIMIT 1) = '.(int) $carrier->id_reference.')');
                    }
                }
            }
            if (Validate::isLoadedObject($context->shop)) {
                $sql->where('hm.id_shop = '.(int) $context->shop->id);
            }

            if ($frontend) {
                if ($useGroups) {
                    $sql->leftJoin('module_group', 'mg', 'mg.`id_module` = m.`id_module`');
                    if (Validate::isLoadedObject($context->shop)) {
                        $sql->where('mg.id_shop = '.((int) $context->shop->id).(count($groups) ? ' AND  mg.`id_group` IN ('.implode(', ', $groups).')' : ''));
                    } elseif (count($groups)) {
                        $sql->where('mg.`id_group` IN ('.implode(', ', $groups).')');
                    }
                }
            }

            $sql->groupBy('hm.id_hook, hm.id_module');
            $sql->orderBy('hm.`position`');

            $list = array();
            if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
                foreach ($result as $row) {
                    $row['hook'] = Tools::strtolower($row['hook']);
                    if (!isset($list[$row['hook']])) {
                        $list[$row['hook']] = array();
                    }

                    $list[$row['hook']][] = array(
                        'id_hook' => $row['id_hook'],
                        'module' => $row['module'],
                        'id_module' => $row['id_module'],
                    );
                }
            }
            if ($hookName != 'displayPayment' && $hookName != 'displayPaymentEU' && $hookName != 'displayBackOfficeHeader') {
                Cache::store($idCache, $list);
                // @todo remove this in 1.6, we keep it in 1.5 for backward compatibility
                self::$_hook_modules_cache_exec = $list;
            }
        } else {
            $list = Cache::retrieve($idCache);
        }

        // If hook_name is given, just get list of modules for this hook
        if ($hookName) {
            $retroHookName = Tools::strtolower(Hook::getRetroHookName($hookName));
            $hookName = Tools::strtolower($hookName);

            $return = array();
            $insertedModules = array();
            if (isset($list[$hookName])) {
                $return = $list[$hookName];
            }
            foreach ($return as $module) {
                $insertedModules[] = $module['id_module'];
            }
            if (isset($list[$retroHookName])) {
                foreach ($list[$retroHookName] as $retroModuleCall) {
                    if (!in_array($retroModuleCall['id_module'], $insertedModules)) {
                        $return[] = $retroModuleCall;
                    }
                }
            }

            return (count($return) > 0 ? $return : false);
        } else {
            return $list;
        }
    }
}
