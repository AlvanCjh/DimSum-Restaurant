<?php 
session_start();
require_once '../connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../index.php");
    exit;
}

if (isset($_POST['select_table']) && isset($_POST['table_id'])) {
    $table_id = (int)$_POST['table_id'];
    
    try {
        // Allow selection if table is available OR occupied
        $sql_check = "SELECT * FROM dining_tables WHERE id = ?";
        $stmt_check = $pdo->prepare($sql_check); 
        $stmt_check->execute([$table_id]);
        $table = $stmt_check->fetch();
        
        if ($table) {
            // Allow 'available' AND 'occupied'
            if ($table['status'] === 'available' || $table['status'] === 'occupied') {
                
                $_SESSION['selected_table_id'] = $table['id'];
                $_SESSION['selected_table_number'] = $table['table_number'];
                
                // Redirect to menu
                header("Location: ../menu/menu.php");
                exit;
            } else {
                $message = "This table is " . htmlspecialchars($table['status']) . ".";
            }
        } else {
            $message = "Table not found.";
        }
    } catch (PDOException $e) {
        $message = "Error selecting table.";
    }
}

$pageTitle = "Select Table"; 
$basePath = "../";
include '../_header.php';

try {
    $sql = "SELECT * FROM dining_tables ORDER BY table_number ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tables = $stmt->fetchAll();
} catch (PDOException $e) {
    $tables = [];
    $error_message = "Error loading tables: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="../css/table.css">

<main class="main-wrapper">
    <div class="app-container">
        <h2 class="page-title">Select a Table</h2>
        
        <div class="search-bar-container" style="margin-bottom: 25px; text-align: center;">
            <input type="text" id="tableSearchInput" 
                   placeholder="Filter by table name (e.g., T01, VIP)" 
                   style="padding: 10px 12px; width: 400px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em;">
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="tables-grid">
            <?php if (empty($tables)): ?>
                <div class="message error">No tables found.</div>
            <?php else: ?>
                <?php foreach ($tables as $table): ?>
                    <div class="table-card <?php echo htmlspecialchars($table['status']); ?>">
                        <div class="table-icon">🍽️</div>
                        <div class="table-number"><?php echo htmlspecialchars($table['table_number']); ?></div>
                        <div class="table-capacity">Capacity: <?php echo $table['capacity']; ?> seats</div>
                        
                        <div class="table-status <?php echo htmlspecialchars($table['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($table['status'])); ?>
                        </div>
                        
                        <?php if ($table['status'] === 'available' || $table['status'] === 'occupied'): ?>
                            <form method="post" style="margin-top: 15px;">
                                <input type="hidden" name="table_id" value="<?php echo $table['id']; ?>">
                                
                                <?php if ($table['status'] === 'available'): ?>
                                    <button type="submit" name="select_table" class="select-table-btn">
                                        Order
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="select_table" class="select-table-btn occupied-btn">
                                        Add More Order
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('tableSearchInput');
    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        const tableCards = document.querySelectorAll('.tables-grid .table-card');
        tableCards.forEach(card => {
            const tableNumber = card.querySelector('.table-number').textContent;
            if (tableNumber.toLowerCase().indexOf(filter) > -1) {
                card.style.display = ""; 
            } else {
                card.style.display = "none";
            }
        });
    });
});
</script>