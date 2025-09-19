# 🚀 Task Queue Dashboard

A beautiful, real-time web dashboard for monitoring and managing the Task Queue system.

## 🎯 Features

### 📊 **Real-time Monitoring**

- **Live Statistics**: Pending, processing, completed, and failed job counts
- **Queue Status**: Visual charts showing job distribution
- **Performance Metrics**: Real-time throughput and processing rates
- **Auto-refresh**: Updates every 5 seconds automatically

### 🎨 **Beautiful UI**

- **Modern Design**: Clean, responsive interface
- **Interactive Charts**: Doughnut charts for queue status
- **Real-time Updates**: Live data without page refresh
- **Mobile Friendly**: Responsive design for all devices

### 🔧 **Management Tools**

- **Quick Actions**: Create test jobs, purge queues
- **Job Details**: View detailed information about jobs
- **Retry Failed Jobs**: One-click retry for failed jobs
- **Queue Management**: Purge specific queues

### 📈 **Analytics**

- **Performance Tracking**: Historical performance data
- **Success/Failure Rates**: Visual metrics
- **Queue Analysis**: Per-queue statistics
- **Trend Monitoring**: Performance over time

## 🚀 Quick Start

### 1. Start the Dashboard Server

```bash
# Using CLI command (recommended)
php worker dashboard:serve

# Or using the standalone server
php server

# Custom host and port
php worker dashboard:serve --host=0.0.0.0 --port=9000
```

### 2. Access the Dashboard

Open your browser and navigate to:

- **Local**: <http://127.0.0.1:8080>
- **Network**: <http://your-server-ip:8080>

### 3. Create Test Data

```bash
# Create some test jobs to see in the dashboard
php worker queue:test --jobs=50

# Start workers to process jobs
php worker queue:work --workers=4
```

## 📱 Dashboard Sections

### 🏠 **Overview Cards**

- **Pending Jobs**: Jobs waiting to be processed
- **Processing Jobs**: Currently running jobs
- **Completed Jobs**: Successfully finished jobs
- **Failed Jobs**: Jobs that failed processing

### 📊 **Queue Status Chart**

- Visual representation of job distribution
- Color-coded by job state
- Real-time updates

### 📋 **Recent Jobs List**

- Latest 20 jobs across all states
- Job ID, queue, priority, and status
- Click for detailed job information

### 📈 **Performance Chart**

- Historical performance data
- Total jobs, pending, and processing trends
- Time-series visualization

### 🎛️ **Control Panel**

- **Refresh Data**: Manual data refresh
- **Create Test Jobs**: Generate sample jobs
- **Purge Queues**: Clear specific queues
- **Retry Failed Jobs**: Retry failed job processing

## 🔌 API Endpoints

The dashboard includes a REST API for programmatic access:

### **GET** `/api.php?action=stats`

Get queue statistics

### **GET** `/api.php?action=recent&limit=50`

Get recent jobs

### **GET** `/api.php?action=failed`

Get failed jobs

### **GET** `/api.php?action=performance`

Get performance metrics

### **POST** `/api.php?action=create_test_jobs`

Create test jobs

```json
{
  "count": 10,
  "queue": "default",
  "priority": 5
}
```

### **POST** `/api.php?action=purge`

Purge a queue

```json
{
  "queue": "default"
}
```

### **POST** `/api.php?action=retry`

Retry a failed job

```json
{
  "job_id": "job_12345"
}
```

## 🎨 Customization

### **Styling**

The dashboard uses modern CSS with:

- **CSS Grid** for responsive layouts
- **Flexbox** for component alignment
- **CSS Variables** for consistent theming
- **Smooth Animations** for better UX

### **Charts**

Powered by **Chart.js** with:

- **Doughnut Charts** for status distribution
- **Line Charts** for performance trends
- **Responsive Design** for all screen sizes
- **Real-time Updates** without page refresh

### **JavaScript**

Modern ES6+ features:

- **Async/Await** for API calls
- **Fetch API** for HTTP requests
- **Chart.js** for data visualization
- **Auto-refresh** with setInterval

## 🔧 Configuration

### **Server Settings**

```bash
# Custom host and port
php worker dashboard:serve --host=0.0.0.0 --port=9000

# Production deployment
php worker dashboard:serve --host=127.0.0.1 --port=80
```

### **Database Connection**

The dashboard automatically connects to the same database as your queue system:

- **SQLite**: `storage/queue.db`
- **MySQL/PostgreSQL**: Configure in your environment

### **Security**

For production use, consider:

- **Authentication**: Add login system
- **HTTPS**: Use SSL certificates
- **Firewall**: Restrict access to dashboard port
- **Rate Limiting**: Prevent API abuse

## 📊 Performance

### **Optimizations**

- **Efficient Queries**: Optimized database queries
- **Caching**: Chart data caching
- **Compression**: Gzip compression for assets
- **CDN Ready**: Static assets can be served via CDN

### **Scalability**

- **Horizontal Scaling**: Multiple dashboard instances
- **Load Balancing**: Distribute dashboard traffic
- **Database Optimization**: Proper indexing
- **Memory Management**: Efficient data handling

## 🚀 Production Deployment

### **Using Nginx**

```nginx
server {
    listen 80;
    server_name dashboard.yourdomain.com;
    
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### **Using Apache**

```apache
<VirtualHost *:80>
    ServerName dashboard.yourdomain.com
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
</VirtualHost>
```

### **Using the Built-in Server**

```bash
# Start the dashboard server using the built-in PHP server
php worker dashboard:serve --port=8080 --host=0.0.0.0
```

## 🎯 Use Cases

### **Development**

- Monitor job processing during development
- Debug failed jobs
- Test queue performance

### **Production**

- Real-time system monitoring
- Performance analytics
- Operational dashboards

### **Operations**

- Queue management
- Performance optimization
- Capacity planning

## 🔍 Troubleshooting

### **Common Issues**

1. **Dashboard not loading**
   - Check if server is running: `ps aux | grep php`
   - Verify port is not in use: `netstat -tlnp | grep 8080`
   - Check firewall settings

2. **No data showing**
   - Ensure jobs exist: `php worker queue:test`
   - Check database connection
   - Verify file permissions

3. **Charts not updating**
   - Check browser console for errors
   - Verify API endpoints are accessible
   - Check network connectivity

### **Debug Mode**

Enable debug logging by modifying the dashboard logger configuration.

## 📚 Integration

### **External Monitoring**

- **Prometheus**: Export metrics via API
- **Grafana**: Use API as data source
- **New Relic**: Monitor dashboard performance
- **DataDog**: Custom dashboard integration

### **Webhooks**

Extend the API to send webhooks for:

- Job failures
- Queue overflow
- Performance alerts
- System events

---

**🎉 Enjoy your beautiful Task Queue Dashboard!**

For more information, visit the main [README.md](README.md) or check the [API documentation](public/api.php).
