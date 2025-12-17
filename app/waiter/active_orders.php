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

try {
    $sql = "SELECT 
                o.id as order_id,
                o.status,
                o.total_amount,
                o.created_at,
                dt.table_number,
                dt.id as table_id,
                u.username as waiter_name,
                
                -- 1. Grab the summary from our new LEFT JOIN below
                COALESCE(summary_data.item_summary, 'No items') as item_summary,

                -- 2. Simple item count (Sum of quantities)
                (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM order_items 
                    WHERE order_id = o.id
                ) as item_count

            FROM orders o
            JOIN dining_tables dt ON o.table_id = dt.id
            LEFT JOIN staffs u ON o.user_id = u.id

            -- FIX: We moved the complex stacking logic here.
            -- This creates a temporary list of stacked items per order, then joins it.
            LEFT JOIN (
                SELECT 
                    aggregated_items.order_id,
                    GROUP_CONCAT(CONCAT(aggregated_items.total_qty, 'x ', mi.name) SEPARATOR ', ') as item_summary
                FROM (
                    -- First: Group items by ID and Sum their Quantities
                    SELECT order_id, menu_item_id, SUM(quantity) as total_qty
                    FROM order_items
                    GROUP BY order_id, menu_item_id
                ) as aggregated_items
                JOIN menu_items mi ON aggregated_items.menu_item_id = mi.id
                GROUP BY aggregated_items.order_id
            ) as summary_data ON o.id = summary_data.order_id

            WHERE o.status IN ('pending', 'prepared')
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
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<main class="main-wrapper">
    <div class="active-orders-container">
        
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <div>
                <h1>📋 Active Orders</h1>
                <div class="auto-refresh-indicator">
                    <img src="../image/Loading.png" alt="Refresh" class="refresh-icon">
                    <span>Live Updates (20s)</span>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="message error">
                <ion-icon name="alert-circle-outline"></ion-icon>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($active_orders)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">🍽️</div>
                <h2>No Active Orders</h2>
                <p>The kitchen is clear. New orders will appear here.</p>
            </div>
        
        <?php else: ?>
            <div class="orders-table-wrapper">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Table</th>
                            <th>Status</th>
                            <th>Order Summary</th>
                            <th>Total</th>
                            <th>Wait Time</th>
                            <th>Waiter</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_orders as $order): 
                            // Status Logic
                            $status_class = '';
                            $status_text = '';
                            switch($order['status']) {
                                case 'pending': $status_class = 'status-pending'; $status_text = 'Pending'; break;
                                case 'prepared': $status_class = 'status-prepared'; $status_text = 'Prepared'; break;
                                default: $status_class = 'status-other'; $status_text = ucfirst($order['status']);
                            }
                            
                            // Truncate text for the preview
                            $display_items = $order['item_summary'];
                            if (strlen($display_items) > 35) {
                                $display_items = substr($display_items, 0, 35) . '...';
                            }
                        ?>
                            <tr>
                                <td data-label="Order ID" class="fw-bold">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td data-label="Table" class="fw-bold table-cell"><?php echo htmlspecialchars($order['table_number']); ?></td>
                                <td data-label="Status">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                
                                <td data-label="Order Summary" class="items-cell">
                                    <div class="item-summary-trigger" onclick="togglePopup(event, this)">
                                        <div class="trigger-content">
                                            <span class="trigger-text"><?php echo htmlspecialchars($display_items ?: 'No items'); ?></span>
                                            <span class="item-count-badge"><?php echo $order['item_count']; ?></span>
                                        </div>
                                        <ion-icon name="chevron-down-outline" class="trigger-icon"></ion-icon>
                                    </div>
                                    
                                    <div class="item-popout">
                                        <div class="popout-header">
                                            <span>Order #<?php echo htmlspecialchars($order['order_id']); ?></span>
                                            <span class="close-btn" onclick="closeAllPopups()">&times;</span>
                                        </div>
                                        <div class="popout-body">
                                            <?php
                                            $items_array = explode(', ', $order['item_summary']);
                                            if (!empty($items_array) && $order['item_summary'] !== '') {
                                                echo '<ul class="popout-item-list">';
                                                foreach ($items_array as $item) {
                                                    echo '<li>' . htmlspecialchars($item) . '</li>';
                                                }
                                                echo '</ul>';
                                            } else {
                                                echo 'No items';
                                            }
                                            ?>
                                        </div>
                                        <div class="popout-footer">
                                            Total: RM <?php echo number_format($order['total_amount'], 2); ?>
                                        </div>
                                    </div>
                                </td>

                                <td data-label="Total" class="fw-bold">RM <?php echo number_format($order['total_amount'], 2); ?></td>
                                
                                <td data-label="Wait Time">
                                    <span class="live-timer" data-time="<?php echo $order['created_at']; ?>">
                                        Loading...
                                    </span>
                                </td>

                                <td data-label="Waiter"><?php echo htmlspecialchars($order['waiter_name'] ?? 'N/A'); ?></td>
                                
                                <td data-label="Actions" class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="view_bill.php?order_id=<?php echo $order['order_id']; ?>" 
                                           class="action-btn print-btn" target="_blank" title="Print Bill">
                                            <ion-icon name="print-outline"></ion-icon>
                                            <span>Print</span>
                                        </a>

                                        <?php if ($order['status'] === 'prepared'): ?>
                                            <a href="../order/payment.php?order_id=<?php echo $order['order_id']; ?>" 
                                               class="action-btn process-btn">
                                                <ion-icon name="card-outline"></ion-icon>
                                                <span>Pay</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="kitchen-wait">
                                                <ion-icon name="restaurant-outline"></ion-icon> Cooking...
                                            </span>
                                        <?php endif; ?>
                                    </div>
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
    // 1. Auto Refresh Logic (Every 20s)
    setTimeout(function() {
        location.reload();
    }, 20000);

    // 2. Pop-out Toggle Logic
    function togglePopup(event, triggerElement) {
        event.stopPropagation(); // Stop bubbling
        
        // The popout is the next sibling element in the DOM
        const popout = triggerElement.nextElementSibling;
        const isAlreadyOpen = popout.classList.contains('show');

        // Close all others first
        closeAllPopups();

        // Toggle this one
        if (!isAlreadyOpen) {
            popout.classList.add('show');
            triggerElement.classList.add('active');
        }
    }

    function closeAllPopups() {
        document.querySelectorAll('.item-popout').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.item-summary-trigger').forEach(el => el.classList.remove('active'));
    }

    // Close when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.items-cell')) {
            closeAllPopups();
        }
    });

    // 3. Live Timer Logic
    function updateTimers() {
        const now = new Date();
        document.querySelectorAll('.live-timer').forEach(timer => {
            const timeString = timer.getAttribute('data-time');
            // Regex to fix SQL date format for Safari/iOS
            const created = new Date(timeString.replace(/-/g, "/")); 
            
            const diffMs = now - created;
            const diffMins = Math.floor(diffMs / 60000);

            // Text Update
            timer.innerText = diffMins < 1 ? "Just now" : diffMins + " mins";

            // Color Update
            timer.classList.remove('timer-green', 'timer-yellow', 'timer-red');
            if (diffMins >= 30) timer.classList.add('timer-red');
            else if (diffMins >= 15) timer.classList.add('timer-yellow');
            else timer.classList.add('timer-green');
        });
    }

    // Init Timers
    updateTimers();
    setInterval(updateTimers, 60000);
</script>