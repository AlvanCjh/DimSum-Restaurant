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

// --- HELPER FUNCTION: SAVE BASE64 IMAGE ---
function saveBase64Image($base64_string, $output_dir) {
    if (empty($base64_string)) return null;
    
    $data = explode(',', $base64_string);
    $image_data = base64_decode($data[1]);
    
    if (!is_dir($output_dir)) {
        mkdir($output_dir, 0777, true);
    }
    
    $filename = uniqid('menu_', true) . '.png'; // Save as PNG
    $filepath = $output_dir . $filename;
    
    if (file_put_contents($filepath, $image_data)) {
        return 'uploads/menu/' . $filename;
    }
    return null;
}

// --- HANDLE DELETE ITEM ---
if (isset($_POST['delete_item'])) {
    $item_id = (int)$_POST['item_id'];
    try {
        // Optional: Delete the image file from server before deleting record
        $stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $img = $stmt->fetchColumn();
        if ($img && file_exists("../" . $img)) {
            unlink("../" . $img);
        }

        $sql = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_id]);
        $message = "Item deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting item: " . $e->getMessage();
        $message_type = "error";
    }
}

// --- HANDLE ADD CATEGORY ---
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
            $message = ($e->getCode() == 23000) ? "Category name already exists!" : "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// --- HANDLE ADD / EDIT MENU ITEM ---
