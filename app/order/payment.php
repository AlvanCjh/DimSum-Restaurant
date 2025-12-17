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

if (!isset($_GET['order_id'])) {
    header("Location: order.php");
    exit;
}

$order_id = (int)$_GET['order_id'];
$message = '';
$message_type = '';

// --- FETCH ORDER DETAILS ---
try {
    $sql_order = "SELECT o.*, dt.table_number 
                  FROM orders o 
                  JOIN dining_tables dt ON o.table_id = dt.id 
                  WHERE o.id = ?";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        header("Location: order.php?error=OrderNotFound");
        exit;
    }

    $sql_items = "SELECT 
                        SUM(oi.quantity) as quantity, 
                        mi.price, 
                        mi.name as item_name
                    FROM order_items oi
                    JOIN menu_items mi ON oi.menu_item_id = mi.id
                    WHERE oi.order_id = ?
                    GROUP BY oi.menu_item_id, mi.name, mi.price";
                  
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll();

    // Calculate Totals
    $subtotal = $order['total_amount'];
    
    // FIX: Round components individually BEFORE summing
    $service_charge = round($subtotal * SERVICE_CHARGE_RATE, 2);
    $sst = round($subtotal * SST_RATE, 2);
    
    // Now sum them up. No extra rounding needed on the total.
    $grand_total = $subtotal + $service_charge + $sst;

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// --- HANDLE PAYMENT SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method']; // cash, credit_card, qr_pay
    $tendered_amount = (float)($_POST['amount_tendered'] ?? 0);
    
    // Validation: Ensure tendered amount covers the bill
    if ($payment_method == 'cash' && $tendered_amount < $grand_total) {
        $message = "Insufficient payment amount!";
        $message_type = "error";
    } 
    // Logic: If not cash (meaning QR/Card), we proceed automatically.
    else {
        // If digital, force tendered to equal grand_total for the receipt record
        if ($payment_method != 'cash') {
            $tendered_amount = $grand_total;
        }
        
        try {
            $pdo->beginTransaction();

            // Insert Payment Record
            // Note: We insert the BILL amount as 'amount' for the database trigger to close the order properly.
            // We usually don't store 'tendered' in the payments table unless you add a specific column for it.
            $sql_payment = "INSERT INTO payments (order_id, amount, subtotal, service_charge, sst, payment_method) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_payment = $pdo->prepare($sql_payment);
            $stmt_payment->execute([
                $order_id, $grand_total, $subtotal, $service_charge, $sst, $payment_method
            ]);
            $payment_id = $pdo->lastInsertId();

            $pdo->commit();

            // Store Change/Tendered info in Session for the Receipt Page to display
            $_SESSION['receipt_data'] = [
                'tendered' => $tendered_amount,
                'change' => $tendered_amount - $grand_total,
                'method' => $payment_method
            ];

            // Redirect to Receipt
            header("Location: receipt.php?payment_id=" . $payment_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Database Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

$pageTitle = "Payment Terminal";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="../css/payment.css">

<main class="main-wrapper">
    <div class="page-header-bar">
        <a href="../waiter/active_orders.php" class="back-link">
            ← Back to Order
        </a>
    </div>
    
    <div class="pos-payment-layout">
        
        <div class="bill-section">
            <div class="bill-header">
                <h2>Table <?php echo htmlspecialchars($order['table_number']); ?></h2>
                <span class="order-id">Order #<?php echo $order_id; ?></span>
            </div>
            
            <div class="bill-items-scroll">
                <?php foreach ($order_items as $item): ?>
                <div class="bill-row">
                    <div class="item-name">
                        <span class="qty"><?php echo $item['quantity']; ?>x</span>
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div class="item-price">RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bill-totals">
                <div class="row"><span>Subtotal</span> <span>RM <?php echo number_format($subtotal, 2); ?></span></div>
                <div class="row"><span>Service (6%)</span> <span>RM <?php echo number_format($service_charge, 2); ?></span></div>
                <div class="row"><span>SST (6%)</span> <span>RM <?php echo number_format($sst, 2); ?></span></div>
                <div class="row total"><span>Total Due</span> <span>RM <?php echo number_format($grand_total, 2); ?></span></div>
            </div>
        </div>

        <div class="input-section">
            
            <form method="post" id="paymentForm" novalidate>
                <input type="hidden" name="total_amount" id="totalAmount" value="<?php echo $grand_total; ?>">
                
                <div class="payment-tabs">
                    <label class="method-btn active" id="btn-cash">
                        <input type="radio" name="payment_method" value="cash" checked onchange="togglePaymentMode('cash', this)">
                        <ion-icon name="cash-outline"></ion-icon> Cash
                    </label>
                    <label class="method-btn" id="btn-qr">
                        <input type="radio" name="payment_method" value="qr_pay" onchange="togglePaymentMode('qr', this)">
                        <ion-icon name="qr-code-outline"></ion-icon> QR Pay
                    </label>
                    <label class="method-btn" id="btn-card">
                        <input type="radio" name="payment_method" value="credit_card" onchange="togglePaymentMode('card', this)">
                        <ion-icon name="card-outline"></ion-icon> Card
                    </label>
                </div>

                <div id="cashInterface" class="payment-mode-ui">
                    <div class="amount-display">
                        <label>Amount Tendered</label>
                        <div class="input-group">
                            <span class="currency">RM</span>
                            <input type="number" name="amount_tendered" id="tenderedInput" step="0.05" placeholder="0.00">
                        </div>
                    </div>

                    <div class="quick-cash-grid">
                        <button type="button" onclick="setCash(<?php echo $grand_total; ?>)">Exact</button>
                        <button type="button" onclick="setCash(10)">RM 10</button>
                        <button type="button" onclick="setCash(20)">RM 20</button>
                        <button type="button" onclick="setCash(50)">RM 50</button>
                        <button type="button" onclick="setCash(100)">RM 100</button>
                        <button type="button" onclick="setCash(200)">RM 200</button>
                    </div>

                    <div class="change-display">
                        <span>Change Due:</span>
                        <strong id="changeAmount">RM 0.00</strong>
                    </div>
                </div>

                <div id="digitalInterface" class="payment-mode-ui" style="display:none;">
                    
                    <div id="qrInstruction" style="display:none; text-align:center;">
                        <div class="instruction-box">
                            <div class="qr-frame">
                                <img src="../image/QR.jpg" alt="DuitNow QR" class="qr-img">
                            </div>
                            <h3>DuitNow / Touch 'n Go</h3>
                            <p>Ask customer to scan the QR code above.</p>
                            <div class="alert-info-box">
                                <ion-icon name="phone-portrait-outline"></ion-icon>
                                Verify success screen on customer's phone
                            </div>
                        </div>
                    </div>

                    <div id="cardInstruction" style="display:none; text-align:center;">
                        <div class="instruction-box">
                            <div class="card-icon-frame">
                                <ion-icon name="card-outline"></ion-icon>
                            </div>
                            <h3>Credit / Debit Card</h3>
                            <p>Please use the external <strong>Card Terminal</strong>.</p>
                            <div class="amount-to-charge">
                                <small>Amount to Charge:</small>
                                <div>RM <?php echo number_format($grand_total, 2); ?></div>
                            </div>
                        </div>
                    </div>

                </div>

                <button type="submit" name="process_payment" class="pay-btn" id="payButton" disabled>
                    Complete Payment
                </button>
            </form>

            <?php if ($message): ?>
                <div class="error-banner"><?php echo $message; ?></div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- 1. Initialize Variables ---
        const grandTotal = <?php echo number_format($grand_total, 2, '.', ''); ?>; // Force raw number format
        const tenderedInput = document.getElementById('tenderedInput');
        const changeDisplay = document.getElementById('changeAmount');
        const payButton = document.getElementById('payButton');
        const cashUI = document.getElementById('cashInterface');
        const digitalUI = document.getElementById('digitalInterface');
        
        let currentMode = 'cash'; 

        // --- 2. Define Global Functions ---

        // Toggle Mode (Cash vs QR/Card)
        window.togglePaymentMode = function(mode, inputElement) {
            currentMode = mode;
            
            // Visual Tab Switching
            document.querySelectorAll('.method-btn').forEach(btn => btn.classList.remove('active'));
            // Safety check in case inputElement is missing
            if(inputElement && inputElement.closest('.method-btn')) {
                inputElement.closest('.method-btn').classList.add('active');
            }

            // Logic Switching
            if (mode === 'cash') {
                cashUI.style.display = 'block';
                digitalUI.style.display = 'none';
                
                // Enable Input for Cash
                tenderedInput.disabled = false; 
                tenderedInput.value = ''; 
                
                calculateChange(); 
                
            } else {
                // Digital Modes
                cashUI.style.display = 'none';
                digitalUI.style.display = 'flex';
                
                const qrView = document.getElementById('qrInstruction');
                const cardView = document.getElementById('cardInstruction');

                if(mode === 'qr') {
                    qrView.style.display = 'block';
                    cardView.style.display = 'none';
                    payButton.innerText = "Verify & Complete Order"; 
                } 
                else if(mode === 'card') { 
                    qrView.style.display = 'none';
                    cardView.style.display = 'block';
                    payButton.innerText = "Transaction Approved"; 
                }

                // DISABLE manual input so browser ignores "required" validation
                tenderedInput.disabled = true;
                
                // Auto-fill hidden value for backend
                tenderedInput.value = grandTotal.toFixed(2); 
                
                // Force Enable Button
                payButton.disabled = false;
                payButton.style.opacity = '1';
                payButton.style.backgroundColor = '#28a745';
                payButton.style.cursor = 'pointer';
            }
        };

        // Set Cash (The Buttons)
        window.setCash = function(amount) {
            // Un-disable input just in case
            tenderedInput.disabled = false;
            
            // Update value
            tenderedInput.value = parseFloat(amount).toFixed(2);
            
            // Notify system that value changed (Fixes "input not detected" bugs)
            tenderedInput.dispatchEvent(new Event('input'));
            
            // Run calculation
            calculateChange();
        };

        // Calculate Change Logic
        window.calculateChange = function() {
            // Ignore if we are in digital mode
            if(currentMode !== 'cash') return;

            const val = tenderedInput.value;
            const tendered = parseFloat(val) || 0;
            const change = tendered - grandTotal;

            if (tendered > 0 && change >= 0) {
                // Sufficient Payment
                changeDisplay.innerHTML = "Change Due:<br>" + "RM " + change.toFixed(2);
                changeDisplay.style.color = "#28a745"; 
                
                payButton.disabled = false;
                payButton.style.opacity = '1';
                payButton.innerText = "Pay & Print Receipt";
                payButton.style.backgroundColor = "#28a745";
                payButton.style.cursor = 'pointer';
            } else {
                // Insufficient Payment
                const short = (grandTotal - tendered).toFixed(2);
                changeDisplay.innerHTML = "Short:<br>" + "RM " + short;
                changeDisplay.style.color = "#dc3545"; 
                
                payButton.disabled = true;
                payButton.style.opacity = '0.5';
                payButton.innerText = "Insufficient Amount";
                payButton.style.backgroundColor = "#dc3545";
                payButton.style.cursor = 'not-allowed';
            }
        };

        // --- 3. Initial Setup ---
        // Ensure inputs invoke the calculation
        if(tenderedInput) {
            tenderedInput.addEventListener('input', window.calculateChange);
        }
    });
</script>