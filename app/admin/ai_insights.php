<?php
session_start();
require_once '../connection.php';
require_once '../ai/ai_helper.php'; // Ensures we have the logic

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch Briefing
$briefing = generateDailyBriefing($pdo);

// Fetch AI Data
$aiInsight = getTopAssociationRule($pdo);
$forecast = getRevenueForecast($pdo);

$pageTitle = "AI Insights";
$basePath = "../";
include '../_header.php';
?>

<link rel="stylesheet" href="/css/ai_insights.css">

<main class="main-wrapper">
    <div class="ai-container">
        <a href="dashboard.php" class="back-link" style="color: #fff;">← Back to Dashboard</a>
        
        <div class="ai-header-section">
            <h1>AI Insights Hub</h1>
            <p>Real-time analysis of your restaurant's performance</p>
        </div>

        <div class="revenue-card">
            <div class="revenue-title">Predicted Daily Revenue</div>
            <div class="revenue-amount">RM <?php echo $forecast; ?></div>
            <div class="revenue-note">✨ Based on historical daily averages</div>
        </div>

        <div class="combo-section">
            <div class="combo-visuals">
                <?php if (!empty($aiInsight['images']) && isset($aiInsight['images'][0])): ?>
                    <img src="../<?php echo htmlspecialchars($aiInsight['images'][0]); ?>" class="combo-img">
                <?php else: ?>
                    <div class="combo-img" style="background:#eee; display:flex; align-items:center; justify-content:center;">?</div>
                <?php endif; ?>

                <div class="plus-sign">+</div>

                <?php if (!empty($aiInsight['images']) && isset($aiInsight['images'][1])): ?>
                    <img src="../<?php echo htmlspecialchars($aiInsight['images'][1]); ?>" class="combo-img">
                <?php else: ?>
                    <div class="combo-img" style="background:#eee; display:flex; align-items:center; justify-content:center;">?</div>
                <?php endif; ?>
            </div>

            <div class="combo-content">
                <span class="badge">🔥 Trending Combo</span>
                <div class="combo-title">
                    <?php echo $aiInsight['text']; ?>
                </div>
                <div class="recommendation-box">
                    <?php echo $aiInsight['suggestion']; ?>
                </div>
            </div>
        </div>

    </div>
</main>