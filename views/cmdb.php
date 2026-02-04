<?php

// Include the language manager and compatibility layer
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

/**
 * Create a sorting link
 */
function createSortLink($title, $field, $data) {
    $currentSort = isset($data['sort']) ? $data['sort'] : '';
    $currentOrder = isset($data['sortorder']) ? $data['sortorder'] : 'ASC';

    $newOrder = ($currentSort === $field && $currentOrder === 'ASC') ? 'DESC' : 'ASC';

    $icon = '';
    if ($currentSort === $field) {
        $icon = $currentOrder === 'ASC' ? ' ↑' : ' ↓';
    }

    // Build the URL, including search parameters
    $url = 'zabbix.php?action=cmdb&sort=' . $field . '&sortorder=' . $newOrder;
    if (!empty($data['search'])) {
        $url .= '&search=' . urlencode($data['search']);
    }
    if (!empty($data['selected_groupid'])) {
        $url .= '&groupid=' . $data['selected_groupid'];
    }

    return new CLink($title . $icon, $url);
}

/**
 * Get the host status display element
 */
function getHostStatusDisplay($host) {
    // Get host status information
    $statusInfo = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown', 'text' => 'Unknown', 'class' => 'status-unknown'];
    
    // If the host is disabled, display “Disabled
    if ($host['status'] == 1) {
        $statusText = '🚫 Disabled';
        $statusClass = 'status-disabled';
    } 
    // If the host is in maintenance, display “Maintenance”
    elseif (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
        $statusText = '🔧 Maintenance';
        $statusClass = 'status-maintenance';
    }
    // Otherwise, display the interface availability status
    else {
        $icon = '';
        switch ($statusInfo['status']) {
            case 'available':
                $icon = '🟢';
                break;
            case 'unavailable':
                $icon = '🔴';
                break;
            case 'unknown':
            default:
                $icon = '🟡';
                break;
        }
        $statusText = $icon . ' ' . $statusInfo['text'];
        $statusClass = $statusInfo['class'];
    }
    
    return (new CSpan($statusText))
        ->addClass($statusClass)
        ->setAttribute('style', 'font-size: 12px;');
}

/**
 * Calculate the number of active hosts (based on actual availability status)
 */
function countActiveHosts($hosts) {
    $activeCount = 0;
    
    foreach ($hosts as $host) {
        // If the host is disabled, skip it
        if ($host['status'] == 1) {
            continue;
        }
        
        // If the host is in maintenance, skip it
        if (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
            continue;
        }
        
        // Check availability status
        $availability = isset($host['availability']) ? $host['availability'] : ['status' => 'unknown'];
        if ($availability['status'] === 'available') {
            $activeCount++;
        }
    }
    
    return $activeCount;
}

// Safety check for data array
if (!isset($data)) {
    $data = [
        'hosts' => [],
        'host_groups' => [],
        'search' => '',
        'selected_groupid' => 0,
        'interface_type' => 0,
        'sort' => 'cpu_total',
        'sortorder' => 'DESC',
        'total_cpu' => 0,
        'total_memory' => 0,
        'total_storage' => 0,
        'avg_cpu_usage' => 0,
        'avg_memory_usage' => 0,
        'avg_storage_usage' => 0
    ];
}

// Get the title from the controller
// $pageTitle = $data['title'] ?? 'CMDB';
$pageTitle = [];

// Add CSS consistent with the Zabbix theme
$styleTag = new CTag('style', true, '
.cmdb-container {
    padding: 2px;
    width: 100%;
    margin: 0 auto;
}

.cmdb-search-form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto auto;
    gap: 15px;
    align-items: end;
}

@media (max-width: 768px) {
    .search-form {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}

.form-field {
    display: flex;
    flex-direction: column;
}

.form-field label {
    font-weight: 600;
	font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    margin-bottom: 5px;
    color: #495057;
    font-size: 16px;
}

.form-field input,
.form-field select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
	font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    transition: border-color 0.15s ease-in-out;
    background-color: #fff;
    height: 38px;
    box-sizing: border-box;
}

