#!/bin/bash

# Task Queue Production Deployment Script
# Usage: ./scripts/deploy.sh [environment]

set -e

# Configuration
ENVIRONMENT=${1:-production}
APP_DIR="/opt/task-queue"
BACKUP_DIR="/opt/backups/task-queue"
LOG_FILE="/var/log/task-queue/deploy.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a $LOG_FILE
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a $LOG_FILE
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
    error "This script should not be run as root for security reasons"
fi

# Check if application directory exists
if [ ! -d "$APP_DIR" ]; then
    error "Application directory $APP_DIR does not exist"
fi

log "🚀 Starting deployment for environment: $ENVIRONMENT"

# Create backup
log "📦 Creating backup..."
BACKUP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).tar.gz"
mkdir -p $BACKUP_DIR

if [ -d "$APP_DIR/storage" ]; then
    tar -czf $BACKUP_FILE -C $APP_DIR storage/ 2>/dev/null || warning "Failed to backup storage directory"
    log "✅ Backup created: $BACKUP_FILE"
else
    warning "Storage directory not found, skipping backup"
fi

# Stop services
log "⏹️ Stopping services..."
sudo supervisorctl stop all 2>/dev/null || warning "Failed to stop supervisor services"
sudo systemctl stop nginx 2>/dev/null || warning "Failed to stop nginx"

# Update application code
log "📥 Updating application code..."
cd $APP_DIR

# Pull latest code (if using git)
if [ -d ".git" ]; then
    git fetch origin
    git reset --hard origin/main
    log "✅ Code updated from git repository"
else
    warning "Not a git repository, manual code update required"
fi

# Install/update dependencies
log "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction || error "Failed to install dependencies"

# Set permissions
log "🔐 Setting permissions..."
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR
sudo chmod -R 777 $APP_DIR/storage
sudo chmod 600 $APP_DIR/.env

# Run database migrations
log "🗄️ Running database migrations..."
php worker migrate:run || error "Database migration failed"

# Clear caches
log "🧹 Clearing caches..."
if [ -d "$APP_DIR/storage/cache" ]; then
    rm -rf $APP_DIR/storage/cache/*
fi

# Test configuration
log "🧪 Testing configuration..."
php worker queue:test --jobs=0 || error "Configuration test failed"

# Start services
log "▶️ Starting services..."
sudo systemctl start nginx || error "Failed to start nginx"
sudo supervisorctl start all || error "Failed to start supervisor services"

# Wait for services to be ready
log "⏳ Waiting for services to be ready..."
sleep 10

# Health check
log "🏥 Running health check..."
if curl -f http://localhost/api.php?action=stats > /dev/null 2>&1; then
    log "✅ Health check passed"
else
    error "Health check failed"
fi

# Cleanup old backups (keep last 7 days)
log "🧹 Cleaning up old backups..."
find $BACKUP_DIR -name "backup_*.tar.gz" -mtime +7 -delete 2>/dev/null || warning "Failed to cleanup old backups"

# Display deployment summary
log "📊 Deployment Summary:"
log "   Environment: $ENVIRONMENT"
log "   Application: $APP_DIR"
log "   Backup: $BACKUP_FILE"
log "   Services: Running"

# Check queue status
log "📈 Queue Status:"
php worker queue:test --jobs=0 | tee -a $LOG_FILE

log "🎉 Deployment completed successfully!"

# Optional: Send notification
if command -v mail >/dev/null 2>&1; then
    echo "Task Queue deployment completed successfully at $(date)" | mail -s "Deployment Success" admin@yourdomain.com 2>/dev/null || true
fi
