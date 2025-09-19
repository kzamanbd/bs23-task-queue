#!/bin/bash

# Health Check Script for Task Queue System
# This script performs comprehensive health checks on the Task Queue system

set -e

# Configuration
API_URL="http://localhost:8080"
TIMEOUT=10
EXIT_CODE=0

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
    EXIT_CODE=1
}

log_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

log_info() {
    echo "ℹ️ $1"
}

# Check if service is responding
check_service() {
    local service_name=$1
    local url=$2
    
    log_info "Checking $service_name..."
    
    if curl -f -s --max-time $TIMEOUT "$url" > /dev/null; then
        log_success "$service_name is responding"
        return 0
    else
        log_error "$service_name is not responding"
        return 1
    fi
}

# Check API endpoints
check_api() {
    log_info "Checking API endpoints..."
    
    # Check stats endpoint
    if curl -f -s --max-time $TIMEOUT "$API_URL/api.php?action=stats" > /dev/null; then
        log_success "Stats API is working"
    else
        log_error "Stats API is not working"
    fi
    
    # Check recent jobs endpoint
    if curl -f -s --max-time $TIMEOUT "$API_URL/api.php?action=recent&limit=5" > /dev/null; then
        log_success "Recent jobs API is working"
    else
        log_error "Recent jobs API is not working"
    fi
    
    # Check performance endpoint
    if curl -f -s --max-time $TIMEOUT "$API_URL/api.php?action=performance" > /dev/null; then
        log_success "Performance API is working"
    else
        log_error "Performance API is not working"
    fi
}

# Check queue functionality
check_queue() {
    log_info "Checking queue functionality..."
    
    # Test queue operations
    if php worker queue:test --jobs=0 > /dev/null 2>&1; then
        log_success "Queue operations are working"
    else
        log_error "Queue operations failed"
    fi
}

# Check database connectivity
check_database() {
    log_info "Checking database connectivity..."
    
    # Test database connection through the application
    if php -r "
        try {
            \$pdo = new PDO('sqlite:storage/queue.db');
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo 'Database connection successful';
        } catch (Exception \$e) {
            echo 'Database connection failed: ' . \$e->getMessage();
            exit(1);
        }
    " > /dev/null 2>&1; then
        log_success "Database connection is working"
    else
        log_error "Database connection failed"
    fi
}

# Check worker processes
check_workers() {
    log_info "Checking worker processes..."
    
    # Check if workers are running (for systemd/supervisor)
    if pgrep -f "queue:work" > /dev/null; then
        log_success "Worker processes are running"
    else
        log_warning "No worker processes found"
    fi
}

# Check disk space
check_disk_space() {
    log_info "Checking disk space..."
    
    local usage=$(df -h . | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$usage" -lt 80 ]; then
        log_success "Disk space is adequate ($usage% used)"
    elif [ "$usage" -lt 90 ]; then
        log_warning "Disk space is getting low ($usage% used)"
    else
        log_error "Disk space is critically low ($usage% used)"
    fi
}

# Check memory usage
check_memory() {
    log_info "Checking memory usage..."
    
    local usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    if [ "$usage" -lt 80 ]; then
        log_success "Memory usage is normal ($usage% used)"
    elif [ "$usage" -lt 90 ]; then
        log_warning "Memory usage is high ($usage% used)"
    else
        log_error "Memory usage is critically high ($usage% used)"
    fi
}

# Check log files
check_logs() {
    log_info "Checking log files..."
    
    local log_dir="logs"
    if [ -d "$log_dir" ]; then
        local error_count=$(find "$log_dir" -name "*.log" -exec grep -l "ERROR\|FATAL" {} \; 2>/dev/null | wc -l)
        if [ "$error_count" -eq 0 ]; then
            log_success "No errors found in log files"
        else
            log_warning "$error_count log files contain errors"
        fi
    else
        log_info "Log directory not found"
    fi
}

# Get system metrics
get_metrics() {
    log_info "System Metrics:"
    
    # Queue statistics
    echo "📊 Queue Statistics:"
    if curl -s --max-time $TIMEOUT "$API_URL/api.php?action=stats" | jq . 2>/dev/null || echo "API not available"
    
    # System resources
    echo "💾 System Resources:"
    echo "  Memory: $(free -h | awk 'NR==2{print $3"/"$2}')"
    echo "  Disk: $(df -h . | awk 'NR==2{print $3"/"$2" ("$5")"}')"
    echo "  Load: $(uptime | awk -F'load average:' '{print $2}')"
    
    # Process count
    echo "🔄 Processes:"
    echo "  PHP processes: $(pgrep -c php || echo 0)"
    echo "  Worker processes: $(pgrep -c "queue:work" || echo 0)"
}

# Main health check
main() {
    echo "🏥 Task Queue System Health Check"
    echo "=================================="
    echo ""
    
    # Basic service checks
    check_service "Web Application" "$API_URL/health"
    check_api
    
    # System checks
    check_database
    check_queue
    check_workers
    check_disk_space
    check_memory
    check_logs
    
    echo ""
    get_metrics
    
    echo ""
    if [ $EXIT_CODE -eq 0 ]; then
        log_success "All health checks passed!"
    else
        log_error "Some health checks failed!"
    fi
    
    exit $EXIT_CODE
}

# Handle command line arguments
case "${1:-}" in
    --quick)
        # Quick health check - only check API
        check_service "API" "$API_URL/api.php?action=stats"
        exit $?
        ;;
    --metrics)
        # Show metrics only
        get_metrics
        exit 0
        ;;
    --help|-h)
        echo "Task Queue Health Check Script"
        echo ""
        echo "Usage: $0 [option]"
        echo ""
        echo "Options:"
        echo "  --quick    Quick check (API only)"
        echo "  --metrics  Show metrics only"
        echo "  --help     Show this help"
        echo ""
        echo "Exit codes:"
        echo "  0  All checks passed"
        echo "  1  One or more checks failed"
        ;;
    *)
        main
        ;;
esac
