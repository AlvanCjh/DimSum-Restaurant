<?php 
session_start();
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
?>

<link rel="stylesheet" href="../css/ai_forecast.css"> 
<link rel="stylesheet" href="/css/ai_reports.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Smart Sales Report</h1>
            <div class="ai-status-badge">
                <span class="pulse-dot"></span> AI Analyst Ready
            </div>
        </div>

        <div class="controls">
            <a href="?view=daily" class="filter-btn <?php echo $view == 'daily' ? 'active' : ''; ?>">Daily View</a>
            <a href="?view=weekly" class="filter-btn <?php echo $view == 'weekly' ? 'active' : ''; ?>">Weekly View</a>
        </div>

        <div class="report-card">
            <h2 style="margin-top:0;">
                <?php echo ucfirst($view); ?> Performance Summary
            </h2>
            
            <div class="stats-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
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

            <h3 style="margin-bottom:10px;">🤖 AI Analysis:</h3>
            <div class="ai-summary-box sentiment-<?php echo $report['sentiment']; ?>">
                <?php echo nl2br(htmlspecialchars($report['summary'])); ?>
                <?php 
                    $clean_summary = str_replace("**", "", $report['summary']); 
                    // Or actually parse it to <b> tags if you prefer
                ?>
            </div>
        </div>

    </div>
</main>