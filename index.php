<?php
session_start();
require 'database/DBconnect.php';
include 'header.php';
$error = '';
$reset_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, password_hash, role, first_name FROM users WHERE email = ?");
        if (!$stmt) {
            $error = 'Database error. Please try again later.';
            error_log("index.php - Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    error_log("index.php - Login successful, user_id: {$user['user_id']}, role: {$user['role']}, email: {$email}");

                    if ($user['role'] === 'admin') {
                        header('Location: Pages/Admins/dashboard.php');
                    } elseif ($user['role'] === 'doctor') {
                        header('Location: Pages/Doctors/Home.php');
                    } elseif ($user['role'] === 'patient') {
                        header('Location: Pages/Patients/Home.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                    error_log("index.php - Invalid password for email: {$email}");
                }
            } else {
                $error = 'Invalid email or password.';
                error_log("index.php - User not found for email: {$email}");
            }
            $stmt->close();
        }
    }
}

// Forgot Password Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['reset_email'] ?? '');
    if (empty($email)) {
        $reset_message = 'Please enter your email address.';
    } else {
        // Check rate limiting
        $stmt_check_rate = $conn->prepare("SELECT COUNT(*) as count FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        if (!$stmt_check_rate) {
            $reset_message = 'Database error. Please try again later.';
            error_log("index.php - Prepare failed (rate check): " . $conn->error);
        } else {
            $stmt_check_rate->bind_param("s", $email);
            if (!$stmt_check_rate->execute()) {
                $reset_message = 'Database error. Please try again later.';
                error_log("index.php - Execute failed (rate check): " . $stmt_check_rate->error);
            } else {
                $rate_result = $stmt_check_rate->get_result();
                $rate_row = $rate_result->fetch_assoc();
                $stmt_check_rate->close();

                if ($rate_row['count'] >= 3) {
                    $reset_message = 'Too many reset attempts. Please try again in an hour.';
                    error_log("index.php - Rate limit exceeded for email: {$email}");
                } else {
                    // Prepare first statement to check if email exists
                    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    if (!$stmt_check) {
                        $reset_message = 'Database error. Please try again later.';
                        error_log("index.php - Prepare failed (check): " . $conn->error);
                    } else {
                        $stmt_check->bind_param("s", $email);
                        $stmt_check->execute();
                        $result = $stmt_check->get_result();

                        if ($result->num_rows === 1) {
                            // Email found, create token and expiration
                            $token = bin2hex(random_bytes(32));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                            // Close first statement before next prepare
                            $stmt_check->close();

                            // Prepare second statement to insert/update password reset token
                            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                            if (!$stmt_insert) {
                                $reset_message = 'Database error. Please try again later.';
                                error_log("index.php - Prepare failed (insert): " . $conn->error);
                            } else {
                                $stmt_insert->bind_param("sssss", $email, $token, $expires_at, $token, $expires_at);
                                if ($stmt_insert->execute()) {
                                    $reset_link = "http://$_SERVER[HTTP_HOST]/DocConnect/reset_password.php?token=$token";
                                    
                                    // HTML email template
                                    $subject = "DocConnect Password Reset";
                                    $message = "
                                    <html>
                                    <head>
                                        <style>
                                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                            .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 6px; }
                                            .footer { margin-top: 20px; font-size: 12px; color: #666; }
                                        </style>
                                    </head>
                                    <body>
                                        <div class='container'>
                                            <h2>Password Reset Request</h2>
                                            <p>Hello,</p>
                                            <p>We received a request to reset your password. Click the button below to reset your password:</p>
                                            <p style='text-align: center;'>
                                                <a href='$reset_link' class='button'>Reset Password</a>
                                            </p>
                                            <p>If you didn't request this, you can safely ignore this email.</p>
                                            <p>This link will expire in 1 hour.</p>
                                            <div class='footer'>
                                                <p>Best regards,<br>DocConnect Team</p>
                                            </div>
                                        </div>
                                    </body>
                                    </html>";

                                    $headers = "MIME-Version: 1.0" . "\r\n";
                                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                                    $headers .= "From: DocConnect <no-reply@docconnect.com>" . "\r\n";

                                    if (@mail($email, $subject, $message, $headers)) {
                                        $reset_message = 'A password reset link has been sent to your email.';
                                        error_log("index.php - Reset email sent successfully to: {$email}");
                                    } else {
                                        $reset_message = 'Failed to send reset email. Please try again.';
                                        error_log("index.php - Mail failed for email: {$email}");
                                    }
                                } else {
                                    $reset_message = 'Failed to process reset request. Please try again.';
                                    error_log("index.php - Insert failed: " . $stmt_insert->error);
                                }
                                $stmt_insert->close();
                            }
                        } else {
                            $reset_message = 'No account found with that email.';
                            $stmt_check->close();
                        }
                    }
                }
            }
        }
    }
}

?>

