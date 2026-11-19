<?php
session_start();
include '../../../database/DBconnect.php';
include '../../../includes/image_handler.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../../Login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

// Get current patient data
$stmt = $conn->prepare("SELECT p.*, u.email FROM patients p JOIN users u ON p.user_id = u.user_id WHERE p.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$currentData = $result->fetch_assoc();
$stmt->close();

if (!$currentData) {
    header('Location: ../Home.php');
    exit;
}

// Process form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$gender = $_POST['gender'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? '';
$height_cm = $_POST['height_cm'] ?? '';
$weight_kg = $_POST['weight_kg'] ?? '';
$blood_type = $_POST['blood_type'] ?? '';
$removeProfilePic = isset($_POST['remove_profile_picture']);

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email)) {
    $errors[] = "First name, last name, and email are required.";
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Validate email uniqueness
if ($email !== $currentData['email']) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already in use.";
    }
    $stmt->close();
}

// Validate date of birth
if (!empty($date_of_birth)) {
    $dob = strtotime($date_of_birth);
    if ($dob === false || $dob > time()) {
        $errors[] = "Invalid date of birth.";
    }
}

// Validate height and weight
if ($height_cm !== '' && (!is_numeric($height_cm) || $height_cm <= 0 || $height_cm > 300)) {
    $errors[] = "Height must be between 0 and 300 cm.";
}

if ($weight_kg !== '' && (!is_numeric($weight_kg) || $weight_kg <= 0 || $weight_kg > 500)) {
    $errors[] = "Weight must be between 0 and 500 kg.";
}

// Handle profile picture
$profile_picture = $currentData['profile_picture'];

if ($removeProfilePic) {
    deleteImage($profile_picture);
    $profile_picture = getDefaultProfilePicture('patient');
} else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients/uploads/profile_pics/';
    $web_path_prefix = '/Healthcare_portal/Pages/Patients/uploads/profile_pics/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $errors[] = "Failed to create upload directory";
        }
    }
    
    if (is_dir($upload_dir) && is_writable($upload_dir)) {
        $file_name = 'patient_' . $user_id . '_' . uniqid() . '_' . basename($_FILES['profile_picture']['name']);
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
            // Delete old picture if not default
            if ($profile_picture && $profile_picture !== getDefaultProfilePicture('patient')) {
                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . $profile_picture;
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            $profile_picture = $web_path_prefix . $file_name;
        } else {
            $errors[] = "Failed to upload profile picture";
        }
    } else {
        $errors[] = "Upload directory is not writable";
    }
}

// If validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: ../Home.php");
    exit;
}

// Calculate BMI if height and weight are provided
$bmi = null;
if ($height_cm !== '' && $weight_kg !== '') {
    $height_m = $height_cm / 100;
    $bmi = $weight_kg / ($height_m * $height_m);
}

// Start transaction
$conn->begin_transaction();

try {
    // Update users table
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, gender = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $gender, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update patients table
    $stmt = $conn->prepare("UPDATE patients SET date_of_birth = ?, height_cm = ?, weight_kg = ?, blood_type = ?, bmi = ?, profile_picture = ? WHERE user_id = ?");
    $stmt->bind_param("sddsssi", $date_of_birth, $height_cm, $weight_kg, $blood_type, $bmi, $profile_picture, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['success'] = "Profile updated successfully.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "An error occurred while updating your profile.";
}

header("Location: ../Home.php");
exit;
?> 

<script>
document.querySelector('#editProfileModal form').addEventListener('submit', function() {
  document.getElementById('saveProfileText').classList.add('d-none');
  document.getElementById('saveSpinner').classList.remove('d-none');
});
</script> 