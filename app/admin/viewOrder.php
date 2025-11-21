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

// --- FETCH DATA ---
try {
    $sql = "SELECT 
                o.id,
                o.status,
                o.total_amount,
                o.created_at,
                dt.table_number
            FROM orders o
            JOIN dining_tables dt ON o.table_id = dt.id
            ORDER BY o.created_at DESC";
    $stmt = $pdo->query($sql);
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
            <h2 class="section-title">Order History (<?php echo count($orders); ?>)</h2>
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
                                <td colspan="6">No orders have been placed yet.</td>
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