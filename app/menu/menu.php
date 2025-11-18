<?php 
session_start();
require_once '../connection.php';

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

// --- HANDLE BATCH ADD TO ORDER (For Staff) ---
if (isset($_POST['add_batch_order']) && isset($_POST['items']) && $_SESSION['role'] === 'staff') {
    $items = $_POST['items']; // Array format: [menu_item_id => quantity]
    $table_id = $_SESSION['selected_table_id'];
    $items_added_count = 0;
    
    try {
        $pdo->beginTransaction();

        // 1. Check or Create Order (Do this once)
        $sql_order = "SELECT * FROM orders WHERE table_id = ? AND status = 'pending' LIMIT 1";
        $stmt_order = $pdo->prepare($sql_order);
        $stmt_order->execute([$table_id]);
        $order = $stmt_order->fetch();
        
        if (!$order) {
            // Create new order
            $sql_new_order = "INSERT INTO orders (table_id, user_id, status) VALUES (?, ?, 'pending')";
            $stmt_new_order = $pdo->prepare($sql_new_order);
            $stmt_new_order->execute([$table_id, $_SESSION['user_id']]);
            $order_id = $pdo->lastInsertId();
            
            // Update table status
            $stmt_update_table = $pdo->prepare("UPDATE dining_tables SET status = 'occupied' WHERE id = ?");
            $stmt_update_table->execute([$table_id]);
        } else {
            $order_id = $order['id'];
        }

        // 2. Loop through submitted items
        foreach ($items as $item_id => $qty) {
            $qty = (int)$qty;
            
            // Only process if quantity is greater than 0
            if ($qty > 0) {
                // Fetch price for this specific item
                $stmt_price = $pdo->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmt_price->execute([$item_id]);
                $price_data = $stmt_price->fetch();

                if ($price_data) {
                    // Check if item already in order
                    $stmt_check = $pdo->prepare("SELECT id, quantity FROM order_items WHERE order_id = ? AND menu_item_id = ?");
                    $stmt_check->execute([$order_id, $item_id]);
                    $existing = $stmt_check->fetch();

                    if ($existing) {
                        // Update quantity
                        $new_qty = $existing['quantity'] + $qty;
                        $stmt_upd = $pdo->prepare("UPDATE order_items SET quantity = ? WHERE id = ?");
                        $stmt_upd->execute([$new_qty, $existing['id']]);
                    } else {
                        // Insert new line item
                        $stmt_ins = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $stmt_ins->execute([$order_id, $item_id, $qty, $price_data['price']]);
                    }
                    $items_added_count++;
                }
            }
        }

        $pdo->commit();
        
        if ($items_added_count > 0) {
            $message = "Successfully added $items_added_count items to the order!";
            $message_type = "success";
        } else {
            $message = "No items were selected (Quantity was 0).";
            $message_type = "error";
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error processing order: " . $e->getMessage();
        $message_type = "error";
    }
}

$pageTitle = "Menu"; 
$basePath = "../";
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
        $item['image'] = $item['image_url'] ? '../' . $item['image_url'] : 'https://via.placeholder.com/150.png?text=No+Image';
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
?>

<link rel="stylesheet" href="../css/menu.css">

<main class="main-wrapper">
  <div class="app-container">
    
    <div style="text-align: center; margin-bottom: 20px;">
      <span style="background-color: var(--fourth); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--text-dark);">
        Table: <?php echo htmlspecialchars($_SESSION['selected_table_number']); ?>
      </span>
      <?php if ($_SESSION['role'] === 'staff'): ?>
        <a href="../order/order.php" style="display: inline-block; margin-left: 15px; padding: 8px 16px; background-color: var(--primary); color: var(--text-dark); text-decoration: none; border-radius: 20px; font-weight: 600;">
          View Current Order
        </a>
      <?php endif; ?>
    </div>
    
    <?php if (!empty($message)): ?>
      <div class="message <?php echo $message_type; ?>" style="max-width: 600px; margin: 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; <?php echo $message_type === 'success' ? 'background-color: #e8f5e9; color: #2e7d32; border: 1px solid #66bb6a;' : 'background-color: #ffebee; color: #c62828; border: 1px solid #ef5350;'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'staff'): ?>
    <form method="post" id="batchOrderForm">
        
        <div class="staff-action-bar">
            <h2 class="page-title" style="margin:0; font-size: 1.5rem;">Our Menu</h2>
            <button type="submit" name="add_batch_order" class="batch-submit-btn">
                Add Selected Items (+0)
            </button>
        </div>
    <?php else: ?>
        <h2 class="page-title">Our Menu</h2>
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
            
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            
            <div class="item-info">
              <h3><?php echo htmlspecialchars($item['name']); ?></h3>
              <p><?php echo htmlspecialchars($item['description']); ?></p>
              
              <div class="item-footer">
                <span class="item-price">
                  RM <?php echo number_format($item['price'], 2); ?>
                </span>
                
                <?php if ($_SESSION['role'] === 'staff'): ?>
                  <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="updateQty(<?php echo $item['id']; ?>, -1)">-</button>
                    <input type="number" 
                           name="items[<?php echo $item['id']; ?>]" 
                           id="qty-<?php echo $item['id']; ?>" 
                           value="0" 
                           min="0" 
                           class="qty-input"
                           readonly>
                    <button type="button" class="qty-btn" onclick="updateQty(<?php echo $item['id']; ?>, 1)">+</button>
                  </div>
                <?php else: ?>
                  <button class="add-to-cart-btn" 
                    data-id="<?php echo $item['id']; ?>"
                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                    data-price="<?php echo $item['price']; ?>">
                    +
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($_SESSION['role'] === 'staff'): ?>
    </form> <?php endif; ?>

  </div>
</main>

<script>
// Existing Filter Logic
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const menuItems = document.querySelectorAll('.menu-item-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            btn.classList.add('active');

            const filterValue = btn.getAttribute('data-filter');

            menuItems.forEach(item => {
                if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// --- NEW: Staff Quantity Logic ---
function updateQty(itemId, change) {
    const input = document.getElementById('qty-' + itemId);
    const card = document.getElementById('card-' + itemId);
    let currentVal = parseInt(input.value) || 0;
    let newVal = currentVal + change;

    if (newVal < 0) newVal = 0;
    
    input.value = newVal;

    // Update Visuals (Highlight card)
    if (newVal > 0) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
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
        submitBtn.innerText = `Add Selected Items (${totalItems})`;
        
        // Optional: visually disable if 0
        if (totalItems === 0) {
            submitBtn.style.opacity = "0.7";
        } else {
            submitBtn.style.opacity = "1";
        }
    }
}
</script>