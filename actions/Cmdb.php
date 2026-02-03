<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class Cmdb extends CController {

    public function init(): void {
        // Compatible with Zabbix 6 and 7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'search' => 'string',
            'groupid' => 'int32',
            'sort' => 'string',
            'sortorder' => 'in ASC,DESC',
            'interface_type' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => _('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $search = $this->getInput('search', '');
        $groupid = $this->getInput('groupid', 0);
        $interface_type = $this->getInput('interface_type', 0);
        $sort = $this->getInput('sort', 'cpu_total');
        $sortorder = $this->getInput('sortorder', 'DESC');

        // Retrieve host group list – based on Zabbix 7.0 API documentation best practices
        $hostGroups = [];
        
        // Try multiple strategies to retrieve host groups to ensure compatibility
        $strategies = [
            // Strategy 1: Retrieve groups that contain hosts (recommended, better performance)
            function() {
                return API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'with_hosts' => true,
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
            },
            
            // Strategy 2: Standard retrieval of all groups
            function() {
                return API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
            },
            
            // Strategy 3: Use extended output (required by some versions)
            function() {
                $groups = API::HostGroup()->get([
                    'output' => 'extend',
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
                return array_map(function($group) {
                    return [
                        'groupid' => $group['groupid'],
                        'name' => $group['name']
                    ];
                }, $groups);
            },
            
            // Strategy 4: Retrieve groups indirectly via hosts (last compatibility option)
            function() {
                $hosts = API::Host()->get([
                    'output' => ['hostid'],
                    'selectHostGroups' => ['groupid', 'name'],
                    'limit' => 1000
                ]);
                
                $groupsMap = [];
                foreach ($hosts as $host) {
                    if (isset($host['groups'])) {
                        foreach ($host['groups'] as $group) {
                            $groupsMap[$group['groupid']] = [
                                'groupid' => $group['groupid'],
                                'name' => $group['name']
                            ];
                        }
                    }
                }
                
                $groups = array_values($groupsMap);
                usort($groups, function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                
                return $groups;
            }
        ];
        
        // Execute strategies in order until successful host groups are retrieved
        foreach ($strategies as $index => $strategy) {
            try {
                $hostGroups = $strategy();
                if (!empty($hostGroups)) {
                    break;
                }
            } catch (Exception $e) {
                error_log("CMDB: Strategy " . ($index + 1) . " failed: " . $e->getMessage());
                continue;
            }
        }
        
        // If all strategies fail, log the error but do not interrupt execution
        if (empty($hostGroups)) {
            error_log("CMDB: All host group retrieval strategies failed");
        }

		// Filtering groups that start with CUSTOMER/, PRODUCT/, or TYPE/
		$filteredHostGroups = [];
		foreach ($hostGroups as $group) {
			$name = $group['name'];
			if (strpos($name, 'CUSTOMER/') === 0 || 
				strpos($name, 'PRODUCT/') === 0 || 
				strpos($name, 'TYPE/') === 0) {
				$filteredHostGroups[] = $group;
			}
		}
		$hostGroups = $filteredHostGroups;
		
        // Retrieve host list – optimized according to Zabbix 7.0 API documentation
        if (!empty($search)) {
            // Search strategy: supports fuzzy search by hostname, visible name, and IP address
            $allFoundHosts = [];
            
            // Step 1: Search hosts by hostname and visible name*
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
                ];                if ($groupid > 0) {
                    $nameSearchParams['groupids'] = [$groupid];
                }
                
                $nameHosts = API::Host()->get($nameSearchParams);
                
                foreach ($nameHosts as $host) {
                    $allFoundHosts[$host['hostid']] = $host;
                }
            } catch (Exception $e) {
                error_log("Name search failed: " . $e->getMessage());
            }
            
            // Step 2: If the search term contains digits, search by IP or DNS
            if (preg_match('/\d/', $search)) {
                try {
                    // Search interfaces first
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
            // When there is no search condition, retrieve all hosts
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

        // Filter by interface type
        if ($interface_type > 0) {
            $filteredHosts = [];
            foreach ($hosts as $host) {
                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $interface) {
                        if ($interface['type'] == $interface_type) {
                            $filteredHosts[] = $host;
                            break; // Break after finding a matching interface
                        }
                    }
                }
            }
            $hosts = $filteredHosts;
        }

        // Process each host to collect CPU, memory, kernel, and OS information
        $hostData = [];
        $totalCpu = 0;
        $totalMemory = 0;
		$totalStorage = 0;
		
        // Filtering out hosts with URL/PUBLIC URL in their names
		$filteredHosts = [];
		foreach ($hosts as $host) {
			$hostName = strtoupper($host['name']);
			if (strpos($hostName, 'URL') === false && strpos($hostName, 'PUBLIC URL') === false) {
				$filteredHosts[] = $host;
			}
		}
		$hosts = $filteredHosts;
        
        foreach ($hosts as $host) {
            $hostInfo = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'maintenance_status' => isset($host['maintenance_status']) ? $host['maintenance_status'] : 0,
                'maintenance_type' => isset($host['maintenance_type']) ? $host['maintenance_type'] : 0,
                'groups' => isset($host['groups']) ? $host['groups'] : (isset($host['hostgroups']) ? $host['hostgroups'] : []),
                'interfaces' => isset($host['interfaces']) ? $host['interfaces'] : [],
                'cpu_total' => '-',
                'cpu_usage' => '-',
                'memory_total' => '-',
                'memory_usage' => '-',
                'kernel_version' => '-',
				'customer' => '-',
				'product' => '-'
            ];

            // Get the actual availability status of the host
            $availability = ItemFinder::getHostAvailabilityStatus($host['hostid'], $host['interfaces']);
            $hostInfo['availability'] = $availability;

			// Extract Inventory data for Customer & Product
			if (isset($host['inventory']) && is_array($host['inventory'])) {
				if (isset($host['inventory']['contact']) && !empty($host['inventory']['contact'])) {
					$hostInfo['customer'] = $host['inventory']['contact'];
				}
				if (isset($host['inventory']['type_full']) && !empty($host['inventory']['type_full'])) {
					$hostInfo['product'] = $host['inventory']['type_full'];
				}
			}

            // Get total CPU count
            $cpuResult = ItemFinder::findCpuCount($host['hostid']);
            if ($cpuResult && $cpuResult['value'] !== null) {
                $hostInfo['cpu_total'] = $cpuResult['value'];
                $totalCpu += intval($cpuResult['value']);
            }

            // Get CPU usage
            $cpuUsageResult = ItemFinder::findCpuUsage($host['hostid']);
            if ($cpuUsageResult && $cpuUsageResult['value'] !== null) {
                $hostInfo['cpu_usage'] = round(floatval($cpuUsageResult['value']), 2) . '%';
            }

            // Get total memory
            $memoryResult = ItemFinder::findMemoryTotal($host['hostid']);
            if ($memoryResult && $memoryResult['value'] !== null) {
                $hostInfo['memory_total'] = ItemFinder::formatMemorySize($memoryResult['value']);
                $totalMemory += intval($memoryResult['value']);
            }

            // Get memory usage
            $memoryUsageResult = ItemFinder::findMemoryUsage($host['hostid']);
            if ($memoryUsageResult && $memoryUsageResult['value'] !== null) {
                $hostInfo['memory_usage'] = round(floatval($memoryUsageResult['value']), 2) . '%';
            }
            if ($memoryResult && $memoryResult['value'] !== null) {
                $hostInfo['memory_total'] = ItemFinder::formatMemorySize($memoryResult['value']);
            }
			
			// Get total storage
			$storageTotal = ItemFinder::findStorageTotal($host['hostid']);
			if ($storageTotal !== null) {
				$hostInfo['storage_total'] = ItemFinder::formatMemorySize($storageTotal);
				$totalStorage += intval($storageTotal);
			} else {
				$hostInfo['storage_total'] = '-';
			}
			
			// Get disk usage
			$diskUsageResult = ItemFinder::findDiskUsage($host['hostid']);
			if ($diskUsageResult !== null) {
				$hostInfo['disk_usage'] = $diskUsageResult;
			} else {
				$hostInfo['disk_usage'] = [];
			}

            // Get kernel version
            $kernelResult = ItemFinder::findKernelVersion($host['hostid']);
            if ($kernelResult && $kernelResult['value'] !== null) {
                $hostInfo['kernel_version'] = ItemFinder::extractKernelInfo($kernelResult['value']);
            }

            // Get system name
            $systemNameResult = ItemFinder::findSystemName($host['hostid']);
            if ($systemNameResult && $systemNameResult['value'] !== null) {
                $hostInfo['system_name'] = $systemNameResult['value'];
            }

            // Get OS
            $osResult = ItemFinder::findOperatingSystem($host['hostid']);
            if ($osResult && $osResult['value'] !== null) {
                $hostInfo['operating_system'] = $osResult['value'];
            }

            // Get OS Architecture
            $archResult = ItemFinder::findOsArchitecture($host['hostid']);
            if ($archResult && $archResult['value'] !== null) {
                $hostInfo['os_architecture'] = $archResult['value'];
            }

            $hostData[] = $hostInfo;
        }
        
        // Sort hosts according to selected field and order
        if (!empty($hostData)) {
            usort($hostData, function($a, $b) use ($sort, $sortorder) {
                $valueA = $a[$sort] ?? '';
                $valueB = $b[$sort] ?? '';

                // For numeric fields, ensure correct comparison
                if (in_array($sort, ['cpu_total', 'cpu_usage', 'memory_total', 'memory_usage'])) {
                    if ($sort === 'cpu_usage' || $sort === 'memory_usage') {
                        $valueA = $valueA !== '-' ? floatval(str_replace('%', '', $valueA)) : 0;
                        $valueB = $valueB !== '-' ? floatval(str_replace('%', '', $valueB)) : 0;
                    } else {
                        $valueA = is_numeric($valueA) ? floatval($valueA) : 0;
                        $valueB = is_numeric($valueB) ? floatval($valueB) : 0;
                    }
                } else {
                    $valueA = (string)$valueA;
                    $valueB = (string)$valueB;
                }

                if ($sortorder === 'DESC') {
                    return $valueB <=> $valueA;
                } else {
                    return $valueA <=> $valueB;
                }
            });
        }
        
        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Configuration Management Database (CMDB) in Zabbix'),
            'host_groups' => $hostGroups,
            'hosts' => $hostData,
            'search' => $search,
            'selected_groupid' => $groupid,
            'interface_type' => $interface_type,
            'sort' => $sort,
            'sortorder' => $sortorder,
            'total_cpu' => $totalCpu,
            'total_memory' => $totalMemory,
            'total_storage' => $totalStorage
        ]);
        
        // Explicitly set the response title (required for Zabbix 6.0)
        $response->setTitle(LanguageManager::t('Host List'));

        $this->setResponse($response);
    }
}
