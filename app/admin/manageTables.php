<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Manage Tables";
$basePath = "../";
include '../_header.php';

$message = "";
$message_type = "";

// --- HANDLE FORM SUBMISSIONS ---

// Add a new table
if (isset($_POST['add_table'])) {
    $table_number = trim($_POST['table_number']);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

    if (!empty($table_number) && $capacity > 0) {
        try {
            $sql = "INSERT INTO dining_tables (table_number, capacity, status) VALUES (?, ?, 'available')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table_number, $capacity]);
            $message = "Table '{$table_number}' added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate)
                $message = "A table with that number already exists.";
            } else {
                $message = "Database error: " . $e->getMessage();
            }
            $message_type = "error";
        }
    } else {
        $message = "Please provide a valid table number and capacity.";
        $message_type = "error";
    }
}

// Update table details (number and capacity)
if (isset($_POST['update_table'])) {
    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
    $table_number = trim($_POST['table_number']);
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);

    if ($table_id && !empty($table_number) && $capacity > 0) {
        try {
            $sql = "UPDATE dining_tables SET table_number = ?, capacity = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table_number, $capacity, $table_id]);
            $message = "Table details updated successfully.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating table: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "Invalid data provided for update.";
        $message_type = "error";
    }
}

// Update table status
if (isset($_POST['update_status'])) {
    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['status'];
    $allowed_statuses = ['available', 'occupied', 'reserved'];

    if ($table_id && in_array($new_status, $allowed_statuses)) {
        try {
            $sql = "UPDATE dining_tables SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $table_id]);
            $message = "Table status updated successfully.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Delete a table
if (isset($_POST['delete_table'])) {
    $table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
    if ($table_id) {
        try {
            $sql = "DELETE FROM dining_tables WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table_id]);
            $message = "Table deleted successfully.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting table: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// --- FETCH DATA ---
try {
    $stmt = $pdo->query("SELECT * FROM dining_tables ORDER BY table_number ASC");
    $tables = $stmt->fetchAll();
} catch (PDOException $e) {
    $tables = [];
    $message = "Error fetching tables: " . $e->getMessage();
    $message_type = "error";
}

$status_colors = [
    'available' => 'status-available',
    'occupied' => 'status-occupied',
    'reserved' => 'status-reserved'
];
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/manageTable.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>Manage Dining Tables</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Table Form -->
        <div class="form-section add-table-form">
            <h2 class="section-title">Add New Table</h2>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="table_number">Table Number/Name *</label>
                        <input type="text" id="table_number" name="table_number" placeholder="e.g., T1, Patio 2" required>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity *</label>
                        <input type="number" id="capacity" name="capacity" min="1" placeholder="e.g., 4" required>
                    </div>
                </div>
                <button type="submit" name="add_table" class="submit-btn">Add Table</button>
            </form>
        </div>

        <!-- Display Existing Tables -->
        <div class="tables-list-section">
            <h2 class="section-title">Existing Tables (<?php echo count($tables); ?>)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Table Number</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tables)): ?>
                            <tr>
                                <td colspan="4">No tables found. Add one above to get started.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <!-- This form is for editing table number and capacity -->
                                    <form method="post" id="editForm_<?php echo $table['id']; ?>">
                                        <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                        <td data-label="Table Number" class="cell-table-number">
                                            <span class="view-mode"><?php echo htmlspecialchars($table['table_number']); ?></span>
                                            <input type="text" name="table_number" value="<?php echo htmlspecialchars($table['table_number']); ?>" class="edit-mode" style="display:none;" required>
                                        </td>
                                        <td data-label="Capacity" class="cell-capacity">
                                            <span class="view-mode"><?php echo htmlspecialchars($table['capacity']); ?></span>
                                            <input type="number" name="capacity" value="<?php echo htmlspecialchars($table['capacity']); ?>" class="edit-mode" style="display:none;" min="1" required>
                                        </td>
                                    </form>
                                    
                                    <!-- This form is for updating the status -->
                                    <td data-label="Status">
                                        <form method="post">
                                            <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                            <select name="status" class="status-select <?php echo $status_colors[$table['status']]; ?>" onchange="this.form.submit()">
                                                <option value="available" <?php echo $table['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                                <option value="occupied" <?php echo $table['status'] === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                                <option value="reserved" <?php echo $table['status'] === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>

                                    <!-- This cell contains the action buttons -->
                                    <td class="actions-cell" data-label="Actions">
                                        <button type="button" class="edit-btn view-mode" onclick="toggleEdit(this)">Edit</button>
                                        <button type="submit" name="update_table" class="submit-btn edit-mode" form="editForm_<?php echo $table['id']; ?>" style="display:none;">Save</button>
                                        <button type="button" class="cancel-btn edit-mode" style="display:none;" onclick="toggleEdit(this)">Cancel</button>
                                        <button type="submit" name="delete_table" class="delete-btn view-mode" form="deleteForm_<?php echo $table['id']; ?>">Delete</button>
                                    </td>

                                    <!-- This form is for the delete action -->
                                    <form method="post" id="deleteForm_<?php echo $table['id']; ?>" onsubmit="return confirm('Are you sure you want to delete this table?');">
                                        <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                        <input type="hidden" name="delete_table" value="1">
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function toggleEdit(button) {
    const row = button.closest('tr');
    const viewElements = row.querySelectorAll('.view-mode');
    const editElements = row.querySelectorAll('.edit-mode');

    viewElements.forEach(el => {
        el.style.display = el.style.display === 'none' ? '' : 'none';
    });

    editElements.forEach(el => {
        el.style.display = el.style.display === 'none' ? '' : 'none';
    });
}
</script>

<!-- Font Awesome for icons if you choose to use them -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>