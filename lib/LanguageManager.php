<?php

namespace Modules\ZabbixCmdb\Lib;

class LanguageManager {
    /**
     * Identifiers and default values consistent with the Zabbix frontend
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;

    /**
     * English-only translations
     */
    private static $translations = [
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
     * Detect the current language
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }

        // 1) User language
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                return self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            }
            return self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
        }

        // 2) System default language
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            return self::$currentLanguage = self::ensureSupportedOrFallback($sys);
        }

        // 3) Zabbix default
        return self::$currentLanguage = self::ZBX_DEFAULT_LANG;
    }

    private static function getUserLanguageFromZabbix() {
        try {
            if (class_exists('CWebUser')) {
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                if (isset(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {}

        if (isset($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        return self::getUserLanguageFromDatabase();
    }

    private static function getUserLanguageFromDatabase() {
        try {
            $userid = $_SESSION['userid'] ?? $_SESSION['user']['userid'] ?? null;
            if (!$userid) {
                return null;
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
                $stmt->execute([$userid]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                return $row['lang'] ?? null;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
                $lang = \CSettingsHelper::get('default_lang');
                if (!empty($lang)) {
                    return $lang;
                }
            }
        } catch (\Throwable $e) {}

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * Map any language to English
     */
    private static function mapZabbixLangToOurs($zabbixLang) {
        $lower = strtolower(trim($zabbixLang));

        // Chinese → English
        if (
            strpos($lower, 'zh') === 0 ||
            strpos($lower, 'cn') !== false ||
            strpos($lower, 'chinese') !== false
        ) {
            return 'en_US';
        }

        // English variants
        if (strpos($lower, 'en') === 0) {
            return 'en_US';
        }

        if ($lower === self::LANG_DEFAULT) {
            return self::LANG_DEFAULT;
        }

        return 'en_US';
    }

    private static function ensureSupportedOrFallback($lang) {
        return self::isSupportedLocale($lang) ? $lang : self::ZBX_DEFAULT_LANG;
    }

    private static function isSupportedLocale($lang) {
        return isset(self::$translations[$lang]);
    }

    /**
     * Translation helpers
     */
    public static function t($key) {
        return self::$translations['en_US'][$key] ?? $key;
    }

    public static function tf($key, ...$args) {
        return sprintf(self::t($key), ...$args);
    }

    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'supported_locales' => array_keys(self::$translations)
        ];
    }

    /**
     * English-only date formatting
     */
    public static function formatDateTime($timestamp, $format = 'Y-m-d H:i:s') {
        return date($format, $timestamp);
    }

    public static function formatDate($timestamp, $format = 'Y-m-d') {
        return date($format, $timestamp);
    }

    public static function formatPeriod($type, $dateString) {
        $timestamp = is_string($dateString) ? strtotime($dateString) : $dateString;
        if ($timestamp === false) {
            return $dateString;
        }

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
