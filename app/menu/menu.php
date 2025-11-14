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
  
  // Handle adding item to order (for staff)
  if (isset($_POST['add_to_order']) && isset($_POST['menu_item_id']) && $_SESSION['role'] === 'staff') {
      $menu_item_id = (int)$_POST['menu_item_id'];
      $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
      $table_id = $_SESSION['selected_table_id'];
      
      try {
          // Get menu item price
          $sql_item = "SELECT price FROM menu_items WHERE id = ?";
          $stmt_item = $pdo->prepare($sql_item);
          $stmt_item->execute([$menu_item_id]);
          $menu_item = $stmt_item->fetch();
          
          if ($menu_item) {
              // Check if order exists for this table
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
                  
                  // Update table status to occupied
                  $sql_update_table = "UPDATE dining_tables SET status = 'occupied' WHERE id = ?";
                  $stmt_update_table = $pdo->prepare($sql_update_table);
                  $stmt_update_table->execute([$table_id]);
              } else {
                  $order_id = $order['id'];
              }
              
              // Check if item already exists in order
              $sql_check = "SELECT * FROM order_items WHERE order_id = ? AND menu_item_id = ?";
              $stmt_check = $pdo->prepare($sql_check);
              $stmt_check->execute([$order_id, $menu_item_id]);
              $existing_item = $stmt_check->fetch();
              
              if ($existing_item) {
                  // Update quantity
                  $new_quantity = $existing_item['quantity'] + $quantity;
                  $sql_update = "UPDATE order_items SET quantity = ? WHERE id = ?";
                  $stmt_update = $pdo->prepare($sql_update);
                  $stmt_update->execute([$new_quantity, $existing_item['id']]);
              } else {
                  // Add new item
                  $sql_add = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)";
                  $stmt_add = $pdo->prepare($sql_add);
                  $stmt_add->execute([$order_id, $menu_item_id, $quantity, $menu_item['price']]);
              }
              
              $message = "Item added to order successfully!";
              $message_type = "success";
          }
      } catch (PDOException $e) {
          $message = "Error adding item: " . $e->getMessage();
          $message_type = "error";
      }
  }
  
  $pageTitle = "Menu"; 
  $basePath = "../";
  include '../_header.php'; 

  // Fetch menu items from database
  try {
      $sql = "SELECT mi.*, mc.name as category_name 
              FROM menu_items mi 
              LEFT JOIN menu_categories mc ON mi.category_id = mc.id 
              ORDER BY mi.id DESC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
      $menuItems = $stmt->fetchAll();
      
      // Transform data to match expected format
      foreach ($menuItems as &$item) {
          $item['category'] = $item['category_name'] ? $item['category_name'] : 'Uncategorized';
          $item['image'] = $item['image_url'] ? '../' . $item['image_url'] : 'https://via.placeholder.com/150.png?text=No+Image';
      }
      unset($item);
  } catch (PDOException $e) {
      $menuItems = [];
      $error_message = "Error loading menu: " . $e->getMessage();
  }
  
  // Fetch all categories for filter buttons
  try {
      $sql_categories = "SELECT DISTINCT mc.name 
                        FROM menu_categories mc 
                        INNER JOIN menu_items mi ON mc.id = mi.category_id 
                        ORDER BY mc.name ASC";
      $stmt_categories = $pdo->prepare($sql_categories);
      $stmt_categories->execute();
      $categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);
  } catch (PDOException $e) {
      $categories = [];
  }
?>

<main class="main-wrapper">

  <div class="app-container">
    <div style="text-align: center; margin-bottom: 20px;">
      <span style="background-color: var(--fourth); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--text-dark);">
        Table: <?php echo htmlspecialchars($_SESSION['selected_table_number']); ?>
      </span>
      <?php if ($_SESSION['role'] === 'staff'): ?>
        <a href="../order/order.php" style="display: inline-block; margin-left: 15px; padding: 8px 16px; background-color: var(--primary); color: var(--text-dark); text-decoration: none; border-radius: 20px; font-weight: 600;">
          View Order
        </a>
      <?php endif; ?>
    </div>
    
    <?php if (!empty($message)): ?>
      <div class="message <?php echo $message_type; ?>" style="max-width: 600px; margin: 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-weight: 500; <?php echo $message_type === 'success' ? 'background-color: #e8f5e9; color: #2e7d32; border: 1px solid #66bb6a;' : 'background-color: #ffebee; color: #c62828; border: 1px solid #ef5350;'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>
    
    <h2 class="page-title">Our Menu</h2>

    <div class="category-filters">
      <button class="filter-btn active" data-filter="all">All</button>
      <?php foreach ($categories as $category): ?>
        <button class="filter-btn" data-filter="<?php echo htmlspecialchars($category); ?>">
          <?php echo htmlspecialchars($category); ?>
        </button>
      <?php endforeach; ?>
      <?php if (empty($categories)): ?>
        <p style="text-align: center; color: var(--text-light); margin-top: 20px;">
          No categories available. Menu items will be displayed below.
        </p>
      <?php endif; ?>
    </div>

    <div id="menu-grid">
      <?php if (empty($menuItems)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-light);">
          <p style="font-size: 1.2rem; margin-bottom: 10px;">No menu items available yet.</p>
          <p>Please check back later or contact the administrator.</p>
        </div>
      <?php else: ?>
        <?php foreach ($menuItems as $item): ?>
          <article class="menu-item-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
            
            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            
            <div class="item-info">
              <h3><?php echo htmlspecialchars($item['name']); ?></h3>
              <p><?php echo htmlspecialchars($item['description']); ?></p>
              
              <div class="item-footer">
                <span class="item-price">
                  RM <?php echo number_format($item['price'], 2); ?>
                </span>
                
                <?php if ($_SESSION['role'] === 'staff'): ?>
                  <!-- Staff: Add to order form -->
                  <form method="post" style="display: inline-block;">
                    <input type="hidden" name="menu_item_id" value="<?php echo $item['id']; ?>">
                    <input type="number" name="quantity" value="1" min="1" style="width: 50px; padding: 4px; margin-right: 5px; border: 1px solid var(--border-color); border-radius: 4px; text-align: center;">
                    <button type="submit" name="add_to_order" class="add-to-cart-btn" style="padding: 8px 15px;">
                      Add to Order
                    </button>
                  </form>
                <?php else: ?>
                  <!-- Customer: Add to cart button (JavaScript) -->
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
  </div>

</main>
