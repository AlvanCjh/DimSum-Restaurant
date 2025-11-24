<?php
session_start();
require_once '../connection.php';

$message = "";

// --- ADMIN SIGN IN LOGIC ---
if (isset($_POST['admin_login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    try {
        $sql = "SELECT * FROM users WHERE email = ? AND role = 'admin'";
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
            $message = "Invalid credentials or not an admin account.";
        }
    } catch (PDOException $e) {
        $message = "Database error. Please try again later.";
    }
}

// --- PAGE SETUP ---
$pageTitle = 'Admin Login';
$basePath = '../'; // Set base path for assets
include '../_header.php';
?>

<!-- Use the same stylesheet as the staff login for a consistent feel -->
<link rel="stylesheet" href="<?php echo $basePath; ?>css/signInUp.css">

<!-- Custom styles to adapt the two-panel layout for a single login form -->
<style>
    /* Add a subtle gradient background for this page */
    body {
        /* Restore background image with overlay */
        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo $basePath; ?>image/background.jpg') !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        display: flex; /* Helps center the container */
    }

    /* Animation for the container to fade in and slide up */
    @keyframes slideUpFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .container {
        width: 37%; /* Made the container wider */
        min-height: 75vh; /* Force a minimum height (75% of viewport height) */
            margin: auto; /* Vertically and horizontally center */
            overflow: visible;
        padding: 3rem 4rem; /* Adjusted padding for a more spacious feel */
        border-radius: 30px; /* Made the corners more curved */
        
        /* --- Reverted to Solid Container --- */
        background-color: var(--third); /* Solid white background */
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Standard soft shadow */

        animation: slideUpFadeIn 0.6s ease-out forwards;

        /* Add flex properties to center the form inside the taller container */
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* Keyframe for individual element animations */
    @keyframes element-fade-in {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-container form > * {
        opacity: 0; /* Start hidden */
        animation: element-fade-in 0.5s ease-out forwards;
    }

    .form-container { 
        width: 100%; 
        padding: 0;
        background: none;
    }

    .overlay-container { display: none; } /* Hide the sliding overlay */

    .admin-logo {
        width: 150px; /* Made the logo bigger */
        display: block;
        margin: 0 auto 1rem; /* Centered the logo */
        margin-bottom: 2rem; /* Increased space below logo */
        animation-delay: 0.2s; /* Stagger animation */
    }

    .form-container h1 {
        animation-delay: 0.3s;
        font-size: 2.5rem; /* Made the title bigger */
        margin-bottom: 1.5rem; /* Adjusted space below the title */
        color: var(--text-dark); /* Dark text for readability */
    }

    .form-container .infield {
        margin-bottom: 1.5rem; /* More space between input fields */
        animation-delay: 0.4s;
    }

    .form-container .infield:nth-of-type(2) { animation-delay: 0.5s; } /* Stagger animation for second input */

    .form-container .infield input {
        background-color: #f9f9f9; /* Light background for inputs */
        border: 1px solid var(--border-color); /* Standard border */
        padding: 1rem 1.2rem; /* More padding for taller inputs */
        border-radius: 10px; /* Slightly rounded input corners */
        color: var(--text-dark); /* Dark text for input */
        font-weight: 500;
    }

    .form-container .infield input:focus {
        border-color: var(--accent-dark);
        box-shadow: 0 0 0 3px rgba(229, 169, 169, 0.3);
    }

    .form-container .error-message {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        font-size: 0.9rem;
        animation-delay: 0.6s;
    }

    .form-container button {
        background: linear-gradient(to right, var(--primary), var(--accent-dark)); /* Gradient button */
        color: white;
        border: none;
        padding: 0.9rem 1.8rem; /* Adjusted padding to make button smaller */
        border-radius: 10px; /* Rounded button corners */
        font-size: 1rem; /* Adjusted font size to make button smaller */
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); /* Subtle shadow */
        animation-delay: 0.7s;
        transition: all 0.3s ease;
    }

    .form-container button:hover {
        /* Add a "glow" effect on hover */
        box-shadow: 0 8px 25px rgba(255, 220, 220, 0.4);
    }

</style>

<div class="container" id="container">
    <div class="form-container sign-in-container">
        <form method="post">
            <img src="<?php echo $basePath; ?>image/logo.png" alt="Yobita Logo" class="admin-logo">
            <h1>Admin Log In</h1>
            <div class="infield">
                <input type="email" placeholder="Email" name="email" required />
            </div>
            <div class="infield">
                <input type="password" placeholder="Password" name="password" required />
            </div>

            <?php if (!empty($message)) : ?>
                <p class="error-message"><?php echo $message; ?></p>
            <?php endif; ?>

            <button type="submit" name="admin_login">Log In</button>

        </form>
    </div>
</div>