<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TaskQueue\QueueManager;
use TaskQueue\Drivers\DatabaseQueueDriver;
use TaskQueue\Support\Encryption;

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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white py-8 text-center">
        <h1 class="text-4xl font-bold mb-2">🚀 Task Queue Dashboard</h1>
        <p class="text-lg opacity-90">Real-time monitoring and management</p>
    </div>
    
    <div class="fixed top-5 right-5 bg-green-500 text-white px-4 py-2 rounded-full text-sm opacity-0 transition-opacity duration-300" id="refreshIndicator">Data refreshed</div>
    
    <div class="max-w-7xl mx-auto px-8 py-8">
        <div class="bg-white rounded-xl p-6 shadow-lg mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap gap-2">
                <button class="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200" onclick="refreshData()">🔄 Refresh Data</button>
                <button class="bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200" onclick="createTestJobs()">➕ Create Test Jobs</button>
                <button class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200" onclick="purgeQueue('default')">🗑️ Purge Default Queue</button>
                <button class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg text-sm font-medium transition-colors duration-200" onclick="purgeQueue('high-priority')">🗑️ Purge High Priority</button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="statsGrid">
            <div class="bg-white rounded-xl p-6 shadow-lg hover:-translate-y-1 transition-transform duration-200">
                <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide mb-2">Pending Jobs</h3>
                <div class="text-4xl font-bold text-amber-500" id="pendingCount">-</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg hover:-translate-y-1 transition-transform duration-200">
                <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide mb-2">Processing Jobs</h3>
                <div class="text-4xl font-bold text-blue-500" id="processingCount">-</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg hover:-translate-y-1 transition-transform duration-200">
                <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide mb-2">Completed Jobs</h3>
                <div class="text-4xl font-bold text-green-500" id="completedCount">-</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-lg hover:-translate-y-1 transition-transform duration-200">
                <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide mb-2">Failed Jobs</h3>
                <div class="text-4xl font-bold text-red-500" id="failedCount">-</div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <div class="lg:col-span-2 bg-white rounded-xl p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Queue Status Distribution</h3>
                <canvas id="queueChart" width="400" height="200"></canvas>
            </div>
            
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Jobs</h3>
                <div id="recentJobsList">
                    <div class="text-center py-8 text-gray-500">Loading recent jobs...</div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl p-6 shadow-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Queue Performance Over Time</h3>
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
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
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
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Pending',
                        data: [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Processing',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
                    `<div class="bg-red-50 text-red-700 p-4 rounded-lg">Error loading data: ${error.message}. Please refresh the page.</div>`;
            }
        }
        
        // Update statistics
        function updateStats(stats) {
            let totalPending = 0, totalProcessing = 0, totalCompleted = 0, totalFailed = 0;
            
            if (stats && typeof stats === 'object') {
                Object.values(stats).forEach(queueStats => {
                    if (queueStats && queueStats.by_state) {
                        totalPending += queueStats.by_state.pending || 0;
                        totalProcessing += queueStats.by_state.processing || 0;
                        totalCompleted += queueStats.by_state.completed || 0;
                        totalFailed += queueStats.by_state.failed || 0;
                    }
                });
            }
            
            document.getElementById('pendingCount').textContent = totalPending;
            document.getElementById('processingCount').textContent = totalProcessing;
            document.getElementById('completedCount').textContent = totalCompleted;
            document.getElementById('failedCount').textContent = totalFailed;
            
            // Update chart
            if (queueChart) {
                queueChart.data.datasets[0].data = [totalPending, totalProcessing, totalCompleted, totalFailed];
                queueChart.update();
            }
        }
        
        // Update recent jobs list
        function updateRecentJobs(jobs) {
            const container = document.getElementById('recentJobsList');
            
            if (!Array.isArray(jobs)) {
                container.innerHTML = '<div class="bg-red-50 text-red-700 p-4 rounded-lg">Invalid jobs data received</div>';
                return;
            }
            
            if (jobs.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No recent jobs found</div>';
                return;
            }
            
            container.innerHTML = jobs.map(job => {
                if (!job || typeof job !== 'object') {
                    return '<div class="bg-red-50 text-red-700 p-4 rounded-lg mb-2">Invalid job data</div>';
                }
                
                const stateColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'processing': 'bg-blue-100 text-blue-800',
                    'completed': 'bg-green-100 text-green-800',
                    'failed': 'bg-red-100 text-red-800'
                };
                
                const stateClass = stateColors[job.state] || 'bg-gray-100 text-gray-800';
                
                return `
                    <div class="flex justify-between items-center p-3 border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200 last:border-b-0">
                        <div class="flex-1">
                            <div class="font-mono text-sm text-gray-600">${job.id || 'Unknown'}</div>
                            <div class="text-sm text-gray-500 mt-1">Queue: ${job.queue || 'Unknown'} | Priority: ${job.priority || 0} | Attempts: ${job.attempts || 0}</div>
                        </div>
                        <div class="px-3 py-1 rounded-full text-xs font-bold uppercase ${stateClass}">${job.state || 'unknown'}</div>
                    </div>
                `;
            }).join('');
        }
        
        // Update performance chart
        function updatePerformanceData(performance) {
            const now = new Date().toLocaleTimeString();
            
            performanceData.push({
                time: now,
                total: performance.total_jobs || performance.total || 0,
                pending: performance.pending || 0,
                processing: performance.processing || 0
            });
            
            // Keep only last 20 data points
            if (performanceData.length > 20) {
                performanceData.shift();
            }
            
            if (performanceChart) {
                performanceChart.data.labels = performanceData.map(d => d.time);
                performanceChart.data.datasets[0].data = performanceData.map(d => d.total);
                performanceChart.data.datasets[1].data = performanceData.map(d => d.pending);
                performanceChart.data.datasets[2].data = performanceData.map(d => d.processing);
                performanceChart.update();
            }
        }
        
        // Show refresh indicator
        function showRefreshIndicator() {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.remove('opacity-0');
            indicator.classList.add('opacity-100');
            setTimeout(() => {
                indicator.classList.remove('opacity-100');
                indicator.classList.add('opacity-0');
            }, 2000);
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
