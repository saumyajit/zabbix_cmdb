<div align="center">

# 🗄️ Zabbix CMDB Module

**Configuration Management Database for Zabbix Hosts**

![Version](https://img.shields.io/badge/version-2.0-blue.svg?style=for-the-badge&logo=git)
![Zabbix](https://img.shields.io/badge/zabbix-6.0%2B-red.svg?style=for-the-badge&logo=zabbix)
![PHP](https://img.shields.io/badge/php-8.0%2B-purple.svg?style=for-the-badge&logo=php)
![License](https://img.shields.io/badge/license-GPL--3.0-green.svg?style=for-the-badge&logo=opensourceinitiative)
![Status](https://img.shields.io/badge/status-production-brightgreen.svg?style=for-the-badge)

[✨ Features](#-features) • [🚀 Installation](#-installation) • [📖 Usage](#-usage) • [🔧 Development](#-development) • [📄 License](#-license)

---

</div>

## 📋 Overview

The **Zabbix CMDB Module** is a comprehensive frontend module that provides Configuration Management Database (CMDB) functionality for Zabbix. It offers centralized viewing and management of host information through an intuitive interface integrated directly into Zabbix Web.

> **Location**: Navigate to **Inventory → CMDB** in your Zabbix menu after installation.

### 🖼️ Preview

<div align="center">
  <img src="images/1.jpg" alt="CMDB Host List Interface" width="48%" />
  <img src="images/2.jpg" alt="CMDB Host Groups Interface" width="48%" />
  <br/>
  <em>Host List & Host Groups Interface</em>
</div>

## ✨ Features

### 🔍 **Search & Filtering**
- **Host Search**: Search by hostname or IP address with instant results
- **Group Filtering**: Filter hosts by host groups
- **Group Search**: Quickly find specific host groups by name

### 📊 **Information Display**
- **Host Details**:
  - Host name (clickable link to host details)
  - IP address and interface type (Agent, SNMP, IPMI, JMX)
  - Hardware specifications (CPU total, Memory total)
  - Kernel version and operating system information
  - Host group associations
  - Host status (Active/Disabled) with visual indicators

### 🌐 **Interface & Design**
- **Responsive Design**: Optimized for desktop and tablet screens
- **Modern UI**: Clean interface with gradient colors and smooth animations
- **Dashboard Statistics**: Real-time display of:
  - Total CPU
  - Total Memory
  - Total Storage allocated for Host
  - Total host groups
  - Total hosts
  - Active hosts count

## 🚀 Installation

### Prerequisites
- Zabbix 6.0+ or 7.0+
- PHP 8.0+
- Git installed on your Zabbix server

### Step 1: Clone the Repository

**For Zabbix 6.0 / 7.0:**
```bash
sudo git clone https://github.com/saumyajit/cmdb.git /usr/share/zabbix/modules/
```
**For Zabbix 7.4:**
```bash
sudo git clone https://github.com/saumyajit/cmdb.git /usr/share/zabbix/modules/
```

### Step 2: Configuration Adjustment

**For Zabbix 7.0+:** No modification needed. The default `manifest_version: 2.0` is compatible.

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
```

### Step 3: Enable the Module

1. Log into your Zabbix web interface
2. Navigate to **Administration → General → Modules**
3. Click **Scan directory** to detect new modules
4. Find **"CMDB Zabbix"** in the module list
5. Click **Enable**
6. Refresh your browser page

### Step 4: Access the Module

After enabling, you'll find a new **CMDB** submenu under the **Inventory** main menu:

## 📖 Usage Guide

### Navigating the CMDB

  **View**:
   - Search hosts by name or IP
   - Filter by host groups using dropdown
   - Click host names to jump to detailed host configuration
   - View comprehensive host information in a single table

### Best Practices

- **Performance**: For environments with 1000+ hosts, consider using filters to limit displayed results
- **Data Freshness**: Information is pulled directly from Zabbix database in real-time
- **Item Dependencies**: CPU and memory totals require corresponding items to be configured in Zabbix

## ⚠️ Important Notes

| Consideration | Details |
|---------------|---------|
| **Performance** | Large environments may experience slower load times. Use filters strategically. |
| **Data Accuracy** | Displayed information reflects current Zabbix database state. |
| **Dependencies** | CPU/Memory data requires proper item configuration in Zabbix. |
| **Compatibility** | Tested with Zabbix 6.0-7.4. Always backup before installation. |

## 🔧 Development

### Project Structure

```
cmdb/
├── manifest.json           # Module configuration
├── Module.php              # Menu registration
├── actions/
│ ├── Cmdb.php              # Business logic (Controller)
├── views/
│ ├── cmdb.php              # Views
├── lib/
│ ├── LanguageManager.php   # Language support (English)
│ └── ItemFinder.php        # Utility functions
│ └── ViewRenderer.php      # Utility functions
│ └── ZabbixVersion.php     # Utility functions
└── images/                 # Screenshots and assets
```

### Extending the Module
For customizations or extensions, refer to the official [Zabbix Module Development Documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

### Contributing
We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request with detailed description
4. Ensure code follows Zabbix module standards

## 📄 License

This project is licensed under the **GNU General Public License v3.0** - the same license as Zabbix.

For complete license details, visit:
- [Zabbix License Information](https://www.zabbix.com/license)
- [GNU GPL v3.0](https://www.gnu.org/licenses/gpl-3.0.html)

## 🤝 Support

- **Issues**: Report bugs or issues on [GitHub Issues](https://github.com/saumyajit/cmdb/issues)
- **Questions**: Check existing issues before creating new ones
- **Enhancements**: Feature requests are welcome with clear use cases

---

<div align="center">

**Built with ❤️ for the Zabbix Community**

[![Star](https://img.shields.io/github/stars/saumyajit/zabbix_cmdb?style=social)](https://github.com/saumyajit/cmdb)
[![Fork](https://img.shields.io/github/forks/saumyajit/zabbix_cmdb?style=social)](https://github.com/saumyajit/cmdb/fork)

</div>
