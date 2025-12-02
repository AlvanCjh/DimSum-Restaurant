<?php
session_start();
require_once '../connection.php';

// --- CONFIGURATION ---
const SERVICE_CHARGE_RATE = 0.06; // 6%
const SST_RATE = 0.06;            // 6%

// --- SECURITY AND INITIALIZATION ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    header("Location: order.php");
    exit;
}

$order_id = (int)$_GET['order_id'];
$message = '';
$message_type = '';

// --- HANDLE PAYMENT SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'];
    $grand_total = (float)$_POST['total_amount'];
    $subtotal = (float)$_POST['subtotal'];
    $service_charge = (float)$_POST['service_charge'];
    $sst = (float)$_POST['sst'];

    if (in_array($payment_method, ['cash', 'online']) && $grand_total > 0) {
        try {
            $pdo->beginTransaction();

            // Insert payment record
            $sql_payment = "INSERT INTO payments (order_id, amount, subtotal, service_charge, sst, payment_method) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_payment = $pdo->prepare($sql_payment);
            $stmt_payment->execute([
                $order_id, $grand_total, $subtotal, $service_charge, $sst, $payment_method
            ]);
            $payment_id = $pdo->lastInsertId();

            // The database trigger `check_full_payment` will automatically update
            // the order status to 'completed' and the table status to 'available'.

            $pdo->commit();

            // Redirect to the e-receipt page
            header("Location: receipt.php?payment_id=" . $payment_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error processing payment: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "Invalid payment method or amount.";
        $message_type = "error";
    }
}

// --- FETCH ORDER DETAILS FOR DISPLAY ---
try {
    $sql_order = "SELECT o.*, dt.table_number 
                  FROM orders o 
                  JOIN dining_tables dt ON o.table_id = dt.id 
                  WHERE o.id = ? AND o.status = 'prepared'";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        // If order is not found or not prepared, redirect
        header("Location: order.php?message=Order not found or not ready for payment. Please wait for kitchen to prepare the order.");
        exit;
    }

    $sql_items = "SELECT oi.quantity, oi.price, mi.name as item_name
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll();

    // --- CALCULATIONS ---
    $subtotal = $order['total_amount'];
    $service_charge = $subtotal * SERVICE_CHARGE_RATE;
    $sst = $subtotal * SST_RATE;
    $grand_total = $subtotal + $service_charge + $sst;

} catch (PDOException $e) {
    die("Error fetching order details: " . $e->getMessage());
}

$pageTitle = "Process Payment";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="../css/payment.css">

<main class="main-wrapper">
    <div class="payment-container">
        <div class="page-header">
            <h1>Process Payment</h1>
            <a href="order.php" class="back-link">← Back to Bills</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="bill-summary">
            <h2>Bill for Table <?php echo htmlspecialchars($order['table_number']); ?></h2>

            <div class="bill-items">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
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

            <div class="bill-totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>RM <?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Service Charge (6%)</span>
                    <span>RM <?php echo number_format($service_charge, 2); ?></span>
                </div>
                <div class="total-row">
                    <span>SST (6%)</span>
                    <span>RM <?php echo number_format($sst, 2); ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>Grand Total</span>
                    <span>RM <?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>

            <div class="payment-actions">
                <form method="post">
                    <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                    <input type="hidden" name="service_charge" value="<?php echo $service_charge; ?>">
                    <input type="hidden" name="sst" value="<?php echo $sst; ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">
                    <input type="hidden" name="payment_method" value="cash">
                    <button type="submit" name="process_payment" class="payment-btn cash">
                        Pay with Cash
                    </button>
                </form>
                <form method="post">
                    <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                    <input type="hidden" name="service_charge" value="<?php echo $service_charge; ?>">
                    <input type="hidden" name="sst" value="<?php echo $sst; ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">
                    <input type="hidden" name="payment_method" value="online">
                    <button type="submit" name="process_payment" class="payment-btn online">
                        Pay with Online
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>