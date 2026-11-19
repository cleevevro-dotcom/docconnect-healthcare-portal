<?php
session_start();
require 'database/DBconnect.php'; // Your DB connection ($conn)
include 'header.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (!$first_name || !$last_name || !$email || !$gender || !$password || !$confirm_password) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $error = 'Please select a valid gender.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Email is already registered.';
        } else {
            // Insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'patient'; // default role

            $insert = $conn->prepare("INSERT INTO users (first_name, last_name, email, password_hash, gender, role) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param("ssssss", $first_name, $last_name, $email, $password_hash, $gender, $role);

            if ($insert->execute()) {
                // Redirect to login page with success flag
                header('Location: index.php?registered=1');
                exit;
            } else {
                $error = 'Registration failed. Please try again later.';
            }
            $insert->close();
        }
        $stmt->close();
    }
}
?>
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
  .signup-center-wrapper {
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
    z-index: 1;
    position: relative;
    padding: 3.5rem 2.5rem;
  }
  .signup-card {
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
  .signup-card:hover {
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
  .input-icon input, .input-icon select {
    padding-left: 2.5rem;
    transition: all 0.3s ease;
    border: 1.5px solid #e2e8f0;
  }
  .input-icon input:focus, .input-icon select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  .input-icon input:focus + i, .input-icon select:focus + i {
    color: #3b82f6;
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
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes pulse {
    from { transform: scale(1); }
    to { transform: scale(1.12); }
  }
  @media (max-width: 900px) {
    .signup-center-wrapper { max-width: 98vw; padding: 1.2rem 0.2rem; }
    .signup-card { padding: 1.2rem 0.7rem 1.2rem 0.7rem; }
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
<div class="signup-center-wrapper">
  <div class="signup-card">
    <div class="gradient-accent"></div>
    <div class="brand-icon text-center mb-2"><i class="fa-solid fa-heart-pulse"></i></div>
    <div class="brand-title">DocCenter</div>
    <div class="brand-tagline">Your Health, Connected</div>
    <div class="welcome-message">Create Your Account</div>
    <div class="benefit-message">Join DocCenter to manage your health, appointments, and more—all in one place.</div>
    <?php if ($error): ?>
      <div class="error-message text-red-700 p-4 rounded-md mb-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="" novalidate autocomplete="off">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="input-icon mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <i class="fa-regular fa-user"></i>
            <input type="text" name="first_name" id="first_name" class="form-control rounded-lg" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-icon mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <i class="fa-regular fa-user"></i>
            <input type="text" name="last_name" id="last_name" class="form-control rounded-lg" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
          </div>
        </div>
      </div>
      <div class="input-icon mb-3">
        <label for="email" class="form-label">Email address</label>
        <i class="fa-regular fa-envelope"></i>
        <input type="email" name="email" id="email" class="form-control rounded-lg" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="input-icon mb-3">
        <label for="gender" class="form-label">Gender</label>
        <i class="fa-solid fa-venus-mars"></i>
        <select id="gender" name="gender" class="form-select rounded-lg" required>
          <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select your gender</option>
          <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
          <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
          <option value="other" <?= (($_POST['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
      <div class="input-icon mb-3">
        <label for="password" class="form-label">Password</label>
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" id="password" class="form-control rounded-lg" required>
      </div>
      <div class="input-icon mb-4">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="confirm_password" id="confirm_password" class="form-control rounded-lg" required>
      </div>
      <button type="submit" class="w-100 btn btn-primary py-3 rounded-lg text-sm font-medium">Sign Up</button>
    </form>
    <div class="text-center mt-4">
      <span>Already have an account? 
        <a href="index.php" class="btn btn-link p-0">Sign In</a>
      </span>
    </div>
  </div>
</div>
<?php require 'footer.php'; ?>
