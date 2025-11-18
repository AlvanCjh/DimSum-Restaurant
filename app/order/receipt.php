<?php
session_start();
require_once '../connection.php';

// --- CONFIGURATION (for display consistency) ---
const SERVICE_CHARGE_RATE = 0.06; // 6%
const SST_RATE = 0.06;            // 6%

// --- SECURITY AND INITIALIZATION ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['payment_id'])) {
    header("Location: order.php");
    exit;
}

$payment_id = (int)$_GET['payment_id'];

// --- FETCH ALL DETAILS FOR RECEIPT ---
try {
    // Fetch payment, order, and table details
    $sql = "SELECT 
                p.id as payment_id,
                p.amount as total_paid,
                p.payment_method,
                p.subtotal,
                p.service_charge,
                p.sst,
                p.payment_time,
                o.id as order_id,
                o.created_at as order_time,
                dt.table_number,
                u.username as staff_name
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN dining_tables dt ON o.table_id = dt.id
            -- Use LEFT JOIN for user in case the user was deleted
            LEFT JOIN users u ON o.user_id = u.id
            WHERE p.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$payment_id]);
    $receipt_details = $stmt->fetch();

    if (!$receipt_details) {
        die("Receipt not found.");
    }

    // Fetch the items for this order
    $sql_items = "SELECT oi.quantity, oi.price, mi.name as item_name
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$receipt_details['order_id']]);
    $order_items = $stmt_items->fetchAll();

    // --- Use stored values directly from the payments table for accuracy ---
    $grand_total = $receipt_details['total_paid'];
    $subtotal = $receipt_details['subtotal'];
    $service_charge = $receipt_details['service_charge'];
    $sst = $receipt_details['sst'];

} catch (PDOException $e) {
    die("Error fetching receipt details: " . $e->getMessage());
}

$pageTitle = "E-Receipt";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="../css/receipt.css">

<main class="main-wrapper">
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Yobita</h1>
            <p>Official Receipt</p>
        </div>

        <div class="receipt-info">
            <p><strong>Receipt ID:</strong> <?php echo htmlspecialchars($receipt_details['payment_id']); ?></p>
            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($receipt_details['order_id']); ?></p>
            <p><strong>Table:</strong> <?php echo htmlspecialchars($receipt_details['table_number']); ?></p>
            <p><strong>Cashier:</strong> <?php echo htmlspecialchars($receipt_details['staff_name'] ?? 'N/A'); ?></p>
            <p><strong>Date:</strong> <?php echo date("d M Y, h:i A", strtotime($receipt_details['payment_time'])); ?></p>
        </div>

        <div class="receipt-items">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="receipt-totals">
            <div class="total-row"><span>Subtotal</span><span>RM <?php echo number_format($subtotal, 2); ?></span></div>
            <div class="total-row"><span>Service (6%)</span><span>RM <?php echo number_format($service_charge, 2); ?></span></div>
            <div class="total-row"><span>SST (6%)</span><span>RM <?php echo number_format($sst, 2); ?></span></div>
            <div class="total-row grand-total"><span>TOTAL</span><span>RM <?php echo number_format($grand_total, 2); ?></span></div>
        </div>

        <div class="receipt-footer">
            <p>Paid via: <strong><?php echo htmlspecialchars(ucfirst($receipt_details['payment_method'])); ?></strong></p>
            <p>Thank you for dining with us!</p>
        </div>

        <div class="receipt-actions">
            <button onclick="window.print()" class="print-btn">Print Receipt</button>
            <a href="../staff/index.php" class="done-btn">Done</a>
        </div>
    </div>
</main>