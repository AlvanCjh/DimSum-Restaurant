<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Manage Menu";
$basePath = "../";
include '../_header.php';

$message = "";
$message_type = "";

// Handle category addition
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_description = trim($_POST['category_description']);
    
    if (!empty($category_name)) {
        try {
            $sql = "INSERT INTO menu_categories (name, description) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_name, $category_description]);
            $message = "Category added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                $message = "Category name already exists!";
            } else {
                $message = "Error adding category: " . $e->getMessage();
            }
            $message_type = "error";
        }
    } else {
        $message = "Category name is required!";
        $message_type = "error";
    }
}

// Handle menu item addition
if (isset($_POST['add_menu_item'])) {
    $name = trim($_POST['item_name']);
    $description = trim($_POST['item_description']);
    $price = floatval($_POST['item_price']);
    $category_id = !empty($_POST['item_category']) ? (int)$_POST['item_category'] : null;
    $image_url = null;
    
    // Handle image upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/menu/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('menu_', true) . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/menu/' . $new_filename;
            } else {
                $message = "Error uploading image!";
                $message_type = "error";
            }
        } else {
            $message = "Invalid image format! Allowed: " . implode(', ', $allowed_extensions);
            $message_type = "error";
        }
    }
    
    if (empty($message) && !empty($name) && $price > 0) {
        try {
            $sql = "INSERT INTO menu_items (category_id, name, description, price, image_url) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category_id, $name, $description, $price, $image_url]);
            $message = "Menu item added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error adding menu item: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif (empty($message)) {
        $message = "Please fill in all required fields!";
        $message_type = "error";
    }
}

// Fetch all categories
try {
    $sql_categories = "SELECT * FROM menu_categories ORDER BY name ASC";
    $stmt_categories = $pdo->query($sql_categories);
    $categories = $stmt_categories->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Fetch all menu items with category names
try {
    $sql_items = "SELECT mi.*, mc.name as category_name 
                  FROM menu_items mi 
                  LEFT JOIN menu_categories mc ON mi.category_id = mc.id 
                  ORDER BY mi.id DESC";
    $stmt_items = $pdo->query($sql_items);
    $menu_items = $stmt_items->fetchAll();
} catch (PDOException $e) {
    $menu_items = [];
}
?>

<main class="main-wrapper">
    <div class="admin-menu-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>Manage Menu</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="two-column-layout">
            <!-- Add Category Form -->
            <div class="form-section">
                <h2 class="section-title">Add New Category</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="category_name">Category Name *</label>
                        <input type="text" id="category_name" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="category_description">Description</label>
                        <textarea id="category_description" name="category_description"></textarea>
                    </div>
                    <button type="submit" name="add_category" class="submit-btn">Add Category</button>
                </form>
            </div>

            <!-- Add Menu Item Form -->
            <div class="form-section">
                <h2 class="section-title">Add New Menu Item</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="item_name">Item Name *</label>
                        <input type="text" id="item_name" name="item_name" required>
                    </div>
                    <div class="form-group">
                        <label for="item_description">Description</label>
                        <textarea id="item_description" name="item_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="item_price">Price (RM) *</label>
                        <input type="number" id="item_price" name="item_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="item_category">Category</label>
                        <select id="item_category" name="item_category">
                            <option value="">No Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item_image">Image</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*">
                    </div>
                    <button type="submit" name="add_menu_item" class="submit-btn">Add Menu Item</button>
                </form>
            </div>
        </div>

        <!-- Display Existing Menu Items -->
        <div class="menu-items-section">
            <h2 class="section-title">Existing Menu Items (<?php echo count($menu_items); ?>)</h2>
            <div class="items-grid">
                <?php if (empty($menu_items)): ?>
                    <p style="text-align: center; color: var(--text-light); grid-column: 1 / -1;">
                        No menu items yet. Add your first item above!
                    </p>
                <?php else: ?>
                    <?php foreach ($menu_items as $item): ?>
                        <div class="menu-item-card">
                            <?php if ($item['image_url']): ?>
                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/250x150?text=No+Image" 
                                     alt="No image">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <?php if ($item['category_name']): ?>
                                <span class="category-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                            <?php endif; ?>
                            <p style="color: var(--text-light); font-size: 0.9rem; margin: 8px 0;">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                            <div class="price">RM <?php echo number_format($item['price'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>