<!-- Modernized Login Page -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body {
    min-height: 100vh;
    background: linear-gradient(120deg, #e0e7ff 0%, #f8fafc 100%);
    font-family: 'Poppins', 'Inter', Arial, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow-x: hidden;
  }
  /* Floating shapes background */
  .floating-bg {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
  }
  .floating-shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.18;
    filter: blur(2px);
    animation: float 8s ease-in-out infinite alternate;
  }
  .shape1 { width: 180px; height: 180px; background: #2563eb; left: 5vw; top: 10vh; animation-delay: 0s; }
  .shape2 { width: 120px; height: 120px; background: #60a5fa; right: 8vw; top: 20vh; animation-delay: 2s; }
  .shape3 { width: 90px; height: 90px; background: #3b82f6; left: 20vw; bottom: 8vh; animation-delay: 1s; }
  .shape4 { width: 140px; height: 140px; background: #1d4ed8; right: 15vw; bottom: 12vh; animation-delay: 3s; }
  @keyframes float {
    from { transform: translateY(0) scale(1); }
    to { transform: translateY(-30px) scale(1.08); }
  }
  .login-center-wrapper {
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
    z-index: 1;
    position: relative;
    padding: 3.5rem 2.5rem;
  }
  .login-card {
    background: rgba(255,255,255,0.85);
    border-radius: 2.5rem;
    box-shadow: 0 12px 40px rgba(37,99,235,0.13);
    padding: 3.5rem 3.2rem 2.5rem 3.2rem;
    width: 100%;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    animation: fadeIn 0.7s cubic-bezier(.4,0,.2,1);
  }
  .login-card:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 20px 25px -5px rgba(37,99,235,0.13), 0 10px 10px -5px rgba(37,99,235,0.07);
  }
  .brand-icon {
    font-size: 4rem;
    color: #2563eb;
    margin-bottom: 0.7rem;
    animation: pulse 1.5s infinite alternate;
    display: block;
    text-align: center;
  }
  .brand-title {
    font-size: 2.7rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: #2563eb;
    text-align: center;
    margin-bottom: 0.2rem;
    text-shadow: 0 2px 8px rgba(37,99,235,0.10);
  }
  .brand-tagline {
    font-size: 1.3rem;
    font-weight: 400;
    color: #64748b;
    text-align: center;
    margin-bottom: 1.5rem;
    letter-spacing: 0.01em;
    opacity: 0.92;
  }
  .welcome-message {
    font-size: 1.3rem;
    color: #1e293b;
    text-align: center;
    margin-bottom: 1.2rem;
    font-weight: 500;
  }
  .benefit-message {
    font-size: 1.15rem;
    color: #3b82f6;
    text-align: center;
    margin-bottom: 1.8rem;
    font-weight: 400;
  }
  .gradient-accent {
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    height: 4px;
    border-radius: 2px;
    width: 100%;
    position: absolute;
    top: 0; left: 0;
  }
  .input-icon {
    position: relative;
  }
  .input-icon i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    transition: color 0.3s ease;
  }
  .input-icon input {
    padding-left: 2.5rem;
    transition: all 0.3s ease;
    border: 1.5px solid #e2e8f0;
  }
  .input-icon input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  .input-icon input:focus + i {
    color: #3b82f6;
  }
  .fade-in {
    animation: fadeIn 0.6s ease-out;
  }
  .slide-in {
    animation: slideIn 0.6s ease-out;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
  }
  @keyframes pulse {
    from { transform: scale(1); }
    to { transform: scale(1.12); }
  }
  .btn-primary {
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    border: none;
    transition: all 0.3s ease;
  }
  .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
  }
  .form-check-input:checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
  }
  .error-message {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    animation: shake 0.5s ease-in-out;
  }
  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
  }
  @media (max-width: 900px) {
    .login-center-wrapper { max-width: 98vw; padding: 1.2rem 0.2rem; }
    .login-card { padding: 1.2rem 0.7rem 1.2rem 0.7rem; }
    .brand-title { font-size: 1.5rem; }
    .brand-tagline { font-size: 1rem; }
  }
</style>

<div class="floating-bg">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
  <div class="floating-shape shape4"></div>
