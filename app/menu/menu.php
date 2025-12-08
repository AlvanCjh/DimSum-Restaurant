<?php 
session_start();
require_once '../connection.php'; // Path fixed for menu/ folder

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Check if table is selected
if (!isset($_SESSION['selected_table_id'])) {
    header("Location: ../table/table.php");
    exit;
}

$message = "";
$message_type = "";
$basePath = "../"; // Path helper

// =================================================================================
//  LOGIC PART 1: SIDEBAR ACTIONS (From Alternate Code - Manage Existing Order)
// =================================================================================

// Helper function if not in connection.php (Assumed from context)
if (!function_exists('recalculateOrderTotal')) {
    function recalculateOrderTotal($pdo, $order_id) {
        $stmt = $pdo->prepare("SELECT SUM(price * quantity) as total FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $total = $stmt->fetchColumn() ?: 0.00;
        $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $stmt->execute([$total, $order_id]);
    }
}

// Handle finalizing the order
if (isset($_POST['finalize_order']) && isset($_POST['order_id'])) {
    $_SESSION['show_order_complete_alert'] = true;
    header("Location: ../waiter/index.php");
    exit;
}

// Handle decreasing quantity (Sidebar)
if (isset($_POST['decrease_quantity']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $order_id_to_update = (int)$_POST['order_id'];
    try {
        $sql_get_qty = "SELECT quantity FROM order_items WHERE id = ?";
        $stmt_get_qty = $pdo->prepare($sql_get_qty);
        $stmt_get_qty->execute([$order_item_id]);
        $item = $stmt_get_qty->fetch();

        if ($item && $item['quantity'] > 1) {
            $stmt_update = $pdo->prepare("UPDATE order_items SET quantity = quantity - 1 WHERE id = ?");
            $stmt_update->execute([$order_item_id]);
        } else {
            $stmt_delete = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
            $stmt_delete->execute([$order_item_id]);
        }
        recalculateOrderTotal($pdo, $order_id_to_update);
    } catch (PDOException $e) {
        $message = "Error updating: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle increasing quantity (Sidebar)
if (isset($_POST['increase_quantity']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $order_id_to_update = (int)$_POST['order_id'];
    try {
        $stmt_update = $pdo->prepare("UPDATE order_items SET quantity = quantity + 1 WHERE id = ?");
        $stmt_update->execute([$order_item_id]);
        recalculateOrderTotal($pdo, $order_id_to_update);
    } catch (PDOException $e) {
        $message = "Error updating: " . $e->getMessage();
        $message_type = "error";
    }
}

// =================================================================================
//  LOGIC PART 2: BATCH ADD TO ORDER (From Main Code - Add New Items)
// =================================================================================

if (isset($_POST['add_batch_order']) && isset($_POST['items']) && $_SESSION['role'] === 'waiter') {
    $items = $_POST['items']; // Array format: [menu_item_id => quantity]
    $table_id = $_SESSION['selected_table_id'];
    $items_added_count = 0;
    
    try {
        $pdo->beginTransaction();

        // 1. Check or Create Order (check for both 'pending' and 'prepared' status)
        $sql_order = "SELECT * FROM orders WHERE table_id = ? AND status IN ('pending', 'prepared') LIMIT 1";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$table_id]);
        $order = $stmt_order->fetch();
        
        if (!$order) {
            $sql_new_order = "INSERT INTO orders (table_id, user_id, status) VALUES (?, ?, 'pending')";
            $stmt_new_order = $pdo->prepare($sql_new_order);
            $stmt_new_order->execute([$table_id, $_SESSION['user_id']]);
            $order_id = $pdo->lastInsertId();
            
            // Update table status
            $stmt_update_table = $pdo->prepare("UPDATE dining_tables SET status = 'occupied' WHERE id = ?");
            $stmt_update_table->execute([$table_id]);
        } else {
            $order_id = $order['id'];
            
            // If order status is 'prepared', revert it back to 'pending' when new items are added
            // This ensures the chef sees the new items on the KDS
            if ($order['status'] === 'prepared') {
                $sql_revert_status = "UPDATE orders SET status = 'pending' WHERE id = ?";
                $stmt_revert_status = $pdo->prepare($sql_revert_status);
                $stmt_revert_status->execute([$order_id]);
            }
        }

        // 2. Loop through submitted items
        foreach ($items as $item_id => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $stmt_price = $pdo->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmt_price->execute([$item_id]);
                $price_data = $stmt_price->fetch();

                if ($price_data) {
                    $stmt_check = $pdo->prepare("SELECT id, quantity FROM order_items WHERE order_id = ? AND menu_item_id = ?");
                    $stmt_check->execute([$order_id, $item_id]);
                    $existing = $stmt_check->fetch();

                    if ($existing) {
                        $new_qty = $existing['quantity'] + $qty;
                        // When updating quantity, clear prepared_at so it shows on KDS again
                        $stmt_upd = $pdo->prepare("UPDATE order_items SET quantity = ?, prepared_at = NULL WHERE id = ?");
                        $stmt_upd->execute([$new_qty, $existing['id']]);
                    } else {
                        $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt_ins->execute([$order_id, $item_id, $qty, $price_data['price']]);
                    }
                    $items_added_count++;
                }
            }
        }

        // Recalculate total after batch add
        recalculateOrderTotal($pdo, $order_id);

        $pdo->commit();
        
        if ($items_added_count > 0) {
            $message = "Successfully added items to order!";
            $message_type = "success";
        } else {
            $message = "No items were selected.";
            $message_type = "error";
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error processing order: " . $e->getMessage();
        $message_type = "error";
    }
}

$pageTitle = "Menu"; 
include '../_header.php'; 

// Fetch menu items
try {
    $sql = "SELECT mi.*, mc.name as category_name 
            FROM menu_items mi 
            LEFT JOIN menu_categories mc ON mi.category_id = mc.id 
            ORDER BY mi.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $menuItems = $stmt->fetchAll();
    
    foreach ($menuItems as &$item) {
        $item['category'] = $item['category_name'] ? $item['category_name'] : 'Uncategorized';
        // Fixed Path
        $item['image'] = $item['image_url'] ? $basePath . $item['image_url'] : 'https://via.placeholder.com/280x200.png?text=No+Image';
    }
    unset($item);
} catch (PDOException $e) {
    $menuItems = [];
}

// Fetch categories
try {
    $sql_categories = "SELECT DISTINCT mc.name 
                        FROM menu_categories mc 
                        INNER JOIN menu_items mi ON mc.id = mi.category_id 
                        ORDER BY mc.name ASC";
    $stmt_categories = $pdo->query($sql_categories);
    $categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch AI Suggestions (Popular/Trending Items)
$aiSuggestions = [];
try {
    $command = "python ../ai/menu_suggestions.py";
    $output = shell_exec($command);
    $suggestionData = json_decode($output, true);
    
    if (isset($suggestionData['suggestions']) && is_array($suggestionData['suggestions'])) {
        $aiSuggestions = $suggestionData['suggestions'];
        // Process image paths
        foreach ($aiSuggestions as &$suggestion) {
            $suggestion['image'] = !empty($suggestion['image_url']) 
                ? $basePath . $suggestion['image_url'] 
                : 'https://via.placeholder.com/280x200.png?text=No+Image';
        }
        unset($suggestion);
    }
} catch (Exception $e) {
    // Silently fail - suggestions are optional
    $aiSuggestions = [];
}
?>

<link rel="stylesheet" href="../css/menu.css">

<main class="main-wrapper" style="padding-top: 100px;">

  <div class="menu-page-layout">
    
    <div class="menu-main-content">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <span style="background-color: var(--fourth); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--text-dark);">
            Table: <?php echo htmlspecialchars($_SESSION['selected_table_number']); ?>
          </span>
      </div>
      
      <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; <?php echo $message_type === 'success' ? 'background-color: #e8f5e9; color: #2e7d32; border: 1px solid #66bb6a;' : 'background-color: #ffebee; color: #c62828; border: 1px solid #ef5350;'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div> 
      <?php endif; ?>
      
      <form method="post" id="batchOrderForm">

        <div class="waiter-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="page-title" style="margin:0;">Our Menu</h2>
            <?php if ($_SESSION['role'] === 'waiter'): ?>
                <button type="submit" name="add_batch_order" class="batch-submit-btn" style="background-color: var(--primary); color: var(--text-dark); padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">
                    Add Selected (+0)
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($aiSuggestions) && $_SESSION['role'] === 'waiter'): ?>
        <div class="ai-suggestions-section">
            <div class="ai-suggestions-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="ai-icon">🤖</span>
                    <h3 class="ai-suggestions-title">AI Recommendations</h3>
                    <span class="ai-badge">Smart Suggestions</span>
                </div>
                <p class="ai-suggestions-subtitle">Most popular items customers love - perfect for recommendations!</p>
            </div>
            
            <div class="ai-suggestions-grid">
                <?php foreach ($aiSuggestions as $suggestion): ?>
                    <div class="ai-suggestion-card" data-item-id="<?php echo $suggestion['id']; ?>">
                        <div class="ai-badge-overlay">
                            <?php 
                            $badgeClass = 'popular';
                            if (strpos($suggestion['badge'], 'Trending') !== false) {
                                $badgeClass = 'trending';
                            } elseif (strpos($suggestion['badge'], 'New') !== false) {
                                $badgeClass = 'new';
                            }
                            ?>
                            <span class="ai-item-badge badge-<?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($suggestion['badge']); ?>
                            </span>
                        </div>
                        <img src="<?php echo htmlspecialchars($suggestion['image']); ?>" 
                             alt="<?php echo htmlspecialchars($suggestion['name']); ?>" 
                             class="ai-suggestion-image"
                             onclick="scrollToMenuItem(<?php echo $suggestion['id']; ?>)">
                        <div class="ai-suggestion-info">
                            <h4 class="ai-suggestion-name"><?php echo htmlspecialchars($suggestion['name']); ?></h4>
                            <p class="ai-suggestion-desc"><?php echo htmlspecialchars(substr($suggestion['description'], 0, 60)) . (strlen($suggestion['description']) > 60 ? '...' : ''); ?></p>
                            <div class="ai-suggestion-footer">
                                <span class="ai-suggestion-price">RM <?php echo number_format($suggestion['price'], 2); ?></span>
                                <?php if ($suggestion['total_quantity'] > 0): ?>
                                    <span class="ai-suggestion-stats">
                                        <ion-icon name="flame-outline"></ion-icon>
                                        <?php echo $suggestion['total_quantity']; ?> orders
                                    </span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="ai-quick-add-btn" onclick="quickAddSuggestion(<?php echo $suggestion['id']; ?>)">
                                <ion-icon name="add-outline"></ion-icon> Quick Add
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="category-filters">
            <button type="button" class="filter-btn active" data-filter="all">All</button>
            <?php foreach ($categories as $category): ?>
            <button type="button" class="filter-btn" data-filter="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div id="menu-grid">
            <?php if (empty($menuItems)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-light);">
                <p>No menu items available yet.</p>
            </div>
            <?php else: ?>
            <?php foreach ($menuItems as $item): ?>
                <article class="menu-item-card" data-category="<?php echo htmlspecialchars($item['category']); ?>" id="card-<?php echo $item['id']; ?>">
                
                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                
                <div class="item-info">
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                    
                    <div class="item-footer">
                    <span class="item-price">
                        RM <?php echo number_format($item['price'], 2); ?>
                    </span>
                    
                    <?php if ($_SESSION['role'] === 'waiter'): ?>
                        <div class="qty-control" style="display: flex; align-items: center; gap: 5px;">
                            <button type="button" class="qty-btn" onclick="updateQty(<?php echo $item['id']; ?>, -1)" style="padding: 5px 10px; cursor: pointer;">-</button>
                            
                            <input type="number" 
                                    name="items[<?php echo $item['id']; ?>]" 
                                    id="qty-<?php echo $item['id']; ?>" 
                                    value="0" 
                                    min="0" 
                                    class="qty-input"
                                    readonly
                                    style="width: 40px; text-align: center; border: 1px solid #ccc; border-radius: 4px; padding: 5px;">
                                    
                            <button type="button" class="qty-btn" onclick="updateQty(<?php echo $item['id']; ?>, 1)" style="padding: 5px 10px; cursor: pointer;">+</button>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
                </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

      </form>
      </div>

    <aside class="order-sidebar">
        <h3>Current Order</h3>
        <?php
            // Fetch current order items for the side panel
            $current_order_items = [];
            $current_order_id = null;
            $current_subtotal = 0;
            try {
                $sql_find_order = "SELECT id, total_amount FROM orders WHERE table_id = ? AND status IN ('pending', 'prepared') LIMIT 1";
                $stmt_find_order = $pdo->prepare($sql_find_order);
                $stmt_find_order->execute([$_SESSION['selected_table_id']]);
                $current_order = $stmt_find_order->fetch();

                if ($current_order) {
                    $current_order_id = $current_order['id'];
                    $current_subtotal = $current_order['total_amount'];

                    $sql_get_items = "SELECT oi.id, oi.quantity, mi.name 
                                      FROM order_items oi 
                                      JOIN menu_items mi ON oi.menu_item_id = mi.id 
                                      WHERE oi.order_id = ?";
                    $stmt_get_items = $pdo->prepare($sql_get_items);
                    $stmt_get_items->execute([$current_order_id]);
                    $current_order_items = $stmt_get_items->fetchAll();
                }
            } catch (PDOException $e) {
                echo "<p>Error loading order.</p>";
            }
        ?>

        <?php if (empty($current_order_items)): ?>
            <p class="no-items-message">No items in this order yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($current_order_items as $order_item): ?>
                    <li>
                        <span><?php echo htmlspecialchars($order_item['name']); ?></span>
                        <div class="quantity-control">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="order_item_id" value="<?php echo $order_item['id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $current_order_id; ?>">
                            </form>
                            
                            <span class="quantity-display"><?php echo $order_item['quantity']; ?></span>
                            
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="order_item_id" value="<?php echo $order_item['id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $current_order_id; ?>">
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="subtotal-display">
                Subtotal: <span>RM <?php echo number_format($current_subtotal, 2); ?></span>
            </div>
            
            <div class="sidebar-actions">
                <form method="post" class="finalize-form">
                    <input type="hidden" name="order_id" value="<?php echo $current_order_id; ?>">
                    <button type="submit" name="finalize_order" class="finalize-order-btn">Confirm Order</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter Logic
    const filterBtns = document.querySelectorAll('.filter-btn');
    const menuItems = document.querySelectorAll('.menu-item-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filterValue = btn.getAttribute('data-filter');

            menuItems.forEach(item => {
                if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                    item.style.display = ''; // Reset to default
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// Batch Quantity Logic
function updateQty(itemId, change) {
    const input = document.getElementById('qty-' + itemId);
    const card = document.getElementById('card-' + itemId);
    let currentVal = parseInt(input.value) || 0;
    let newVal = currentVal + change;

    if (newVal < 0) newVal = 0;
    
    input.value = newVal;

    // Update Visuals (Highlight card)
    // Note: CSS for .selected needs to be in menu.css or added inline if missing
    if (newVal > 0) {
        card.classList.add('selected');
        card.style.borderColor = 'var(--primary)'; // Inline fallback
        card.style.backgroundColor = '#f0fdf4';   // Inline fallback
    } else {
        card.classList.remove('selected');
        card.style.borderColor = 'transparent'; // Inline fallback
        card.style.backgroundColor = '#fff';    // Inline fallback
    }

    updateTotalButton();
}

function updateTotalButton() {
    const inputs = document.querySelectorAll('.qty-input');
    let totalItems = 0;
    
    inputs.forEach(input => {
        totalItems += parseInt(input.value) || 0;
    });

    const submitBtn = document.querySelector('.batch-submit-btn');
    if (submitBtn) {
        submitBtn.innerText = `Add Selected (+${totalItems})`;
        
        if (totalItems === 0) {
            submitBtn.style.opacity = "0.7";
        } else {
            submitBtn.style.opacity = "1";
        }
    }
}

// AI Suggestions Functions
function scrollToMenuItem(itemId) {
    const card = document.getElementById('card-' + itemId);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Highlight the card temporarily
        card.style.transition = 'all 0.3s ease';
        card.style.transform = 'scale(1.05)';
        card.style.boxShadow = '0 8px 20px rgba(255, 159, 28, 0.4)';
        setTimeout(() => {
            card.style.transform = 'scale(1)';
            card.style.boxShadow = '';
        }, 1000);
    }
}

function quickAddSuggestion(itemId) {
    const input = document.getElementById('qty-' + itemId);
    if (input) {
        updateQty(itemId, 1);
        // Scroll to item to show it was added
        setTimeout(() => {
            scrollToMenuItem(itemId);
        }, 100);
    }
}
</script>