.form-field input:focus,
.form-field select:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn {
    padding: 3px 6px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 400;
    text-align: center;
    transition: all 0.15s ease-in-out;
    height: 38px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    color: #fff;
    background-color: #0056b3;
    border-color: #004085;
}

.btn-secondary {
    color: #6c757d;
    background-color: transparent;
    border-color: #6c757d;
}

.btn-secondary:hover {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-icon {
    font-size: 2rem;
    flex-shrink: 0;
    margin-right: 15px;
}

.stat-content {
    text-align: center;
    flex: 1;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}

.stat-label {
    font-size: 0.875rem;
	font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
	padding-top: 10px;
}

.hosts-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    table-layout: fixed;
    overflow: visible;
}

.hosts-table thead th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dee2e6;
    max-width: 300px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: visible;
    min-height: 20px;
    line-height: 1.4;
}

.hosts-table tbody td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 13px;
    vertical-align: top;
    max-width: 300px;
    word-break: break-all;
    overflow-wrap: break-word;
    white-space: normal;
    overflow: hidden;
    min-height: 20px;
    line-height: 1.4;
    max-height: 55px; /* 3 lines of text height */
    position: relative;
}

.hosts-table tbody td:hover {
    overflow: visible;
    max-height: none;
    background-color: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 100;
    white-space: normal;
    border-radius: 4px;
}

.hosts-table tbody tr:hover {
    background-color: #f8f9fa;
}

.hosts-table tbody tr:last-child td {
    border-bottom: none;
}

.host-link {
    color: #007bff;
    text-decoration: none;
}

.host-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.interface-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 4px;
    margin-bottom: 2px;
}

.interface-agent {
    background-color: #28a745;
    color: white;
}

.interface-snmp {
    background-color: #007bff;
    color: white;
}

.interface-ipmi {
    background-color: #fd7e14;
    color: white;
}

.interface-jmx {
    background-color: #6f42c1;
    color: white;
}

.status-enabled {
    color: #28a745;
    font-weight: 600;
}

.status-disabled {
    color: #dc3545;
    font-weight: 600;
}

.status-available {
    color: #28a745;
    font-weight: 600;
}

.status-unavailable {
    color: #dc3545;
    font-weight: 600;
}

.status-maintenance {
    color: #ffc107;
    font-weight: 600;
}

.status-unknown {
    color: #6c757d;
    font-weight: 600;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-style: italic;
    background-color: #f8f9fa;
}

.group-tag {
    background-color: #e7f3ff;
    color: #004085;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    margin-right: 3px;
    margin-bottom: 2px;
    display: inline-block;
    border: 1px solid #b8daff;
}

