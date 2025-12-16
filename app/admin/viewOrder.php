<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "View Orders";
$basePath = "../";
include '../_header.php';

// --- HANDLE FILTER ---
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// --- FETCH DATA ---
try {
    $sql = "SELECT 
                o.id,
                o.status,
                o.total_amount,
                o.created_at,
                dt.table_number
            FROM orders o
            JOIN dining_tables dt ON o.table_id = dt.id";
            
    $params = [];

    // Apply Date Filter if set
    if (!empty($filter_date)) {
        $sql .= " WHERE DATE(o.created_at) = ?";
        $params[] = $filter_date;
    }

    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    $orders = [];
    $message = "Error fetching orders: " . $e->getMessage();
    $message_type = "error";
}

$status_classes = [
    'pending' => 'status-pending',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled'
];
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/viewOrders.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>All Customer Orders</h1>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="orders-list-section">
            
            <div class="header-flex">
                <h2 class="section-title">Order History (<?php echo count($orders); ?>)</h2>
                
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="date-input">
                        <button type="submit" class="filter-btn">Filter</button>
                        <?php if(!empty($filter_date)): ?>
                            <a href="viewOrder.php" class="reset-link">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th>Date & Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px;">
                                    No orders found<?php echo !empty($filter_date) ? " for this date." : "."; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="Order ID">#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td data-label="Table"><?php echo htmlspecialchars($order['table_number']); ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge <?php echo $status_classes[$order['status']] ?? ''; ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Total Amount">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td data-label="Date & Time"><?php echo date("d M Y, h:i A", strtotime($order['created_at'])); ?></td>
                                    <td class="actions-cell" data-label="Actions">
                                        <a href="orderDetail.php?id=<?php echo $order['id']; ?>" class="action-btn view-btn">
                                            <ion-icon name="eye-outline"></ion-icon> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>