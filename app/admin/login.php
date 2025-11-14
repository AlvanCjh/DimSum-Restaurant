<?php
// --- 1. START SESSION & INCLUDE CONNECTION ---
session_start();

$pageTitle = "Admin Login"; 
$basePath = "../";
include '../_header.php';

// This path assumes your admin login file is in /admin/login.php
// It goes up one level to the root to find connection.php
require_once '../connection.php'; 

// Initialize message variable
$message = "";

// --- 2. ADMIN SIGN IN LOGIC ---
if (isset($_POST['login'])) {
    
    // Get form data
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    try {
        // Find user by email
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            
            // --- VALIDATION ---
            // Check if the user's role is 'admin'
            if ($user['role'] == 'admin') {

                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Redirect to the main admin dashboard or index
                // (Using ../index.php as per your previous staff login)
                header("Location: dashboard.php"); 
                exit; 

            } else {
                // User is 'staff' or another role, but not 'admin'
                $message = "You do not have permission to access the admin area.";
            }

        } else {
            // User not found or password was incorrect
            $message = "Invalid email or password.";
        }

    } catch (PDOException $e) {
        $message = "Database error. Please try again later.";
        // For debugging: $message = $e->getMessage();
    }
}
?>



<link rel="stylesheet" href="/css/adminSignIn.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<div class="container" id="container">
    <div class="form-container sign-in-container">
        <form method="post">
            <h1>Admin Sign In</h1>
            <div class="infield">
                <input type="email" placeholder="Email" name="email" required />
            </div>
            <div class="infield">
                <input type="password" placeholder="Password" name="password" required />
            </div>

            <?php 
            // --- 3. DISPLAY ERROR MESSAGE ---
            // This checks if a login was attempted and if the $message is not empty
            if (isset($_POST['login']) && !empty($message)) { 
            ?>
                <p class="error-message"><?php echo $message; ?></p>
            <?php 
            } 
            ?>

            <button type="submit" name="login">Sign In</button>
        </form>
    </div>
</div>