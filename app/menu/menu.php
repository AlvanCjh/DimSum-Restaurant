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
    </div>
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
                
                <button class="add-to-cart-btn" 
                  data-id="<?php echo $item['id']; ?>"
                  data-name="<?php echo htmlspecialchars($item['name']); ?>"
                  data-price="<?php echo $item['price']; ?>">
                  +
                </button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</main>