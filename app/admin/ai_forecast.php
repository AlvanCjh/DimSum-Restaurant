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
                        <button onclick="downloadPDF()" class="action-btn pdf-btn">
                            <ion-icon name="download-outline"></ion-icon> Download PDF
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

            <div id="report-content" class="report-content" data-date="<?php echo date('F j, Y g:i A'); ?>">
            <div class="report-header-print">
                <h1 class="report-title-print">YOBITA RESTAURANT</h1>
                <h2 class="report-subtitle-print">AI Sales Forecast Report</h2>
            </div>
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
                                echo "<td><span class='price-tag'>$" . number_format($currVal, 2) . "</span></td>";
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<?php if($hasData): ?>
<script>
    const historyDates = <?php echo json_encode($data['history_dates']); ?>;
    const historySales = <?php echo json_encode($data['history_sales']); ?>;
    const forecastDates = <?php echo json_encode($data['forecast_dates']); ?>;
    const forecastSales = <?php echo json_encode($data['forecast_sales']); ?>;

    const allLabels = historyDates.concat(forecastDates);
    
    const historyDataPadded = historySales.concat(Array(forecastDates.length).fill(null));
    
    const lastHistoryVal = historySales[historySales.length - 1];
    const forecastDataPadded = Array(historySales.length - 1).fill(null);
    forecastDataPadded.push(lastHistoryVal); 
    forecastDataPadded.push(...forecastSales);

    const ctx = document.getElementById('forecastChart').getContext('2d');

    const colorHistory = '#4e73df'; 
    const colorForecast = '#fd7e14'; 

    new Chart(ctx, {
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

    // Print functionality
    function printReport() {
        window.print();
    }

    // PDF Download functionality
    function downloadPDF() {
        const { jsPDF } = window.jspdf;
        const container = document.querySelector('.admin-container');
        
        // Show loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:10000;';
        loadingMsg.innerHTML = '<p>Generating PDF...</p>';
        document.body.appendChild(loadingMsg);

        // Hide elements that shouldn't be in PDF
        const backLink = document.querySelector('.back-link');
        const reportActions = document.querySelector('.report-actions');
        const statusBadge = document.querySelector('.ai-status-badge');
        const reportHeader = document.querySelector('.report-header-print');
        const reportFooter = document.querySelector('.report-footer-print');
        
        if (backLink) backLink.style.display = 'none';
        if (reportActions) reportActions.style.display = 'none';
        if (statusBadge) statusBadge.style.display = 'none';
        if (reportHeader) reportHeader.style.display = 'block';
        if (reportFooter) reportFooter.style.display = 'block';

        // Wait a bit for any animations to complete
        setTimeout(() => {
            html2canvas(container, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;

                // Add first page
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                // Add additional pages if needed
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                // Generate filename with timestamp
                const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
                pdf.save('AI_Sales_Forecast_' + timestamp + '.pdf');
                
                // Restore hidden elements
                if (backLink) backLink.style.display = '';
                if (reportActions) reportActions.style.display = '';
                if (statusBadge) statusBadge.style.display = '';
                if (reportHeader) reportHeader.style.display = 'none';
                if (reportFooter) reportFooter.style.display = 'none';
                
                // Remove loading indicator
                document.body.removeChild(loadingMsg);
            }).catch(error => {
                console.error('PDF generation error:', error);
                
                // Restore hidden elements on error
                if (backLink) backLink.style.display = '';
                if (reportActions) reportActions.style.display = '';
                if (statusBadge) statusBadge.style.display = '';
                if (reportHeader) reportHeader.style.display = 'none';
                if (reportFooter) reportFooter.style.display = 'none';
                
                alert('Error generating PDF. Please try again.');
                document.body.removeChild(loadingMsg);
            });
        }, 500);
    }
</script>
<?php endif; ?>