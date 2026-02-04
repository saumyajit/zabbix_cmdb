<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class CmdbExport extends CController {

    public function init(): void {
        // Disable CSRF validation for export
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'search' => 'string',
            'groupid' => 'int32',
            'interface_type' => 'int32',
            'format' => 'in csv'
        ];

        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // Get the same filters as the main view
        $search = $this->getInput('search', '');
        $groupid = $this->getInput('groupid', 0);
        $interface_type = $this->getInput('interface_type', 0);

        // NEW: Check if at least one filter is applied
        $hasFilter = !empty($search) || $groupid > 0 || $interface_type > 0;
        
        if (!$hasFilter) {
            // No filter selected - export a message CSV instead
            $this->exportEmptyMessage();
            return;
        }

        // Reuse the same host retrieval logic from Cmdb.php
        $hosts = $this->getFilteredHosts($search, $groupid, $interface_type);
        
        // Generate CSV
        $this->generateCSV($hosts);
    }

    private function getFilteredHosts($search, $groupid, $interface_type) {
        // Copy the exact same host retrieval logic from your Cmdb.php
        
        if (!empty($search)) {
            $allFoundHosts = [];
            
            try {
                $nameSearchParams = [
                    'output' => ['hostid', 'host', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
                    'selectHostGroups' => ['groupid', 'name'],
                    'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error'],
                    'selectInventory' => ['contact', 'type_full'],
                    'search' => [
                        'host' => '*' . $search . '*',
                        'name' => '*' . $search . '*'
                    ],
                    'searchWildcardsEnabled' => true,
                    'searchByAny' => true,
                    'sortfield' => 'host',
                    'sortorder' => 'ASC',
                    'limit' => 1000
                ];
                
                if ($groupid > 0) {
                    $nameSearchParams['groupids'] = [$groupid];
                }
                
                $nameHosts = API::Host()->get($nameSearchParams);
                
                foreach ($nameHosts as $host) {
                    $allFoundHosts[$host['hostid']] = $host;
                }
            } catch (Exception $e) {
                error_log("Name search failed: " . $e->getMessage());
            }
            
            if (preg_match('/\d/', $search)) {
                try {
                    $interfaces = API::HostInterface()->get([
                        'output' => ['hostid', 'ip', 'dns'],
                        'search' => [
                            'ip' => '*' . $search . '*',
                            'dns' => '*' . $search . '*'
                        ],
                        'searchWildcardsEnabled' => true,
                        'searchByAny' => true
                    ]);
                    
                    if (!empty($interfaces)) {
                        $hostIds = array_unique(array_column($interfaces, 'hostid'));
                        
                        $ipSearchParams = [
                            'output' => ['hostid', 'host', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
                            'selectHostGroups' => ['groupid', 'name'],
                            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error'],
                            'selectInventory' => ['contact', 'type_full'],
                            'hostids' => $hostIds,
                            'sortfield' => 'host',
                            'sortorder' => 'ASC'
                        ];
                        
                        if ($groupid > 0) {
                            $ipSearchParams['groupids'] = [$groupid];
                        }
                        
                        $ipHosts = API::Host()->get($ipSearchParams);
                        
                        foreach ($ipHosts as $host) {
                            $allFoundHosts[$host['hostid']] = $host;
                        }
                    }
                } catch (Exception $e) {
                    error_log("IP search failed: " . $e->getMessage());
                }
            }
            
            $hosts = array_values($allFoundHosts);
        } else {
            $hostParams = [
                'output' => ['hostid', 'host', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
                'selectHostGroups' => ['groupid', 'name'],
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error'],
                'selectInventory' => ['contact', 'type_full'],
                'sortfield' => 'host',
                'sortorder' => 'ASC',
                'limit' => 1000
            ];
            
            if ($groupid > 0) {
                $hostParams['groupids'] = [$groupid];
            }
            
            try {
                $hosts = API::Host()->get($hostParams);
            } catch (Exception $e) {
                error_log("Host fetch failed: " . $e->getMessage());
                $hosts = [];
            }
        }

        // Apply interface type filter
        if ($interface_type > 0) {
            $filteredHosts = [];
            foreach ($hosts as $host) {
                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $interface) {
                        if ($interface['type'] == $interface_type) {
                            $filteredHosts[] = $host;
                            break;
                        }
                    }
                }
            }
            $hosts = $filteredHosts;
        }

        // Filter out URL hosts
        $filteredHosts = [];
        foreach ($hosts as $host) {
            $hostName = strtoupper($host['name']);
            if (strpos($hostName, 'URL') === false && strpos($hostName, 'PUBLIC URL') === false) {
                $filteredHosts[] = $host;
            }
        }

        return $filteredHosts;
    }

    private function exportEmptyMessage() {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cmdb_export_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write message
        fputcsv($output, [LanguageManager::t('Please select a filter (Host Group, Interface Type, or Search) before exporting.')]);
        
        fclose($output);
        exit;
    }

    private function generateCSV($hosts) {
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cmdb_export_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV Headers
        $headers = [
            LanguageManager::t('Host Name'),
            LanguageManager::t('IP Address'),
            LanguageManager::t('Customer'),
            LanguageManager::t('Product'),
            LanguageManager::t('Architecture'),
            LanguageManager::t('Interface Type'),
            LanguageManager::t('CPU Total'),
            LanguageManager::t('CPU Usage'),
            LanguageManager::t('Memory Total'),
            LanguageManager::t('Memory Usage'),
            LanguageManager::t('Storage Total'),
            LanguageManager::t('Disk Usage'),
            LanguageManager::t('Operating System'),
            LanguageManager::t('Kernel Version'),
            LanguageManager::t('Host Group'),
            LanguageManager::t('Status')
        ];
        fputcsv($output, $headers);

        // Process each host and write data
        foreach ($hosts as $host) {
            // Get availability status
            $availability = ItemFinder::getHostAvailabilityStatus($host['hostid'], $host['interfaces'] ?? []);
            
            // Determine status text
            if ($host['status'] == 1) {
                $statusText = 'Disabled';
            } elseif (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
                $statusText = 'Maintenance';
            } else {
                $statusText = ucfirst($availability['status']);
            }

            // Get IP addresses
            $ipAddresses = [];
            if (!empty($host['interfaces'])) {
                foreach ($host['interfaces'] as $interface) {
                    if (!empty($interface['ip'])) {
                        $ipAddresses[] = $interface['ip'];
                    }
                }
            }
            $ipAddress = !empty($ipAddresses) ? implode(', ', $ipAddresses) : '-';

            // Get interface types
            $interfaceTypes = [];
            if (!empty($host['interfaces'])) {
                foreach ($host['interfaces'] as $interface) {
                    switch ($interface['type']) {
                        case 1: $interfaceTypes[] = 'Agent'; break;
                        case 2: $interfaceTypes[] = 'SNMP'; break;
                        case 3: $interfaceTypes[] = 'IPMI'; break;
                        case 4: $interfaceTypes[] = 'JMX'; break;
                    }
                }
            }
            $interfaceType = !empty($interfaceTypes) ? implode(', ', array_unique($interfaceTypes)) : '-';

            // Get host groups (filtered)
            $groupNames = [];
            $groups = isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []);
            foreach ($groups as $group) {
                $name = $group['name'];
                if (strpos($name, 'CUSTOMER/') === 0 || 
                    strpos($name, 'PRODUCT/') === 0 || 
                    strpos($name, 'TYPE/') === 0) {
                    $groupNames[] = $name;
                }
            }
            $hostGroup = !empty($groupNames) ? implode(', ', $groupNames) : '-';

            // Get customer and product from inventory
            $customer = '-';
            $product = '-';
            if (isset($host['inventory']) && is_array($host['inventory'])) {
                if (isset($host['inventory']['contact']) && !empty($host['inventory']['contact'])) {
                    $customer = $host['inventory']['contact'];
                }
                if (isset($host['inventory']['type_full']) && !empty($host['inventory']['type_full'])) {
                    $product = $host['inventory']['type_full'];
                }
            }

            // Get metrics
            $cpuTotal = ItemFinder::findCpuCount($host['hostid']);
            $cpuTotalValue = ($cpuTotal && $cpuTotal['value'] !== null) ? $cpuTotal['value'] : '-';

            $cpuUsage = ItemFinder::findCpuUsage($host['hostid']);
            $cpuUsageValue = ($cpuUsage && $cpuUsage['value'] !== null) ? round(floatval($cpuUsage['value']), 2) . '%' : '-';

            $memoryTotal = ItemFinder::findMemoryTotal($host['hostid']);
            $memoryTotalValue = ($memoryTotal && $memoryTotal['value'] !== null) ? ItemFinder::formatMemorySize($memoryTotal['value']) : '-';

            $memoryUsage = ItemFinder::findMemoryUsage($host['hostid']);
            $memoryUsageValue = ($memoryUsage && $memoryUsage['value'] !== null) ? round(floatval($memoryUsage['value']), 2) . '%' : '-';

            $storageTotal = ItemFinder::findStorageTotal($host['hostid']);
            $storageTotalValue = ($storageTotal !== null) ? ItemFinder::formatMemorySize($storageTotal) : '-';

            $diskUsage = ItemFinder::findDiskUsage($host['hostid']);
            $diskUsageValue = '-';
            if (!empty($diskUsage)) {
                $diskParts = [];
                foreach ($diskUsage as $disk) {
                    $diskParts[] = $disk['mount'] . ': ' . $disk['percentage'] . '%';
                }
                $diskUsageValue = implode('; ', $diskParts);
            }

            $os = ItemFinder::findOperatingSystem($host['hostid']);
            $osValue = ($os && $os['value'] !== null) ? $os['value'] : '-';

            $kernel = ItemFinder::findKernelVersion($host['hostid']);
            $kernelValue = ($kernel && $kernel['value'] !== null) ? ItemFinder::extractKernelInfo($kernel['value']) : '-';

            $arch = ItemFinder::findOsArchitecture($host['hostid']);
            $archValue = ($arch && $arch['value'] !== null) ? $arch['value'] : '-';

            // Write row
            $row = [
                $host['name'],
                $ipAddress,
                $customer,
                $product,
                $archValue,
                $interfaceType,
                $cpuTotalValue,
                $cpuUsageValue,
                $memoryTotalValue,
                $memoryUsageValue,
                $storageTotalValue,
                $diskUsageValue,
                $osValue,
                $kernelValue,
                $hostGroup,
                $statusText
            ];
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
