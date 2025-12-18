<?php
session_start();
require_once 'connection.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pageTitle = 'Profile';
$message = '';
$error = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT username, email, profile_picture FROM staffs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Could not fetch user data: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- PROCESS FORM DATA ---
    $userId = $_SESSION['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $imageData = $_POST['image_data'] ?? null;

    $updateFields = [];
    $updateValues = [];

    // --- VALIDATE AND PREPARE UPDATES ---

    // Update username if changed
    if ($username !== $user['username']) {
        $updateFields[] = "username = ?";
        $updateValues[] = $username;
    }

    // Update email if changed
    if ($email !== $user['email']) {
        
        // --- NEW VALIDATION: Check for @yobita.com domain ---
        if (!preg_match("/@yobita\.com$/", $email)) {
            $error = "Email address must end with @yobita.com";
        } 
        // Validate email format
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address (e.g., user@yobita.com).";
        } else {
            // Check if new email is already taken
            $stmt = $pdo->prepare("SELECT id FROM staffs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = "This email address is already in use by another account.";
            } else {
                $updateFields[] = "email = ?";
                $updateValues[] = $email;
            }
        }
    }

    // Update password if new one is provided and matches
    if (empty($error) && !empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateFields[] = "password = ?";
            $updateValues[] = $hashedPassword;
        }
    }

    // --- UPDATED IMAGE SAVING LOGIC ---
    // Update profile picture if new one is uploaded
    if (empty($error) && $imageData) {
        // Decode base64 image data
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
        
        // Define the target directory
        $targetDir = 'uploads/profile/';
        
        // Create the directory if it doesn't exist
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Set the filename with the correct path
        $filename = $targetDir . 'profile_' . $userId . '_' . time() . '.png';
        
        // Save the new image file
        if (file_put_contents($filename, $data)) {
            // Delete old picture if it's not the default one
            if ($user['profile_picture'] && $user['profile_picture'] !== 'uploads/Default_pfp.png' && file_exists($user['profile_picture'])) {
                unlink($user['profile_picture']);
            }
            $updateFields[] = "profile_picture = ?";
            $updateValues[] = $filename;
        } else {
            $error = "Failed to save the new profile picture.";
        }
    }

    // --- EXECUTE DATABASE UPDATE ---
    if (empty($error) && !empty($updateFields)) {
        $sql = "UPDATE staffs SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateValues[] = $userId;

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($updateValues)) {
                $message = "Profile updated successfully!";

                // Refresh session variables and user data for display
                $_SESSION['username'] = $username;
                if (isset($filename)) {
                    $_SESSION['profile_picture'] = $filename;
                }
                $stmt = $pdo->prepare("SELECT username, email, profile_picture FROM staffs WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

            } else {
                $error = "Failed to update profile. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (empty($error) && empty($updateFields)) {
        $message = "No changes were made.";
    }
}

include '_header.php';
?>

<style>
    body {
        /* Apply the background image and overlay */
        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo $basePath; ?>image/background.jpg') !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        display: flex; /* Helps center the container vertically */
    }
</style>

<link rel="stylesheet" href="css/profile.css">

<div class="profile-container">
    <h2>Edit Profile</h2>

    <?php if ($message): ?><p class="message success"><?php echo $message; ?></p><?php endif; ?>
    <?php if ($error): ?><p class="message error"><?php echo $error; ?></p><?php endif; ?>

    <form action="profile.php" method="post" id="profile-form">
        <div class="pfp-section">
            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" id="pfp-preview" style="animation-delay: 0.2s;">
            <label for="pfp-upload" class="pfp-upload-btn" style="animation-delay: 0.3s;">Change Picture</label>
            <input type="file" id="pfp-upload" accept="image/*" style="display: none;">
            <input type="hidden" name="image_data" id="image_data">
        </div>

        <div class="form-group">
            <label for="username" style="animation-delay: 0.4s;">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required style="animation-delay: 0.5s;">
        </div>

        <div class="form-group">
            <label for="email" style="animation-delay: 0.6s;">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                   required style="animation-delay: 0.7s;" 
                   pattern=".+@yobita\.com" 
                   title="Please enter an email ending in @yobita.com">
            <small style="color: #888; display: block; margin-top: 5px;">Please enter a valid email (xxx@yobita.com)</small>
        </div>

        <hr style="animation-delay: 0.8s;">
        <h4 style="animation-delay: 0.9s;">Change Password (optional)</h4>
        <div class="form-group">
            <label for="new_password" style="animation-delay: 1.0s;">New Password</label>
            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password" style="animation-delay: 1.1s;">
        </div>

        <div class="form-group">
            <label for="confirm_password" style="animation-delay: 1.2s;">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" style="animation-delay: 1.3s;">
        </div>

        <button type="submit" class="submit-btn" style="animation-delay: 1.4s;">Save Changes</button>
    </form>
</div>

<div id="crop-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>Crop Your New Profile Picture</h2>
        <div class="crop-container">
            <img id="image-to-crop">
        </div>
        <button id="crop-button" class="submit-btn">Crop and Save</button>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const pfpUpload = document.getElementById('pfp-upload');
    const pfpPreview = document.getElementById('pfp-preview');
    const modal = document.getElementById('crop-modal');
    const imageToCrop = document.getElementById('image-to-crop');
    const cropButton = document.getElementById('crop-button');
    const closeModal = document.querySelector('.close-modal');
    const imageDataInput = document.getElementById('image_data');
    let cropper;

    // Open file selector when "Change Picture" is clicked
    pfpPreview.addEventListener('click', () => pfpUpload.click());
    document.querySelector('.pfp-upload-btn').addEventListener('click', () => pfpUpload.click());

    // Handle file selection
    pfpUpload.addEventListener('change', function (e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const reader = new FileReader();
            reader.onload = function (event) {
                imageToCrop.src = event.target.result;
                modal.style.display = 'block';
                if (cropper) {
                    cropper.destroy();
                }
                cropper = new Cropper(imageToCrop, {
                    aspectRatio: 1,
                    viewMode: 1,
                    background: false,
                    responsive: true,
                    autoCropArea: 1,
                    movable: false,
                    zoomable: false,
                });
            };
            reader.readAsDataURL(files[0]);
        }
    });

    // Handle modal close
    closeModal.onclick = function () {
        modal.style.display = 'none';
        if (cropper) {
            cropper.destroy();
        }
        pfpUpload.value = ''; // Reset file input
    }

    // Handle crop button click
    cropButton.addEventListener('click', function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas({
                width: 250,
                height: 250,
            });
            // Update preview on the page
            pfpPreview.src = canvas.toDataURL();
            // Store base64 data in hidden input for form submission
            imageDataInput.value = canvas.toDataURL('image/png');
            // Close modal
            closeModal.onclick();
        }
    });
});
</script>