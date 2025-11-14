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
                <h2 class="card-title">View Orders</h2>
                <p class="card-description">Monitor and manage customer orders</p>
                <a href="#" class="card-button">View Orders</a>
            </div>

            <div class="dashboard-card">
                <div class="card-icon">🪑</div>
                <h2 class="card-title">Manage Tables</h2>
                <p class="card-description">View and manage dining table status</p>
                <a href="#" class="card-button">Manage Tables</a>
            </div>
        </div>

        <?php
        // Get statistics
        try {
            $stats = [];
            
            // Count menu items
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM menu_items");
            $stats['menu_items'] = $stmt->fetch()['count'];
            
            // Count categories
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM menu_categories");
            $stats['categories'] = $stmt->fetch()['count'];
            
            // Count orders
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
            $stats['orders'] = $stmt->fetch()['count'];
            
            // Count tables
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM dining_tables");
            $stats['tables'] = $stmt->fetch()['count'];
        } catch (PDOException $e) {
            $stats = ['menu_items' => 0, 'categories' => 0, 'orders' => 0, 'tables' => 0];
        }
        ?>

        <div class="stats-section">
            <h2 style="color: var(--text-dark); margin-bottom: 20px; text-align: center;">Statistics</h2>
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



