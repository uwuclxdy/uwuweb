# uwuweb - Linux Setup Guide with XAMPP

Quick setup guide for uwuweb grade management system on Linux using XAMPP.

## Prerequisites

- XAMPP (7.4+ recommended)
- Git (optional)
- Web browser

## Installation Steps

### 1. Install XAMPP

```bash
# Download XAMPP (adjust version as needed)
wget https://sourceforge.net/projects/xampp/files/XAMPP%20Linux/8.2.12/xampp-linux-x64-8.2.12-0-installer.run

# Make installer executable
chmod +x xampp-linux-x64-8.2.12-0-installer.run

# Run installer
sudo ./xampp-linux-x64-8.2.12-0-installer.run
```

### 2. Start XAMPP Services

```bash
# Start all services
sudo /opt/lampp/lampp start

# Or start specific services
sudo /opt/lampp/lampp startapache
sudo /opt/lampp/lampp startmysql
```

### 3. Set Up Project Files

```bash
# Using Git
cd /opt/lampp/htdocs
sudo git clone [repository-url] uwuweb

# Manual copy (alternative)
sudo cp -r /path/to/downloaded/uwuweb /opt/lampp/htdocs/
```

### 4. Set Up Database

```bash
# Import database via command line
cd /opt/lampp/bin
sudo ./mysql -u root -p < /opt/lampp/htdocs/uwuweb/db/uwuweb.sql
# Press Enter when prompted for password
```

### 5. Configure Database Connection

```bash
# Edit the configuration file if needed
sudo nano /opt/lampp/htdocs/uwuweb/includes/db.php
```

Default config (usually works with standard XAMPP):
```php
$db_config = [
    'host' => 'localhost',
    'dbname' => 'uwuweb',
    'charset' => 'utf8mb4',
    'username' => 'root',
    'password' => ''
];
```

### 6. Set Permissions

```bash
# Set proper permissions
sudo chmod -R 755 /opt/lampp/htdocs/uwuweb
sudo chown -R daemon:daemon /opt/lampp/htdocs/uwuweb
```

### 7. Access Application

Open: `http://localhost/uwuweb`

## Default Login

- Username: `admin`
- Password: `Admin123!`

## Troubleshooting

### Apache Won't Start

```bash
# Check if port 80 is in use
sudo netstat -tuln | grep 80

# Edit Apache config to change port
sudo nano /opt/lampp/etc/httpd.conf
# Find "Listen 80" and change to "Listen 8080"

# Restart Apache
sudo /opt/lampp/lampp restart
```

### Enable Error Display

```bash
# Edit configuration file
sudo nano /opt/lampp/htdocs/uwuweb/includes/db.php

# Add to top of file:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

### Check Logs

```bash
# View Apache error logs
sudo tail -f /opt/lampp/logs/error_log
```

## Security Note

This setup is for local development only. Not secure for production use.