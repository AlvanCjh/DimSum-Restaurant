<?php 
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- AJAX Endpoint for Dynamic Forecasts ---
if (isset($_GET['action']) && $_GET['action'] === 'get_forecast') {
    $command = "python ../ai/forecast_sales.py";

    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $start_date = escapeshellarg($_GET['start_date']);
        $end_date = escapeshellarg($_GET['end_date']);
        $command .= " --start_date $start_date --end_date $end_date";
    } 
    elseif (isset($_GET['days'])) {
        $forecast_days = intval($_GET['days']);
        $command .= " --days " . escapeshellarg($forecast_days > 0 ? $forecast_days : 7);
    } else {
        $command .= " --days 7"; 
    }

    $json_output = shell_exec($command);
    header('Content-Type: application/json');
    echo $json_output;
    exit;
}

// --- Initial Page Load ---
$pageTitle = "AI Insights";
$basePath = "../";
include '../_header.php';

$default_days = 7;
$command = "python ../ai/forecast_sales.py --days " . escapeshellarg($default_days); 
$output = shell_exec($command);
$data = json_decode($output, true);

$hasData = isset($data['history_sales']);
$errorMsg = isset($data['error']) ? $data['error'] : "Unknown error connecting to AI Engine.";
?>

<link rel="stylesheet" href="../css/ai_forecast.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="report-header-print">
            <img src="../image/logo.png" alt="Logo" class="print-logo">
            <h1 class="report-title-print">YOBITA RESTAURANT</h1>
            <h2 class="report-subtitle-print">AI Sales Forecast Report</h2>
        </div>

        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h1>AI Sales Forecast</h1>
            
            <?php if($hasData): ?>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="ai-status-badge">
                        <span class="pulse-dot"></span> AI Model Active
                    </div>
                    <div class="report-actions">
                        <button onclick="printReport()" class="action-btn print-btn">
                            <ion-icon name="print-outline"></ion-icon> Print
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if(!$hasData): ?>
            <div class="alert-warning">
                <strong>AI Status:</strong> <?php echo $errorMsg; ?>
                <br><em>Tip: Make sure you have completed orders in the database.</em>
            </div>
        <?php else: ?>

            <div id="report-content" class="report-content">
            
                <div class="forecast-section">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 15px;">
                        <h2 id="forecast-title" class="section-title mb-0" style="margin: 0;">7-Day Revenue Prediction</h2>
                        
                        <div class="forecast-controls btn-group" role="group" aria-label="Forecast Period">
                            <button type="button" class="forecast-btn active" data-days="7">7 Days</button>
                            <button type="button" class="forecast-btn" data-days="14">14 Days</button>
                            <button type="button" class="forecast-btn" data-days="30">30 Days</button>
                            <button type="button" class="forecast-btn" id="customRangeBtn">Custom</button>
                        </div>
                    </div>

                    <div class="custom-range-container" id="customRangeContainer" style="display:none;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <label for="startDate" style="margin:0;">From:</label>
                            <input type="date" id="startDate" name="start_date" disabled>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <label for="endDate" style="margin:0;">To:</label>
                            <input type="date" id="endDate" name="end_date">
                        </div>
                        <button type="button" id="applyCustomRangeBtn">Apply Filter</button>
                    </div>

                    <div id="forecast-summary-cards" class="row mb-4">
                        <div class="col-md-5 col-lg-4">
                            <div class="revenue-card">
                                <div class="revenue-label">
                                    <ion-icon name="trending-up-outline"></ion-icon>
                                    Total Predicted Revenue
                                </div>
                                <div id="predicted-revenue-value" class="revenue-value">Loading...</div> 
                                <div class="revenue-subtext">Based on historical sales trends</div>
                            </div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="forecastChart"></canvas>
                        <div class="chart-loading-overlay" id="chartLoader">
                            <div class="spinner"></div>
                            <span>Fetching new forecast...</span>
                        </div>
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
                            <tbody id="forecast-table-body">
                                <?php 
                                $lastValue = end($data['history_sales']);
                                
                                for($i = 0; $i < count($data['forecast_dates']); $i++) {
                                    $currVal = $data['forecast_sales'][$i];
                                    $dateStr = date("F d, Y", strtotime($data['forecast_dates'][$i]));
                                    
                                    $trend = ($currVal >= $lastValue) ? 'up' : 'down';
                                    $trendIcon = ($trend == 'up') 
                                        ? '<span style="color: #28a745;">▲ Rising</span>' 
                                        : '<span style="color: #dc3545;">▼ Falling</span>';

                                    echo "<tr>";
                                    echo "<td>" . $dateStr . "</td>";
                                    echo "<td><span class='price-tag'>RM" . number_format($currVal, 2) . "</span></td>";
                                    echo "<td>" . $trendIcon . "</td>";
                                    echo "</tr>";

                                    $lastValue = $currVal; 
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-footer-print">
                    <p>Report Generated: <?php echo date('F j, Y g:i A'); ?></p>
                </div>
            </div>

        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<?php if($hasData): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Initial Data from PHP ---
    const initialData = <?php echo json_encode($data); ?>;
    const { history_dates: historyDates, history_sales: historySales, forecast_dates: forecastDates, forecast_sales: forecastSales } = initialData;

    // --- Chart.js Data Preparation ---
    const allLabels = historyDates.concat(forecastDates);
    
    const historyDataPadded = historySales.concat(Array(forecastDates.length).fill(null));
    
    const lastHistoryVal = historySales[historySales.length - 1];
    const forecastDataPadded = Array(historySales.length - 1).fill(null);
    forecastDataPadded.push(lastHistoryVal); 
    forecastDataPadded.push(...forecastSales);

    const ctx = document.getElementById('forecastChart').getContext('2d');

    const colorHistory = '#4e73df'; 
    const colorForecast = '#fd7e14'; 

    const forecastChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                {
                    label: 'Actual Sales',
                    data: historyDataPadded,
                    borderColor: colorHistory,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)', 
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
                    borderDash: [8, 6], 
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
                legend: { display: false }, 
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

    // --- Helper Functions for Dynamic Updates ---

    function updateSummaryCards(forecastSalesData) {
        const totalPredictedRevenue = forecastSalesData.reduce((sum, value) => sum + value, 0);
        document.getElementById('predicted-revenue-value').textContent = `$${totalPredictedRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function updateBreakdownTable(forecastDatesData, forecastSalesData, lastHistoryValue) {
        const tableBody = document.getElementById('forecast-table-body');
        tableBody.innerHTML = ''; // Clear existing rows
        let lastValue = lastHistoryValue;

        for (let i = 0; i < forecastDatesData.length; i++) {
            const currVal = forecastSalesData[i];
            const dateStr = new Date(forecastDatesData[i] + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            
            const trend = (currVal >= lastValue) ? 'up' : 'down';
            const trendIcon = (trend == 'up') 
                ? '<span style="color: #28a745;">▲ Rising</span>' 
                : '<span style="color: #dc3545;">▼ Falling</span>';

            const row = `
                <tr>
                    <td>${dateStr}</td>
                    <td><span class='price-tag'>$${currVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></td>
                    <td>${trendIcon}</td>
                </tr>`;
            tableBody.innerHTML += row;
            lastValue = currVal;
        }
    }

    function updateChart(chart, newData) {
        const { history_dates, history_sales, forecast_dates, forecast_sales } = newData;

        const allLabels = history_dates.concat(forecast_dates);
        const historyDataPadded = history_sales.concat(Array(forecast_dates.length).fill(null));
        
        const lastHistoryVal = history_sales[history_sales.length - 1];
        const forecastDataPadded = Array(history_sales.length - 1).fill(null);
        forecastDataPadded.push(lastHistoryVal); 
        forecastDataPadded.push(...forecast_sales);

        chart.data.labels = allLabels;
        chart.data.datasets[0].data = historyDataPadded;
        chart.data.datasets[1].data = forecastDataPadded;
        chart.update();
    }

    // --- Main Function to Fetch and Update ---
    async function fetchAndUpdateForecast(params) {
        // Show loading state
        document.getElementById('predicted-revenue-value').textContent = 'Loading...';
        const chartLoader = document.getElementById('chartLoader');
        chartLoader.classList.add('visible');
        document.getElementById('forecast-table-body').innerHTML = '<tr><td colspan="3" style="text-align:center;">Loading...</td></tr>';

        let queryString = '';
        let titleDays = '';
        if (params.days) {
            queryString = `days=${params.days}`;
            titleDays = params.days;
        } else if (params.startDate && params.endDate) {
            queryString = `start_date=${params.startDate}&end_date=${params.endDate}`;
            titleDays = 'Custom';
        }

        try {
            const response = await fetch(`ai_forecast.php?action=get_forecast&${queryString}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            
            const newData = await response.json();
            if (newData.error) throw new Error(newData.error);

            // Update UI elements
            document.getElementById('forecast-title').textContent = `${titleDays}-Day Revenue Prediction`;
            updateChart(forecastChart, newData);
            updateSummaryCards(newData.forecast_sales);
            updateBreakdownTable(newData.forecast_dates, newData.forecast_sales, newData.history_sales[newData.history_sales.length - 1]);

        } catch (error) {
            console.error("Error fetching forecast data:", error);
            document.getElementById('predicted-revenue-value').textContent = 'Error';
            document.getElementById('forecast-table-body').innerHTML = `<tr><td colspan="3" style="text-align:center; color: red;">Failed to load data.</td></tr>`;
        } finally {
            chartLoader.classList.remove('visible');
        }
    }

    // --- Initial UI Population ---
    updateSummaryCards(initialData.forecast_sales);
    
    // Set min/max dates for custom range picker
    const lastHistoryDate = new Date(historyDates[historyDates.length - 1] + 'T00:00:00');
    const forecastStartDate = new Date(lastHistoryDate);
    forecastStartDate.setDate(lastHistoryDate.getDate() + 1);

    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    startDateInput.value = forecastStartDate.toISOString().split('T')[0];
    endDateInput.min = forecastStartDate.toISOString().split('T')[0];

    // --- Event Listeners ---
    const buttons = document.querySelectorAll('.forecast-controls .forecast-btn');
    const customRangeBtn = document.getElementById('customRangeBtn');
    const customRangeContainer = document.getElementById('customRangeContainer');
    const applyCustomRangeBtn = document.getElementById('applyCustomRangeBtn');

    buttons.forEach(button => {
        button.addEventListener('click', function () {
            buttons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            customRangeContainer.style.display = 'none';

            const days = this.getAttribute('data-days');
            if (days) { 
                fetchAndUpdateForecast({ days: days });
            }
        });
    });

    customRangeBtn.addEventListener('click', function() {
        customRangeContainer.style.display = 'flex';
    });

    applyCustomRangeBtn.addEventListener('click', function() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        if (startDate && endDate) {
            fetchAndUpdateForecast({ startDate: startDate, endDate: endDate });
        }
    });

});

function printReport() {
    window.print();
}
</script>
<?php endif; ?>