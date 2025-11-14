<?php 
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$pageTitle = "Select Table"; 
$basePath = "../";
include '../_header.php';

// Fetch all dining tables from database
try {
    $sql = "SELECT * FROM dining_tables ORDER BY table_number ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tables = $stmt->fetchAll();
} catch (PDOException $e) {
    $tables = [];
    $error_message = "Error loading tables: " . $e->getMessage();
}

// Handle table selection
if (isset($_POST['select_table']) && isset($_POST['table_id'])) {
    $table_id = (int)$_POST['table_id'];
    
    // Verify table exists and is available
    try {
        $sql_check = "SELECT * FROM dining_tables WHERE id = ? AND status = 'available'";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$table_id]);
        $table = $stmt_check->fetch();
        
        if ($table) {
            // Store selected table in session
            $_SESSION['selected_table_id'] = $table['id'];
            $_SESSION['selected_table_number'] = $table['table_number'];
            
            // Redirect to menu page
            header("Location: ../menu/menu.php");
            exit;
        } else {
            $message = "Table is not available. Please select another table.";
        }
    } catch (PDOException $e) {
        $message = "Error selecting table. Please try again.";
    }
}
?>

<link rel="stylesheet" href="../css/table.css">

<main class="main-wrapper">
    <div class="app-container">
        <h2 class="page-title">Select a Table</h2>
        
        <?php if (isset($message)): ?>
            <div class="message error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="tables-grid">
            <?php if (empty($tables)): ?>
                <div class="message error">No tables found in the database.</div>
            <?php else: ?>
                <?php foreach ($tables as $table): ?>
                    <div class="table-card <?php echo htmlspecialchars($table['status']); ?>">
                        <div class="table-icon">🍽️</div>
                        <div class="table-number"><?php echo htmlspecialchars($table['table_number']); ?></div>
                        <div class="table-capacity">Capacity: <?php echo $table['capacity']; ?> seats</div>
                        <div class="table-status <?php echo htmlspecialchars($table['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($table['status'])); ?>
                        </div>
                        
                        <?php if ($table['status'] === 'available'): ?>
                            <form method="post" style="margin-top: 15px;">
                                <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                <button type="submit" name="select_table" class="select-table-btn">
                                    Select Table
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>



