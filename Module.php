<?php

namespace Modules\ZabbixCmdb;

// Dynamically import version compatibility helper
require_once __DIR__ . '/lib/ZabbixVersion.php';
use Modules\ZabbixCmdb\Lib\ZabbixVersion;
use Modules\ZabbixCmdb\Lib\LanguageManager;
use CMenu;
use CMenuItem;

// Select the base class based on the actual existing class
// Zabbix 7.0+ uses Zabbix\Core\CModule
// Zabbix 6.0 uses Core\CModule
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    // Fallback handling: create an empty base class
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        $lm = new LanguageManager();
        
        // Compatible menu registration for different Zabbix versions
        try {
            // Try using the APP class (supported in both Zabbix 6 and 7)
            if (class_exists('APP')) {
                $app = class_exists('APP') ? new \ReflectionClass('APP') : null;
                
                if ($app && $app->hasMethod('Component')) {
                    // Zabbix 7.0+ approach
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                         //   (new CMenuItem($lm->t('CMDB')))->setSubMenu(
                            (new CMenuItem(_('CMDB')))->setAction('cmdb')
                         //       new CMenu([
                         //           (new CMenuItem($lm->t('Host List')))->setAction('cmdb'),
                         //           (new CMenuItem($lm->t('Host Groups')))->setAction('cmdb.groups')
                         //       ])
                         //   )
                          );
                }
            }
        } catch (\Exception $e) {
            // Log the error but do not interrupt execution
            error_log('CMDB Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
