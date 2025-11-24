<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Admin Dashboard";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="/css/dashboard.css">

<main class="main-wrapper">
    <div class="admin-dashboard">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-icon">🍽️</div>
                <h2 class="card-title">Manage Menu</h2>
                <p class="card-description">Add, edit, or remove menu items and categories</p>
                <a href="addMenu.php" class="card-button">Manage Menu</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">📊</div>
                <h2 class="card-title">View Statistics</h2>
                <p class="card-description">Order History and Statistics</p>
                <a href="viewOrder.php" class="card-button">View Statistics</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🪑</div>
                <h2 class="card-title">Manage Tables</h2>
                <p class="card-description">View and manage dining table</p>
                <a href="manageTables.php" class="card-button">Manage Tables</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">👥</div>
                <h2 class="card-title">Manage Staff</h2>
                <p class="card-description">View staff profiles and performance stats</p>
                <a href="manageStaff.php" class="card-button">View Staff</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🤖</div>
                <h2 class="card-title">AI Insights</h2>
                <p class="card-description">Market analysis & revenue forecasting</p>
                <a href="ai_forecast.php" class="card-button">Open AI Hub</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🤖</div>
                <h2 class="card-title">AI Reports Generation</h2>
                <p class="card-description">Automate AI Report Generation</p>
                <a href="ai_reports.php" class="card-button">Open AI Reports</a>
            </div>
        </div>

        <?php
        // Get statistics
        try {
            $stats = [];
            $stats['menu_items'] = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
            $stats['categories'] = $pdo->query("SELECT COUNT(*) FROM menu_categories")->fetchColumn();
            $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $stats['tables'] = $pdo->query("SELECT COUNT(*) FROM dining_tables")->fetchColumn();
        } catch (PDOException $e) {
            $stats = ['menu_items' => 0, 'categories' => 0, 'orders' => 0, 'tables' => 0];
        }
        ?>

        <div class="stats-section">
            <h2 style="color: #fff; margin-bottom: 20px; text-align: center;">Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['menu_items']; ?></div>
                    <div class="stat-label">Menu Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['categories']; ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['tables']; ?></div>
                    <div class="stat-label">Dining Tables</div>
                </div>
            </div>
        </div>
    </div>
</main>