if (isset($_POST['save_menu_item'])) {
    $is_edit = !empty($_POST['item_id']);
    $item_id = $is_edit ? (int)$_POST['item_id'] : null;
    
    $name = trim($_POST['item_name']);
    $description = trim($_POST['item_description']);
    $price = floatval($_POST['item_price']);
    $category_id = !empty($_POST['item_category']) ? (int)$_POST['item_category'] : null;
    $cropped_image = $_POST['cropped_image_data']; // This is the base64 string
    
    if (!empty($name) && $price > 0) {
        try {
            $image_url = null;
            
            // Process Image if a new one was cropped
            if (!empty($cropped_image)) {
                $image_url = saveBase64Image($cropped_image, '../uploads/menu/');
            }

            if ($is_edit) {
                // Update Logic
                $sql = "UPDATE menu_items SET category_id=?, name=?, description=?, price=?";
                $params = [$category_id, $name, $description, $price];
                
                // Only update image URL if a new one was uploaded
                if ($image_url) {
                    $sql .= ", image_url=?";
                    $params[] = $image_url;
                    
                    // Optional: Delete old image
                    $old_img_stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE id = ?");
                    $old_img_stmt->execute([$item_id]);
                    $old_img = $old_img_stmt->fetchColumn();
                    if ($old_img && file_exists("../" . $old_img)) unlink("../" . $old_img);
                }
                
                $sql .= " WHERE id=?";
                $params[] = $item_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "Item updated successfully!";
            } else {
                // Insert Logic
                $sql = "INSERT INTO menu_items (category_id, name, description, price, image_url) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$category_id, $name, $description, $price, $image_url]);
                $message = "Menu item added successfully!";
            }
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "Name and Price are required!";
        $message_type = "error";
    }
}

// Fetch Data
try {
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name ASC")->fetchAll();
    $menu_items = $pdo->query("SELECT mi.*, mc.name as category_name FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mi.id DESC")->fetchAll();
} catch (PDOException $e) {
    $categories = []; $menu_items = [];
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<link rel="stylesheet" href="/css/addMenu.css">

<main class="main-wrapper">
    <div class="admin-menu-container admin-container">
        <a href="dashboard.php" class="back-link fade-in-down">← Back to Dashboard</a>
        
        <div class="page-header fade-in-down delay-1">
            <h1>Manage Menu</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message fade-in-down delay-2 <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="two-column-layout fade-in-up">
            <div class="form-section delay-1">
                <h2 class="section-title">Add New Category</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="category_description"></textarea>
                    </div>
                    <button type="submit" name="add_category" class="submit-btn">Add Category</button>
                </form>
            </div>

            <div class="form-section delay-2" id="itemFormSection">
                <h2 class="section-title" id="formTitle">Add New Menu Item</h2>
                <form method="post" enctype="multipart/form-data" id="menuItemForm">
                    <input type="hidden" name="item_id" id="item_id">
                    <input type="hidden" name="cropped_image_data" id="cropped_image_data">

                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" id="item_name" name="item_name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="item_description" name="item_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Price (RM) *</label>
                        <input type="number" id="item_price" name="item_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="item_category" name="item_category">
                            <option value="">No Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Image (Upload to crop)</label>
                        <input type="file" id="image_input" accept="image/*">
                        <div id="current_image_preview" style="display:none; margin-top:10px;">
                            <p style="font-size:0.8rem;">Current Image:</p>
                            <img src="" style="max-height: 80px; border-radius: 4px;">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="save_menu_item" class="submit-btn" id="submitBtn">Add Menu Item</button>
                        <button type="button" id="cancelEditBtn" class="cancel-btn" style="display:none;">Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="menu-items-section fade-in-up delay-3">
            <h2 class="section-title">Existing Menu Items (<?php echo count($menu_items); ?>)</h2>
            <div class="search-container">
                <input type="text" id="menuSearchInput" placeholder="🔍 Search items by name, category, or description...">
            </div>
            <div class="items-grid">
                <?php foreach ($menu_items as $item): ?>
                    <div class="menu-item-card fade-in-up">
                        <div class="card-image">
                            <img src="<?php echo $item['image_url'] ? '../'.htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/250x150?text=No+Image'; ?>" alt="Item">
                        </div>
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <?php if ($item['category_name']): ?>
                                <span class="category-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                            <?php endif; ?>
                            <p class="desc"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="price">RM <?php echo number_format($item['price'], 2); ?></div>
                            
                            <div class="card-actions">
                                <button type="button" class="edit-btn" 
                                    data-id="<?php echo $item['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    data-desc="<?php echo htmlspecialchars($item['description']); ?>"
                                    data-price="<?php echo $item['price']; ?>"
                                    data-cat="<?php echo $item['category_id']; ?>"
                                    data-img="<?php echo $item['image_url']; ?>">
                                    Edit
                                </button>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="delete_item" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<div id="cropperModal" class="modal">
    <div class="modal-content">
        <h2>Crop Image</h2>
        <div class="img-container">
            <img id="imageToCrop" src="">
        </div>
        <div class="modal-actions">
            <button type="button" id="cropCancelBtn" class="cancel-btn">Cancel</button>
            <button type="button" id="cropImageBtn" class="submit-btn">Crop & Save</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- VARIABLES ---
    const imageInput = document.getElementById('image_input');
    const cropperModal = document.getElementById('cropperModal');
    const imageToCrop = document.getElementById('imageToCrop');
    const cropBtn = document.getElementById('cropImageBtn');
    const cancelCropBtn = document.getElementById('cropCancelBtn');
    const croppedDataInput = document.getElementById('cropped_image_data');
    
    let cropper;

    // --- CROPPER LOGIC ---
    imageInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            const reader = new FileReader();
            
            reader.onload = function(event) {
                imageToCrop.src = event.target.result;
                cropperModal.style.display = 'flex';
                
                if(cropper) cropper.destroy();
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 16 / 9, // Adjust ratio as needed (e.g., 1 for square)
                    viewMode: 2,
                });
            };
            reader.readAsDataURL(file);
        }
    });

    cropBtn.addEventListener('click', function() {
        const canvas = cropper.getCroppedCanvas({
            width: 600, // Resize processed image
            height: 337
        });
        
        // Store base64 in hidden input
        croppedDataInput.value = canvas.toDataURL('image/png');
        
        // Close modal
        cropperModal.style.display = 'none';
        cropper.destroy();
        cropper = null;
        
        // Show user it's ready
        alert("Image cropped and ready to upload!");
    });

    cancelCropBtn.addEventListener('click', function() {
        cropperModal.style.display = 'none';
        if(cropper) cropper.destroy();
        imageInput.value = ''; // Reset input
    });

    // --- EDIT MODE LOGIC ---
    const editBtns = document.querySelectorAll('.edit-btn');
    const formTitle = document.getElementById('formTitle');
    const submitBtn = document.getElementById('submitBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const itemIdInput = document.getElementById('item_id');
    
    // Inputs
    const nameIn = document.getElementById('item_name');
    const descIn = document.getElementById('item_description');
    const priceIn = document.getElementById('item_price');
    const catIn = document.getElementById('item_category');
    const currentImgDiv = document.getElementById('current_image_preview');
    const currentImgTag = currentImgDiv.querySelector('img');

    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Scroll to form
            document.querySelector('.two-column-layout').scrollIntoView({ behavior: 'smooth' });

            // Populate fields
            itemIdInput.value = this.dataset.id;
            nameIn.value = this.dataset.name;
            descIn.value = this.dataset.desc;
            priceIn.value = this.dataset.price;
            catIn.value = this.dataset.cat;
            
            // Handle Image Preview
            if (this.dataset.img) {
                currentImgTag.src = '../' + this.dataset.img;
                currentImgDiv.style.display = 'block';
            } else {
                currentImgDiv.style.display = 'none';
            }

            // Change UI to Edit Mode
            formTitle.innerText = "Edit Menu Item";
            submitBtn.innerText = "Update Item";
            submitBtn.classList.add('update-mode');
            cancelEditBtn.style.display = 'inline-block';
            croppedDataInput.value = ''; // Reset any pending new image
        });
    });

    cancelEditBtn.addEventListener('click', function() {
        // Reset Form
        document.getElementById('menuItemForm').reset();
        itemIdInput.value = '';
        currentImgDiv.style.display = 'none';
        
        // Reset UI
        formTitle.innerText = "Add New Menu Item";
        submitBtn.innerText = "Add Menu Item";
        submitBtn.classList.remove('update-mode');
        this.style.display = 'none';
    });
});

// --- SEARCH FILTER LOGIC ---
    const searchInput = document.getElementById('menuSearchInput');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const cards = document.querySelectorAll('.menu-item-card');

            cards.forEach(card => {
                // Get text content from specific elements
                const name = card.querySelector('h3').innerText.toLowerCase();
                const desc = card.querySelector('.desc').innerText.toLowerCase();
                
                // Check category (it might be optional)
                const catBadge = card.querySelector('.category-badge');
                const cat = catBadge ? catBadge.innerText.toLowerCase() : '';
                
                // If match found in Name, Description, or Category
                if (name.includes(filter) || desc.includes(filter) || cat.includes(filter)) {
                    card.style.display = ""; // Show
                } else {
                    card.style.display = "none"; // Hide
                }
            });
        });
    }
    
</script>