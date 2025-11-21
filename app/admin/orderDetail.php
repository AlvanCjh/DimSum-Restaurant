<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: view_orders.php");
    exit;
}

$order_id = (int)$_GET['id'];

$pageTitle = "Order Details";
$basePath = "../";
include '../_header.php';

// --- FETCH DATA ---
try {
    // Fetch main order details
    $sql_order = "SELECT 
                    o.id, o.status, o.total_amount, o.created_at,
                    dt.table_number,
                    u.username as staff_name
                  FROM orders o
                  JOIN dining_tables dt ON o.table_id = dt.id
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE o.id = ?";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        die("Order not found.");
    }

    // Fetch order items
    $sql_items = "SELECT oi.quantity, oi.price, mi.name as item_name
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/viewOrders.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="viewOrder.php" class="back-link">← Back to All Orders</a>
                <div class="page-header">
            <h1>Order #<?php echo htmlspecialchars($order['id']); ?></h1>
        </div>

        <div class="order-details-grid">
            <!-- Order Summary -->
            <div class="order-summary-card">
                <h2 class="section-title">Summary</h2>
                <div class="summary-item">
                    <span>Table:</span>
                    <strong><?php echo htmlspecialchars($order['table_number']); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Status:</span>
                    <strong class="status-text-<?php echo $order['status']; ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Total Amount:</span>
                    <strong>RM <?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Order Placed:</span>
                    <strong><?php echo date("d M Y, h:i A", strtotime($order['created_at'])); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Handled By:</span>
                    <strong><?php echo htmlspecialchars($order['staff_name'] ?? 'N/A'); ?></strong>
                </div>
            </div>

            <!-- Order Items -->
            <div class="order-items-card">
                <h2 class="section-title">Items in this Order</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>RM <?php echo number_format($item['price'], 2); ?></td>
                                    <td>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>