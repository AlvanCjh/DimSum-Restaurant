<?php 
session_start();
require_once '../connection.php'; 

// --- 1. ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}
if (!isset($_SESSION['selected_table_id'])) {
    header("Location: ../table/table.php");
    exit;
}

$message = "";
$message_type = "";
$basePath = "../"; 

// --- 2. HELPER FUNCTIONS ---
if (!function_exists('recalculateOrderTotal')) {
    function recalculateOrderTotal($pdo, $order_id) {
        $stmt = $pdo->prepare("SELECT SUM(price * quantity) FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $total = $stmt->fetchColumn() ?: 0.00;
        $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $stmt->execute([$total, $order_id]);
        return $total;
    }
}

// --- 3. SIDEBAR ACTIONS (Modify Saved Items) ---
// Decrease Saved Item
if (isset($_POST['decrease_quantity']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $order_id_to_update = (int)$_POST['order_id'];
    try {
        $stmt = $pdo->prepare("SELECT quantity FROM order_items WHERE id = ?");
        $stmt->execute([$order_item_id]);
        $item = $stmt->fetch();
        if ($item) {
            if ($item['quantity'] > 1) {
                $pdo->prepare("UPDATE order_items SET quantity = quantity - 1 WHERE id = ?")->execute([$order_item_id]);
            } else {
                $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$order_item_id]);
            }
            recalculateOrderTotal($pdo, $order_id_to_update);
        }
    } catch (PDOException $e) {}
}

// Increase Saved Item
if (isset($_POST['increase_quantity']) && isset($_POST['order_item_id'])) {
    $order_item_id = (int)$_POST['order_item_id'];
    $order_id_to_update = (int)$_POST['order_id'];
    try {
        $pdo->prepare("UPDATE order_items SET quantity = quantity + 1 WHERE id = ?")->execute([$order_item_id]);
        recalculateOrderTotal($pdo, $order_id_to_update);
    } catch (PDOException $e) {}
}

