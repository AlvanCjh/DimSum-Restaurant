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
        <div class="search-bar-container" style="margin-bottom: 25px; text-align: center;">
                <input type="text" id="tableSearchInput" 
                       placeholder="Filter by table name (e.g., T01, VIP)" 
                       style="padding: 10px 12px; width: 400px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em;">
            </div>
        
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

<script>
// Wait for the document to be fully loaded
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Get the search input element
    const searchInput = document.getElementById('tableSearchInput');
    
    // 2. Add an event listener for when the user types
    searchInput.addEventListener('keyup', function() {
        
        // 3. Get the search term and convert to lowercase for matching
        const filter = searchInput.value.toLowerCase();
        
        // 4. Get all the table cards
        const tableCards = document.querySelectorAll('.tables-grid .table-card');
        
        // 5. Loop through each table card
        tableCards.forEach(card => {
            // 6. Find the element with the table number inside the card
            const tableNumberElement = card.querySelector('.table-number');
            
            if (tableNumberElement) {
                // 7. Get the table name text
                const tableName = tableNumberElement.textContent || tableNumberElement.innerText;
                
                // 8. Check if the table name includes the filter text
                if (tableName.toLowerCase().indexOf(filter) > -1) {
                    // If it matches, show the card
                    card.style.display = ""; 
                } else {
                    // If it doesn't match, hide the card
                    card.style.display = "none";
                }
            }
        });
    });
});
</script>

