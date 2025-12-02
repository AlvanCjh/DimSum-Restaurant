<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Manage waiter";
$basePath = "../";
include '../_header.php';

// --- FETCH staffs & ORDER COUNTS ---
try {
    // We select staffs and count their orders by joining the orders table
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.email, 
                u.role, 
                u.profile_picture, 
                u.created_at,
                COUNT(o.id) as orders_handled
            FROM staffs u
            LEFT JOIN orders o ON u.id = o.user_id
            GROUP BY u.id
            ORDER BY u.role ASC, u.username ASC";
            
    $stmt = $pdo->query($sql);
    $waiter_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $waiter_members = [];
    $message = "Error fetching waiter: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/viewOrders.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>css/manageStaff.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>waiter Directory</h1>
        </div>

        <div class="orders-list-section">
            <h2 class="section-title">All staffs (<?php echo count($waiter_members); ?>)</h2>
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
                                <td data-label="Name"><strong><?php echo htmlspecialchars($waiter['username']); ?></strong></td>
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