// --- 4. CONFIRM DRAFT ORDER (Add New Items) ---
if (isset($_POST['confirm_draft_order']) && isset($_POST['draft_items'])) {
    $items = json_decode($_POST['draft_items'], true);
    $table_id = $_SESSION['selected_table_id'];
    $items_added_count = 0;
    
    if (is_array($items) && count($items) > 0) {
        try {
            $pdo->beginTransaction();

            // Find or Create Order
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE table_id = ? AND status IN ('pending', 'prepared') LIMIT 1");
            $stmt->execute([$table_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $pdo->prepare("INSERT INTO orders (table_id, user_id, status) VALUES (?, ?, 'pending')")->execute([$table_id, $_SESSION['user_id']]);
                $order_id = $pdo->lastInsertId();
                $pdo->prepare("UPDATE dining_tables SET status = 'occupied' WHERE id = ?")->execute([$table_id]);
            } else {
                $order_id = $order['id'];
                if ($order['status'] === 'prepared') {
                    $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ?")->execute([$order_id]);
                }
            }

            // Process Items
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
                            $pdo->prepare("UPDATE order_items SET quantity = ?, prepared_at = NULL WHERE id = ?")->execute([$new_qty, $existing['id']]);
                        } else {
                            $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)")->execute([$order_id, $item_id, $qty, $price_data['price']]);
                        }
                        $items_added_count++;
                    }
                }
            }
            recalculateOrderTotal($pdo, $order_id);
            $pdo->commit();
            
            if ($items_added_count > 0) {
                $message = "Order updated successfully!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

$pageTitle = "Menu"; 
include '../_header.php'; 

// --- 5. FETCH DATA (STRICT DUPLICATE REMOVER) ---
$menuItems = [];
try {
    // Fetch everything raw first
    $stmt = $pdo->prepare("
        SELECT mi.*, mc.name as category_name 
        FROM menu_items mi 
        LEFT JOIN menu_categories mc ON mi.category_id = mc.id 
        ORDER BY mi.category_id ASC, mi.id DESC
    ");
    $stmt->execute();
    $rawItems = $stmt->fetchAll();

    // STRICT PHP FILTERING: Only allow unique names
    $seenNames = [];
    foreach ($rawItems as $item) {
        $nameKey = strtolower(trim($item['name'])); // Normalize name
        
        if (!in_array($nameKey, $seenNames)) {
            // Fix Image Path
            $item['image'] = $item['image_url'] 
                ? $basePath . $item['image_url'] 
                : 'https://via.placeholder.com/280x200.png?text=No+Image';
            
            // Fix Null Category
            $item['category_name'] = $item['category_name'] ?: 'Uncategorized';
            
            // Add to final list
            $menuItems[] = $item;
            $seenNames[] = $nameKey;
        }
    }
} catch (PDOException $e) { 
    $menuItems = [];
}

// Fetch Categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT name FROM menu_categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { }

// Fetch AI Suggestions
$aiSuggestions = [];
try {
    $command = "python ../ai/menu_suggestions.py";
    $output = shell_exec($command);
    $data = json_decode($output, true);
    if (isset($data['suggestions'])) {
        $aiSuggestions = $data['suggestions'];
        foreach ($aiSuggestions as &$sug) {
            $sug['image'] = !empty($sug['image_url']) ? $basePath . $sug['image_url'] : 'https://via.placeholder.com/280x200';
        }
    }
} catch (Exception $e) { }

// Fetch Current Order
$current_order_items = [];
$current_order_id = null;
$current_subtotal = 0;
try {
    $stmt = $pdo->prepare("SELECT id, total_amount FROM orders WHERE table_id = ? AND status IN ('pending', 'prepared') LIMIT 1");
    $stmt->execute([$_SESSION['selected_table_id']]);
    $curr = $stmt->fetch();
    if ($curr) {
        $current_order_id = $curr['id'];
        $current_subtotal = $curr['total_amount'];
        $stmt_items = $pdo->prepare("SELECT oi.id, oi.quantity, mi.name, oi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
        $stmt_items->execute([$current_order_id]);
        $current_order_items = $stmt_items->fetchAll();
    }
} catch (PDOException $e) { }
?>

<link rel="stylesheet" href="../css/menu.css">

<main class="main-wrapper" style="padding-top: 100px;">
  <div class="menu-page-layout">
    
    <div class="menu-main-content">
      
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <a href="../table/table.php" class="back-link">← Change Table</a>
          <span style="background-color: var(--fourth); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--text-dark);">
            Table: <?php echo htmlspecialchars($_SESSION['selected_table_number']); ?>
          </span>
      </div>
      
      <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type === 'success' ? 'msg-success' : 'msg-error'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div> 
      <?php endif; ?>
      
      <div class="waiter-action-bar">
          <h2 class="page-title" style="margin:0;">Our Menu</h2>
      </div>

      <?php if (!empty($aiSuggestions)): ?>
      <div class="ai-suggestions-section" id="aiSection">
          <div class="ai-suggestions-header" onclick="toggleAI()">
              <div style="display: flex; align-items: center; gap: 10px; flex-grow: 1;">
                  <span class="ai-icon">🤖</span>
                  <h3 class="ai-suggestions-title">AI Recommendations</h3>
                  <span class="ai-badge">Smart Suggestions</span>
              </div>
              <div class="ai-toggle-icon"><ion-icon name="chevron-up-outline" id="aiToggleIcon"></ion-icon></div>
          </div>
          
          <div class="ai-content-wrapper" id="aiContent">
              <div class="ai-suggestions-grid">
                  <?php foreach ($aiSuggestions as $suggestion): ?>
                      <div class="ai-suggestion-card" onclick="adjustDraft(<?php echo $suggestion['id']; ?>, '<?php echo addslashes($suggestion['name']); ?>', <?php echo $suggestion['price']; ?>, 1)">
                          <div class="ai-badge-overlay">
                              <span class="ai-item-badge badge-popular"><?php echo htmlspecialchars($suggestion['badge']); ?></span>
                          </div>
                          <img src="<?php echo htmlspecialchars($suggestion['image']); ?>" class="ai-suggestion-image">
                          <div class="ai-suggestion-info">
                              <h4 class="ai-suggestion-name"><?php echo htmlspecialchars($suggestion['name']); ?></h4>
                              <div class="ai-suggestion-footer">
                                  <span class="ai-suggestion-price">RM <?php echo number_format($suggestion['price'], 2); ?></span>
                              </div>
                              <button type="button" class="ai-quick-add-btn"><ion-icon name="add-outline"></ion-icon> Quick Add</button>
                          </div>
                      </div>
                  <?php endforeach; ?>
              </div>
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
              <p style="text-align:center; width:100%; padding:20px; color:#666;">No items found.</p>
          <?php else: ?>
              <?php foreach ($menuItems as $item): ?>
                  <article class="menu-item-card" data-category="<?php echo htmlspecialchars($item['category_name']); ?>" id="card-<?php echo $item['id']; ?>">
                      <img src="<?php echo htmlspecialchars($item['image']); ?>" class="item-image" loading="lazy">
                      <div class="item-info">
                          <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                          <p><?php echo htmlspecialchars($item['description']); ?></p>
                          
                          <div class="item-footer">
                              <span class="item-price">RM <?php echo number_format($item['price'], 2); ?></span>
                              
                              <div class="qty-control">
                                  <button type="button" class="qty-btn" onclick="adjustDraft(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>, -1)">-</button>
                                  <input type="number" id="disp-qty-<?php echo $item['id']; ?>" value="0" class="qty-input" readonly>
                                  <button type="button" class="qty-btn" onclick="adjustDraft(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>, 1)">+</button>
                              </div>
                          </div>
                      </div>
                  </article>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
    </div>

    <aside class="order-sidebar">
        <h3>Current Order</h3>
        
      <div class="saved-items-container">
            <?php if (!empty($current_order_items)): ?>
                <h4 class="sidebar-subhead">Sent to Kitchen</h4>
                <ul class="saved-list">
                    <?php foreach ($current_order_items as $order_item): ?>
                        <li>
                            <span><?php echo htmlspecialchars($order_item['name']); ?></span>
                            <div class="quantity-control small-control">
                                
                                <button type="button" class="qty-btn disabled" disabled style="opacity: 0.3; cursor: not-allowed;">-</button>
                                
                                <span class="quantity-display"><?php echo $order_item['quantity']; ?></span>
                                
                                <button type="button" class="qty-btn disabled" disabled style="opacity: 0.3; cursor: not-allowed;">+</button>
                                
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div id="draft-items-container" style="display:none; border-top: 2px dashed #E2E8F0; margin-top: 15px; padding-top: 15px;">
            <h4 class="sidebar-subhead" style="color: var(--primary);">New Items</h4>
            <ul id="draft-list" class="draft-list"></ul>
        </div>

        <div class="subtotal-display">
            Subtotal: <span id="grand-total">RM <?php echo number_format($current_subtotal, 2); ?></span>
        </div>
        
        <div class="sidebar-actions">
            <form method="post" id="draftForm">
                <input type="hidden" name="draft_items" id="draftItemsInput">
                <input type="hidden" name="confirm_draft_order" value="1">
                <button type="button" onclick="submitDraftOrder()" class="finalize-order-btn" id="confirmBtn" style="opacity: 0.5; pointer-events: none;">
                    Confirm Order
                </button>
            </form>
        </div>
    </aside>

  </div>
</main>

<script>
// --- DRAFT LOGIC ---
let draftCart = {};
const savedTotal = <?php echo $current_subtotal ? $current_subtotal : 0; ?>;

function adjustDraft(id, name, price, change) {
    if (!draftCart[id]) draftCart[id] = { name: name, price: price, qty: 0 };
    draftCart[id].qty += change;

    if (draftCart[id].qty <= 0) {
        delete draftCart[id];
        updateCardUI(id, 0, false);
    } else {
        updateCardUI(id, draftCart[id].qty, true);
    }
    renderSidebar();
}

function updateCardUI(id, qty, isSelected) {
    const disp = document.getElementById('disp-qty-' + id);
    const card = document.getElementById('card-' + id);
    if(disp) disp.value = qty;
    if(card) {
        if(isSelected) {
            card.classList.add('selected');
            card.style.borderColor = 'var(--primary)'; 
            card.style.backgroundColor = '#f0fdf4';
        } else {
            card.classList.remove('selected');
            card.style.borderColor = 'transparent'; 
            card.style.backgroundColor = '#fff';
        }
    }
}

function renderSidebar() {
    const list = document.getElementById('draft-list');
    const container = document.getElementById('draft-items-container');
    const totalEl = document.getElementById('grand-total');
    const btn = document.getElementById('confirmBtn');
    
    list.innerHTML = '';
    let draftTotal = 0;
    let hasDraft = false;

    for (const [id, item] of Object.entries(draftCart)) {
        hasDraft = true;
        draftTotal += (item.price * item.qty);
        list.innerHTML += `
            <li>
                <span>${item.name}</span>
                <div class="quantity-control small-control">
                    <button type="button" class="qty-btn" onclick="adjustDraft(${id}, '${item.name}', ${item.price}, -1)">-</button>
                    <span class="quantity-display">${item.qty}</span>
                    <button type="button" class="qty-btn" onclick="adjustDraft(${id}, '${item.name}', ${item.price}, 1)">+</button>
                </div>
            </li>`;
    }

    container.style.display = hasDraft ? 'block' : 'none';
    const finalTotal = savedTotal + draftTotal;
    totalEl.innerText = "RM " + finalTotal.toFixed(2);

    if (hasDraft) {
        btn.style.opacity = "1";
        btn.style.pointerEvents = "auto";
    } else {
        btn.style.opacity = "0.5";
        btn.style.pointerEvents = "none";
    }
}

function submitDraftOrder() {
    let simpleCart = {};
    for (const [id, item] of Object.entries(draftCart)) {
        simpleCart[id] = item.qty;
    }
    document.getElementById('draftItemsInput').value = JSON.stringify(simpleCart);
    document.getElementById('draftForm').submit();
}

// --- AI DROPDOWN ---
function toggleAI() {
    const content = document.getElementById('aiContent');
    const icon = document.getElementById('aiToggleIcon');
    if (content.classList.contains('open')) {
        content.classList.remove('open');
        content.style.maxHeight = null;
        icon.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('open');
        content.style.maxHeight = content.scrollHeight + "px";
        icon.style.transform = 'rotate(180deg)';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Start Open
    const content = document.getElementById('aiContent');
    const icon = document.getElementById('aiToggleIcon');
    if(content) {
        content.classList.add('open');
        content.style.maxHeight = content.scrollHeight + "px";
        icon.style.transform = 'rotate(180deg)';
    }
    
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
                    item.style.display = ''; 
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});
</script>