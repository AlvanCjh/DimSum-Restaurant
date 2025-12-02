<?php
session_start();
require_once '../connection.php';

// Security: Only waiter role can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "Active Orders";
$basePath = "../";
include '../_header.php';

// Fetch active orders (pending or prepared status)
try {
    $sql = "SELECT 
                o.id as order_id,
                o.status,
                o.total_amount,
                o.created_at,
                dt.table_number,
                dt.id as table_id,
                u.username as waiter_name,
                COUNT(oi.id) as item_count
            FROM orders o
            JOIN dining_tables dt ON o.table_id = dt.id
            LEFT JOIN staffs u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status IN ('pending', 'prepared')
            GROUP BY o.id, o.status, o.total_amount, o.created_at, dt.table_number, dt.id, u.username
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $active_orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $active_orders = [];
    $error_message = "Error loading orders: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/active_orders.css">

<main class="main-wrapper">
    <div class="active-orders-container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <div>
                <h1>📋 Active Orders</h1>
                <div class="auto-refresh-indicator">
                    <span class="refresh-icon">🔄</span>
                    <span>Auto-refreshing every 20 seconds</span>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($active_orders)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">📭</div>
                <h2>No Active Orders</h2>
                <p>There are no pending or prepared orders at the moment.</p>
            </div>
        <?php else: ?>
            <div class="orders-table-wrapper">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Created At</th>
                            <th>waiter</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_orders as $order): 
                            $status_class = '';
                            $status_text = '';
                            switch($order['status']) {
                                case 'pending':
                                    $status_class = 'status-pending';
                                    $status_text = 'Pending';
                                    break;
                                case 'prepared':
                                    $status_class = 'status-prepared';
                                    $status_text = 'Prepared';
                                    break;
                                default:
                                    $status_class = 'status-other';
                                    $status_text = ucfirst($order['status']);
                            }
                        ?>
                            <tr>
                                <td data-label="Order ID">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td data-label="Table"><?php echo htmlspecialchars($order['table_number']); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td data-label="Items"><?php echo $order['item_count']; ?> items</td>
                                <td data-label="Total">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td data-label="Created">
                                    <?php echo date("d M Y, h:i A", strtotime($order['created_at'])); ?>
                                </td>
                                <td data-label="waiter"><?php echo htmlspecialchars($order['waiter_name'] ?? 'N/A'); ?></td>
                                <td data-label="Actions" class="actions-cell">
                                    <?php if ($order['status'] === 'prepared'): ?>
                                        <a href="../order/payment.php?order_id=<?php echo $order['order_id']; ?>" 
                                           class="action-btn process-btn">
                                            <ion-icon name="card-outline"></ion-icon> Process Payment
                                        </a>
                                    <?php else: ?>
                                        <span class="action-info">Waiting for kitchen...</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Auto-refresh every 20 seconds
setTimeout(function() {
    location.reload();
}, 20000);
</script>

