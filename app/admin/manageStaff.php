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

// --- FETCH USERS & ORDER COUNTS ---
try {
    // We select users and count their orders by joining the orders table
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.email, 
                u.role, 
                u.profile_picture, 
                u.created_at,
                COUNT(o.id) as orders_handled
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            GROUP BY u.id
            ORDER BY u.role ASC, u.username ASC";
            
    $stmt = $pdo->query($sql);
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $staff_members = [];
    $message = "Error fetching staff: " . $e->getMessage();
}
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/viewOrders.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>css/manageStaff.css">

<main class="main-wrapper">
    <div class="admin-container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="page-header">
            <h1>Staff Directory</h1>
        </div>

        <div class="orders-list-section">
            <h2 class="section-title">All Users (<?php echo count($staff_members); ?>)</h2>
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
                        <?php foreach ($staff_members as $staff): ?>
                            <tr>
                                <td data-label="Profile">
                                    <img src="<?php echo $staff['profile_picture'] ? $basePath . $staff['profile_picture'] : $basePath.'uploads/Default_pfp.png'; ?>" 
                                         alt="pfp" class="table-pfp">
                                </td>
                                <td data-label="Name"><strong><?php echo htmlspecialchars($staff['username']); ?></strong></td>
                                <td data-label="Role">
                                    <span class="role-badge role-<?php echo $staff['role']; ?>">
                                        <?php echo ucfirst($staff['role']); ?>
                                    </span>
                                </td>
                                <td data-label="Email"><?php echo htmlspecialchars($staff['email']); ?></td>
                                <td data-label="Orders Handled"><?php echo $staff['orders_handled']; ?></td>
                                <td data-label="Joined"><?php echo date("d M Y", strtotime($staff['created_at'])); ?></td>
                                <td class="actions-cell" data-label="Actions">
                                    <a href="staffDetail.php?id=<?php echo $staff['id']; ?>" class="action-btn view-btn">
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