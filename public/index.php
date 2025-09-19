<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Support\Encryption;
use PDO;

// Setup database connection
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/queue.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup encryption
$encryption = new Encryption('demo-encryption-key-32-characters');

// Create queue manager
$driver = new DatabaseQueueDriver($pdo, $encryption);
$manager = new QueueManager($driver, new \Monolog\Logger('dashboard'));
$manager->connect();

// Redirect API requests to api.php
if (isset($_GET['action']) || isset($_POST['action'])) {
    include 'api.php';
    exit;
}

// Get initial data
$stats = $manager->getQueueStats();
$failedJobs = $manager->getFailedJobs();
$recentJobs = [];

$states = ['pending', 'processing', 'completed', 'failed'];
foreach ($states as $state) {
    $jobs = $manager->getJobsByState($state, null, 20);
    foreach ($jobs as $job) {
        $recentJobs[] = [
            'id' => $job->getId(),
            'state' => $job->getState(),
            'queue' => $job->getQueue(),
            'priority' => $job->getPriority(),
            'created_at' => $job->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $job->getUpdatedAt()->format('Y-m-d H:i:s'),
            'attempts' => $job->getAttempts(),
            'tags' => $job->getTags(),
        ];
    }
}

usort($recentJobs, function($a, $b) {
    return strtotime($b['updated_at']) - strtotime($a['updated_at']);
});

