<?php

namespace Modules\ZabbixCmdb\Lib;

class LanguageManager {
    /**
     * Identifiers and default values consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'CMDB' => 'CMDB',
            'Configuration Management Database' => '配置管理数据库',
            'Search hosts...' => '搜索主机...',
            'Search by hostname or IP' => '按主机名或IP搜索',
            'All Groups' => '所有分组',
            'Select host group' => '选择主机分组',
            'Host Name' => '主机名',
            'IP Address' => 'IP地址',
            'Interface Type' => '接口方式',
            'All Interfaces' => '所有接口',
            'Interface Type' => '接口方式',
            'CPU Total' => 'CPU总量',
            'CPU Usage' => 'CPU使用率',
            'Memory Total' => '内存总量',
            'Memory Usage' => '内存使用率',
            'Host Group' => '主机分组',
            'System Name' => '系统名称',
            'Architecture' => '架构',
            'Operating System' => '操作系统',
            'Kernel Version' => '内核版本',
            'Agent' => 'Agent',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => '未找到主机',
            'Loading...' => '加载中...',
            'Search' => '搜索',
            'Clear' => '清除',
            'Total Hosts' => '主机总数',
            'Host Groups' => '主机分组',
            'Host List' => '主机列表',
            'Search host groups...' => '搜索主机分组...',
            'Enter group name' => '输入分组名称',
            'Group Name' => '分组名称',
            'Host Count' => '主机数量',
            'Active Hosts' => '启用主机',
            'Search by group name' => '按分组名称搜索',
            'Search groups...' => '搜索分组...',
            'Status' => '状态',
            'No groups found' => '未找到分组',
            'Empty Group' => '空分组',
            'Active Group' => '活跃分组',
            'Basic Group' => '基础分组',
            'hosts' => '个主机',
            'cores' => '核',
            'Invalid input parameters.' => '无效的输入参数。'
        ],
        'en_US' => [
            'CMDB' => 'CMDB',
            'Configuration Management Database' => 'Configuration Management Database',
            'Search hosts...' => 'Search hosts...',
            'Search by hostname or IP' => 'Search by hostname or IP',
            'All Groups' => 'All Groups',
            'Select host group' => 'Select host group',
            'Host Name' => 'Host Name',
            'IP Address' => 'IP Address',
            'Interface Type' => 'Interface Type',
            'All Interfaces' => 'All Interfaces',
            'Interface Type' => 'Interface Type',
            'CPU Total' => 'CPU Total',
            'CPU Usage' => 'CPU Usage',
            'Memory Total' => 'Memory Total',
            'Memory Usage' => 'Memory Usage',
			'Storage Total' => 'Storage Total',
			'Disk Usage' => 'Disk Usage',
            'Host Group' => 'Host Group',
            'System Name' => 'System Name',
            'Architecture' => 'Architecture',
            'Operating System' => 'Operating System',
            'Kernel Version' => 'Kernel Version',
            'Agent' => 'Agent',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => 'No hosts found',
            'Loading...' => 'Loading...',
            'Search' => 'Search',
            'Clear' => 'Clear',
            'Total Hosts' => 'Total Hosts',
            'Host Groups' => 'Host Groups',
			'Customer' => 'Customer',
			'Product' => 'Product',
            'Host List' => 'Host List',
            'Search host groups...' => 'Search host groups...',
            'Enter group name' => 'Enter group name',
            'Group Name' => 'Group Name',
            'Host Count' => 'Host Count',
            'Active Hosts' => 'Active Hosts',
            'Search by group name' => 'Search by group name',
            'Search groups...' => 'Search groups...',
            'Status' => 'Status',
            'No groups found' => 'No groups found',
            'Empty Group' => 'Empty Group',
            'Active Group' => 'Active Group',
            'Basic Group' => 'Basic Group',
            'hosts' => 'hosts',
            'cores' => 'cores',
	        'Export CSV' => 'Export CSV',
            'Invalid input parameters.' => 'Invalid input parameters.'
        ]
    ];

    /**
     * Detect the current language (aligned with Zabbix source code logic)
     * Priority:
     * 1) User language (users.lang); if it is 'default', inherit the system default
     * 2) System default language (settings.default_lang); fall back to ZBX default if reading fails
     * 3) Zabbix default language (en_US)
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }
        
        // 1) User language
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            // 'default' means inheriting the system default
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        // 2) System default language
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        // 3) Zabbix default language
        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    /**
     * Try to obtain the current user's language setting from the Zabbix system
     */
    private static function getUserLanguageFromZabbix() {
        // Method 0: Prefer using the official Zabbix wrapper CWebUser
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                // Static get method (newer versions)
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                // Static data container in older versions
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore and continue with other methods
        }

        // Method 1: Try to obtain CWebUser information via global variables
        try {
            // Check whether there is CWebUser-related information in $GLOBALS
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (Throwable $e) {
            // Continue with other methods
        }
        
        // Method 2: Try to obtain from global variables (installation process / page initialization cache)
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (Throwable $e) {
            // Continue with other methods
        }
        
        // Method 3: Retrieve from the session (set by the Zabbix frontend after login)
        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // Method 4: Try to directly access the database to get the user's language setting
        return self::getUserLanguageFromDatabase();
    }

    /**
     * Try to obtain the user's language setting via the API
     */
    private static function getUserLanguageFromAPI() {
        try {
            // Get the current user ID
            $userid = null;

            // Get the user ID from the session
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // Try using different API class names (compatible with different versions)
            $apiClass = null;
            if (class_exists('API') || class_exists('\API')) {
                $apiClass = '\API';
            } elseif (class_exists('CApiService') || class_exists('\CApiService')) {
                $apiClass = '\CApiService';
            } elseif (class_exists('\Zabbix\Api\ApiService')) {
                $apiClass = '\Zabbix\Api\ApiService';
            } elseif (class_exists('\API')) {
                $apiClass = '\API';
            }

            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1
                ]);

                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (Throwable $e) {
            // API unavailable or error occurred
        }