.kernel-display {
    background-color: #fff3cd;
    padding: 3px 6px;
    border-radius: 3px;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 11px;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.disk-usage-container {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 11px;
    max-height: 55px;
    overflow: hidden;
}

.disk-usage-item {
    display: flex;
    align-items: center;
    padding: 1px 0;
    gap: 3px;  /* This for better spacing */
}

.disk-mount {
    font-weight: 600;
    min-width: 45px;
    margin-right: 5px;
}

.disk-size {  /* This is for Disk Class */
    color: #6c757d;
    font-size: 10px;
    font-weight: normal;
}

.disk-percentage {
    font-weight: 600;
}

.cmdb-title {
    text-align: center;
    padding: 20px;
    margin-bottom: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.cmdb-title h1 {
    color: #ffffff;
    font-size: 28px;
	font-family: "Trebuchet MS", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Tahoma, sans-serif;
    font-weight: 600;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    letter-spacing: 0.5px;
}


/* Column width controls */
.hosts-table thead th:nth-child(1) { width: 180px; } /* Host Name */
.hosts-table thead th:nth-child(2) { width: 70px; } /* IP Address */
.hosts-table thead th:nth-child(3) { width: 120px; } /* Customer */
.hosts-table thead th:nth-child(4) { width: 75px; } /* Product */
.hosts-table thead th:nth-child(5) { width: 40px; }  /* Architecture */
.hosts-table thead th:nth-child(6) { width: 80px; } /* Interface Type */
.hosts-table thead th:nth-child(7) { width: 55px; }  /* CPU Total */
.hosts-table thead th:nth-child(8) { width: 55px; }  /* CPU Usage */
.hosts-table thead th:nth-child(9) { width: 70px; } /* Memory Total */
.hosts-table thead th:nth-child(10) { width: 78px; } /* Memory Usage */
.hosts-table thead th:nth-child(11) { width: 200px; } /* Disk Usage */
.hosts-table thead th:nth-child(12) { width: 180px; } /* Operating System */
.hosts-table thead th:nth-child(13) { width: 180px; } /* Host Group */


.hosts-table tbody td:nth-child(1) { width: 180px; }
.hosts-table tbody td:nth-child(2) { width: 70px; }
.hosts-table tbody td:nth-child(3) { width: 120px; }
.hosts-table tbody td:nth-child(4) { width: 75px; }
.hosts-table tbody td:nth-child(5) { width: 40px; }
.hosts-table tbody td:nth-child(6) { width: 80px; }
.hosts-table tbody td:nth-child(7) { width: 55px; }
.hosts-table tbody td:nth-child(8) { width: 55px; }
.hosts-table tbody td:nth-child(9) { width: 70px; }
.hosts-table tbody td:nth-child(10) { width: 70px; }
.hosts-table tbody td:nth-child(11) { width: 200px; }
.hosts-table tbody td:nth-child(12) { width: 200px; }
.hosts-table tbody td:nth-child(13) { width: 180px; }


');

// Create the main content
$content = (new CDiv())
    ->addClass('cmdb-container')
    
	// ADD TITLE HERE (BEFORE THE SEARCH FORM)
    ->addItem(
        (new CDiv())
            ->addClass('cmdb-title')
            ->addItem(
                (new CTag('h1', true, LanguageManager::t('Configuration Management Database (CMDB) in Zabbix')))
            )
    )
	
	->addItem(
        (new CDiv())
            ->addClass('cmdb-search-form')
            ->addItem(
                (new CForm())
                    ->setMethod('get')
                    ->setAction('zabbix.php?action=cmdb')
                    ->addItem(
                        (new CDiv())
                            ->addClass('search-form')
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('🔍 ' . LanguageManager::t('Search by Hostname or IP')))
                                    ->addItem(
                                        (new CTextBox('search', $data['search']))
                                            ->setAttribute('placeholder', LanguageManager::t('Search Hosts...'))
                                            ->setAttribute('oninput', 'handleSearchInput(this)')
                                    )
                            )
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('📂 ' . LanguageManager::t('Select Host Group')))
                                    ->addItem((function() use ($data) {
                                        $select = new CTag('select', true);
                                        $select->setAttribute('name', 'groupid');
                                        $select->setAttribute('id', 'groupid-select');
                                        $select->setAttribute('onchange', 'handleGroupChange(this)');

                                        // Add the “All Groups” option
                                        $optAll = new CTag('option', true, LanguageManager::t('Select Group'));
                                        $optAll->setAttribute('value', '0');
                                        $select->addItem($optAll);

                                        // Add actual host groups
                                        if (!empty($data['host_groups'])) {
                                            foreach ($data['host_groups'] as $group) {
                                                $opt = new CTag('option', true, $group['name']);
                                                $opt->setAttribute('value', $group['groupid']);
                                                if (isset($data['selected_groupid']) && $data['selected_groupid'] == $group['groupid']) {
                                                    $opt->setAttribute('selected', 'selected');
                                                }
                                                $select->addItem($opt);
                                            }
                                        }

                                        return $select;
                                    })())
                            )
                            ->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(new CLabel('🔌 ' . LanguageManager::t('Interface Type')))
                                    ->addItem((function() use ($data) {
                                        $select = new CTag('select', true);
                                        $select->setAttribute('name', 'interface_type');
                                        $select->setAttribute('id', 'interface-type-select');
                                        $select->setAttribute('onchange', 'handleInterfaceTypeChange(this)');

                                        $interfaceTypes = [
                                            0 => LanguageManager::t('All Interfaces'),
                                            1 => LanguageManager::t('Agent'),
                                            2 => LanguageManager::t('SNMP'),
                                            3 => LanguageManager::t('IPMI'),
                                            4 => LanguageManager::t('JMX')
                                        ];

                                        foreach ($interfaceTypes as $value => $label) {
                                            $opt = new CTag('option', true, $label);
                                            $opt->setAttribute('value', $value);
                                            if (isset($data['interface_type']) && $data['interface_type'] == $value) {
                                                $opt->setAttribute('selected', 'selected');
                                            }
                                            $select->addItem($opt);
                                        }

                                        return $select;
                                    })())
                            )
							->addItem(
                                (new CDiv())
                                    ->addClass('form-field')
                                    ->addItem(
										(new CButton('export', '📥 ' . LanguageManager::t('Export To CSV')))
											->addClass('btn')
											->addClass('btn-secondary')
											->setAttribute('type', 'button')
											->setAttribute('onclick', '(function(){
												var url = new URLSearchParams(window.location.search);
												window.location.href = "zabbix.php?action=cmdb.export&format=csv&search=" + 
													encodeURIComponent(url.get("search")||"") + 
													"&groupid=" + encodeURIComponent(url.get("groupid")||"0") + 
													"&interface_type=" + encodeURIComponent(url.get("interface_type")||"0");
											})()')
                                    )
                            )
                    )
                    ->addItem((new CInput('hidden', 'action', 'cmdb')))
            )
    );