$manager->disconnect();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Queue Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-card.pending .value { color: #f39c12; }
        .stat-card.processing .value { color: #3498db; }
        .stat-card.completed .value { color: #27ae60; }
        .stat-card.failed .value { color: #e74c3c; }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .recent-jobs {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .recent-jobs h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .job-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .job-item:hover {
            background: #f8f9fa;
        }
        
        .job-item:last-child {
            border-bottom: none;
        }
        
        .job-info {
            flex: 1;
        }
        
        .job-id {
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
        }
        
        .job-queue {
            font-size: 0.9rem;
            color: #888;
            margin-top: 0.25rem;
        }
        
        .job-state {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .job-state.pending { background: #fef3cd; color: #856404; }
        .job-state.processing { background: #cce5ff; color: #004085; }
        .job-state.completed { background: #d4edda; color: #155724; }
        .job-state.failed { background: #f8d7da; color: #721c24; }
        
        .controls {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .controls h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn.danger {
            background: #e74c3c;
        }
        
        .btn.danger:hover {
            background: #c0392b;
        }
        
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .refresh-indicator.show {
            opacity: 1;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 Task Queue Dashboard</h1>
        <p>Real-time monitoring and management</p>
    </div>
    
    <div class="refresh-indicator" id="refreshIndicator">Data refreshed</div>
    
    <div class="container">
        <div class="controls">
            <h3>Quick Actions</h3>
            <button class="btn" onclick="refreshData()">🔄 Refresh Data</button>
            <button class="btn" onclick="createTestJobs()">➕ Create Test Jobs</button>
            <button class="btn danger" onclick="purgeQueue('default')">🗑️ Purge Default Queue</button>
            <button class="btn danger" onclick="purgeQueue('high-priority')">🗑️ Purge High Priority</button>
        </div>
        
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card pending">
                <h3>Pending Jobs</h3>
                <div class="value" id="pendingCount">-</div>
            </div>
            <div class="stat-card processing">
                <h3>Processing Jobs</h3>
                <div class="value" id="processingCount">-</div>
            </div>
            <div class="stat-card completed">
                <h3>Completed Jobs</h3>
                <div class="value" id="completedCount">-</div>
            </div>
            <div class="stat-card failed">
                <h3>Failed Jobs</h3>
                <div class="value" id="failedCount">-</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="chart-container">
                <h3>Queue Status Distribution</h3>
                <canvas id="queueChart" width="400" height="200"></canvas>
            </div>
            
            <div class="recent-jobs">
                <h3>Recent Jobs</h3>
                <div id="recentJobsList">
                    <div class="loading">Loading recent jobs...</div>
                </div>
            </div>
        </div>
        
        <div class="chart-container" style="margin-top: 2rem;">
            <h3>Queue Performance Over Time</h3>
            <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <script>
        let queueChart, performanceChart;
        let performanceData = [];
        
        // Initialize charts
        function initCharts() {
            // Queue status chart
            const ctx1 = document.getElementById('queueChart').getContext('2d');
            queueChart = new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Processing', 'Completed', 'Failed'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#f39c12', '#3498db', '#27ae60', '#e74c3c'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Performance chart
            const ctx2 = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Total Jobs',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Pending',
                        data: [],
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Processing',
                        data: [],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Fetch data from API
        async function fetchData() {
            try {
                const [statsResponse, recentResponse, performanceResponse] = await Promise.all([
                    fetch('api.php?action=stats'),
                    fetch('api.php?action=recent&limit=20'),
                    fetch('api.php?action=performance')
                ]);
                
                // Check if responses are ok
                if (!statsResponse.ok) {
                    throw new Error(`Stats API error: ${statsResponse.status}`);
                }
                if (!recentResponse.ok) {
                    throw new Error(`Recent jobs API error: ${recentResponse.status}`);
                }
                if (!performanceResponse.ok) {
                    throw new Error(`Performance API error: ${performanceResponse.status}`);
                }
                
                const stats = await statsResponse.json();
                const recentJobs = await recentResponse.json();
                const performance = await performanceResponse.json();
                
                updateStats(stats);
                updateRecentJobs(recentJobs);
                updatePerformanceData(performance);
                
                showRefreshIndicator();
            } catch (error) {
                console.error('Error fetching data:', error);
                document.getElementById('recentJobsList').innerHTML = 
                    `<div class="error">Error loading data: ${error.message}. Please refresh the page.</div>`;
            }
        }
        
        // Update statistics
        function updateStats(stats) {
            let totalPending = 0, totalProcessing = 0, totalCompleted = 0, totalFailed = 0;
            
            Object.values(stats).forEach(queueStats => {
                totalPending += queueStats.by_state.pending || 0;
                totalProcessing += queueStats.by_state.processing || 0;
                totalCompleted += queueStats.by_state.completed || 0;
                totalFailed += queueStats.by_state.failed || 0;
            });
            
            document.getElementById('pendingCount').textContent = totalPending;
            document.getElementById('processingCount').textContent = totalProcessing;
            document.getElementById('completedCount').textContent = totalCompleted;
            document.getElementById('failedCount').textContent = totalFailed;
            
            // Update chart
            queueChart.data.datasets[0].data = [totalPending, totalProcessing, totalCompleted, totalFailed];
            queueChart.update();
        }
        
        // Update recent jobs list
        function updateRecentJobs(jobs) {
            const container = document.getElementById('recentJobsList');
            
            if (jobs.length === 0) {
                container.innerHTML = '<div class="loading">No recent jobs found</div>';
                return;
            }
            
            container.innerHTML = jobs.map(job => `
                <div class="job-item">
                    <div class="job-info">
                        <div class="job-id">${job.id}</div>
                        <div class="job-queue">Queue: ${job.queue} | Priority: ${job.priority} | Attempts: ${job.attempts}</div>
                    </div>
                    <div class="job-state ${job.state}">${job.state}</div>
                </div>
            `).join('');
        }
        
        // Update performance chart
        function updatePerformanceData(performance) {
            const now = new Date().toLocaleTimeString();
            
            performanceData.push({
                time: now,
                total: performance.total_jobs,
                pending: performance.pending,
                processing: performance.processing
            });
            
            // Keep only last 20 data points
            if (performanceData.length > 20) {
                performanceData.shift();
            }
            
            performanceChart.data.labels = performanceData.map(d => d.time);
            performanceChart.data.datasets[0].data = performanceData.map(d => d.total);
            performanceChart.data.datasets[1].data = performanceData.map(d => d.pending);
            performanceChart.data.datasets[2].data = performanceData.map(d => d.processing);
            performanceChart.update();
        }
        
        // Show refresh indicator
        function showRefreshIndicator() {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.add('show');
            setTimeout(() => indicator.classList.remove('show'), 2000);
        }
        
        // Refresh data
        function refreshData() {
            fetchData();
        }
        
        // Create test jobs
        async function createTestJobs() {
            try {
                const response = await fetch('api.php?action=create_test_jobs', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'count=10&queue=default&priority=5'
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(`Created ${result.count} test jobs successfully!`);
                    refreshData();
                } else {
                    alert('Error creating test jobs');
                }
            } catch (error) {
                alert('Error creating test jobs: ' + error.message);
            }
        }
        
        // Purge queue
        async function purgeQueue(queueName) {
            if (!confirm(`Are you sure you want to purge the "${queueName}" queue? This action cannot be undone.`)) {
                return;
            }
            
            try {
                const response = await fetch('api.php?action=purge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `queue=${encodeURIComponent(queueName)}`
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(`Queue "${queueName}" purged successfully!`);
                    refreshData();
                } else {
                    alert('Error purging queue');
                }
            } catch (error) {
                alert('Error purging queue: ' + error.message);
            }
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            fetchData();
            
            // Auto-refresh every 5 seconds
            setInterval(fetchData, 5000);
        });
    </script>
</body>
</html>
