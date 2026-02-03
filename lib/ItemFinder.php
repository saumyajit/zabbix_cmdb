<?php

namespace Modules\ZabbixCmdb\Lib;

use API;

class ItemFinder {
    
    /**
     * Find CPU count monitoring item
     */
    public static function findCpuCount($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.cpu.num']],
            ['filter' => ['key_' => 'system.hw.cpu.num']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'Number of CPUs'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Number of cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU cores'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.num'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * Find total memory monitoring item
     */
    public static function findMemoryTotal($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'vm.memory.size[total]']],
            ['filter' => ['key_' => 'vm.memory.total']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'Total memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory total'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'vm.memory.size'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

    /**
     * Find CPU usage monitoring item
     */
    public static function findCpuUsage($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.cpu.util[,avg1]']],
            ['filter' => ['key_' => 'system.cpu.util[]']],
            ['filter' => ['key_' => 'system.cpu.util']],
            ['filter' => ['key_' => 'system.cpu.load[avg1]']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'CPU utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU Utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'CPU usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Processor load'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.util'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'cpu.load'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

    /**
     * Find memory usage monitoring item
     */
    public static function findMemoryUsage($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'vm.memory.util[]']],
            ['filter' => ['key_' => 'vm.memory.util']],
            ['filter' => ['key_' => 'vm.memory.pused']],
            ['filter' => ['key_' => 'vm.memory.utilization']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'Memory utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory Utilization'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Memory usage'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Used memory'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'memory.util'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'memory.pused'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }

    /**
     * Find kernel version item
     */
    public static function findKernelVersion($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.uname']],
            ['filter' => ['key_' => 'system.sw.os[uname]']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'System uname'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Kernel version'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.uname'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * Find system name item
     */
    public static function findSystemName($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.hostname']],
            ['filter' => ['key_' => 'system.sw.os[hostname]']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'System name'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'Hostname'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.hostname'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * Find OS item
     */
    public static function findOperatingSystem($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.sw.os']],
            ['filter' => ['key_' => 'system.sw.os[name]']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'Operating system'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'OS name'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.sw.os'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * Find OS Architecture item
     */
    public static function findOsArchitecture($hostid) {
        $patterns = [
            // Prefer using exact keys
            ['filter' => ['key_' => 'system.sw.arch']],
            ['filter' => ['key_' => 'system.hw.arch']],
            // Use name-based search as a fallback
            ['search' => ['name' => 'Operating system architecture'], 'searchWildcardsEnabled' => true],
            ['search' => ['name' => 'System architecture'], 'searchWildcardsEnabled' => true],
            ['search' => ['key_' => 'system.sw.arch'], 'searchWildcardsEnabled' => true]
        ];
        
        return self::findItemByPatterns($hostid, $patterns);
    }
    
    /**
     * Find items based on a pattern list and retrieve their values
     */
    private static function findItemByPatterns($hostid, $patterns) {
        foreach ($patterns as $pattern) {
            $searchParams = array_merge([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'value_type'],
                'hostids' => $hostid,
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'limit' => 1
            ], $pattern);
            
            $items = API::Item()->get($searchParams);
            
            if (!empty($items)) {
                $item = $items[0];
                $value = null;
                
                // First, try to use the last value
                if (isset($item['lastvalue']) && $item['lastvalue'] !== '') {
                    $value = $item['lastvalue'];
                }
                
                // If there is no last value, try to get the latest historical data
                if ($value === null || $value === '') {
                    $historyType = 0; //Default to numeric value type
                    
                    // Determine the history table type based on value_type
                    switch ($item['value_type']) {
                        case ITEM_VALUE_TYPE_FLOAT:
                            $historyType = 0;
                            break;
                        case ITEM_VALUE_TYPE_UINT64:
                            $historyType = 3;
                            break;
                        case ITEM_VALUE_TYPE_STR:
                        case ITEM_VALUE_TYPE_TEXT:
                        case ITEM_VALUE_TYPE_LOG:
                            $historyType = 1;
                            break;
                    }
                    
                    $recentHistory = API::History()->get([
                        'output' => ['value'],
                        'itemids' => $item['itemid'],
                        'sortfield' => 'clock',
                        'sortorder' => 'DESC',
                        'limit' => 1,
                        'history' => $historyType
                    ]);
                    
                    if (!empty($recentHistory)) {
                        $value = $recentHistory[0]['value'];
                    }
                }
                
                return [
                    'item' => $item,
                    'value' => $value
                ];
            }
        }
        
        return null;
    }

    /**
     * Format Memory Size
     */
    public static function formatMemorySize($bytes) {
        if (empty($bytes) || !is_numeric($bytes)) {
            return '-';
        }
        
        $bytes = floatval($bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Extract kernel version information
     */
    public static function extractKernelInfo($fullString) {
        if (empty($fullString)) {
            return '-';
        }

        // If the string is too long, try to extract key information
        if (strlen($fullString) > 50) {
            // Try to extract the Linux kernel version
            if (preg_match('/Linux\s+\S+\s+(\S+)/', $fullString, $matches)) {
                return $matches[1];
            }
            
            // Try to extract Windows version information
            if (preg_match('/Windows\s+[^0-9]*([0-9]+[^,\s]*)/i', $fullString, $matches)) {
                return 'Windows ' . $matches[1];
            }
            
            // If it is another system, truncate the first 50 characters
            return substr($fullString, 0, 47) . '...';
        }
        
        return $fullString;
    }

    /**
     * Get host interface availability status (based on native Zabbix interface availability)
     * Return an array of status information
     */
    public static function getHostAvailabilityStatus($hostid, $interfaces = []) {
        try {
            // If interface information is not provided, retrieve it from the API
            if (empty($interfaces)) {
                $interfaces = API::HostInterface()->get([
                    'hostids' => [$hostid],
                    'output' => ['interfaceid', 'type', 'main', 'available', 'error']
                ]);
            }

            if (empty($interfaces)) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // Find the availability status of the primary interface
            $mainInterface = null;
            foreach ($interfaces as $interface) {
                if ($interface['main'] == 1) {
                    $mainInterface = $interface;
                    break;
                }
            }

            // If there is no primary interface, use the first interface
            if (!$mainInterface && !empty($interfaces)) {
                $mainInterface = $interfaces[0];
            }

            if (!$mainInterface) {
                return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

            // Return the corresponding display based on Zabbix interface availability status
            // Availability: 0 = unknown, 1 = available, 2 = unavailable
            switch ($mainInterface['available']) {
                case '1':
                    return ['status' => 'available', 'text' => 'Available', 'class' => 'status-available'];
                case '2':
                    return ['status' => 'unavailable', 'text' => 'Unavailable', 'class' => 'status-unavailable'];
                case '0':
                default:
                    return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
            }

        } catch (Exception $e) {
            error_log("Failed to check host availability for {$hostid}: " . $e->getMessage());
            return ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
        }
    }
	
	/**
	* Find total storage across all filesystems
	*/
	public static function findStorageTotal($hostid) {
		try {
			$hostid = intval($hostid);
			
			if ($hostid <= 0) {
				return null;
			}
			
			$allItems = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'lastvalue', 'value_type'],
				'hostids' => [$hostid],
				'filter' => ['status' => ITEM_STATUS_ACTIVE]
			]);
	
			$totalStorage = 0;
			$processedMounts = [];
			
			foreach ($allItems as $item) {
				$key = $item['key_'];
				
				if (strpos($key, 'vfs.fs.size') !== false && preg_match('/vfs\.fs\.size\[([^,\]]+),\s*total\]/', $key, $matches)) {
					$mountPoint = trim($matches[1]);
					$mountPoint = str_replace('"', '', $mountPoint);
					
					if (strpos($mountPoint, '{#') !== false || 
						strpos($mountPoint, '$') !== false ||
						in_array($mountPoint, $processedMounts)) {
						continue;
					}
					
					if (isset($item['lastvalue']) && is_numeric($item['lastvalue']) && $item['lastvalue'] > 0) {
						$value = floatval($item['lastvalue']);
						$totalStorage += $value;
						$processedMounts[] = $mountPoint;
					}
				}
			}
	
			return $totalStorage > 0 ? $totalStorage : null;
			
		} catch (Exception $e) {
			error_log("Failed to get storage total for host {$hostid}: " . $e->getMessage());
			return null;
		}
	}

	/**
	* Find disk usage for all filesystems
	* Returns an array of mount points with their usage percentages and sizes
	*/
	public static function findDiskUsage($hostid) {
		try {
			$hostid = intval($hostid);
			
			if ($hostid <= 0) {
				return null;
			}
			
			$diskUsageData = [];
			
			$allItems = API::Item()->get([
				'output' => ['itemid', 'name', 'key_', 'lastvalue', 'value_type'],
				'hostids' => [$hostid],
				'filter' => ['status' => ITEM_STATUS_ACTIVE]
			]);
	
			$vfsItems = [];
			foreach ($allItems as $item) {
				if (strpos($item['key_'], 'vfs.fs.size') !== false) {
					$vfsItems[] = $item;
				}
			}
	
			$filesystems = [];
			
			foreach ($vfsItems as $item) {
				$key = $item['key_'];
				
				if (preg_match('/vfs\.fs\.size\[([^,\]]+),\s*(pused|pfree|total|used|free)\]/', $key, $matches)) {
					$mountPoint = trim($matches[1]);
					$metric = $matches[2];
					
					if (strpos($mountPoint, '{#') !== false || strpos($mountPoint, '$') !== false) {
						continue;
					}
					
					$mountPoint = str_replace('"', '', $mountPoint);
					
					if (!isset($filesystems[$mountPoint])) {
						$filesystems[$mountPoint] = [];
					}
					
					if (isset($item['lastvalue']) && $item['lastvalue'] !== null && $item['lastvalue'] !== '') {
						$filesystems[$mountPoint][$metric] = floatval($item['lastvalue']);
					}
				}
			}
			
			foreach ($filesystems as $mountPoint => $metrics) {
				$percentage = null;
				$totalSize = null;
				
				// Get total size
				if (isset($metrics['total'])) {
					$totalSize = $metrics['total'];
				}
				
				// Calculate percentage
				if (isset($metrics['pused'])) {
					$percentage = $metrics['pused'];
				}
				elseif (isset($metrics['pfree'])) {
					$percentage = 100 - $metrics['pfree'];
				}
				elseif (isset($metrics['total']) && isset($metrics['used'])) {
					$total = $metrics['total'];
					$used = $metrics['used'];
					if ($total > 0) {
						$percentage = ($used / $total) * 100;
					}
				}
				elseif (isset($metrics['total']) && isset($metrics['free'])) {
					$total = $metrics['total'];
					$free = $metrics['free'];
					if ($total > 0) {
						$percentage = (($total - $free) / $total) * 100;
					}
				}
				
				if ($percentage !== null) {
					$diskUsageData[] = [
						'mount' => $mountPoint,
						'percentage' => round($percentage, 2),
						'total_size' => $totalSize
					];
				}
			}
			
			usort($diskUsageData, function($a, $b) {
				$aIsWindows = preg_match('/^[A-Z]:$/', $a['mount']);
				$bIsWindows = preg_match('/^[A-Z]:$/', $b['mount']);
				
				if ($aIsWindows && !$bIsWindows) return -1;
				if (!$aIsWindows && $bIsWindows) return 1;
				
				if ($a['mount'] === '/' && $b['mount'] !== '/') return -1;
				if ($a['mount'] !== '/' && $b['mount'] === '/') return 1;
				
				return strcmp($a['mount'], $b['mount']);
			});
			
			return !empty($diskUsageData) ? $diskUsageData : null;
			
		} catch (Exception $e) {
			error_log("Failed to get disk usage for host {$hostid}: " . $e->getMessage());
			return null;
		}
	}

	
	/**
	* Format disk usage data for display
	*/
	public static function formatDiskUsage($diskUsageData) {
		if (empty($diskUsageData)) {
			return '-';
		}
		
		$formatted = [];
		foreach ($diskUsageData as $disk) {
			$formatted[] = $disk['mount'] . ': ' . $disk['percentage'] . '%';
		}
		
		return implode("\n", $formatted);
	}

}
