<?php 
session_start();

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check admin login...
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Smart Reports";
$basePath = "../";
include '../_header.php';

// Default to daily
$view = isset($_GET['view']) ? $_GET['view'] : 'daily';

// Run Python Script with argument
$command = "python ../ai/generate_report.py " . escapeshellarg($view);
$output = shell_exec($command);
$report = json_decode($output, true);
?>

<link rel="stylesheet" href="../css/ai_forecast.css"> 
<link rel="stylesheet" href="../css/ai_reports.css">

<main class="main-wrapper">
    <div class="admin-container" data-view="<?php echo ucfirst($view); ?>">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Smart Sales Report</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <div class="ai-status-badge">
                    <span class="pulse-dot"></span> AI Analyst Ready
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
        </div>

        <div class="controls">
            <a href="?view=daily" class="filter-btn <?php echo $view == 'daily' ? 'active' : ''; ?>">Daily View</a>
            <a href="?view=weekly" class="filter-btn <?php echo $view == 'weekly' ? 'active' : ''; ?>">Weekly View</a>
        </div>

        <div id="report-content" class="report-content" data-date="<?php echo date('F j, Y g:i A'); ?>">
        <div class="report-header-print">
            <h1 class="report-title-print">YOBITA RESTAURANT</h1>
            <h2 class="report-subtitle-print">AI Sales Report - <?php echo ucfirst($view); ?> View</h2>
        </div>
        <div class="report-card">
            <h2 style="margin-top:0;">
                <?php echo ucfirst($view); ?> Performance Summary
            </h2>
            
            <div class="stats-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div style="background:#f1f3f4; padding:15px; border-radius:10px; text-align:center;">
                    <small style="text-transform:uppercase; color:#777;">Total Revenue</small>
                    <div style="font-size:1.8rem; font-weight:bold; color:#333;">
                        RM <?php echo number_format($report['revenue'], 2); ?>
                    </div>
                </div>
                <div style="background:#f1f3f4; padding:15px; border-radius:10px; text-align:center;">
                    <small style="text-transform:uppercase; color:#777;">Total Orders</small>
                    <div style="font-size:1.8rem; font-weight:bold; color:#333;">
                        <?php echo $report['orders']; ?>
                    </div>
                </div>
            </div>

            <h3 style="margin-bottom:10px;">🤖 AI Analysis:</h3>
            <div class="ai-summary-box sentiment-<?php echo $report['sentiment']; ?>">
                <?php echo nl2br(htmlspecialchars($report['summary'])); ?>
                <?php 
                    $clean_summary = str_replace("**", "", $report['summary']); 
                    // Or actually parse it to <b> tags if you prefer
                ?>
            </div>
        </div>
        <div class="report-footer-print">
            <p>Report Generated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>
        </div>

    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    // Print functionality
    function printReport() {
        window.print();
    }

    // PDF Download functionality
    function downloadPDF() {
        const { jsPDF } = window.jspdf;
        const container = document.querySelector('.admin-container');
        const view = container.getAttribute('data-view') || 'Report';
        
        // Show loading indicator
        const loadingMsg = document.createElement('div');
        loadingMsg.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:10000;';
        loadingMsg.innerHTML = '<p>Generating PDF...</p>';
        document.body.appendChild(loadingMsg);

        // Hide elements that shouldn't be in PDF
        const backLink = document.querySelector('.back-link');
        const reportActions = document.querySelector('.report-actions');
        const controls = document.querySelector('.controls');
        const statusBadge = document.querySelector('.ai-status-badge');
        const reportHeader = document.querySelector('.report-header-print');
        const reportFooter = document.querySelector('.report-footer-print');
        
        if (backLink) backLink.style.display = 'none';
        if (reportActions) reportActions.style.display = 'none';
        if (controls) controls.style.display = 'none';
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
                pdf.save('AI_Sales_Report_' + view + '_' + timestamp + '.pdf');
                
                // Restore hidden elements
                if (backLink) backLink.style.display = '';
                if (reportActions) reportActions.style.display = '';
                if (controls) controls.style.display = '';
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
                if (controls) controls.style.display = '';
                if (statusBadge) statusBadge.style.display = '';
                if (reportHeader) reportHeader.style.display = 'none';
                if (reportFooter) reportFooter.style.display = 'none';
                
                alert('Error generating PDF. Please try again.');
                document.body.removeChild(loadingMsg);
            });
        }, 500);
    }
</script>