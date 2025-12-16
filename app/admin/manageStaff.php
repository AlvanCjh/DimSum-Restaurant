<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Manage Staff";
$basePath = "../";
include '../_header.php';

// --- FETCH staffs & ORDER COUNTS ---
try {
    $sql = "SELECT 
                u.id, u.username, u.email, u.role, u.profile_picture, u.created_at,
                COUNT(o.id) as orders_handled,
                COALESCE(SUM(o.total_amount), 0) as total_revenue,
                MAX(o.created_at) as last_active
            FROM staffs u
            LEFT JOIN orders o ON u.id = o.user_id
            GROUP BY u.id
            ORDER BY u.role ASC, u.username ASC";
            
    $stmt = $pdo->query($sql);
    $waiter_members = $stmt->fetchAll();

    // --- AI badge evaluation via Python helper ---
    function computeAiBadges($waiter_members) {
        $scriptPath = realpath(__DIR__ . '/../ai/staff_badges.py');
        if (!$scriptPath) return [];

        $payload = ["staff" => []];
        foreach ($waiter_members as $m) {
            // Only analyze waiters
            if ($m['role'] === 'waiter') {
                $payload["staff"][] = [
                    "id" => $m["id"],
                    "orders_handled" => (int)$m["orders_handled"],
                    "total_revenue" => (float)$m["total_revenue"],
                    "last_active" => $m["last_active"],
                    "created_at" => $m["created_at"],
                ];
            }
        }

        if (empty($payload["staff"])) return [];

        $descriptorSpec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $cmd = escapeshellcmd("python") . " " . escapeshellarg($scriptPath);
        $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__ . '/../');

        if (!is_resource($process)) return [];

        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($process);

        $decoded = json_decode($output, true);
        if (!isset($decoded["badges"])) return [];

        $map = [];
        foreach ($decoded["badges"] as $b) { $map[$b["id"]] = $b; }
        return $map;
    }

    $aiBadges = computeAiBadges($waiter_members);
    
    // Assign Badges Logic
    foreach ($waiter_members as &$member) {
        if ($member['role'] === 'waiter') {
            $memberBadge = $aiBadges[$member["id"]] ?? null;
            $member["ai_badge"] = $memberBadge["label"] ?? "Consistent";
            $member["ai_badge_reason"] = $memberBadge["reason"] ?? "Steady performance";
        } else {
            // Clear badges for non-waiters
            $member["ai_badge"] = "";
            $member["ai_badge_reason"] = "";
        }
    }
    unset($member);
} catch (PDOException $e) {
    $waiter_members = [];
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/viewOrders.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>css/manageStaff.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <div>
                <h1>Staff Directory</h1>
                <p style="color:#777; margin:5px 0 0 0;">Manage roles and view AI performance metrics</p>
            </div>
            
            <div class="ai-status-badge">
                <span class="pulse-dot"></span>
                AI Staff Ranking Active
            </div>
        </div>

        <div class="orders-list-section">
            <h2 class="section-title">All Staff (<?php echo count($waiter_members); ?>)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Orders Handled</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($waiter_members as $waiter): ?>
                            <tr>
                                <td data-label="Profile">
                                    <img src="<?php echo $waiter['profile_picture'] ? $basePath . $waiter['profile_picture'] : $basePath.'uploads/Default_pfp.png'; ?>" 
                                         alt="pfp" class="table-pfp">
                                </td>
                                <td data-label="Name">
                                    <strong><?php echo htmlspecialchars($waiter['username']); ?></strong>
                                    
                                    <?php if (!empty($waiter['ai_badge'])): ?>
                                        <div class="ai-badge-container">
                                            <span class="ai-badge">
                                                <?php echo htmlspecialchars($waiter['ai_badge']); ?>
                                            </span>
                                            <span class="ai-badge-reason">
                                                <?php echo htmlspecialchars($waiter['ai_badge_reason']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Role">
                                    <span class="role-badge role-<?php echo $waiter['role']; ?>">
                                        <?php echo ucfirst($waiter['role']); ?>
                                    </span>
                                </td>
                                <td data-label="Email"><?php echo htmlspecialchars($waiter['email']); ?></td>
                                <td data-label="Orders Handled"><?php echo $waiter['orders_handled']; ?></td>
                                <td data-label="Joined"><?php echo date("d M Y", strtotime($waiter['created_at'])); ?></td>
                                <td class="actions-cell" data-label="Actions">
                                    <a href="staffDetail.php?id=<?php echo $waiter['id']; ?>" class="action-btn view-btn">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>