</div>
<div class="login-center-wrapper">
  <div class="login-card">
    <div class="gradient-accent"></div>
    <div class="text-center mb-3">
    </div>
    <div class="brand-icon text-center mb-2"><i class="fa-solid fa-heart-pulse"></i></div>
    <div class="brand-title">DocCenter</div>
    <div class="brand-tagline">Your Health, Connected</div>
    <div class="welcome-message">Welcome Back!</div>
    <div class="benefit-message">Access your health records, appointments, and more—all in one place.</div>
    <?php if ($error): ?>
      <div class="error-message text-red-700 p-4 rounded-md mb-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="" class="space-y-6" autocomplete="off">
      <input type="hidden" name="login" value="1">
      <div class="slide-in input-icon mb-3">
        <label for="email" class="form-label">Email Address</label>
        <i class="fas fa-envelope"></i>
        <input type="email" id="email" name="email" class="form-control rounded-lg" required autofocus placeholder="Enter your email">
      </div>
      <div class="slide-in input-icon mb-3 position-relative">
        <label for="password" class="form-label">Password</label>
        <i class="fas fa-lock"></i>
        <input type="password" id="password" name="password" class="form-control rounded-lg pr-5" required placeholder="Enter your password">
        <button type="button" class="btn btn-link btn-sm show-password-btn position-absolute" style="top: 50%; right: 10px; transform: translateY(-50%); z-index: 2; color: #64748b;" tabindex="-1" aria-label="Show password"><i class="fa-regular fa-eye" id="togglePasswordIcon"></i></button>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
          <input type="checkbox" id="remember" name="remember" class="form-check-input h-4 w-4 rounded border-gray-300">
          <label for="remember" class="form-check-label ms-2 text-sm text-gray-700">Remember me</label>
        </div>
        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 transition-colors" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot Password?</a>
      </div>
      <button type="submit" class="w-100 btn btn-primary py-3 rounded-lg text-sm font-medium ripple">Sign In</button>
    </form>
    <div class="mt-4 text-center">
      <p class="text-sm text-gray-600">Don't have an account? <a href="signup.php" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">Sign Up</a></p>
      <p class="mt-2"><a href="#" class="text-secondary small" data-bs-toggle="modal" data-bs-target="#helpModal">Need help?</a></p>
    </div>
  </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl shadow-lg">
      <div class="modal-header" style="background: linear-gradient(90deg, #e0e7ff 0%, #f8fafc 100%); border: 0; border-top-left-radius: 1.2rem; border-top-right-radius: 1.2rem;">
        <span class="me-2"><i class="fa-solid fa-lock text-primary"></i></span>
        <h5 class="modal-title fw-bold text-primary mb-0" id="forgotPasswordModalLabel">Reset Your Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <?php if ($reset_message): ?>
          <div class="alert alert-<?php echo strpos($reset_message, 'sent') !== false ? 'success' : 'danger'; ?> mb-4"><?php echo htmlspecialchars($reset_message); ?></div>
        <?php endif; ?>
        <form method="POST" action="" class="space-y-4">
          <input type="hidden" name="forgot_password" value="1">
          <div class="input-icon mb-3">
            <label for="reset_email" class="form-label">Email Address</label>
            <i class="fas fa-envelope"></i>
            <input type="email" id="reset_email" name="reset_email" class="form-control rounded-lg" required placeholder="Enter your email">
          </div>
          <button type="submit" class="w-100 btn btn-primary py-3 rounded-lg text-sm font-medium">Send Reset Link</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
// Lottie animation for login
lottie.loadAnimation({
  container: document.getElementById('lottie-login'),
  renderer: 'svg',
  loop: true,
  autoplay: true,
  path: 'https://assets10.lottiefiles.com/packages/lf20_ktwnwv5m.json'
});
// Show/hide password toggle
const passwordInput = document.getElementById('password');
const togglePasswordBtn = document.querySelector('.show-password-btn');
const togglePasswordIcon = document.getElementById('togglePasswordIcon');
togglePasswordBtn.addEventListener('click', function(e) {
  e.preventDefault();
  if (passwordInput.type === 'password') {
    passwordInput.type = 'text';
    togglePasswordIcon.classList.remove('fa-eye');
    togglePasswordIcon.classList.add('fa-eye-slash');
    togglePasswordBtn.setAttribute('aria-label', 'Hide password');
  } else {
    passwordInput.type = 'password';
    togglePasswordIcon.classList.remove('fa-eye-slash');
    togglePasswordIcon.classList.add('fa-eye');
    togglePasswordBtn.setAttribute('aria-label', 'Show password');
  }
});
// Button ripple effect
const rippleBtns = document.querySelectorAll('.ripple');
rippleBtns.forEach(btn => {
  btn.addEventListener('click', function(e) {
    const circle = document.createElement('span');
    circle.classList.add('ripple-effect');
    this.appendChild(circle);
    const d = Math.max(this.clientWidth, this.clientHeight);
    circle.style.width = circle.style.height = d + 'px';
    circle.style.left = e.clientX - this.getBoundingClientRect().left - d/2 + 'px';
    circle.style.top = e.clientY - this.getBoundingClientRect().top - d/2 + 'px';
    setTimeout(() => circle.remove(), 600);
  });
});
</script>
<style>
.ripple-effect {
  position: absolute;
  border-radius: 50%;
  transform: scale(0);
  animation: ripple 0.6s linear;
  background: rgba(59,130,246,0.25);
  pointer-events: none;
  z-index: 10;
}
@keyframes ripple {
  to {
    transform: scale(2.5);
    opacity: 0;
  }
}
</style>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="helpModalLabel">Need Help?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Contact us at <a href="mailto:support@yourdomain.com">support@yourdomain.com</a> or call 123-456-7890.</p>
        <p>Or visit our <a href="contact.php">Contact Page</a>.</p>
      </div>
    </div>
  </div>
</div>

<?php require 'footer.php'; ?>