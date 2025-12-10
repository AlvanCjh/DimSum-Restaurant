<?php
session_start();
require_once '../connection.php';

// --- CONFIGURATION ---
const SERVICE_CHARGE_RATE = 0.06;
const SST_RATE = 0.06;

// --- SECURITY ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../index.php");
    exit;
}
if (!isset($_GET['payment_id'])) {
    header("Location: order.php");
    exit;
}

$payment_id = (int)$_GET['payment_id'];

// --- FETCH DATA ---
try {
    $sql = "SELECT p.*, o.id as order_id, o.created_at as order_time, 
                   dt.table_number, u.username as waiter_name, o.total_amount as order_total_required
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN dining_tables dt ON o.table_id = dt.id
            LEFT JOIN staffs u ON o.user_id = u.id
            WHERE p.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$payment_id]);
    $receipt_details = $stmt->fetch();

    if (!$receipt_details) die("Receipt not found.");

    // Fetch Items
    $sql_items = "SELECT oi.quantity, oi.price, mi.name as item_name
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$receipt_details['order_id']]);
    $order_items = $stmt_items->fetchAll();


    // --- CALCULATE TOTALS & CHANGE ---
    // 1. Calculate the exact bill total
    $bill_total = $receipt_details['subtotal'] + $receipt_details['service_charge'] + $receipt_details['sst'];
    
    // 2. Determine Amount Tendered & Change
    // FIX: Check $_SESSION first! This contains the actual cash (e.g., RM 100) from the previous screen.
    if (isset($_SESSION['receipt_data'])) {
        $total_paid = $_SESSION['receipt_data']['tendered'];
        $change_due = $_SESSION['receipt_data']['change'];
        
        // Optional: Clear session after use so it doesn't persist if refreshed
        // unset($_SESSION['receipt_data']); 
    } else {
        // Fallback: If opening an old receipt from history, assume exact payment
        $total_paid = $receipt_details['amount']; 
        $change_due = 0.00;
    }
    
    // Change Calculation
    $change_due = $total_paid - $bill_total;
    if($change_due < 0) $change_due = 0; // Safety

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = "Payment Success";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="../css/receipt.css">

<main class="main-wrapper">
    
    <div class="success-container" id="successView">
        <div class="checkmark-wrapper">
            <svg class="checkmark" viewBox="0 0 52 52">
                <path d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        
        <div class="success-header">
            <h2>Payment Successful!</h2>
            <p>Transaction #<?php echo str_pad($payment_id, 6, '0', STR_PAD_LEFT); ?> completed</p>
        </div>

        <div class="payment-summary">
            <div class="summary-row">
                <span>Bill Total</span>
                <span>RM <?php echo number_format($bill_total, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Amount Tendered</span>
                <span>RM <?php echo number_format($total_paid, 2); ?></span>
            </div>
            <div class="summary-row highlight">
                <span class="change-label">Change Due</span>
                <span class="change-label">RM <?php echo number_format($change_due, 2); ?></span>
            </div>
        </div>

        <button onclick="showReceipt()" class="generate-btn">
            <i class="fas fa-receipt"></i> Generate Receipt
        </button>
        
        <a href="../waiter/index.php" class="skip-btn">Skip & Return to Home</a>
    </div>


    <div class="receipt-container" id="receiptView">
        <div class="receipt-header">
            <h1>Yobita</h1>
            <p>Official Receipt</p>
        </div>

        <div class="receipt-info">
            <p><strong>Receipt ID:</strong> <?php echo str_pad($payment_id, 6, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date("d M Y, h:i A", strtotime($receipt_details['payment_time'])); ?></p>
            <p><strong>Table:</strong> <?php echo htmlspecialchars($receipt_details['table_number']); ?></p>
            <p><strong>waiter:</strong> <?php echo htmlspecialchars($receipt_details['waiter_name'] ?? 'waiter'); ?></p>
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
                        <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="receipt-totals">
            <div class="total-row"><span>Subtotal</span><span>RM <?php echo number_format($receipt_details['subtotal'], 2); ?></span></div>
            <div class="total-row"><span>Service (6%)</span><span>RM <?php echo number_format($receipt_details['service_charge'], 2); ?></span></div>
            <div class="total-row"><span>SST (6%)</span><span>RM <?php echo number_format($receipt_details['sst'], 2); ?></span></div>
            <div class="total-row grand-total"><span>TOTAL</span><span>RM <?php echo number_format($bill_total, 2); ?></span></div>
            
            <div class="total-row" style="margin-top:10px; font-size:0.8rem;">
                <span>Paid (<?php echo ucfirst($receipt_details['payment_method']); ?>)</span>
                <span>RM <?php echo number_format($total_paid, 2); ?></span>
            </div>
            <div class="total-row" style="font-size:0.8rem;">
                <span>Change</span>
                <span>RM <?php echo number_format($change_due, 2); ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            <p>Thank you for dining with us!</p>
            <p>Please come again.</p>
        </div>

        <div class="receipt-actions">
            <button onclick="window.print()" class="print-btn">Print</button>
            <a href="../waiter/index.php" class="done-btn">Done</a>
        </div>
    </div>

</main>

<script>
    function showReceipt() {
        // Hide success screen
        document.getElementById('successView').style.display = 'none';
        
        // Show receipt screen
        document.getElementById('receiptView').style.display = 'block';
    }
</script>