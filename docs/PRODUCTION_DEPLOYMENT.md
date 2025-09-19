# 🚀 Task Queue System - Production Deployment Guide

This guide provides comprehensive instructions for deploying the Task Queue system in a production environment with high availability, security, and performance considerations.

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Production Architecture](#production-architecture)
3. [Environment Setup](#environment-setup)
4. [Security Configuration](#security-configuration)
5. [Database Setup](#database-setup)
6. [Queue Workers Deployment](#queue-workers-deployment)
7. [Web Dashboard Deployment](#web-dashboard-deployment)
8. [Monitoring & Logging](#monitoring--logging)
9. [Load Balancing](#load-balancing)
10. [Backup & Recovery](#backup--recovery)
11. [Performance Tuning](#performance-tuning)
12. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements

- **CPU**: 2 cores
- **RAM**: 4GB
- **Storage**: 20GB SSD
- **Network**: 100 Mbps

### Recommended Production Requirements

- **CPU**: 4+ cores
- **RAM**: 8GB+
- **Storage**: 100GB+ SSD with high IOPS
- **Network**: 1 Gbps

### Software Dependencies

- **PHP**: 8.1 or higher
- **Composer**: 2.0+
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+
- **Web Server**: Nginx 1.18+ or Apache 2.4+
- **Process Manager**: Supervisor or systemd

---

## Production Architecture

### Recommended Architecture

```md
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Load Balancer │    │   Web Server    │    │   Queue Workers │
│     (Nginx)     │────│   (PHP-FPM)     │────│   (Supervisor)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Dashboard     │    │   Database      │    │   Redis Cache   │
│   (Web UI)      │    │   (MySQL/PostgreSQL) │   (Optional)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### High Availability Setup

```md
┌─────────────────┐    ┌─────────────────┐
│   Load Balancer │    │   Load Balancer │
│   (Primary)     │    │   (Secondary)   │
└─────────────────┘    └─────────────────┘
         │                       │
    ┌────┴────┐              ┌────┴────┐
    │         │              │         │
┌───▼───┐ ┌──▼──┐        ┌───▼───┐ ┌──▼──┐
│ Web 1 │ │Web 2│        │ Web 3 │ │Web 4│
└───┬───┘ └──┬──┘        └───┬───┘ └──┬──┘
    │         │              │         │
    └─────────┼──────────────┼─────────┘
              │              │
    ┌─────────▼──────────────▼─────────┐
    │        Database Cluster         │
    │    (Master-Slave Replication)   │
    └─────────────────────────────────┘
```

---

## Environment Setup

### 1. Server Preparation

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y php8.1-cli php8.1-fpm php8.1-mysql php8.1-pgsql \
    php8.1-sqlite3 php8.1-curl php8.1-json php8.1-mbstring \
    php8.1-xml php8.1-zip nginx supervisor git curl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Application Deployment

```bash
# Create application directory
sudo mkdir -p /opt/task-queue
sudo chown www-data:www-data /opt/task-queue
cd /opt/task-queue

# Clone or copy application files
sudo -u www-data git clone <repository-url> .
# OR copy files from development

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Set proper permissions
sudo chown -R www-data:www-data /opt/task-queue
sudo chmod -R 755 /opt/task-queue
sudo chmod -R 777 /opt/task-queue/storage
```

---

## Security Configuration

### 1. Environment Variables

Create `/opt/task-queue/.env`:

```bash
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=task_queue_prod
DB_USERNAME=task_queue_user
DB_PASSWORD=your_secure_password_here

# Encryption
ENCRYPTION_KEY=your_32_character_encryption_key_here

# Queue Configuration
QUEUE_DEFAULT_TIMEOUT=3600
QUEUE_DEFAULT_MEMORY=128
QUEUE_DEFAULT_MAX_ATTEMPTS=3

# Logging
LOG_LEVEL=info
LOG_CHANNEL=file

# Security
APP_ENV=production
APP_DEBUG=false
```

### 2. Database Security

```sql
-- Create dedicated database user
CREATE DATABASE task_queue_prod;
CREATE USER 'task_queue_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX ON task_queue_prod.* TO 'task_queue_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. File Permissions

```bash
# Set restrictive permissions
sudo chown -R www-data:www-data /opt/task-queue
sudo chmod -R 755 /opt/task-queue
sudo chmod -R 777 /opt/task-queue/storage
sudo chmod 600 /opt/task-queue/.env

# Remove development files
sudo rm -rf /opt/task-queue/tests
sudo rm -rf /opt/task-queue/docs
```

---

## Database Setup

### MySQL Configuration

```ini
# /etc/mysql/mysql.conf.d/task-queue.cnf
[mysqld]
# Performance optimizations
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connection settings
max_connections = 200
max_connect_errors = 10000

# Query cache
query_cache_type = 1
query_cache_size = 64M

# Table optimization
innodb_file_per_table = 1
```

### PostgreSQL Configuration

```bash
# /etc/postgresql/13/main/postgresql.conf
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
```

### Database Migration

```bash
# Run database migrations
cd /opt/task-queue
sudo -u www-data php worker migrate:run
```

---

## Queue Workers Deployment

### 1. Supervisor Configuration

Create `/etc/supervisor/conf.d/task-queue.conf`:

```ini
[program:task-queue-workers]
command=php /opt/task-queue/worker queue:work --workers=4 --timeout=3600 --memory=128
directory=/opt/task-queue
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/task-queue/workers.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
numprocs=2
process_name=%(program_name)s_%(process_num)02d

[program:task-queue-high-priority]
command=php /opt/task-queue/worker queue:work priority-queue --workers=2 --timeout=1800 --memory=64
directory=/opt/task-queue
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/task-queue/high-priority.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
numprocs=1
process_name=%(program_name)s_%(process_num)02d

[program:task-queue-dashboard]
command=php /opt/task-queue/worker dashboard:serve --port=8080 --host=127.0.0.1
directory=/opt/task-queue
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/task-queue/dashboard.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

### 2. Systemd Service (Alternative)

Create `/etc/systemd/system/task-queue.service`:

```ini
[Unit]
Description=Task Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/opt/task-queue
ExecStart=/usr/bin/php worker queue:work --workers=4 --timeout=3600 --memory=128
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### 3. Start Services

```bash
# Create log directory
sudo mkdir -p /var/log/task-queue
sudo chown www-data:www-data /var/log/task-queue

# Reload and start services
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all

# OR for systemd
sudo systemctl enable task-queue
sudo systemctl start task-queue
```

---

## Web Dashboard Deployment

### 1. Nginx Configuration

Create `/etc/nginx/sites-available/task-queue`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /opt/task-queue/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=dashboard:10m rate=5r/s;
    
    # Dashboard Routes
    location / {
        limit_req zone=dashboard burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # API Routes
    location /api.php {
        limit_req zone=api burst=20 nodelay;
        try_files $uri /index.php?$query_string;
    }
    
    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    # Static Files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(vendor|storage|tests)/ {
        deny all;
    }
}
```

### 2. Enable Site

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/task-queue /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Monitoring & Logging

### 1. Log Configuration

Create `/opt/task-queue/config/logging.php`:

```php
<?php

return [
    'default' => 'daily',
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => '/var/log/task-queue/app.log',
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 14,
        ],
        'worker' => [
            'driver' => 'daily',
            'path' => '/var/log/task-queue/worker.log',
            'level' => 'info',
            'days' => 30,
        ],
        'error' => [
            'driver' => 'daily',
            'path' => '/var/log/task-queue/error.log',
            'level' => 'error',
            'days' => 90,
        ],
    ],
];
```

### 2. Monitoring Script

Create `/opt/task-queue/scripts/monitor.sh`:

```bash
#!/bin/bash

# Task Queue Monitoring Script
LOG_FILE="/var/log/task-queue/monitor.log"
ALERT_EMAIL="admin@yourdomain.com"

# Check worker processes
check_workers() {
    WORKER_COUNT=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
    if [ $WORKER_COUNT -lt 2 ]; then
        echo "$(date): WARNING - Only $WORKER_COUNT workers running" >> $LOG_FILE
        # Send alert
        echo "Task Queue Alert: Low worker count ($WORKER_COUNT)" | mail -s "Task Queue Alert" $ALERT_EMAIL
    fi
}

# Check queue depth
check_queue_depth() {
    PENDING_JOBS=$(php /opt/task-queue/worker queue:stats | grep "Pending" | awk '{print $2}')
    if [ $PENDING_JOBS -gt 1000 ]; then
        echo "$(date): WARNING - High queue depth: $PENDING_JOBS jobs" >> $LOG_FILE
        echo "Task Queue Alert: High queue depth ($PENDING_JOBS jobs)" | mail -s "Task Queue Alert" $ALERT_EMAIL
    fi
}

# Check database connectivity
check_database() {
    php /opt/task-queue/worker queue:test --jobs=0 > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "$(date): ERROR - Database connection failed" >> $LOG_FILE
        echo "Task Queue Alert: Database connection failed" | mail -s "Task Queue Critical Alert" $ALERT_EMAIL
    fi
}

# Run checks
check_workers
check_queue_depth
check_database

echo "$(date): Health check completed" >> $LOG_FILE
```

### 3. Cron Job

```bash
# Add to crontab
*/5 * * * * /opt/task-queue/scripts/monitor.sh
```

---

## Load Balancing

### 1. Nginx Load Balancer Configuration

```nginx
upstream task_queue_backend {
    least_conn;
    server 10.0.1.10:8080 weight=3;
    server 10.0.1.11:8080 weight=3;
    server 10.0.1.12:8080 weight=2 backup;
    
    keepalive 32;
}

server {
    listen 80;
    server_name api.yourdomain.com;
    
    location / {
        proxy_pass http://task_queue_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Health check
        proxy_next_upstream error timeout invalid_header http_500 http_502 http_503;
    }
}
```

### 2. HAProxy Configuration

```haproxy
global
    daemon
    maxconn 4096

defaults
    mode http
    timeout connect 5000ms
    timeout client 50000ms
    timeout server 50000ms

frontend task_queue_frontend
    bind *:80
    default_backend task_queue_backend

backend task_queue_backend
    balance roundrobin
    option httpchk GET /health
    server web1 10.0.1.10:8080 check
    server web2 10.0.1.11:8080 check
    server web3 10.0.1.12:8080 check backup
```

---

## Backup & Recovery

### 1. Database Backup Script

Create `/opt/task-queue/scripts/backup.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/opt/backups/task-queue"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="task_queue_prod"

# Create backup directory
mkdir -p $BACKUP_DIR

# MySQL Backup
mysqldump -u task_queue_user -p$DB_PASSWORD $DB_NAME | gzip > $BACKUP_DIR/task_queue_$DATE.sql.gz

# Keep only last 30 days of backups
find $BACKUP_DIR -name "task_queue_*.sql.gz" -mtime +30 -delete

echo "Backup completed: task_queue_$DATE.sql.gz"
```

### 2. Application Backup

```bash
#!/bin/bash

BACKUP_DIR="/opt/backups/task-queue"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup application files (excluding vendor and storage)
tar -czf $BACKUP_DIR/app_$DATE.tar.gz \
    --exclude='vendor' \
    --exclude='storage/logs' \
    --exclude='storage/queue.db' \
    /opt/task-queue

echo "Application backup completed: app_$DATE.tar.gz"
```

### 3. Automated Backup Cron

```bash
# Daily database backup at 2 AM
0 2 * * * /opt/task-queue/scripts/backup.sh

# Weekly application backup on Sunday at 1 AM
0 1 * * 0 /opt/task-queue/scripts/app_backup.sh
```

---

## Performance Tuning

### 1. PHP-FPM Configuration

```ini
; /etc/php/8.1/fpm/pool.d/task-queue.conf
[task-queue]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm-task-queue.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000

; Performance settings
request_terminate_timeout = 300
request_slowlog_timeout = 10
slowlog = /var/log/php-fpm-slow.log
```

### 2. Database Optimization

```sql
-- Add indexes for better performance
CREATE INDEX idx_job_state_priority ON job_queue (state, priority);
CREATE INDEX idx_job_created_at ON job_queue (created_at);
CREATE INDEX idx_job_queue_state ON job_queue (queue_name, state);
CREATE INDEX idx_job_processing ON job_queue (state, updated_at) WHERE state = 'processing';

-- Optimize table
OPTIMIZE TABLE job_queue;
```

### 3. Redis Caching (Optional)

```php
// Add Redis caching for frequently accessed data
use Redis;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Cache queue stats
$stats = $redis->get('queue_stats');
if (!$stats) {
    $stats = $manager->getQueueStats();
    $redis->setex('queue_stats', 60, json_encode($stats));
}
```

---

## Troubleshooting

### Common Issues

#### 1. Workers Not Processing Jobs

```bash
# Check worker status
sudo supervisorctl status task-queue-workers

# Check logs
tail -f /var/log/task-queue/workers.log

# Restart workers
sudo supervisorctl restart task-queue-workers:*
```

#### 2. High Memory Usage

```bash
# Check memory usage
ps aux | grep queue:work

# Adjust memory limits in supervisor config
# Restart services
sudo supervisorctl update
```

#### 3. Database Connection Issues

```bash
# Test database connection
php /opt/task-queue/worker queue:test --jobs=0

# Check database status
sudo systemctl status mysql
```

#### 4. Dashboard Not Loading

```bash
# Check web server logs
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM logs
sudo tail -f /var/log/php8.1-fpm.log

# Test PHP processing
curl -I http://localhost/api.php?action=stats
```

### Performance Monitoring

```bash
# Monitor queue performance
watch -n 5 'php /opt/task-queue/worker queue:stats'

# Monitor system resources
htop
iotop
```

### Log Analysis

```bash
# Analyze error patterns
grep "ERROR" /var/log/task-queue/error.log | tail -100

# Monitor job processing times
grep "processing_time" /var/log/task-queue/worker.log | awk '{print $NF}' | sort -n
```

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks

1. **Daily**: Monitor logs and queue depth
2. **Weekly**: Review performance metrics and optimize queries
3. **Monthly**: Update dependencies and security patches
4. **Quarterly**: Review and test backup/recovery procedures

### Emergency Procedures

1. **Service Outage**: Restart workers and check database connectivity
2. **High Queue Depth**: Scale up workers or investigate job failures
3. **Data Corruption**: Restore from latest backup and replay logs

### Contact Information

- **System Administrator**: <admin@yourdomain.com>
- **Development Team**: <dev@yourdomain.com>
- **Emergency Hotline**: +1-XXX-XXX-XXXX

---

## 📚 Additional Resources

- [Task Queue Documentation](./README.md)
- [API Reference](./API_REFERENCE.md)
- [Performance Testing Guide](./PERFORMANCE_REPORT.md)
- [Security Best Practices](./SECURITY.md)

---

**Last Updated**: September 19, 2025  
**Version**: 1.0.0  
**Maintained by**: Task Queue Development Team
