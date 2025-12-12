<?php 
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Menu Matrix";
$basePath = "../";
include '../_header.php';

// Execute Python Script
$command = "python ../ai/menu_matrix.py"; 
$output = shell_exec($command);
$data = json_decode($output, true);

$hasData = isset($data['items']);
$errorMsg = isset($data['error']) ? $data['error'] : "Unknown error connecting to AI Engine.";
?>

<link rel="stylesheet" href="../css/menu_matrix.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="page-header">
            <div>
                <h1>Smart Menu Insights</h1>
                <p style="color:#777; margin:5px 0 0 0;">Profitability vs. Popularity Analysis (30 Days)</p>
            </div>
            
            <?php if($hasData): ?>
                <div class="ai-status-badge">
                    <span class="pulse-dot"></span> Matrix Generated
                </div>
            <?php endif; ?>
        </div>

        <?php if(!$hasData): ?>
            <div class="alert-warning" style="background:#fff3cd; color:#856404; padding:15px; border-radius:8px;">
                <strong>AI Status:</strong> <?php echo $errorMsg; ?>
                <br><em>Tip: You need completed orders in the last 30 days to generate this chart.</em>
            </div>
        <?php else: ?>

            <div class="matrix-section">
                <div class="chart-container">
                    <canvas id="matrixChart"></canvas>
                </div>
                
                <div class="quadrant-legend">
                    <div class="legend-item"><span class="dot" style="background:#28a745"></span> <strong>Stars:</strong> High Profit, High Sales</div>
                    <div class="legend-item"><span class="dot" style="background:#ffc107"></span> <strong>Plowhorses:</strong> Low Profit, High Sales</div>
                    <div class="legend-item"><span class="dot" style="background:#17a2b8"></span> <strong>Puzzles:</strong> High Profit, Low Sales</div>
                    <div class="legend-item"><span class="dot" style="background:#dc3545"></span> <strong>Dogs:</strong> Low Profit, Low Sales</div>
                </div>
            </div>

            <div class="matrix-section">
                <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Menu Item Analysis</h2>
                <div class="table-responsive">
                    <table class="analysis-table">
                        <thead>
                            <tr>
                                <th>Menu Item</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Est. Margin</th>
                                <th>Action Plan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['items'] as $item): ?>
                                <?php 
                                    $class = strtolower($item['category']); 
                                    $action = "";
                                    if($item['category'] == 'Star') $action = "🏆 Promote heavily! Ensure quality consistency.";
                                    if($item['category'] == 'Plowhorse') $action = "💸 Increase price slightly or reduce portion cost.";
                                    if($item['category'] == 'Puzzle') $action = "📢 Marketing needed. Rename item or take better photos.";
                                    if($item['category'] == 'Dog') $action = "⚠️ Consider removing from menu to simplify kitchen.";
                                ?>
                                <tr>
                                    <td><strong><?php echo $item['name']; ?></strong></td>
                                    <td><span class="cat-badge cat-<?php echo $class; ?>"><?php echo $item['category']; ?></span></td>
                                    <td><?php echo $item['total_qty']; ?></td>
                                    <td>RM <?php echo number_format($item['margin'], 2); ?></td>
                                    <td class="recommendation"><?php echo $action; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if($hasData): ?>
<script>
    const rawData = <?php echo json_encode($data['items']); ?>;
    const benchX = <?php echo $data['benchmark_x']; ?>; // Avg Qty
    const benchY = <?php echo $data['benchmark_y']; ?>; // Avg Margin

    // Prepare Data for Chart.js
    const chartPoints = rawData.map(item => {
        let color = '#ccc';
        if(item.category === 'Star') color = '#28a745';
        if(item.category === 'Plowhorse') color = '#ffc107';
        if(item.category === 'Puzzle') color = '#17a2b8';
        if(item.category === 'Dog') color = '#dc3545';

        return {
            x: item.total_qty,
            y: item.margin,
            label: item.name,
            backgroundColor: color
        };
    });

    const ctx = document.getElementById('matrixChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Menu Items',
                data: chartPoints,
                pointRadius: 8,
                pointHoverRadius: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            let p = ctx.raw;
                            return p.label + ': Sold ' + p.x + ', Margin RM' + p.y.toFixed(2); 
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: { display: true, text: 'Popularity (Quantity Sold)', font: {weight: 'bold'} },
                    grid: {
                        color: (ctx) => (ctx.tick.value >= benchX && ctx.tick.value < benchX + 1) ? '#333' : '#eee',
                        lineWidth: (ctx) => (ctx.tick.value >= benchX && ctx.tick.value < benchX + 1) ? 2 : 1
                    }
                },
                y: {
                    title: { display: true, text: 'Profitability (Est. Margin)', font: {weight: 'bold'} },
                    grid: {
                        color: (ctx) => (ctx.tick.value >= benchY && ctx.tick.value < benchY + 1) ? '#333' : '#eee',
                        lineWidth: (ctx) => (ctx.tick.value >= benchY && ctx.tick.value < benchY + 1) ? 2 : 1
                    }
                }
            }
        }
    });
</script>
<?php endif; ?>