// If host data exists, add statistic cards
if (!empty($data['hosts'])) {
    $content->addItem(
        (new CDiv())
            ->addClass('stats-container')
            
            // CPU Total Card
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🧠'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['total_cpu'] ?? 0))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('CPU Total')))->addClass('stat-label'))
                    )
            )
            
            // CPU Usage Card (NEW)
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('⚡'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(($data['avg_cpu_usage'] ?? 0) . '%'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('CPU Used')))->addClass('stat-label'))
                    )
            )
            
            // Memory Total Card
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('💾'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['total_memory'] ? ItemFinder::formatMemorySize($data['total_memory']) : '0 B'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Memory Total')))->addClass('stat-label'))
                    )
            )
            
            // Memory Usage Card (NEW)
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('📊'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(($data['avg_memory_usage'] ?? 0) . '%'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Memory Used')))->addClass('stat-label'))
                    )
            )
            
            // Storage Total Card
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('💿'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv($data['total_storage'] ? ItemFinder::formatMemorySize($data['total_storage']) : '0 B'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Storage Total')))->addClass('stat-label'))
                    )
            )
            
            // Storage Usage Card (NEW)
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('📈'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(($data['avg_storage_usage'] ?? 0) . '%'))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Storage Used')))->addClass('stat-label'))
                    )
            )
            
            // Total Host Card
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(count($data['hosts'])))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Total Hosts')))->addClass('stat-label'))
                    )
            )
            
            // Active/Enable Host Card
            ->addItem(
                (new CDiv())
                    ->addClass('stat-card')
                    ->addItem((new CSpan('🟢'))->addClass('stat-icon'))
                    ->addItem(
                        (new CDiv())
                            ->addClass('stat-content')
                            ->addItem((new CDiv(countActiveHosts($data['hosts'])))->addClass('stat-number'))
                            ->addItem((new CDiv(LanguageManager::t('Active Hosts')))->addClass('stat-label'))
                    )
            )
    );
}

// Create the table
$table = new CTable();
$table->addClass('hosts-table');

