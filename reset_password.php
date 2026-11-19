<?php
session_start();
require 'database/DBconnect.php';
require 'header.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$show_form = true;

// Function to validate password strength
function validatePassword($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Validate password strength
        $password_errors = validatePassword($new_password);
        if (!empty($password_errors)) {
            $error = implode("<br>", $password_errors);
        } else {
            // Validate token again
            $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {
                $error = "Invalid or expired reset token.";
                $show_form = false;
            } else {
                $row = $result->fetch_assoc();
                if (new DateTime() > new DateTime($row['expires_at'])) {
                    $error = "Reset token has expired. Please request a new one.";
                    $show_form = false;
                } else {
                    // Update user password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                    $update_stmt->bind_param("ss", $hashed_password, $row['email']);
                    if ($update_stmt->execute()) {
                        // Delete the token after successful reset
                        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                        $delete_stmt->bind_param("s", $token);
                        $delete_stmt->execute();

                        // Log the password reset
                        error_log("Password reset successful for email: {$row['email']}");
                        
                        $success = "Your password has been reset successfully! You can now <a href='index.php' class='text-blue-600 hover:text-blue-800'>log in</a>.";
                        $show_form = false;
                    } else {
                        $error = "Failed to reset password. Please try again.";
                        error_log("Password reset failed for email: {$row['email']}");
                    }
                    $update_stmt->close();
                }
            }
            $stmt->close();
        }
    }
} else {
    // Initial token validation on GET request
    if (empty($token)) {
        $error = "Invalid reset link.";
        $show_form = false;
    } else {
        $stmt = $conn->prepare("SELECT expires_at FROM password_resets WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $error = "Invalid or expired reset token.";
            $show_form = false;
        } else {
            $row = $result->fetch_assoc();
            if (new DateTime() > new DateTime($row['expires_at'])) {
                $error = "Reset token has expired. Please request a new one.";
                $show_form = false;
            }
        }
        $stmt->close();
    }
}
?>

<div class="container mx-auto py-12 px-8 max-w-md">
    <div class="bg-white rounded-xl shadow-lg p-8">
        <h2 class="text-2xl font-bold text-center mb-6 text-blue-900">Reset Your Password</h2>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-6 text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-6 text-center"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-5">
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" id="new_password" name="new_password" required 
                           class="w-full border-gray-300 rounded-md px-3 py-2.5 focus:ring-blue-400 focus:border-blue-400" 
                           placeholder="Enter new password">
                    <div class="mt-2 text-sm text-gray-500">
                        Password must contain:
                        <ul class="list-disc list-inside">
                            <li>At least 8 characters</li>
                            <li>One uppercase letter</li>
                            <li>One lowercase letter</li>
                            <li>One number</li>
                            <li>One special character</li>
                        </ul>
                    </div>
                </div>
                <div class="mb-5">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="w-full border-gray-300 rounded-md px-3 py-2.5 focus:ring-blue-400 focus:border-blue-400" 
                           placeholder="Confirm new password">
                </div>
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2.5 rounded-md hover:bg-blue-700 hover:scale-105 transition text-sm font-medium">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
