<?php 
session_start();

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check admin login...
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Smart Reports";
$basePath = "../";
include '../_header.php';

// Default to daily
$view = isset($_GET['view']) ? $_GET['view'] : 'daily';

// Run Python Script with argument
$command = "python ../ai/generate_report.py " . escapeshellarg($view);
$output = shell_exec($command);
$report = json_decode($output, true);

// Handle cases where Python might fail
if (!$report) {
    $report = [
        "revenue" => 0, "previous_revenue" => 0, 
        "orders" => 0, "summary" => "Error generating report.", 
        "sentiment" => "neutral", "top_item" => "N/A"
    ];
}
?>

<link rel="stylesheet" href="../css/ai_reports.css">

<main class="main-wrapper">
    <div class="admin-container" data-view="<?php echo ucfirst($view); ?>">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="report-header-print">
            <img src="../image/logo.png" alt="Logo" class="print-logo">
            <h1 class="report-title-print">YOBITA RESTAURANT</h1>
            <h2 class="report-subtitle-print">AI Sales Report - <?php echo ucfirst($view); ?> View</h2>
        </div>

        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Smart Sales Report</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <div class="ai-status-badge">
                    <span class="pulse-dot"></span> AI Analyst Ready
                </div>
                <div class="report-actions">
                    <button onclick="printReport()" class="action-btn print-btn">
                        <ion-icon name="print-outline"></ion-icon> Print
                    </button>
                </div>
            </div>
        </div>

        <div class="controls">
            <a href="?view=daily" class="filter-btn <?php echo $view == 'daily' ? 'active' : ''; ?>">Daily View</a>
            <a href="?view=weekly" class="filter-btn <?php echo $view == 'weekly' ? 'active' : ''; ?>">Weekly View</a>
        </div>

        <div id="report-content" class="report-content" data-date="<?php echo date('F j, Y g:i A'); ?>">
        
            <div class="report-card">
                <h2 style="margin-top:0;">
                    <?php echo ucfirst($view); ?> Performance Summary
                </h2>
                
                <div class="stats-grid">
                    <div style="background:#f1f3f4; padding:15px; border-radius:10px; text-align:center;">
                        <small style="text-transform:uppercase; color:#777;">Total Revenue</small>
                        <div style="font-size:1.8rem; font-weight:bold; color:#333;">
                            RM <?php echo number_format($report['revenue'], 2); ?>
                        </div>
                    </div>
                    <div style="background:#f1f3f4; padding:15px; border-radius:10px; text-align:center;">
                        <small style="text-transform:uppercase; color:#777;">Total Orders</small>
                        <div style="font-size:1.8rem; font-weight:bold; color:#333;">
                            <?php echo $report['orders']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="chart-section">
                    <h3>Performance Comparison</h3>
                    <div class="chart-container">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>

                <h3 style="margin-bottom:10px; margin-top:20px;">🤖 AI Analysis:</h3>
                <div class="ai-summary-box sentiment-<?php echo $report['sentiment']; ?>">
                    <?php echo nl2br(htmlspecialchars($report['summary'])); ?>
                </div>
            </div>
            
            <div class="report-footer-print">
                <p>Report Generated: <?php echo date('F j, Y g:i A'); ?></p>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<script>
    // 1. Setup Chart Data
    const viewType = "<?php echo $view; ?>";
    const currentRevenue = <?php echo $report['revenue']; ?>;
    const previousRevenue = <?php echo $report['previous_revenue']; ?>;

    let labels = [];
    if (viewType === 'daily') {
        labels = ['Yesterday', 'Today'];
    } else {
        labels = ['Last Week', 'This Week'];
    }

    // 2. Render Chart
    const ctx = document.getElementById('comparisonChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (RM)',
                data: [previousRevenue, currentRevenue],
                backgroundColor: [
                    '#e2e6ea', // Grey for previous
                    '#4e73df'  // Blue for current
                ],
                borderColor: [
                    '#ccc',
                    '#224abe'
                ],
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f0f0', borderDash: [5, 5] },
                    ticks: { callback: function(value) { return 'RM' + value; } }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // 3. Print Function
    function printReport() {
        window.print();
    }
</script>