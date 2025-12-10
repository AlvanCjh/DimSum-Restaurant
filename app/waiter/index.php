<?php 
session_start(); 
require_once '../connection.php';

// Redirect if not logged in as waiter
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../index.php");
    exit;
}

// --- Fetch Dashboard Stats ---
try {
    // Count available tables
    $stmt_available = $pdo->query("SELECT COUNT(*) FROM dining_tables WHERE status = 'available'");
    $available_tables = $stmt_available->fetchColumn();

    // Count occupied tables
    $stmt_occupied = $pdo->query("SELECT COUNT(*) FROM dining_tables WHERE status = 'occupied'");
    $occupied_tables = $stmt_occupied->fetchColumn();

    // Count reserved tables
    $stmt_reserved = $pdo->query("SELECT COUNT(*) FROM dining_tables WHERE status = 'reserved'");
    $reserved_tables = $stmt_reserved->fetchColumn();
} catch (PDOException $e) {
    // Set defaults on error
    $available_tables = $occupied_tables = $reserved_tables = 'N/A';
}

$pageTitle = "Dashboard";
$basePath = "../"; 
include '../_header.php'; 
?>

<!-- Custom styles to apply the background image -->
<style>
    body {
        /* Apply the background image and overlay */
        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo $basePath; ?>image/background.jpg') !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
    }
</style>

<?php
// Check if the session variable is set for the alert
if (isset($_SESSION['show_order_complete_alert'])) {
    echo "<script>alert('Order Complete!');</script>";
    // Unset the session variable so it doesn't show again on refresh
    unset($_SESSION['show_order_complete_alert']);
}
?>

<main class="main-wrapper">
    <div class="dashboard-header">
        <img src="<?php echo $basePath; ?>image/logo.png" alt="Yobita Logo" class="dashboard-logo">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>Here's a summary of the restaurant's activity right now.</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card available">
            <div class="stat-value"><?php echo $available_tables; ?></div>
            <div class="stat-label">Available Tables</div>
            <div class="stat-icon">🍽️</div>
        </div>
        <div class="stat-card occupied">
            <div class="stat-value"><?php echo $occupied_tables; ?></div>
            <div class="stat-label">Active Orders</div>
            <div class="stat-icon">🔥</div>
        </div>
        <div class="stat-card reserved">
            <div class="stat-value"><?php echo $reserved_tables; ?></div>
            <div class="stat-label">Reserved Tables</div>
            <div class="stat-icon">📅</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="../table/table.php" class="btn action-btn"><ion-icon name="add-circle-outline"></ion-icon> Order</a>
        <!-- <a href="../order/order.php" class="btn action-btn"><ion-icon name="receipt-outline"></ion-icon>Bill</a> -->
        <a href="active_orders.php" class="btn action-btn"><ion-icon name="list-outline"></ion-icon>Active Orders</a>
    </div>
</main>