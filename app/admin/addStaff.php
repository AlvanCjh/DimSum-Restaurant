<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = "";
$message_type = "";

if (isset($_POST['add_staff'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
        
        // --- NEW VALIDATION: Check for @yobita.com domain ---
        if (!preg_match("/@yobita\.com$/", $email)) {
            $message = "Registration restricted to @yobita.com emails only!";
            $message_type = "error";
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM staffs WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Email already exists!";
                    $message_type = "error";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $default_pfp = 'uploads/Default_pfp.png';
                    
                    $sql = "INSERT INTO staffs (username, email, password, role, profile_picture) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$username, $email, $hashed_password, $role, $default_pfp])) {
                        $message = "New " . htmlspecialchars(ucfirst($role)) . " added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Failed to add staff member.";
                        $message_type = "error";
                    }
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    } else {
        $message = "Please fill in all fields.";
        $message_type = "error";
    }
}

$pageTitle = "Add Staff";
$basePath = "../";
include '../_header.php';
?>

<style>
    body {
        background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?php echo $basePath; ?>image/background.jpg') !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
    }
    
    /* FIX: Push content down so Header doesn't block it */
    .main-wrapper {
        padding-top: 100px !important; 
    }
</style>

<link rel="stylesheet" href="<?php echo $basePath; ?>css/addStaff.css">

<main class="main-wrapper">
    <div class="admin-container">
        <div class="form-card">
            <a href="manageStaff.php" class="back-link">
                <ion-icon name="arrow-back-outline"></ion-icon> Back to Staff List
            </a>
            
            <div class="page-header">
                <h1>Register New Staff</h1>
                <p>Create a new account for a new staff</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php if($message_type == 'success'): ?>
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                    <?php else: ?>
                        <ion-icon name="alert-circle-outline"></ion-icon>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-group">
                    <div class="input-wrapper">
                        <ion-icon name="person-outline" class="input-icon"></ion-icon>
                        <input type="text" name="username" required placeholder="Username" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <ion-icon name="mail-outline" class="input-icon"></ion-icon>
                        <input type="email" name="email" required placeholder="Email Address (xxx@yobita.com)" 
                               autocomplete="off" 
                               pattern=".+@yobita\.com" 
                               title="Please enter an email ending in @yobita.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <ion-icon name="lock-closed-outline" class="input-icon"></ion-icon>
                        <input type="password" name="password" required placeholder="Password" autocomplete="new-password">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <div class="input-wrapper">
                        <ion-icon name="briefcase-outline" class="input-icon"></ion-icon>
                        <select name="role" required>
                            <option value="waiter">Waiter</option>
                            <option value="chef">Chef</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_staff" class="submit-btn">Register Staff</button>
            </form>
        </div>
    </div>
</main>