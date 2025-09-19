# 🐳 Simple Docker Setup

This is a minimal Docker configuration for the Task Queue system.

## Quick Start

### 1. Build and Run

```bash
# Build and start services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### 2. Access the Application

- **Dashboard**: <http://localhost:8080>
- **API**: <http://localhost:8080/api.php?action=stats>

### 3. Manage Services

```bash
# Stop services
docker-compose down

# Restart services
docker-compose restart

# Scale workers
docker-compose up -d --scale worker=3

# Rebuild after code changes
docker-compose build
docker-compose up -d
```

## Services

### App Service

- Runs the web dashboard
- Exposed on port 8080
- Handles API requests

### Worker Service

- Processes queue jobs
- Runs 2 workers by default
- Automatically restarts on failure

## Configuration

### Environment Variables

```bash
APP_ENV=production  # Application environment
```

### Volumes

- `./storage` → `/var/www/html/storage` - Persistent storage for queue data

## Development

### Making Changes

1. Edit your code
2. Rebuild: `docker-compose build`
3. Restart: `docker-compose up -d`

### Testing

```bash
# Create test jobs
docker-compose exec app php bin/queue queue:test --jobs=5

# Check queue status
docker-compose exec app php bin/queue queue:test --jobs=0
```

## Troubleshooting

### Common Issues

```bash
# Check logs
docker-compose logs app
docker-compose logs worker

# Enter container
docker-compose exec app bash

# Check queue status
curl http://localhost:8080/api.php?action=stats
```

### Reset Everything

```bash
# Stop and remove everything
docker-compose down -v

# Remove images
docker-compose down --rmi all

# Start fresh
docker-compose up -d
```
