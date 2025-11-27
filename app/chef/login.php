<?php
session_start();
require_once '../connection.php';

$message = "";

// --- ADMIN SIGN IN LOGIC ---
if (isset($_POST['admin_login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    try {
        $sql = "SELECT * FROM users WHERE email = ? AND role = 'chef'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Login successful, set session variables
            $_SESSION['user_id'] = $admin['id']; // This was missing
            $_SESSION['username'] = $admin['username']; // This was missing
            $_SESSION['role'] = $admin['role']; // This was missing
            $_SESSION['profile_picture'] = $admin['profile_picture']; // This was missing

            // Redirect to admin dashboard
            header("Location: dashboard.php");
            exit;

        } else {
            $message = "Invalid credentials or not a chef account.";
        }
    } catch (PDOException $e) {
        $message = "Database error. Please try again later.";
    }
}

// --- PAGE SETUP ---
$pageTitle = 'chef Login';
$basePath = '../'; // Set base path for assets
include '../_header.php';
?>

<!-- Use the same stylesheet as the staff login for a consistent feel -->
<link rel="stylesheet" href="<?php echo $basePath; ?>css/signIn.css">

<div class="container" id="container">
    <div class="form-container">
        <form method="post">
            <img src="<?php echo $basePath; ?>image/logo.png" alt="Yobita Logo" class="admin-logo anim-1">
            
            <h1 class="anim-2">Chef Log In</h1>
            
            <div class="infield anim-3">
                <input type="email" placeholder="Email" name="email" required />
            </div>
            <div class="infield anim-4">
                <input type="password" placeholder="Password" name="password" required />
            </div>

            <?php if (!empty($message)) : ?>
                <p class="error-message anim-5"><?php echo $message; ?></p>
            <?php endif; ?>

            <button type="submit" name="admin_login" class="anim-5">Log In</button>

        </form>
    </div>
</div>