#!/bin/bash

# Simple Docker deployment script for Task Queue

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR:${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] WARNING:${NC} $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    error "Docker is not running. Please start Docker first."
fi

case "${1:-start}" in
    start)
        log "🚀 Starting Task Queue with Docker..."
        log "👥 Starting 5 worker containers..."
        docker-compose up -d
        log "✅ Services started successfully!"
        log "📊 Dashboard: http://localhost:8080"
        log "👥 Workers: 5 containers processing jobs"
        log "📋 Queue Status:"
        sleep 5
        curl -s http://localhost:8080/api.php?action=stats | jq . 2>/dev/null || echo "API starting up..."
        ;;
    
    stop)
        log "⏹️ Stopping Task Queue services..."
        docker-compose down
        log "✅ Services stopped successfully!"
        ;;
    
    restart)
        log "🔄 Restarting Task Queue services..."
        docker-compose restart
        log "✅ Services restarted successfully!"
        ;;
    
    logs)
        log "📋 Showing logs..."
        docker-compose logs -f
        ;;
    
    status)
        log "📊 Service Status:"
        docker-compose ps
        echo ""
        log "📈 Queue Status:"
        curl -s http://localhost:8080/api.php?action=stats | jq . 2>/dev/null || echo "API not available"
        ;;
    
    build)
        log "🔨 Building Docker images..."
        docker-compose build --no-cache
        log "✅ Images built successfully!"
        ;;
    
    test)
        log "🧪 Creating test jobs..."
        docker-compose exec app php bin/queue queue:test --jobs=5
        log "✅ Test jobs created!"
        ;;
    
    scale)
        workers=${2:-5}
        log "📈 Scaling workers to $workers..."
        docker-compose up -d --scale worker=$workers
        log "✅ Workers scaled to $workers!"
        log "📊 Dashboard: http://localhost:8080"
        ;;
    
    clean)
        log "🧹 Cleaning up Docker resources..."
        docker-compose down -v
        docker system prune -f
        log "✅ Cleanup completed!"
        ;;
    
    help|--help|-h)
        echo "Task Queue Docker Manager"
        echo ""
        echo "Usage: $0 [command]"
        echo ""
        echo "Commands:"
        echo "  start     Start services (default)"
        echo "  stop      Stop services"
        echo "  restart   Restart services"
        echo "  logs      Show logs"
        echo "  status    Show service status"
        echo "  build     Build Docker images"
        echo "  test      Create test jobs"
        echo "  scale N   Scale workers to N replicas"
        echo "  clean     Clean up Docker resources"
        echo "  help      Show this help"
        echo ""
        echo "Examples:"
        echo "  $0 start"
        echo "  $0 scale 5"
        echo "  $0 logs"
        ;;
    
    *)
        error "Unknown command: $1. Use '$0 help' for usage information."
        ;;
esac
