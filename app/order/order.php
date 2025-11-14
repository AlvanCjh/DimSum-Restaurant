<?php
session_start();
require_once '../connection.php';

function recalculateOrderTotal($pdo, $order_id) {
    try {
        // 1. Calculate the new total by summing up all items for this order.
        // This assumes 'price' is stored in the 'order_items' table.
        $sql_sum = "SELECT SUM(quantity * price) AS new_total
                    FROM order_items
                    WHERE order_id = ?";
        
        $stmt_sum = $pdo->prepare($sql_sum);
        $stmt_sum->execute([$order_id]);
        $result = $stmt_sum->fetch();

        // If no items are left, total is 0.
        $new_total = $result['new_total'] ? $result['new_total'] : 0;

        // 2. Update the 'total_amount' in the main 'orders' table.
        $sql_update_order = "UPDATE orders SET total_amount = ? WHERE id = ?";
        $stmt_update_order = $pdo->prepare($sql_update_order);
        $stmt_update_order->execute([$new_total, $order_id]);
        
    } catch (PDOException $e) {
        // In a real app, you should log this error
        error_log("Error recalculating total for order $order_id: " . $e->getMessage());
    }
}

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "View Orders";
$basePath = "../";
include '../_header.php';

$selected_table_id = null;
$current_order = null;
$order_items = [];
$message = "";
$message_type = "";

// Handle table selection
if (isset($_POST['select_table'])) {
    $selected_table_id = (int)$_POST['table_id'];
    $_SESSION['selected_table_id'] = $selected_table_id;
}

// Use session table if set
if (isset($_SESSION['selected_table_id'])) {
    $selected_table_id = $_SESSION['selected_table_id'];
}

