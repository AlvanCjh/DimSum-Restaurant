<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once '../connection.php';

// Security: Only chef role can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Kitchen Display System";
$basePath = "../";
include '../_header.php';

// Fetch pending orders with their items (only items that haven't been prepared)
try {
    // First, get all pending orders
    $sql = "SELECT 
                o.id as order_id,
                o.created_at,
                dt.table_number,
                dt.id as table_id
            FROM orders o
            JOIN dining_tables dt ON o.table_id = dt.id
            WHERE o.status = 'pending'
            ORDER BY o.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll();
    
    // Get detailed items for each order (only unprepared items)
    $orders_with_items = [];
    foreach ($orders as $order) {
        $sql_items = "SELECT 
                        oi.id,
                        oi.quantity,
                        mi.name as item_name,
                        oi.prepared_at
                      FROM order_items oi
                      JOIN menu_items mi ON oi.menu_item_id = mi.id
                      WHERE oi.order_id = ? AND oi.prepared_at IS NULL
                      ORDER BY oi.id ASC";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$order['order_id']]);
        $order['items'] = $stmt_items->fetchAll();
        // Only add order if it has items to prepare
        if (!empty($order['items'])) {
            $orders_with_items[] = $order;
        }
    }
    
} catch (PDOException $e) {
    $orders_with_items = [];
    $error_message = "Error loading orders: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/chef_dashboard.css">

<main class="main-wrapper">
    <div class="kds-container">
        <div class="kds-header">
            <h1>🍳 Kitchen Display System</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            <div class="auto-refresh-indicator">
                <span class="refresh-icon">🔄</span>
                <span>Auto-refreshing every 20 seconds</span>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                Order marked as prepared successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                Error processing request. Please try again.
            </div>
        <?php endif; ?>

        <?php if (empty($orders_with_items)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">✅</div>
                <h2>All Clear!</h2>
                <p>No pending orders at the moment.</p>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders_with_items as $order): 
                    // Calculate time elapsed
                    $created_time = new DateTime($order['created_at']);
                    $now = new DateTime();
                    $interval = $now->diff($created_time);
                    $time_elapsed = '';
                    
                    if ($interval->h > 0) {
                        $time_elapsed = $interval->h . 'h ' . $interval->i . 'm';
                    } else {
                        $time_elapsed = $interval->i . 'm';
                    }
                    if ($interval->i == 0 && $interval->s > 0) {
                        $time_elapsed = 'Just now';
                    }
                ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div class="table-number-large">Table <?php echo htmlspecialchars($order['table_number']); ?></div>
                            
                        </div>
                        
                        <div class="order-items-list">
                            <h3>Items to Prepare:</h3>
                            <ul>
                                <?php foreach ($order['items'] as $item): ?>
                                    <li>
                                        <span class="item-quantity"><?php echo $item['quantity']; ?>x</span>
                                        <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <form method="POST" action="mark_prepared.php" class="mark-prepared-form">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <button type="submit" class="mark-prepared-btn">
                                ✓ Mark Prepared
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Auto-refresh every 20 seconds with cache-busting
setTimeout(function() {
    window.location.href = window.location.pathname + '?t=' + new Date().getTime();
}, 20000);
</script>