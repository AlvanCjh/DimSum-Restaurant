<?php 
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "AI Insights";
$basePath = "../";
include '../_header.php';

// Execute Python Script
$command = "python ../ai/forecast_sales.py"; 
$output = shell_exec($command);
$data = json_decode($output, true);

$hasData = isset($data['history_sales']);
$errorMsg = isset($data['error']) ? $data['error'] : "Unknown error connecting to AI Engine.";
?>

<link rel="stylesheet" href="../css/ai_forecast.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="page-header">
            <h1>AI Sales Forecast</h1>
            <?php if($hasData): ?>
                <span class="ai-status-badge">
                    🟢 AI Model Active
                </span>
            <?php endif; ?>
        </div>

        <?php if(!$hasData): ?>
            <div class="alert-warning">
                <strong>AI Status:</strong> <?php echo $errorMsg; ?>
                <br><em>Tip: Make sure you have completed orders in the database.</em>
            </div>
        <?php else: ?>

            <div class="forecast-section">
                <h2 class="section-title">7-Day Revenue Prediction</h2>
                
                <div class="chart-container">
                    <canvas id="forecastChart"></canvas>
                </div>

                <div class="chart-legend">
                    <span class="legend-dot" style="background-color: #4e73df;"></span> Actual Sales
                    &nbsp;&nbsp;&nbsp;
                    <span class="legend-dot" style="background-color: #fd7e14;"></span> AI Prediction
                </div>
            </div>

            <div class="tables-list-section">
                <h2 class="section-title">Predicted Revenue Breakdown</h2>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Predicted Amount</th>
                                <th>Trend Indicator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get the last actual sale value to compare trend
                            $lastValue = end($data['history_sales']);
                            
                            for($i = 0; $i < count($data['forecast_dates']); $i++) {
                                $currVal = $data['forecast_sales'][$i];
                                $dateStr = date("F d, Y", strtotime($data['forecast_dates'][$i]));
                                
                                // Determine trend
                                $trend = ($currVal >= $lastValue) ? 'up' : 'down';
                                $trendIcon = ($trend == 'up') 
                                    ? '<span style="color: #28a745;">▲ Rising</span>' 
                                    : '<span style="color: #dc3545;">▼ Falling</span>';

                                echo "<tr>";
                                echo "<td>" . $dateStr . "</td>";
                                echo "<td><span class='price-tag'>$" . number_format($currVal, 2) . "</span></td>";
                                echo "<td>" . $trendIcon . "</td>";
                                echo "</tr>";

                                $lastValue = $currVal; // Update comparison for next row
                            }
                            ?>
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
    const historyDates = <?php echo json_encode($data['history_dates']); ?>;
    const historySales = <?php echo json_encode($data['history_sales']); ?>;
    const forecastDates = <?php echo json_encode($data['forecast_dates']); ?>;
    const forecastSales = <?php echo json_encode($data['forecast_sales']); ?>;

    const allLabels = historyDates.concat(forecastDates);
    
    // Prepare Datasets
    const historyDataPadded = historySales.concat(Array(forecastDates.length).fill(null));
    
    const lastHistoryVal = historySales[historySales.length - 1];
    const forecastDataPadded = Array(historySales.length - 1).fill(null);
    forecastDataPadded.push(lastHistoryVal); 
    forecastDataPadded.push(...forecastSales);

    const ctx = document.getElementById('forecastChart').getContext('2d');

    // --- Professional Color Theme ---
    const colorHistory = '#4e73df'; // Primary Blue
    const colorForecast = '#fd7e14'; // Orange (Distinctive for forecast)

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                {
                    label: 'Actual Sales',
                    data: historyDataPadded,
                    borderColor: colorHistory,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)', // Very faint blue fill
                    pointBackgroundColor: colorHistory,
                    pointRadius: 4,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'AI Forecast',
                    data: forecastDataPadded,
                    borderColor: colorForecast,
                    borderDash: [8, 6], // Dashed line
                    pointBackgroundColor: '#fff',
                    pointBorderColor: colorForecast,
                    pointRadius: 4,
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }, // We use our custom HTML legend
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: { size: 13 },
                    bodyFont: { size: 14, weight: 'bold' },
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            return '$ ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#888' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f0f0', borderDash: [5, 5] },
                    ticks: {
                        color: '#888',
                        callback: function(value) { return '$' + value; }
                    }
                }
            }
        }
    });
</script>
<?php endif; ?>