// Handle updating item quantity
if (isset($_POST['update_quantity']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $quantity = (int)$_POST['quantity'];
    
    try {
        // --- MODIFICATION: START ---
        // 1. Get the order_id FROM the item_id BEFORE updating/deleting
        $sql_get_order = "SELECT order_id FROM order_items WHERE id = ?";
        $stmt_get_order = $pdo->prepare($sql_get_order);
        $stmt_get_order->execute([$order_item_id]);
        $item_info = $stmt_get_order->fetch();

        if ($item_info) {
            $order_id = $item_info['order_id'];
            // --- MODIFICATION: END ---

            if ($quantity > 0) {
                $sql_update = "UPDATE order_items SET quantity = ? WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$quantity, $order_item_id]);
                $message = "Quantity updated successfully!";
                $message_type = "success";
            } else {
                // Remove item if quantity is 0
                $sql_delete = "DELETE FROM order_items WHERE id = ?";
                $stmt_delete = $pdo->prepare($sql_delete);
                $stmt_delete->execute([$order_item_id]);
                $message = "Item removed from order!";
                $message_type = "success";
            }

            // --- MODIFICATION: START ---
            // 2. Recalculate the total for the entire order
            recalculateOrderTotal($pdo, $order_id);
            // --- MODIFICATION: END ---
        
        } else {
            $message = "Item not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error updating quantity: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle removing item
if (isset($_POST['remove_item']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    
    try {
        // --- MODIFICATION: START ---
        // 1. Get the order_id BEFORE deleting
        $sql_get_order = "SELECT order_id FROM order_items WHERE id = ?";
        $stmt_get_order = $pdo->prepare($sql_get_order);
        $stmt_get_order->execute([$order_item_id]);
        $item_info = $stmt_get_order->fetch();

        if ($item_info) {
            $order_id = $item_info['order_id'];
            // --- MODIFICATION: END ---

            $sql_delete = "DELETE FROM order_items WHERE id = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([$order_item_id]);
            $message = "Item removed from order!";
            $message_type = "success";

            // --- MODIFICATION: START ---
            // 2. Recalculate the total
            recalculateOrderTotal($pdo, $order_id);
            // --- MODIFICATION: END ---

        } else {
            $message = "Item not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error removing item: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch all tables for selection
try {
    $sql_tables = "SELECT * FROM dining_tables ORDER BY table_number ASC";
    $stmt_tables = $pdo->query($sql_tables);
    $tables = $stmt_tables->fetchAll();
} catch (PDOException $e) {
    $tables = [];
}

// Fetch current order and items if table is selected
if ($selected_table_id) {
    try {
        $sql_order = "SELECT o.*, dt.table_number 
                      FROM orders o 
                      JOIN dining_tables dt ON o.table_id = dt.id 
                      WHERE o.table_id = ? AND o.status = 'pending' 
                      LIMIT 1";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$selected_table_id]);
        $current_order = $stmt_order->fetch();
        
        if ($current_order) {
            $sql_items = "SELECT oi.*, mi.name as item_name, mi.image_url, mi.description
                          FROM order_items oi
                          JOIN menu_items mi ON oi.menu_item_id = mi.id
                          WHERE oi.order_id = ?
                          ORDER BY oi.id DESC";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$current_order['id']]);
            $order_items = $stmt_items->fetchAll();
        }
    } catch (PDOException $e) {
        $error_message = "Error loading order: " . $e->getMessage();
    }
}
?>

<link rel="stylesheet" href="../css/order.css">
<link rel="stylesheet" href="../css/table.css">

<head>
    <script src="../js/script.js" defer></script>
</head>

<main class="main-wrapper">
    <div class="order-container">
        <div class="page-header">
            <h1>View Orders</h1>
            <a href="../staff/index.php" class="back-link">← Back to Dashboard</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Table Selection -->
        <div class="table-selection-section">
            <h2 class="page-title" style="text-align: center; margin-bottom: 30px;">Select Table to View Order</h2>
            <div class="search-bar-container" style="margin-bottom: 25px; text-align: center;">
                <input type="text" id="tableSearchInput" 
                       placeholder="Filter by table name (e.g., T01, VIP)" 
                       style="padding: 10px 12px; width: 400px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em;">
            </div>
            <div class="tables-grid">
                <?php if (empty($tables)): ?>
                    <div class="message error">No tables found in the database.</div>
                <?php else: ?>
                    <?php foreach ($tables as $table): ?>
                        <div class="table-card <?php echo htmlspecialchars($table['status']); ?>" 
                             style="<?php echo ($selected_table_id == $table['id']) ? 'border-width: 4px; box-shadow: 0 8px 25px rgba(92, 75, 75, 0.25);' : ''; ?>">
                            <div class="table-icon">🍽️</div>
                            <div class="table-number"><?php echo htmlspecialchars($table['table_number']); ?></div>
                            <div class="table-capacity">Capacity: <?php echo $table['capacity']; ?> seats</div>
                            <div class="table-status <?php echo htmlspecialchars($table['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($table['status'])); ?>
                            </div>
                            
                            <form method="post" style="margin-top: 15px;">
                                <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                <button type="submit" name="select_table" class="select-table-btn">
                                    View Order
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_table_id && $current_order): ?>
            <!-- Current Order Display -->
            <div class="order-info-section">
                <div class="order-header">
                    <h2>Order for Table <?php echo htmlspecialchars($current_order['table_number']); ?></h2>
                    <div class="order-meta">
                        <span class="order-id">Order #<?php echo $current_order['id']; ?></span>
                        <span class="order-total">Total: RM <?php echo number_format($current_order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-items-section">
                    <h3>Order Items</h3>
                    <?php if (empty($order_items)): ?>
                        <p class="empty-message">No items in this order yet. Add items from the menu page.</p>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="../menu/menu.php" class="add-items-link">Go to Menu to Add Items</a>
                        </div>
                    <?php else: ?>
                        <div class="order-items-list">
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item-card">
                                    <div class="item-image">
                                        <?php if ($item['image_url']): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">No Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <h4><?php echo htmlspecialchars($item['item_name']); ?></h4>
                                        <p class="item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="item-price">RM <?php echo number_format($item['price'], 2); ?> each</div>
                                    </div>
                                    <div class="item-actions">
                                        <form method="post" class="quantity-form">
                                            <input type="hidden" name="order_item_id" value="<?php echo $item['id']; ?>">
                                            <label>Quantity:</label>
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                   min="0" class="quantity-input" required>
                                            <button type="submit" name="update_quantity" class="update-btn">Update</button>
                                        </form>
                                    </div>
                                    <div class="item-subtotal">
                                        RM <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="../menu/menu.php" class="add-items-link">Add More Items from Menu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($selected_table_id): ?>
            <div class="no-order-message">
                <p>No active order for Table <?php 
                    $table_num = '';
                    foreach ($tables as $t) {
                        if ($t['id'] == $selected_table_id) {
                            $table_num = $t['table_number'];
                            break;
                        }
                    }
                    echo htmlspecialchars($table_num);
                ?>.</p>
                <p>Start adding items from the menu to create an order.</p>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="../menu/menu.php" class="add-items-link">Go to Menu to Add Items</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Wait for the document to be fully loaded
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Get the search input element
    const searchInput = document.getElementById('tableSearchInput');
    
    // 2. Add an event listener for when the user types
    searchInput.addEventListener('keyup', function() {
        
        // 3. Get the search term and convert to lowercase for matching
        const filter = searchInput.value.toLowerCase();
        
        // 4. Get all the table cards
        const tableCards = document.querySelectorAll('.tables-grid .table-card');
        
        // 5. Loop through each table card
        tableCards.forEach(card => {
            // 6. Find the element with the table number inside the card
            const tableNumberElement = card.querySelector('.table-number');
            
            if (tableNumberElement) {
                // 7. Get the table name text
                const tableName = tableNumberElement.textContent || tableNumberElement.innerText;
                
                // 8. Check if the table name includes the filter text
                if (tableName.toLowerCase().indexOf(filter) > -1) {
                    // If it matches, show the card
                    card.style.display = ""; 
                } else {
                    // If it doesn't match, hide the card
                    card.style.display = "none";
                }
            }
        });
    });
});
</script>