        return null;
    }

    /**
     * Try to directly retrieve the current user's language setting from the database
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // Get the current user ID
            $userid = null;

            // Get the user ID from the session
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // Try to connect to the database (requires Zabbix database configuration)
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
                $stmt->execute([$userid]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['lang'])) {
                    return $result['lang'];
                }
            }
        } catch (Throwable $e) {
            // Database connection failed or another error occurred
        }

        return null;
    }

    /**
     * Read the system default language (settings.default_lang or config.default_lang)
     */
    private static function getSystemDefaultLanguage() {
        try {
            // Method 0: Prefer using the official Zabbix wrapper CSettingsHelper
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                if (method_exists('CSettingsHelper', 'get')) {
                    $val = \CSettingsHelper::get('default_lang');
                    if (!empty($val)) {
                        return $val;
                    }
                }
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                // First check the settings table (used by Zabbix 6/7)
                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                // Compatibility with older versions: config table
                $stmt2 = $pdo->query("SHOW TABLES LIKE 'config'");
                $hasConfig = $stmt2 && $stmt2->fetch();
                if ($hasConfig) {
                    $stmt3 = $pdo->query("SELECT default_lang FROM config LIMIT 1");
                    $row2 = $stmt3 ? $stmt3->fetch(\PDO::FETCH_ASSOC) : false;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore and fall back
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Map Zabbix language codes to our language codes
     */
    private static function mapZabbixLangToOurs($zabbixLang) {
        // Convert to lowercase first to handle case inconsistencies
        $lowerLang = strtolower(trim($zabbixLang));

        $langMap = [
            // Various Chinese variants
            'zh_cn' => 'zh_CN',
            'zh-cn' => 'zh_CN',
            'zh_tw' => 'zh_CN', // 繁体中文也使用简体中文翻译
            'zh-tw' => 'zh_CN',
            'zh' => 'zh_CN',
            'chinese' => 'zh_CN',
            'china' => 'zh_CN',
            'cn' => 'zh_CN',

            // 英文的各种变体
            'en_us' => 'en_US',
            'en-us' => 'en_US',
            'en_gb' => 'en_US',
            'en-gb' => 'en_US',
            'en' => 'en_US',
            'english' => 'en_US',
            'us' => 'en_US',
            'gb' => 'en_US',

            // 默认
            'default' => self::LANG_DEFAULT
        ];

        // If a direct mapping is found, return the result
        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }

        // If not found, try partial matching
        if (strpos($lowerLang, 'zh') === 0 || strpos($lowerLang, 'cn') !== false || strpos($lowerLang, 'chinese') !== false) {
            return 'zh_CN';
        }

        if (strpos($lowerLang, 'en') === 0 || strpos($lowerLang, 'english') !== false) {
            return 'en_US';
        }

        // Use English by default
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Check and fall back to a supported language
     */
    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        // Supported languages are limited to zh_CN / en_US
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Only languages for which translations are provided are considered available
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    /**
     * Get translated text
     */
    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        // If the current language has no translation, try using English
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        // If none exist, return the original key
        return $key;
    }

    /**
     * Get translated text with parameters
     */
    public static function tf($key, ...$args) {
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    /**
     * Get the current language
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * Reset the language cache (mainly for testing)
     */
    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    /**
     * Get language detection information (for debugging)
     */
    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'zabbix_user_lang' => self::getUserLanguageFromZabbix(),
            'db_lang' => self::getUserLanguageFromDatabase(),
            'system_lang' => self::getSystemDefaultLanguage(),
            'supported_locales' => array_keys(self::$translations)
        ];
    }

    /**
     * Check whether the current language is Chinese
     */
    public static function isChinese() {
        return self::detectLanguage() === 'zh_CN';
    }

    /**
     * Format date and time (based on language)
     */
    public static function formatDateTime($timestamp, $format = null) {
        if ($format === null) {
            $format = self::isChinese() ? 'Y年m月d日 H:i:s' : 'Y-m-d H:i:s';
        }

        return date($format, $timestamp);
    }

    /**
     * Format date (based on language)
     */
    public static function formatDate($timestamp, $format = null) {
        if ($format === null) {
            $format = self::isChinese() ? 'Y年m月d日' : 'Y-m-d';
        }

        return date($format, $timestamp);
    }

    /**
     * Format period (based on language)
     */
    public static function formatPeriod($type, $dateString) {
        // Convert date string to timestamp
        if (is_string($dateString)) {
            $timestamp = strtotime($dateString);
        } else {
            $timestamp = $dateString;
        }

        if ($timestamp === false) {
            return $dateString; // If conversion fails, return the original string
        }

        if (self::isChinese()) {
            switch ($type) {
                case 'day':
                case 'daily':
                    return date('Y年m月d日', $timestamp);
                case 'week':
                case 'weekly':
                    $year = date('Y', $timestamp);
                    $week = date('W', $timestamp);
                    return $year . '年第' . $week . '周';
                case 'month':
                case 'monthly':
                    return date('Y年m月', $timestamp);
                default:
                    return date('Y-m-d', $timestamp);
            }
        } else {
            switch ($type) {
                case 'day':
                case 'daily':
                    return date('Y-m-d', $timestamp);
                case 'week':
                case 'weekly':
                    return date('Y', $timestamp) . ' Week ' . date('W', $timestamp);
                case 'month':
                case 'monthly':
                    return date('Y-m', $timestamp);
                default:
                    return date('Y-m-d', $timestamp);
            }
        }
    }
}
