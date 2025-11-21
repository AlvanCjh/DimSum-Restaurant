<?php
session_start();
require_once '../connection.php';

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "Process Bill";
$basePath = "../";
include '../_header.php';

$selected_table_id = null;
$current_order = null;
$order_items = [];
$message = "";
$message_type = "";

?>

<link rel="stylesheet" href="../css/order.css">
<link rel="stylesheet" href="../css/table.css">

<!-- Custom styles for a cleaner background -->
<style>
    body {
        background-color: var(--second) !important;
    }
</style>

<main class="main-wrapper">
    <div class="order-container">
        <div class="page-header">
            <h1>Process Bill</h1>
            <a href="../staff/index.php" class="back-link">← Back to Dashboard</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Active Bills Display -->
        <div class="table-selection-section">
            <h2 class="page-title" style="text-align: center; margin-bottom: 30px;">Select a Bill to Process</h2>
            
            <?php
            // Fetch only tables that have a 'pending' order
            try {
                $sql_active_orders = "SELECT dt.id as table_id, dt.table_number, dt.capacity, o.id as order_id, o.total_amount
                                      FROM orders o
                                      JOIN dining_tables dt ON o.table_id = dt.id
                                      WHERE o.status = 'pending'
                                      ORDER BY dt.table_number ASC";
                $stmt_active_orders = $pdo->query($sql_active_orders);
                $active_orders = $stmt_active_orders->fetchAll();
            } catch (PDOException $e) {
                $active_orders = [];
                echo '<div class="message error">Could not fetch active bills.</div>';
            }
            ?>

            <div class="tables-grid">
                <?php if (empty($active_orders)): ?>
                    <div class="no-order-message" style="grid-column: 1 / -1;">
                        <p>There are no active bills to process right now.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_orders as $order): ?>
                        <div class="table-card occupied">
                            <div class="table-icon">🍽️</div>
                            <div class="table-number"><?php echo htmlspecialchars($order['table_number']); ?></div>
                            <div class="table-capacity">Order Total: RM <?php echo number_format($order['total_amount'], 2); ?></div>
                            <div class="table-actions">
                                <a href="payment.php?order_id=<?php echo $order['order_id']; ?>" class="select-table-btn" style="width: 100%;">
                                    Go to Bill
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="../js/script.js" defer></script>