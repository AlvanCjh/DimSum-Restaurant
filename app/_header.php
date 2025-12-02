<?php
// Session should be started before including this file

if (!isset($pageTitle)) {
    $pageTitle = 'Home';
}

// Set base path for assets (defaults to current directory, can be set to "../" for subdirectories)
if (!isset($basePath)) {
    $basePath = '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?php echo $basePath; ?>css/index.css"> 
    <title><?php echo $pageTitle; ?> - Yobita</title> 

    <header class="main-header">
        <div class="header-content">
            <a href="<?php echo $basePath; ?>" class="logo">Yobita</a>
            
            <nav class="main-nav">
                <ul>
                    
                    
                    <!-- ===== LOGIN / PROFILE LOGIC START ===== -->

                    <?php if (isset($_SESSION['user_id'])) : ?>
                        
                        <!-- USER IS LOGGED IN -->
                        <li class="profile-nav-item">
                            <a href="<?php echo $basePath; ?>profile.php" class="profile-link <?php echo ($pageTitle === 'Profile') ? 'active' : ''; ?>">
                                <!-- Show profile pic -->
                                <img src="<?php echo $basePath . htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Profile" class="nav-profile-pic">
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </a>
                            <ul class="profile-dropdown">
                                <li><a href="<?php echo $basePath; ?>profile.php">Edit Profile</a></li>
                                <li><a href="<?php echo $basePath; ?>logout.php">Logout</a></li>
                            </ul>
                        </li>

                    <?php else : ?>

                        <!-- USER IS NOT LOGGED IN -->
                        <li class="dropdown">
                            <a href="#" class="login-link <?php echo ($pageTitle === 'Login') ? 'active' : ''; ?>">Login</a>
                            <ul class="dropdown-menu">
                                <li><a href="<?php echo $basePath; ?>index.php">Staff Login</a></li>
                                <li><a href="<?php echo $basePath; ?>chef/login.php">Chef Login</a></li>
                                <li><a href="<?php echo $basePath; ?>admin/login.php">Admin Login</a></li> 
                            </ul>
                        </li>

                    <?php endif; ?>
                    
                    <!-- ===== LOGIN / PROFILE LOGIC END ===== -->
                    
                    <!-- ===== CART - Only show if user is logged in as staff ===== -->
                    
                </ul>
            </nav>
        </div>
    </header>

     <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <!-- Cropper -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo $basePath; ?>js/script.js" defer></script>
</head>
<body>
<!-- The rest of your page content (e.g., in index.php) will go here -->