// Add table headers (with sorting links)
$header = [
    createSortLink(LanguageManager::t('Host Name'), 'name', $data),
//  createSortLink(LanguageManager::t('System Name'), 'system_name', $data),
    createSortLink(LanguageManager::t('IP Address'), 'ip', $data),
	createSortLink(LanguageManager::t('Customer'), 'customer', $data),
	createSortLink(LanguageManager::t('Product'), 'product', $data),
    createSortLink(LanguageManager::t('Arch'), 'os_architecture', $data),
    LanguageManager::t('Interface Type'),
    createSortLink(LanguageManager::t('CPU Total'), 'cpu_total', $data),
    createSortLink(LanguageManager::t('CPU Usage'), 'cpu_usage', $data),
    createSortLink(LanguageManager::t('Memory Total'), 'memory_total', $data),
    createSortLink(LanguageManager::t('Memory Usage'), 'memory_usage', $data),
    LanguageManager::t('Disk Usage'),
    createSortLink(LanguageManager::t('Operating System'), 'operating_system', $data),
    LanguageManager::t('Host Group')
];
$table->setHeader($header);

// If there is no host data
if (empty($data['hosts'])) {
    $table->addRow([
        (new CCol(LanguageManager::t('No hosts found')))
            ->addClass('no-data')
            ->setAttribute('colspan', 14)
    ]);
} else {
    // Add host data rows
    foreach ($data['hosts'] as $host) {
        // Get the primary IP address
        $mainIp = '';
        $interfaceTypes = [];
        foreach ($host['interfaces'] as $interface) {
            if ($interface['main'] == 1) {
                $mainIp = !empty($interface['ip']) ? $interface['ip'] : $interface['dns'];
            }

            // Collect interface types
            $typeClass = '';
            $typeText = '';
            $typeIcon = '';
            switch ($interface['type']) {
                case 1:
                    $typeClass = 'interface-agent';
                    $typeIcon = '🤖';
                    $typeText = LanguageManager::t('Agent');
                    break;
                case 2:
                    $typeClass = 'interface-snmp';
                    $typeIcon = '📡';
                    $typeText = LanguageManager::t('SNMP');
                    break;
                case 3:
                    $typeClass = 'interface-ipmi';
                    $typeIcon = '🔧';
                    $typeText = LanguageManager::t('IPMI');
                    break;
                case 4:
                    $typeClass = 'interface-jmx';
                    $typeIcon = '☕';
                    $typeText = LanguageManager::t('JMX');
                    break;
            }

            if (!empty($typeText)) {
                $interfaceTypes[] = (new CSpan($typeText))->addClass('interface-type ' . $typeClass);
            }
        }        // Get Host Groups
        $groupNames = [];
        if (isset($host['groups']) && is_array($host['groups'])) {
            $groupNames = array_column($host['groups'], 'name');
        }

        // Host name and status
        $hostNameCol = new CCol();
        $hostNameCol->addItem(
            (new CLink(htmlspecialchars($host['name']), 'zabbix.php?action=host.view&hostid=' . $host['hostid']))
                ->addClass('host-link')
        );
        $hostNameCol->addItem(
            (new CDiv())
                ->addItem(
                    getHostStatusDisplay($host)
                )
        );

        // System name
        $systemNameCol = new CCol();
        if (isset($host['system_name']) && $host['system_name'] !== null) {
            $systemNameCol->addItem(
                (new CSpan(htmlspecialchars($host['system_name'])))->setAttribute('style', 'font-family: monospace; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
            );
        } else {
            $systemNameCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // IP Address
        $ipCol = new CCol(
            (new CSpan(htmlspecialchars($mainIp)))->addClass('code-display')->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
        );

		// Customer Info
		$customerCol = new CCol();
		if (isset($host['customer']) && $host['customer'] !== '-' && $host['customer'] !== null) {
			$customerCol->addItem(
				(new CSpan(htmlspecialchars($host['customer'])))
					->setAttribute('style', 'font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
			);
		} else {
			$customerCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
		}
		
		// Product Info
		$productCol = new CCol();
		if (isset($host['product']) && $host['product'] !== '-' && $host['product'] !== null) {
			$productCol->addItem(
				(new CSpan(htmlspecialchars($host['product'])))
					->setAttribute('style', 'font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
			);
		} else {
			$productCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
		}

        // Architecture
        $archCol = new CCol();
        if (isset($host['os_architecture']) && $host['os_architecture'] !== null) {
            $archCol->addItem(
                (new CSpan(htmlspecialchars($host['os_architecture'])))->setAttribute('style', 'font-family: monospace; font-size: 13px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
            );
        } else {
            $archCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // Interface Type
        $interfaceCol = new CCol();
        if (!empty($interfaceTypes)) {
            $interfaceContainer = (new CDiv())->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;');
            foreach ($interfaceTypes as $interfaceType) {
                $interfaceContainer->addItem($interfaceType);
            }
            $interfaceCol->addItem($interfaceContainer);
        } else {
            $interfaceCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // Total CPU
        $cpuCol = new CCol();
        if ($host['cpu_total'] !== '-') {
            $cpuCol->addItem([
                (new CSpan(htmlspecialchars($host['cpu_total'])))->setAttribute('style', 'font-weight: 600; color: #4f46e5;'),
                ' ',
                (new CSpan('cores'))->setAttribute('style', 'color: #6c757d; font-size: 12px;')
            ]);
        } else {
            $cpuCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // CPU Usage
        $cpuUsageCol = new CCol();
        if ($host['cpu_usage'] !== '-') {
            $usageValue = floatval(str_replace('%', '', $host['cpu_usage']));
            $usageColor = '#28a745'; // Green
            $usageIcon = '🟢'; // Normal
            if ($usageValue > 80) {
                $usageColor = '#dc3545'; // Red
                $usageIcon = '🔴'; // High Load
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107'; // Yellow
                $usageIcon = '🟡'; // Medium Load
            }
            $cpuUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . htmlspecialchars($host['cpu_usage'])))->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $cpuUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // Total Memory
        $memoryCol = new CCol();
        if ($host['memory_total'] !== '-') {
            $memoryCol->addItem(
                (new CSpan(htmlspecialchars($host['memory_total'])))->setAttribute('style', 'font-weight: 600; color: #059669;')
            );
        } else {
            $memoryCol->addItem((new CSpan('-'))->setAttribute('style', 'color: #6c757d;'));
        }

        // Memory Usage
        $memoryUsageCol = new CCol();
        if ($host['memory_usage'] !== '-') {
            $usageValue = floatval(str_replace('%', '', $host['memory_usage']));
            $usageColor = '#28a745'; // Green
            $usageIcon = '🟢'; // Normal
            if ($usageValue > 80) {
                $usageColor = '#dc3545'; // Red
                $usageIcon = '🔴'; // High Load
            } elseif ($usageValue > 60) {
                $usageColor = '#ffc107'; // Yellow
                $usageIcon = '🟡'; // Medium Load
            }
            $memoryUsageCol->addItem(
                (new CSpan($usageIcon . ' ' . htmlspecialchars($host['memory_usage'])))->setAttribute('style', 'font-weight: 600; color: ' . $usageColor . ';')
            );
        } else {
            $memoryUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
        }

		// Disk Usage
		$diskUsageCol = new CCol();
		if (!empty($host['disk_usage'])) {
			$diskContainer = (new CDiv())->addClass('disk-usage-container');
			
			foreach ($host['disk_usage'] as $disk) {
				$percentage = $disk['percentage'];
				$mount = htmlspecialchars($disk['mount']);
				$totalSize = isset($disk['total_size']) && $disk['total_size'] > 0 
					? ItemFinder::formatMemorySize($disk['total_size']) 
					: '';
				
				// Determine color based on usage
				$colorClass = 'disk-normal';
				$icon = '🟢';
				if ($percentage > 80) {
					$colorClass = 'disk-high';
					$icon = '🔴';
				} elseif ($percentage > 60) {
					$colorClass = 'disk-medium';
					$icon = '🟡';
				}
				
				// Format: / => 16.93 GB (68.58% 🟡)
				$diskText = $mount . ' => ';
				if (!empty($totalSize)) {
					$diskText .= $totalSize . ' ';
				}
				$diskText .= '(' . $icon . ' ' . $percentage . '%)';
				
				$diskItem = (new CDiv())
					->addClass('disk-usage-item')
					->addItem((new CSpan($diskText))->addClass('disk-percentage ' . $colorClass));
				
				$diskContainer->addItem($diskItem);
			}
			
			$diskUsageCol->addItem($diskContainer);
		} else {
			$diskUsageCol->addItem((new CSpan('⚪ -'))->setAttribute('style', 'color: #6c757d;'));
		}

        // Operating System
        $osCol = new CCol();
        if (isset($host['operating_system']) && $host['operating_system'] !== null) {
            $osName = $host['operating_system'];
            $osIcon = '💻'; // Default Icon
            
            // Set the icon based on the operating system type
            if (stripos($osName, 'windows') !== false) {
                $osIcon = '🪟';
            } elseif (stripos($osName, 'linux') !== false) {
                $osIcon = '🐧';
            } elseif (stripos($osName, 'ubuntu') !== false) {
                $osIcon = '🟠';
            } elseif (stripos($osName, 'centos') !== false || stripos($osName, 'red hat') !== false) {
                $osIcon = '🔴';
            } elseif (stripos($osName, 'debian') !== false) {
                $osIcon = '🔵';
            } elseif (stripos($osName, 'mac') !== false || stripos($osName, 'darwin') !== false) {
                $osIcon = '🍎';
            } elseif (stripos($osName, 'freebsd') !== false) {
                $osIcon = '👿';
            } elseif (stripos($osName, 'solaris') !== false) {
                $osIcon = '☀️';
            }
            
            $osCol->addItem(
                (new CSpan(htmlspecialchars($osName)))
                    ->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;')
                    ->setAttribute('title', htmlspecialchars($osName))
            );
        } else {
            $osCol->addItem((new CSpan('❓ -'))->setAttribute('style', 'color: #6c757d;'));
        }

        // Host Groups
        $groupCol = new CCol();
        $groupContainer = (new CDiv())->setAttribute('style', 'display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; max-height: 3.8em;');
        foreach ($groupNames as $groupName) {
            $groupContainer->addItem(
                (new CSpan(htmlspecialchars($groupName)))->addClass('group-tag')
            );
            $groupContainer->addItem(' ');
        }
        $groupCol->addItem($groupContainer);

        $table->addRow([
            $hostNameCol,
//          $systemNameCol,
            $ipCol,
			$customerCol,
			$productCol,
            $archCol,
            $interfaceCol,
            $cpuCol,
            $cpuUsageCol,
            $memoryCol,
            $memoryUsageCol,
			$diskUsageCol,
            $osCol,
            $groupCol
        ]);
    }
}

$content->addItem($table);

// Add JavaScript
$content->addItem(new CTag('script', true, '
// Add automatic search functionality
// Global variable used for debouncing
var searchTimeout;

function handleSearchInput(input) {
    clearTimeout(searchTimeout);
    var form = input.closest("form");

    searchTimeout = setTimeout(function() {
        if (form) {
            form.submit();
        }
    }, 500);
}

function handleGroupChange(select) {
    var form = select.closest("form");

    if (form) {
        form.submit();
    }
}

function handleInterfaceTypeChange(select) {
    var form = select.closest("form");

    if (form) {
        form.submit();
    }
}

// Adding Export Function
function exportToCSV() {
    var search = document.querySelector(\'input[name="search"]\').value;
    var groupid = document.getElementById(\'groupid-select\').value;
    var interfaceType = document.getElementById(\'interface-type-select\').value;
    
    var exportUrl = \'zabbix.php?action=cmdb.export&format=csv\';
    if (search) exportUrl += \'&search=\' + encodeURIComponent(search);
    if (groupid) exportUrl += \'&groupid=\' + groupid;
    if (interfaceType) exportUrl += \'&interface_type=\' + interfaceType;
    
    window.location.href = exportUrl;
}

document.addEventListener("DOMContentLoaded", function() {
    // Additional initialization logic can be added here
    var searchInput = document.querySelector("input[name=\"search\"]");
    var groupSelect = document.getElementById("groupid-select");
    var interfaceTypeSelect = document.getElementById("interface-type-select");
});
'));

// Render the page using the compatibility renderer (module views must output directly and cannot return)
ViewRenderer::render($pageTitle, $styleTag, $content);
