<?php
/**
 * 2016 Mijn Presta
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@mijnpresta.nl so we can send you a copy immediately.
 *
 *  @author    Michael Dekker <info@mijnpresta.nl>
 *  @copyright 2016 Mijn Presta
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class MPPaymentsToCarriers
 */
class MPPaymentsToCarriers extends Module
{
    public $hooks = array('actionObjectCarrierDeleteBefore');

    /** @var string $moduleUrl */
    public $moduleUrl;

    /**
     * Mppaymentstocarriers constructor.
     */
    public function __construct()
    {
        $this->name = 'mppaymentstocarriers';
        $this->tab = 'administration';
        $this->version = '1.2.0';
        $this->author = 'Mijn Presta';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Payments to carriers');
        $this->description = $this->l('Link payment methods to carriers');

        // Only check from Back Office
        if ($this->context->cookie->id_employee) {
            $this->moduleUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query(array(
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'module_name' => $this->name,
                ));
        }

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install the module
     *
     * @return bool Whether the module has been successfully installed
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        require_once _PS_MODULE_DIR_.$this->name.'/sql/install.php';

        $this->installCarrierPaymentRestrictions();

        foreach ($this->hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall the module
     *
     * @return bool Whether the module has been successfully uninstalled
     */
    public function uninstall()
    {
        foreach ($this->hooks as $hook) {
            $this->unregisterHook($hook);
        }

        return parent::uninstall();
    }

    /**
     * Get configuration form
     *
     * @return string Configuration form HTML
     */
    public function getContent()
    {
        $this->postProcess();
        $warnings = $this->detectBOSettingsWarnings();
        $errors = $this->detectBOSettingsErrors();
        $confirmations = array();

        if (empty($warnings) && empty($errors)) {
            $confirmations[] = $this->l('The module has been configured correctly');
        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'current_page' => $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name,
            'module_errors' => $errors,
            'module_warnings' => $warnings,
            'module_confirmations' => $confirmations,
            'modulesServices' => $this->getTabName('AdminModules', (int) $this->context->language->id),
            'payment' => $this->getTabName('AdminPayment', (int) $this->context->language->id),
        ));

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

    }

    /**
     * Add all methods in a module override to the override class
     *
     * @param string $className Class name
     *
     * @return bool Whether override has been successfully installed
     * @throws Exception
     */
    public function addOverride($className)
    {
        $origPath = $path = PrestaShopAutoload::getInstance()->getClassPath($className.'Core');
        if (!$path) {
            $path = 'modules'.DIRECTORY_SEPARATOR.$className.DIRECTORY_SEPARATOR.$className.'.php';
        }
        $pathOverride = $this->getLocalPath().'override'.DIRECTORY_SEPARATOR.$path;
        if (!file_exists($pathOverride)) {
            return false;
        } else {
            file_put_contents($pathOverride, preg_replace('#(\r\n|\r)#ism', "\n", Tools::file_get_contents($pathOverride)));
        }
        $patternEscapeCom = '#(^\s*?\/\/.*?\n|\/\*(?!\n\s+\* module:.*?\* date:.*?\* version:.*?\*\/).*?\*\/)#ism';
        // Check if there is already an override file, if not, we just need to copy the file
        if ($file = PrestaShopAutoload::getInstance()->getClassPath($className)) {
            // Check if override file is writable
            $overridePath = _PS_ROOT_DIR_.'/'.$file;
            if ((!file_exists($overridePath) && !is_writable(dirname($overridePath))) || (file_exists($overridePath) && !is_writable($overridePath))) {
                throw new Exception(sprintf(Tools::displayError('file (%s) not writable'), $overridePath));
            }
            // Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
            do {
                $uniq = uniqid();
            } while (class_exists($className.'OverrideOriginal_remove', false));
            // Make a reflection of the override class and the module override class
            $overrideFile = file($overridePath);
            $overrideFile = array_diff($overrideFile, array("\n"));
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$className.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$className.'OverrideOriginal'.$uniq), implode('', $overrideFile)));
            $overrideClass = new ReflectionClass($className.'OverrideOriginal'.$uniq);
            $moduleFile = file($pathOverride);
            $moduleFile = array_diff($moduleFile, array("\n"));
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$className.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$className.'Override'.$uniq), implode('', $moduleFile)));
            $moduleClass = new ReflectionClass($className.'Override'.$uniq);
            // Check if none of the methods already exists in the override class
            foreach ($moduleClass->getMethods() as $method) {
                if ($overrideClass->hasMethod($method->getName())) {
                    $methodOverride = $overrideClass->getMethod($method->getName());
                    if (preg_match('/module: (.*)/ism', $overrideFile[$methodOverride->getStartLine() - 5], $name) && preg_match('/date: (.*)/ism', $overrideFile[$methodOverride->getStartLine() - 4], $date) && preg_match('/version: ([0-9.]+)/ism', $overrideFile[$methodOverride->getStartLine() - 3], $version) && $name[1]) {
                        if ($name[1] == $this->name) {
                            return true;
                        }
                        throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden by the module %3$s version %4$s at %5$s.'), $method->getName(), $className, $name[1], $version[1], $date[1]));
                    }
                    throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden.'), $method->getName(), $className));
                }
                $moduleFile = preg_replace('/((:?public|private|protected)\s+(static\s+)?function\s+(?:\b'.$method->getName().'\b))/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1", $moduleFile);
                if ($moduleFile === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override method %1$s in class %2$s.'), $method->getName(), $className));
                }
            }
            // Check if none of the properties already exists in the override class
            foreach ($moduleClass->getProperties() as $property) {
                if ($overrideClass->hasProperty($property->getName())) {
                    throw new Exception(sprintf(Tools::displayError('The property %1$s in the class %2$s is already defined.'), $property->getName(), $className));
                }
                $moduleFile = preg_replace('/((?:public|private|protected)\s)\s*(static\s)?\s*(\$\b'.$property->getName().'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2$3", $moduleFile);
                if ($moduleFile === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override property %1$s in class %2$s.'), $property->getName(), $className));
                }
            }
            foreach ($moduleClass->getConstants() as $constant => $value) {
                if ($overrideClass->hasConstant($constant)) {
                    throw new Exception(sprintf(Tools::displayError('The constant %1$s in the class %2$s is already defined.'), $constant, $className));
                }
                $moduleFile = preg_replace('/(const\s)\s*(\b'.$constant.'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2", $moduleFile);
                if ($moduleFile === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override constant %1$s in class %2$s.'), $constant, $className));
                }
            }
            // Insert the methods from module override in override
            $copyFrom = array_slice($moduleFile, $moduleClass->getStartLine() + 1, $moduleClass->getEndLine() - $moduleClass->getStartLine() - 2);
            array_splice($overrideFile, $overrideClass->getEndLine() - 1, 0, $copyFrom);
            $code = implode('', $overrideFile);
            file_put_contents($overridePath, preg_replace($patternEscapeCom, '', $code));
        } else {
            $overrideSrc = $pathOverride;
            $overrideDest = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'override'.DIRECTORY_SEPARATOR.$path;
            $dirName = dirname($overrideDest);
            if (!$origPath && !is_dir($dirName)) {
                $oldumask = umask(0000);
                @mkdir($dirName, 0777);
                umask($oldumask);
            }
            if (!is_writable($dirName)) {
                throw new Exception(sprintf(Tools::displayError('directory (%s) not writable'), $dirName));
            }
            $moduleFile = file($overrideSrc);
            $moduleFile = array_diff($moduleFile, array("\n"));
            if ($origPath) {
                do {
                    $uniq = uniqid();
                } while (class_exists($className.'OverrideOriginal_remove', false));
                eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$className.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$className.'Override'.$uniq), implode('', $moduleFile)));
                $moduleClass = new ReflectionClass($className.'Override'.$uniq);
                // For each method found in the override, prepend a comment with the module name and version
                foreach ($moduleClass->getMethods() as $method) {
                    $moduleFile = preg_replace('/((:?public|private|protected)\s+(static\s+)?function\s+(?:\b'.$method->getName().'\b))/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1", $moduleFile);
                    if ($moduleFile === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override method %1$s in class %2$s.'), $method->getName(), $className));
                    }
                }
                // Same loop for properties
                foreach ($moduleClass->getProperties() as $property) {
                    $moduleFile = preg_replace('/((?:public|private|protected)\s)\s*(static\s)?\s*(\$\b'.$property->getName().'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2$3", $moduleFile);
                    if ($moduleFile === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override property %1$s in class %2$s.'), $property->getName(), $className));
                    }
                }
                // Same loop for constants
                foreach ($moduleClass->getConstants() as $constant => $value) {
                    $moduleFile = preg_replace('/(const\s)\s*(\b'.$constant.'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2", $moduleFile);
                    if ($moduleFile === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override constant %1$s in class %2$s.'), $constant, $className));
                    }
                }
            }
            file_put_contents($overrideDest, preg_replace($patternEscapeCom, '', $moduleFile));
            // Re-generate the class index
            Tools::generateIndex();
        }

        return true;
    }

    /**
     * Remove all methods in a module override from the override class
     *
     * @param string $className Class name
     *
     * @return bool Whether override has been successfully removed
     */
    public function removeOverride($className)
    {
        $origPath = $path = PrestaShopAutoload::getInstance()->getClassPath($className.'Core');
        if ($origPath && !$file = PrestaShopAutoload::getInstance()->getClassPath($className)) {
            return true;
        } elseif (!$origPath && Module::getModuleIdByName($className)) {
            $path = 'modules'.DIRECTORY_SEPARATOR.$className.DIRECTORY_SEPARATOR.$className.'.php';
        }
        // Check if override file is writable
        if ($origPath) {
            $overridePath = _PS_ROOT_DIR_.'/'.$file;
        } else {
            $overridePath = _PS_OVERRIDE_DIR_.$path;
        }
        if (!is_file($overridePath) || !is_writable($overridePath)) {
            return false;
        }
        file_put_contents($overridePath, preg_replace('#(\r\n|\r)#ism', "\n", Tools::file_get_contents($overridePath)));
        if ($origPath) {
            // Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
            do {
                $uniq = uniqid();
            } while (class_exists($className.'OverrideOriginal_remove', false));
            // Make a reflection of the override class and the module override class
            $overrideFile = file($overridePath);
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$className.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$className.'OverrideOriginal_remove'.$uniq), implode('', $overrideFile)));
            $overrideClass = new ReflectionClass($className.'OverrideOriginal_remove'.$uniq);
            $moduleFile = file($this->getLocalPath().'override/'.$path);
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$className.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$className.'Override_remove'.$uniq), implode('', $moduleFile)));
            $moduleClass = new ReflectionClass($className.'Override_remove'.$uniq);
            // Remove methods from override file
            foreach ($moduleClass->getMethods() as $method) {
                if (!$overrideClass->hasMethod($method->getName())) {
                    continue;
                }
                $method = $overrideClass->getMethod($method->getName());
                $length = $method->getEndLine() - $method->getStartLine() + 1;
                $moduleMethod = $moduleClass->getMethod($method->getName());
                $overrideFileOrig = $overrideFile;
                $origContent = preg_replace('/\s/', '', implode('', array_splice($overrideFile, $method->getStartLine() - 1, $length, array_pad(array(), $length, '#--remove--#'))));
                $moduleContent = preg_replace('/\s/', '', implode('', array_splice($moduleFile, $moduleMethod->getStartLine() - 1, $length, array_pad(array(), $length, '#--remove--#'))));
                $replace = true;
                if (preg_match('/\* module: ('.$this->name.')/ism', $overrideFile[$method->getStartLine() - 5])) {
                    $overrideFile[$method->getStartLine() - 6] = $overrideFile[$method->getStartLine() - 5] = $overrideFile[$method->getStartLine() - 4] = $overrideFile[$method->getStartLine() - 3] = $overrideFile[$method->getStartLine() - 2] = '#--remove--#';
                    $replace = false;
                }
                if (md5($moduleContent) != md5($origContent) && $replace) {
                    $overrideFile = $overrideFileOrig;
                }
            }
            // Remove properties from override file
            foreach ($moduleClass->getProperties() as $property) {
                if (!$overrideClass->hasProperty($property->getName())) {
                    continue;
                }
                // Replace the declaration line by #--remove--#
                foreach ($overrideFile as $lineNumber => &$lineContent) {
                    if (preg_match('/(public|private|protected)\s+(static\s+)?(\$)?'.$property->getName().'/i', $lineContent)) {
                        if (preg_match('/\* module: ('.$this->name.')/ism', $overrideFile[$lineNumber - 4])) {
                            $overrideFile[$lineNumber - 5] = $overrideFile[$lineNumber - 4] = $overrideFile[$lineNumber - 3] = $overrideFile[$lineNumber - 2] = $overrideFile[$lineNumber - 1] = '#--remove--#';
                        }
                        $lineContent = '#--remove--#';
                        break;
                    }
                }
            }
            // Remove properties from override file
            foreach ($moduleClass->getConstants() as $constant => $value) {
                if (!$overrideClass->hasConstant($constant)) {
                    continue;
                }
                // Replace the declaration line by #--remove--#
                foreach ($overrideFile as $lineNumber => &$lineContent) {
                    if (preg_match('/(const)\s+(static\s+)?(\$)?'.$constant.'/i', $lineContent)) {
                        if (preg_match('/\* module: ('.$this->name.')/ism', $overrideFile[$lineNumber - 4])) {
                            $overrideFile[$lineNumber - 5] = $overrideFile[$lineNumber - 4] = $overrideFile[$lineNumber - 3] = $overrideFile[$lineNumber - 2] = $overrideFile[$lineNumber - 1] = '#--remove--#';
                        }
                        $lineContent = '#--remove--#';
                        break;
                    }
                }
            }
            $count = count($overrideFile);
            for ($i = 0; $i < $count; ++$i) {
                if (preg_match('/(^\s*\/\/.*)/i', $overrideFile[$i])) {
                    $overrideFile[$i] = '#--remove--#';
                } elseif (preg_match('/(^\s*\/\*)/i', $overrideFile[$i])) {
                    if (!preg_match('/(^\s*\* module:)/i', $overrideFile[$i + 1])
                        && !preg_match('/(^\s*\* date:)/i', $overrideFile[$i + 2])
                        && !preg_match('/(^\s*\* version:)/i', $overrideFile[$i + 3])
                        && !preg_match('/(^\s*\*\/)/i', $overrideFile[$i + 4])) {
                        for (; $overrideFile[$i] && !preg_match('/(.*?\*\/)/i', $overrideFile[$i]); ++$i) {
                            $overrideFile[$i] = '#--remove--#';
                        }
                        $overrideFile[$i] = '#--remove--#';
                    }
                }
            }
            // Rewrite nice code
            $code = '';
            foreach ($overrideFile as $line) {
                if ($line == '#--remove--#') {
                    continue;
                }
                $code .= $line;
            }
            $toDelete = preg_match('/<\?(?:php)?\s+(?:abstract|interface)?\s*?class\s+'.$className.'\s+extends\s+'.$className.'Core\s*?[{]\s*?[}]/ism', $code);
        }
        if (!isset($toDelete) || $toDelete) {
            unlink($overridePath);
        } else {
            file_put_contents($overridePath, $code);
        }
        // Re-generate the class index
        Tools::generateIndex();

        return true;
    }

    /**
     * Hook to Carrier delete action
     *
     * @param Carrier $params Carrier object
     *
     * @return bool Hook has been successfully executed
     */
    public function hookActionObjectCarrierDeleteBefore($params)
    {
        /** @var Carrier $carrier */
        $carrier = $params['object'];
        if (!Module::isEnabled('mppaymentstocarriers') || version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return true;
        }

        Carrier::cleanPositions();

        return (
            Db::getInstance()->delete('cart_rule_carrier', '`id_carrier` = '.(int) $carrier->id)
            && Db::getInstance()->delete('module_carrier', 'id_reference = '.(int) $carrier->id_reference)
            && $carrier->deleteTaxRulesGroup(Shop::getShops(true, null, true))
        );
    }

    /**
     * Post process
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('fixitall')) {
            $this->fixItAll();
        }
    }

    /**
     * Installs the default carrier payment restrictions
     *
     * @throws PrestaShopDatabaseException
     */
    protected function installCarrierPaymentRestrictions()
    {
        $sql = new DbQuery();
        $sql->select('s.`id_shop`');
        $sql->from('shop', 's');
        $shops = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $carriers = Carrier::getCarriers($this->context->language->id, false, false, false, null, Carrier::ALL_CARRIERS);

        $modules = Module::getModulesOnDisk(true);

        $paymentModules = array();
        foreach ($modules as $module) {
            if ($module->tab == 'payments_gateways') {
                $paymentModules[] = $module;
            }
        }

        foreach ($shops as $shop) {
            foreach ($carriers as $carrier) {
                foreach ($paymentModules as $module) {
                    Db::getInstance()->insert(
                        'module_carrier',
                        array(
                            'id_reference' => (int) $carrier['id_reference'],
                            'id_module' => (int) $module->id,
                            'id_shop' => (int) $shop['id_shop'],
                        ),
                        false,
                        false,
                        Db::INSERT_IGNORE
                    );
                }
            }
        }
    }

    /**
     * Detect Back Office settings
     *
     * @return array Array with error message strings
     */
    protected function detectBOSettingsWarnings()
    {
        $warnings = array();

        foreach ($this->hooks as $hook) {
            $check = $this->generateHookWarning($hook);
            if (!empty($check)) {
                $warnings[] = $check;
            }
        }

        return $warnings;
    }

    /**
     * Detect Back Office settings
     *
     * @return array Array with error message strings
     */
    protected function detectBOSettingsErrors()
    {
        $idLang = (int) Context::getContext()->language->id;

        $this->context->smarty->assign(array(
            'performancePage' => $this->context->link->getAdminLink('AdminPerformance', true),
            'nonnativeMainTab' => $this->getTabName('AdminParentPreferences', $idLang),
            'nonnativeSubTab' => $this->getTabName('AdminPerformance', $idLang),
            'nonnativeOption' => Translate::getAdminTranslation('Disable overrides', 'AdminPerformance'),
            'overrideMainTab' => $this->getTabName('AdminParentPreferences', $idLang),
            'overrideSubTab' => $this->getTabName('AdminPerformance', $idLang),
            'overrideOption' => Translate::getAdminTranslation('Disable non PrestaShop modules', 'AdminPerformance'),
            'yesText' => Translate::getAdminTranslation('Yes', 'AdminPerformance'),
            'noText' => Translate::getAdminTranslation('No', 'AdminPerformance'),
        ));

        $output = array();
        if (Configuration::get('PS_DISABLE_NON_NATIVE_MODULE')) {
            $output[] = $this->context->smarty->fetch($this->local_path.'views/templates/admin/non_native_warning.tpl');
        }
        if (Configuration::get('PS_DISABLE_OVERRIDES')) {
            $output[] = $this->context->smarty->fetch($this->local_path.'views/templates/admin/override_warning.tpl');
        }

        return $output;
    }

    /**
     * Generate a hook warning
     *
     * @param string $hookName Hook name
     *
     *@return string Hook warning
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function generateHookWarning($hookName)
    {
        $idHook = (int) Hook::getIdByName($hookName);

        if ($idHook) {
            $hookModuleSql = new DbQuery();
            $hookModuleSql->select('hm.`id_shop`');
            $hookModuleSql->from('hook_module', 'hm');
            $hookModuleSql->innerJoin('hook', 'h', 'hm.`id_hook` = h.`id_hook`');
            $hookModuleSql->where('hm.`id_module` = '.(int) $this->id);
            $hookModuleSql->where('hm.`id_hook` = '.(int) $idHook);

            $shopSql = new DbQuery();
            $shopSql->select('s.`name`');
            $shopSql->from('shop', 's');
            $shopSql->where('s.`id_shop` NOT IN ('.$hookModuleSql->build().')');

            $shops = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($shopSql);

            if (!empty($shops) && is_array($shops)) {
                foreach ($shops as &$shop) {
                    $shop = $shop['name'];
                }
                $this->context->smarty->assign(array(
                    'shops' => implode(', ', $shops),
                    'hookName' => $hookName,
                ));

                return $this->context->smarty->fetch($this->local_path.'views/templates/admin/hook_warning.tpl');
            }
        }

        return '';
    }

    /**
     * Get Tab name from database
     *
     * @param $className string Class name of tab
     * @param $idLang int Language id
     *
     * @return string Returns the localized tab name
     */
    protected function getTabName($className, $idLang)
    {
        if ($className == null || $idLang == null) {
            return '';
        }

        $sql = new DbQuery();
        $sql->select('tl.`name`');
        $sql->from('tab_lang', 'tl');
        $sql->innerJoin('tab', 't', 't.`id_tab` = tl.`id_tab`');
        $sql->where('t.`class_name` = \''.pSQL($className).'\'');
        $sql->where('tl.`id_lang` = '.(int) $idLang);

        try {
            return (string) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        } catch (Exception $e) {
            return $this->l('Unknown');
        }
    }

    /**
     * Attemp to fix it all
     */
    protected function fixItAll()
    {
        // Manage overrides
        $this->uninstallOverrides();
        $this->installOverrides();

        // Register hooks
        foreach ($this->hooks as $hook) {
            $this->registerHook($hook);
        }

        $this->updateAllValue('PS_DISABLE_NON_NATIVE_MODULE', false);
        $this->updateAllValue('PS_DISABLE_OVERRIDES', false);

        // Redirect in order to load the latest settings
        Tools::redirectAdmin($this->moduleUrl);
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key    Configuration key
     * @param mixed  $values Configuration values, can be string or array with id_lang as key
     * @param bool   $html   Contains HTML
     */
    protected function updateAllValue($key, $values, $html = false)
    {
        foreach (Shop::getShops() as $shop) {
            Configuration::updateValue($key, $values, $html, $shop['id_shop_group'], $shop['id_shop']);
        }
        Configuration::updateGlobalValue($key, $values, $html);
    }
}
