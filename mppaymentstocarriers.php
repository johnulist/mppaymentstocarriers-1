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
    /**
     * Mppaymentstocarriers constructor.
     */
    public function __construct()
    {
        $this->name = 'mppaymentstocarriers';
        $this->tab = 'administration';
        $this->version = '1.1.1';
        $this->author = 'Mijn Presta';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Payments to carriers');
        $this->description = $this->l('Link payment methods to carriers');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install the module
     *
     * @return bool Whether the module has been succesfully installed
     */
    public function install()
    {
        require_once _PS_MODULE_DIR_.$this->name.'/sql/install.php';

        $this->installCarrierPaymentRestrictions();

        return parent::install();
    }

    /**
     * Uninstall the module
     *
     * @return bool Whether the module has been successfully uninstalled
     */
    public function uninstall()
    {
        require_once _PS_MODULE_DIR_.$this->name.'/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Add all methods in a module override to the override class
     *
     * @param string $classname
     * @return bool Whether override has been successfully installed
     * @throws Exception
     */
    public function addOverride($classname)
    {
        $orig_path = $path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');
        if (!$path) {
            $path = 'modules'.DIRECTORY_SEPARATOR.$classname.DIRECTORY_SEPARATOR.$classname.'.php';
        }
        $path_override = $this->getLocalPath().'override'.DIRECTORY_SEPARATOR.$path;
        if (!file_exists($path_override)) {
            return false;
        } else {
            file_put_contents($path_override, preg_replace('#(\r\n|\r)#ism', "\n", file_get_contents($path_override)));
        }
        $pattern_escape_com = '#(^\s*?\/\/.*?\n|\/\*(?!\n\s+\* module:.*?\* date:.*?\* version:.*?\*\/).*?\*\/)#ism';
        // Check if there is already an override file, if not, we just need to copy the file
        if ($file = PrestaShopAutoload::getInstance()->getClassPath($classname)) {
            // Check if override file is writable
            $override_path = _PS_ROOT_DIR_.'/'.$file;
            if ((!file_exists($override_path) && !is_writable(dirname($override_path))) || (file_exists($override_path) && !is_writable($override_path))) {
                throw new Exception(sprintf(Tools::displayError('file (%s) not writable'), $override_path));
            }
            // Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
            do {
                $uniq = uniqid();
            } while (class_exists($classname.'OverrideOriginal_remove', false));
            // Make a reflection of the override class and the module override class
            $override_file = file($override_path);
            $override_file = array_diff($override_file, array("\n"));
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal'.$uniq), implode('', $override_file)));
            $override_class = new ReflectionClass($classname.'OverrideOriginal'.$uniq);
            $module_file = file($path_override);
            $module_file = array_diff($module_file, array("\n"));
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override'.$uniq), implode('', $module_file)));
            $module_class = new ReflectionClass($classname.'Override'.$uniq);
            // Check if none of the methods already exists in the override class
            foreach ($module_class->getMethods() as $method) {
                if ($override_class->hasMethod($method->getName())) {
                    $method_override = $override_class->getMethod($method->getName());
                    if (preg_match('/module: (.*)/ism', $override_file[$method_override->getStartLine() - 5], $name) && preg_match('/date: (.*)/ism', $override_file[$method_override->getStartLine() - 4], $date) && preg_match('/version: ([0-9.]+)/ism', $override_file[$method_override->getStartLine() - 3], $version)) {
                        throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden by the module %3$s version %4$s at %5$s.'), $method->getName(), $classname, $name[1], $version[1], $date[1]));
                    }
                    throw new Exception(sprintf(Tools::displayError('The method %1$s in the class %2$s is already overridden.'), $method->getName(), $classname));
                }
                $module_file = preg_replace('/((:?public|private|protected)\s+(static\s+)?function\s+(?:\b'.$method->getName().'\b))/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1", $module_file);
                if ($module_file === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override method %1$s in class %2$s.'), $method->getName(), $classname));
                }
            }
            // Check if none of the properties already exists in the override class
            foreach ($module_class->getProperties() as $property) {
                if ($override_class->hasProperty($property->getName())) {
                    throw new Exception(sprintf(Tools::displayError('The property %1$s in the class %2$s is already defined.'), $property->getName(), $classname));
                }
                $module_file = preg_replace('/((?:public|private|protected)\s)\s*(static\s)?\s*(\$\b'.$property->getName().'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2$3", $module_file);
                if ($module_file === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override property %1$s in class %2$s.'), $property->getName(), $classname));
                }
            }
            foreach ($module_class->getConstants() as $constant => $value) {
                if ($override_class->hasConstant($constant)) {
                    throw new Exception(sprintf(Tools::displayError('The constant %1$s in the class %2$s is already defined.'), $constant, $classname));
                }
                $module_file = preg_replace('/(const\s)\s*(\b'.$constant.'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2", $module_file);
                if ($module_file === null) {
                    throw new Exception(sprintf(Tools::displayError('Failed to override constant %1$s in class %2$s.'), $constant, $classname));
                }
            }
            // Insert the methods from module override in override
            $copy_from = array_slice($module_file, $module_class->getStartLine() + 1, $module_class->getEndLine() - $module_class->getStartLine() - 2);
            array_splice($override_file, $override_class->getEndLine() - 1, 0, $copy_from);
            $code = implode('', $override_file);
            file_put_contents($override_path, preg_replace($pattern_escape_com, '', $code));
        } else {
            $override_src = $path_override;
            $override_dest = _PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'override'.DIRECTORY_SEPARATOR.$path;
            $dir_name = dirname($override_dest);
            if (!$orig_path && !is_dir($dir_name)) {
                $oldumask = umask(0000);
                @mkdir($dir_name, 0777);
                umask($oldumask);
            }
            if (!is_writable($dir_name)) {
                throw new Exception(sprintf(Tools::displayError('directory (%s) not writable'), $dir_name));
            }
            $module_file = file($override_src);
            $module_file = array_diff($module_file, array("\n"));
            if ($orig_path) {
                do {
                    $uniq = uniqid();
                } while (class_exists($classname.'OverrideOriginal_remove', false));
                eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override'.$uniq), implode('', $module_file)));
                $module_class = new ReflectionClass($classname.'Override'.$uniq);
                // For each method found in the override, prepend a comment with the module name and version
                foreach ($module_class->getMethods() as $method) {
                    $module_file = preg_replace('/((:?public|private|protected)\s+(static\s+)?function\s+(?:\b'.$method->getName().'\b))/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1", $module_file);
                    if ($module_file === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override method %1$s in class %2$s.'), $method->getName(), $classname));
                    }
                }
                // Same loop for properties
                foreach ($module_class->getProperties() as $property) {
                    $module_file = preg_replace('/((?:public|private|protected)\s)\s*(static\s)?\s*(\$\b'.$property->getName().'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2$3", $module_file);
                    if ($module_file === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override property %1$s in class %2$s.'), $property->getName(), $classname));
                    }
                }
                // Same loop for constants
                foreach ($module_class->getConstants() as $constant => $value) {
                    $module_file = preg_replace('/(const\s)\s*(\b'.$constant.'\b)/ism', "/*\n    * module: ".$this->name."\n    * date: ".date('Y-m-d H:i:s')."\n    * version: ".$this->version."\n    */\n    $1$2", $module_file);
                    if ($module_file === null) {
                        throw new Exception(sprintf(Tools::displayError('Failed to override constant %1$s in class %2$s.'), $constant, $classname));
                    }
                }
            }
            file_put_contents($override_dest, preg_replace($pattern_escape_com, '', $module_file));
            // Re-generate the class index
            Tools::generateIndex();
        }
        return true;
    }

    /**
     * Remove all methods in a module override from the override class
     *
     * @param string $classname
     * @return bool Whether override has been successfully removed
     */
    public function removeOverride($classname)
    {
        $orig_path = $path = PrestaShopAutoload::getInstance()->getClassPath($classname.'Core');
        if ($orig_path && !$file = PrestaShopAutoload::getInstance()->getClassPath($classname)) {
            return true;
        } elseif (!$orig_path && Module::getModuleIdByName($classname)) {
            $path = 'modules'.DIRECTORY_SEPARATOR.$classname.DIRECTORY_SEPARATOR.$classname.'.php';
        }
        // Check if override file is writable
        if ($orig_path) {
            $override_path = _PS_ROOT_DIR_.'/'.$file;
        } else {
            $override_path = _PS_OVERRIDE_DIR_.$path;
        }
        if (!is_file($override_path) || !is_writable($override_path)) {
            return false;
        }
        file_put_contents($override_path, preg_replace('#(\r\n|\r)#ism', "\n", file_get_contents($override_path)));
        if ($orig_path) {
            // Get a uniq id for the class, because you can override a class (or remove the override) twice in the same session and we need to avoid redeclaration
            do {
                $uniq = uniqid();
            } while (class_exists($classname.'OverrideOriginal_remove', false));
            // Make a reflection of the override class and the module override class
            $override_file = file($override_path);
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?#i'), array(' ', 'class '.$classname.'OverrideOriginal_remove'.$uniq), implode('', $override_file)));
            $override_class = new ReflectionClass($classname.'OverrideOriginal_remove'.$uniq);
            $module_file = file($this->getLocalPath().'override/'.$path);
            eval(preg_replace(array('#^\s*<\?(?:php)?#', '#class\s+'.$classname.'(\s+extends\s+([a-z0-9_]+)(\s+implements\s+([a-z0-9_]+))?)?#i'), array(' ', 'class '.$classname.'Override_remove'.$uniq), implode('', $module_file)));
            $module_class = new ReflectionClass($classname.'Override_remove'.$uniq);
            // Remove methods from override file
            foreach ($module_class->getMethods() as $method) {
                if (!$override_class->hasMethod($method->getName())) {
                    continue;
                }
                $method = $override_class->getMethod($method->getName());
                $length = $method->getEndLine() - $method->getStartLine() + 1;
                $module_method = $module_class->getMethod($method->getName());
                $module_length = $module_method->getEndLine() - $module_method->getStartLine() + 1;
                $override_file_orig = $override_file;
                $orig_content = preg_replace('/\s/', '', implode('', array_splice($override_file, $method->getStartLine() - 1, $length, array_pad(array(), $length, '#--remove--#'))));
                $module_content = preg_replace('/\s/', '', implode('', array_splice($module_file, $module_method->getStartLine() - 1, $length, array_pad(array(), $length, '#--remove--#'))));
                $replace = true;
                if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$method->getStartLine() - 5])) {
                    $override_file[$method->getStartLine() - 6] = $override_file[$method->getStartLine() - 5] = $override_file[$method->getStartLine() - 4] = $override_file[$method->getStartLine() - 3] = $override_file[$method->getStartLine() - 2] = '#--remove--#';
                    $replace = false;
                }
                if (md5($module_content) != md5($orig_content) && $replace) {
                    $override_file = $override_file_orig;
                }
            }
            // Remove properties from override file
            foreach ($module_class->getProperties() as $property) {
                if (!$override_class->hasProperty($property->getName())) {
                    continue;
                }
                // Replace the declaration line by #--remove--#
                foreach ($override_file as $line_number => &$line_content) {
                    if (preg_match('/(public|private|protected)\s+(static\s+)?(\$)?'.$property->getName().'/i', $line_content)) {
                        if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$line_number - 4])) {
                            $override_file[$line_number - 5] = $override_file[$line_number - 4] = $override_file[$line_number - 3] = $override_file[$line_number - 2] = $override_file[$line_number - 1] = '#--remove--#';
                        }
                        $line_content = '#--remove--#';
                        break;
                    }
                }
            }
            // Remove properties from override file
            foreach ($module_class->getConstants() as $constant => $value) {
                if (!$override_class->hasConstant($constant)) {
                    continue;
                }
                // Replace the declaration line by #--remove--#
                foreach ($override_file as $line_number => &$line_content) {
                    if (preg_match('/(const)\s+(static\s+)?(\$)?'.$constant.'/i', $line_content)) {
                        if (preg_match('/\* module: ('.$this->name.')/ism', $override_file[$line_number - 4])) {
                            $override_file[$line_number - 5] = $override_file[$line_number - 4] = $override_file[$line_number - 3] = $override_file[$line_number - 2] = $override_file[$line_number - 1] = '#--remove--#';
                        }
                        $line_content = '#--remove--#';
                        break;
                    }
                }
            }
            $count = count($override_file);
            for ($i = 0; $i < $count; ++$i) {
                if (preg_match('/(^\s*\/\/.*)/i', $override_file[$i])) {
                    $override_file[$i] = '#--remove--#';
                } elseif (preg_match('/(^\s*\/\*)/i', $override_file[$i])) {
                    if (!preg_match('/(^\s*\* module:)/i', $override_file[$i + 1])
                        && !preg_match('/(^\s*\* date:)/i', $override_file[$i + 2])
                        && !preg_match('/(^\s*\* version:)/i', $override_file[$i + 3])
                        && !preg_match('/(^\s*\*\/)/i', $override_file[$i + 4])) {
                        for (; $override_file[$i] && !preg_match('/(.*?\*\/)/i', $override_file[$i]); ++$i) {
                            $override_file[$i] = '#--remove--#';
                        }
                        $override_file[$i] = '#--remove--#';
                    }
                }
            }
            // Rewrite nice code
            $code = '';
            foreach ($override_file as $line) {
                if ($line == '#--remove--#') {
                    continue;
                }
                $code .= $line;
            }
            $to_delete = preg_match('/<\?(?:php)?\s+(?:abstract|interface)?\s*?class\s+'.$classname.'\s+extends\s+'.$classname.'Core\s*?[{]\s*?[}]/ism', $code);
        }
        if (!isset($to_delete) || $to_delete) {
            unlink($override_path);
        } else {
            file_put_contents($override_path, $code);
        }
        // Re-generate the class index
        Tools::generateIndex();
        return true;
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

        $sql = new DbQuery();
        $sql->select('c.`id_reference`');
        $sql->from('carrier', 'c');
        $sql->where('c.`deleted` = 0');
        $carriers = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $sql = new DbQuery();
        $sql->select('m.`id_module`');
        $sql->from('module', 'm');
        $sql->leftJoin('hook_module', 'hm', 'm.`id_module` = hm.`id_module`');
        $sql->leftJoin('hook', 'h', 'h.`id_hook` = hm.`id_hook`');
        $sql->where('h.`name` = \'displayPayment\' OR h.`name` = \'displayPaymentEU\'');
        $modules = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        foreach ($shops as $shop) {
            foreach ($carriers as $carrier) {
                foreach ($modules as $module) {
                    Db::getInstance()->insert(
                        'module_carrier',
                        array(
                            'id_reference' => (int) $carrier['id_reference'],
                            'id_module' => (int) $module['id_module'],
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
}
