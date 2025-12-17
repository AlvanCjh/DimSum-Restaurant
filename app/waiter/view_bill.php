<?php
session_start();
require_once '../connection.php';

// --- CONFIGURATION ---
const SERVICE_CHARGE_RATE = 0.06;
const SST_RATE = 0.06;

// --- SECURITY ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    die("Access Denied");
}

if (!isset($_GET['order_id'])) {
    die("Order ID missing.");
}

$order_id = (int)$_GET['order_id'];

try {
    // 1. Fetch Order Info
    $sql_order = "SELECT o.*, dt.table_number, u.username as waiter_name 
                  FROM orders o 
                  JOIN dining_tables dt ON o.table_id = dt.id 
                  LEFT JOIN staffs u ON o.user_id = u.id 
                  WHERE o.id = ?";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        die("Order not found.");
    }

    // 2. Fetch Items (Grouped/Stacked)
    $sql_items = "SELECT 
                    mi.name, 
                    mi.price, 
                    SUM(oi.quantity) as quantity 
                  FROM order_items oi
                  JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?
                  GROUP BY mi.id, mi.name, mi.price";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll();

    // 3. Calculate Totals (Replicating Payment Logic)
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    // Crucial: Round components individually to match payment.php logic
    $service_charge = round($subtotal * SERVICE_CHARGE_RATE, 2);
    $sst = round($subtotal * SST_RATE, 2);
    $grand_total = $subtotal + $service_charge + $sst;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?php echo $order_id; ?></title>
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <style>
        /* --- General Variables --- */
        :root {
            --paper-color: #fff;
            --ink-color: #333;
            --bg-color: #555;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Courier New', Courier, monospace; /* Monospace looks like a receipt */
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        /* --- The Receipt Paper --- */
        .receipt-container {
            background: var(--paper-color);
            width: 320px; /* Typical width for thermal receipt */
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 50px;
        }

        /* --- Receipt Sections --- */
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        .header h2 { margin: 0; font-size: 1.5rem; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 0.9rem; }
        .provisional-mark {
            margin-top: 10px;
            border: 2px solid #000;
            display: inline-block;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .items-table th { text-align: left; border-bottom: 1px solid #000; padding-bottom: 5px;}
        .items-table td { padding: 4px 0; vertical-align: top; }
        .col-qty { width: 30px; }
        .col-price { text-align: right; }

        .totals {
            border-top: 1px dashed #000;
            padding-top: 10px;
            text-align: right;
            font-size: 0.9rem;
        }
        .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .grand-total { font-weight: bold; font-size: 1.1rem; margin-top: 8px; border-top: 1px solid #000; padding-top: 5px; }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
        }

        /* --- Action Buttons (Screen Only) --- */
        .actions {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 100;
        }

        .btn {
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: sans-serif;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }
        .btn:hover { transform: translateY(-2px); }
        
        .btn-print { background: #fff; color: #333; }
        .btn-close { background: #ff5252; color: #fff; }

        /* --- PRINT MODE (Thermal Printer Format) --- */
        @media print {
            body { 
                background: none; 
                padding: 0;
                display: block;
            }
            .receipt-container {
                width: 100%; /* Fill paper width */
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            .actions { display: none !important; } /* Hide buttons */
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header">
            <h2>Yobita</h2>
            <p>Dim Sum Restaurant</p>
            <div class="provisional-mark">PREVIEW BILL</div>
        </div>

        <div class="meta-info">
            <div>
                <div><strong>Table: <?php echo htmlspecialchars($order['table_number']); ?></strong></div>
                <div>Ord #: <?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div style="text-align:right;">
                <div><?php echo date("d/m/y", strtotime($order['created_at'])); ?></div>
                <div><?php echo date("H:i", strtotime($order['created_at'])); ?></div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-qty">Qty</th>
                    <th>Item</th>
                    <th class="col-price">Amt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $line_total = $item['price'] * $item['quantity'];
                ?>
                <tr>
                    <td class="col-qty"><?php echo $item['quantity']; ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td class="col-price"><?php echo number_format($line_total, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span>Subtotal:</span>
                <span><?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="row">
                <span>Service (6%):</span>
                <span><?php echo number_format($service_charge, 2); ?></span>
            </div>
            <div class="row">
                <span>SST (6%):</span>
                <span><?php echo number_format($sst, 2); ?></span>
            </div>
            <div class="row grand-total">
                <span>TOTAL:</span>
                <span>RM <?php echo number_format($grand_total, 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p>* This is not a tax invoice *</p>
            <p>Please pay at counter</p>
        </div>
    </div>

    <div class="actions">
        <button onclick="window.print()" class="btn btn-print">
            <ion-icon name="print-outline"></ion-icon> Print Check
        </button>
        <button onclick="window.close()" class="btn btn-close">
            <ion-icon name="close-circle-outline"></ion-icon> Close
        </button>
    </div>

</body>
</html>