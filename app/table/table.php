<?php 
session_start();
require_once '../connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../index.php");
    exit;
}

// --- HANDLE SELECTION LOGIC ---
if (isset($_POST['select_table'])) { 
    $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
    
    if ($table_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM dining_tables WHERE id = ?");
        $stmt->execute([$table_id]);
        $table = $stmt->fetch();

        if ($table) {
            $_SESSION['selected_table_id'] = $table['id'];
            $_SESSION['selected_table_number'] = $table['table_number'];
            header("Location: ../menu/menu.php");
            exit;
        }
    }
}

$pageTitle = "Table Selection"; 
$basePath = "../";
include '../_header.php';

try {
    $sql = "SELECT * FROM dining_tables ORDER BY table_number ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tables = $stmt->fetchAll();
} catch (PDOException $e) {
    $tables = [];
}

// --- COORDINATES ---
$coordinates = [
    'T01' => ['top' => '35%', 'left' => '18%'],
    'T02' => ['top' => '35%', 'left' => '33%'],
    'T03' => ['top' => '35%', 'left' => '48%'],
    'T04' => ['top' => '55%', 'left' => '18%'],
    'T05' => ['top' => '55%', 'left' => '33%'],
    'T06' => ['top' => '55%', 'left' => '48%'],
    'T07' => ['top' => '78%', 'left' => '18%'],
    'T08' => ['top' => '78%', 'left' => '33%'],
    'T09' => ['top' => '78%', 'left' => '48%'],
    'T10' => ['top' => '78%', 'left' => '63%'],
    'VIP1' => ['top' => '25%', 'left' => '82%'],
    'VIP2' => ['top' => '50%', 'left' => '85%'],
    'VIP3' => ['top' => '78%', 'left' => '82%'],
];
?>

<link rel="stylesheet" href="../css/table.css">

<main class="main-wrapper">
    
    <div class="table-selection-container">
        
        <div class="header-flex">
            <div class="header-left">
                <a href="../waiter/index.php" class="back-link">← Back to Dashboard</a>
                <h2 class="page-title">Table Selection</h2>
            </div>

            <div class="map-legend">
                <div class="legend-item"><span class="dot available"></span> Available</div>
                <div class="legend-item"><span class="dot occupied"></span> Occupied</div>
                <div class="legend-item"><span class="dot reserved"></span> Reserved</div>
            </div>
        </div>

        <div class="blueprint-container">
            
            <div class="area-kitchen">
                <span>KITCHEN AREA</span>
            </div>
            
            <div class="area-vip-zone">
                <span class="zone-label">VIP</span>
            </div>

            <div class="area-bar">BAR</div>
            <div class="area-entrance">Entrance</div>
            
            <?php foreach ($tables as $table): ?>
                <?php 
                    $t_num = $table['table_number'];
                    $pos = isset($coordinates[$t_num]) ? $coordinates[$t_num] : ['top' => '50%', 'left' => '50%'];
                    $isVip = stripos($t_num, 'VIP') !== false;
                    $shapeClass = $isVip ? 'shape-vip' : 'shape-regular';
                ?>
                
                <div class="table-node <?php echo $shapeClass . ' ' . $table['status']; ?>" 
                     style="top: <?php echo $pos['top']; ?>; left: <?php echo $pos['left']; ?>;"
                     onclick="openTableModal('<?php echo $table['id']; ?>', '<?php echo $t_num; ?>', '<?php echo $table['status']; ?>', '<?php echo $table['capacity']; ?>')">
                    
                    <span class="table-label"><?php echo $t_num; ?></span>
                    
                    <div class="chair c-top"></div>
                    <div class="chair c-bottom"></div>
                    <?php if($isVip): ?>
                        <div class="chair c-left"></div>
                        <div class="chair c-right"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</main>

<div id="tableModal" class="modal-overlay">
    <div class="modal-box">
        <span class="close-icon" onclick="closeTableModal()">&times;</span>
        <h3 id="modalTitle">Table Selection</h3>
        
        <div class="modal-info">
            <p id="modalStatus"></p>
            <p id="modalCapacity"></p>
        </div>
        
        <form method="post">
            <input type="hidden" name="table_id" id="modalTableId">
            <button type="submit" name="select_table" id="modalActionBtn" class="action-btn">
                Confirm
            </button>
        </form>
    </div>
</div>

<script>
function openTableModal(id, number, status, capacity) {
    const modal = document.getElementById('tableModal');
    const title = document.getElementById('modalTitle');
    const statusText = document.getElementById('modalStatus');
    const capText = document.getElementById('modalCapacity');
    const inputId = document.getElementById('modalTableId');
    const btn = document.getElementById('modalActionBtn');

    title.innerText = number;
    statusText.innerHTML = `Status: <strong>${status.toUpperCase()}</strong>`;
    capText.innerText = `Capacity: ${capacity} Pax`;
    inputId.value = id;

    btn.className = "action-btn"; 
    
    if (status === 'available') {
        btn.innerText = "Start Order";
        btn.classList.add('btn-green');
        btn.disabled = false;
        btn.style.cursor = "pointer";
    } else if (status === 'occupied') {
        btn.innerText = "Add Items";
        btn.classList.add('btn-blue');
        btn.disabled = false;
        btn.style.cursor = "pointer";
    } else {
        btn.innerText = "Unavailable";
        btn.classList.add('btn-gray');
        btn.disabled = true;
        btn.style.cursor = "not-allowed";
    }

    modal.style.display = 'flex';
}

function closeTableModal() {
    document.getElementById('tableModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('tableModal')) {
        closeTableModal();
    }
}
</script>