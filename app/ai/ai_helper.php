<?php
// app/ai/ai_helper.php

function getTopAssociationRule($pdo) {
    // This SQL query finds the two items most frequently ordered together
    // It joins the order_items table to itself to find pairs
    $sql = "SELECT 
                m1.name AS item_1, 
                m1.image_url AS image_1,
                m2.name AS item_2, 
                m2.image_url AS image_2,
                COUNT(*) AS frequency
            FROM order_items a
            JOIN order_items b ON a.order_id = b.order_id AND a.menu_item_id < b.menu_item_id
            JOIN menu_items m1 ON a.menu_item_id = m1.id
            JOIN menu_items m2 ON b.menu_item_id = m2.id
            GROUP BY a.menu_item_id, b.menu_item_id
            ORDER BY frequency DESC
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        return [
            'title' => '🔥 Popular Combo Detected',
            'text' => "Data shows that <strong>{$result['item_1']}</strong> is frequently purchased with <strong>{$result['item_2']}</strong>.",
            'suggestion' => "💡 <strong>AI Tip:</strong> Create a 'Best Seller Bundle' with these two items to increase average order value!",
            'frequency' => $result['frequency'],
            'images' => [$result['image_1'], $result['image_2']]
        ];
    } else {
        // Fallback if not enough data yet
        return [
            'title' => '🤖 AI Learning...',
            'text' => "Not enough order data yet to detect patterns.",
            'suggestion' => "Keep selling! I will analyze trends once we have more orders.",
            'images' => []
        ];
    }
}

function getRevenueForecast($pdo) {
    // Simple prediction: Calculate average daily revenue and predict tomorrow's
    try {
        $sql = "SELECT AVG(daily_total) as avg_revenue FROM (
                    SELECT DATE(created_at) as date, SUM(total_amount) as daily_total 
                    FROM orders 
                    WHERE status = 'completed' 
                    GROUP BY DATE(created_at)
                ) as daily_sales";
        $stmt = $pdo->query($sql);
        $avg = $stmt->fetchColumn();
        return $avg ? number_format($avg, 2) : "0.00";
    } catch (Exception $e) {
        return "0.00";
    }
}

// app/ai/ai_helper.php

function generateDailyBriefing($pdo) {
    // 1. Get Today's Total
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->execute([$today]);
    $todayTotal = $stmt->fetchColumn() ?: 0;

    // 2. Get Average for this Day of Week (e.g., previous Tuesdays)
    $dayOfWeek = date('w'); // 0 (Sun) - 6 (Sat)
    $sqlAvg = "SELECT AVG(daily_total) FROM (
                SELECT DATE(created_at) as d, SUM(total_amount) as daily_total 
                FROM orders 
                WHERE DAYOFWEEK(created_at) = ? AND DATE(created_at) != ? AND status = 'completed'
                GROUP BY DATE(created_at)
               ) as history";
    $stmtAvg = $pdo->prepare($sqlAvg);
    $stmtAvg->execute([$dayOfWeek + 1, $today]); // SQL DAYOFWEEK is 1-7
    $avgTotal = $stmtAvg->fetchColumn() ?: 0;

    // 3. Find Peak Hour
    $sqlPeak = "SELECT HOUR(created_at) as hr, COUNT(*) as cnt 
                FROM orders 
                WHERE DATE(created_at) = ? 
                GROUP BY HOUR(created_at) 
                ORDER BY cnt DESC LIMIT 1";
    $stmtPeak = $pdo->prepare($sqlPeak);
    $stmtPeak->execute([$today]);
    $peakData = $stmtPeak->fetch();
    $peakHour = $peakData ? date("g A", strtotime($peakData['hr'] . ":00")) : "N/A";

    // 4. Construct the Narrative
    $diff = $todayTotal - $avgTotal;
    $percent = $avgTotal > 0 ? round(($diff / $avgTotal) * 100) : 100;
    $trend = $diff >= 0 ? "up 📈" : "down 📉";
    $adjective = $diff >= 0 ? "strong" : "slow";
    
    $todayFormatted = number_format($todayTotal, 2);

    return [
        'headline' => "Today's Performance is $trend ($percent%)",
        'body' => "You have generated <strong>RM $todayFormatted</strong> so far today, which is a <strong>$adjective</strong> performance compared to your typical " . date('l') . ". The busiest time of the day was around <strong>$peakHour</strong>.",
        'action' => $diff < 0 ? "💡 Tip: Consider running a 'Happy Hour' promo next " . date('l') . " to boost sales." : "🎉 Great job! Your current strategy is working."
    ];
}
?>

