<?php
// --- 1. START SESSION & INCLUDE CONNECTION ---
session_start();

// Include connection.php from the same directory
require_once 'connection.php';

// Initialize message variable
$message = "";


// --- 2. SIGN UP LOGIC ---
if (isset($_POST['signup'])) {
    
    // Get form data
    $username = trim($_POST['name']); // The form input is "name"
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Set default profile picture path (relative to root)
    // Based on your screenshot: uploads/Default_pfp.png
    $profile_pic = 'uploads/Default_pfp.png';

    // Validate
    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } else {
        
        try {
            // Check if email already exists
            $sql_check = "SELECT id FROM users WHERE email = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$email]);

            if ($stmt_check->rowCount() > 0) {
                $message = "An account with this email already exists.";
            } else {
                // Email is available, hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $sql_insert = "INSERT INTO users (username, email, password, profile_picture) VALUES (?, ?, ?, ?)";
                $stmt_insert = $pdo->prepare($sql_insert);
                
                if ($stmt_insert->execute([$username, $email, $hashed_password, $profile_pic])) {
                    $message = "Account created! Please sign in.";
                } else {
                    $message = "Registration failed. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $message = "Database error. Please try again later.";
            // For debugging, you could use: $message = $e->getMessage();
        }
    }
}


// --- 3. SIGN IN LOGIC ---
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
            
            // Check if user is 'staff' or 'admin'
            if ($user['role'] == 'staff') {

                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                
                // Redirect to the main index.php (one level up)
                header("Location: staff/index.php"); 
                exit; 

            } else {
                $message = "You do not have permission to access this area.";
            }

        } else {
            $message = "Invalid email or password.";
        }

    } catch (PDOException $e) {
        $message = "Database error. Please try again later.";
        // For debugging, you could use: $message = $e->getMessage();
    }
}

include '_header.php'; 
?>

<link rel="stylesheet" href="css/signInUp.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<head>
    <script src="js/script.js" defer></script>
</head>

<div class="container" id="container">
    <div class="form-container sign-up-container">
       <form method="post">
           <h1>Create Staff Account</h1>
           <div class="infield">
               <input type="text" placeholder="Name" name="name" required />
           </div>
           <div class="infield">
               <input type="email" placeholder="Email" name="email" required />
           </div>
           <div class="infield">
               <input type="password" placeholder="Password" name="password" required />
           </div>

           <?php if (isset($_POST['signup']) && !empty($message)) { ?>
               <p class="error-message"><?php echo $message; ?></p>
           <?php } ?>

           <button type="submit" name="signup">Sign Up</button>
       </form>
   </div>


    <div class="form-container sign-in-container">
        <form method="post">
            <h1>Staff Sign In</h1>
            <div class="infield">
                <input type="email" placeholder="Email" name="email" required />
            </div>
            <div class="infield">
                <input type="password" placeholder="Password" name="password" required />
            </div>

            <?php if (isset($_POST['login']) && !empty($message)) { ?>
                <p class="error-message"><?php echo $message; ?></p>
            <?php } ?>

            <button type="submit" name="login">Sign In</button>
        </form>
    </div>


    <div class="overlay-container" id="overlayCon">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <img src="/image/logo.png" alt="Yobita Logo" class="overlay-logo">
                    <h1>Welcome Back!</h1>
                    <p>Already have an account? Log in to continue.</p>
                    <button class="ghost" id="signInBtn">Log In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <img src="/image/logo.png" alt="Yobita Logo" class="overlay-logo">
                    <h1>Hello, New Staff!</h1>
                    <p>Enter your details to create an account.</p>
                    <button class="ghost" id="signUpBtn">Sign Up</button>
                </div>
            </div>
    </div>
</div>