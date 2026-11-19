<?php
session_start();
include '../../../database/DBconnect.php';
include '../../../includes/image_handler.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../../Login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

// Get current doctor data
$stmt = $conn->prepare("SELECT d.*, u.email, dc.clinic_image FROM doctors d 
    JOIN users u ON d.user_id = u.user_id 
    LEFT JOIN doctors_clinic dc ON d.doctor_id = dc.doctor_id 
    WHERE d.user_id = ?");
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
$specialty = trim($_POST['specialty'] ?? '');
$years_experience = $_POST['years_experience'] ?? 0;
$clinic_address = trim($_POST['clinic_address'] ?? '');
$clinic_name = trim($_POST['clinic_name'] ?? '');
$short_bio = trim($_POST['short_bio'] ?? '');
$description = trim($_POST['description'] ?? '');
$removeProfilePic = isset($_POST['remove_profile_picture']);
$removeClinicImage = isset($_POST['remove_clinic_image']);

// Validate required fields
if (empty($first_name) || empty($last_name) || empty($email)) {
    $errors[] = "First name, last name, and email are required.";
}

if (empty($specialty)) {
    $errors[] = "Specialty is required.";
}

if (empty($clinic_name)) {
    $errors[] = "Clinic name is required.";
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Validate email uniqueness only if email is being changed
if ($email !== $currentData['email']) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already in use.";
    }
    $stmt->close();
}

// Validate years of experience
if (!is_numeric($years_experience) || (int)$years_experience < 0) {
    $errors[] = "Years of experience must be a non-negative integer.";
} else {
    $years_experience = (int)$years_experience;
}

// Handle profile picture
$profile_picture = $currentData['profile_picture'];

if ($removeProfilePic) {
    deleteImage($profile_picture);
    $profile_picture = 'Uploads/default_profile.png';
} else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $result = handleImageUpload(
        $_FILES['profile_picture'],
        'Uploads/profile_pics',
        'doctor_' . $user_id
    );
    
    if ($result['success']) {
        // Delete old picture if not default
        if ($profile_picture && $profile_picture !== 'Uploads/default_profile.png') {
            deleteImage($profile_picture);
        }
        $profile_picture = $result['path'];
        error_log("New profile picture path: " . $profile_picture);
    } else {
        $errors[] = $result['error'];
    }
}

// Handle clinic image
$clinic_image = $currentData['clinic_image'] ?? null;

if ($removeClinicImage) {
    if ($clinic_image) {
        deleteImage($clinic_image);
    }
    $clinic_image = null;
} else if (isset($_FILES['clinic_image']) && $_FILES['clinic_image']['error'] === UPLOAD_ERR_OK) {
    $result = handleImageUpload(
        $_FILES['clinic_image'],
        'Uploads/clinic_images',
        'clinic_' . $user_id
    );
    
    if ($result['success']) {
        // Delete old image if exists
        if ($clinic_image) {
            deleteImage($clinic_image);
        }
        $clinic_image = $result['path'];
        error_log("New clinic image path: " . $clinic_image);
    } else {
        $errors[] = $result['error'];
    }
}

// Debug clinic image path before database operations
error_log("Clinic image path before DB operations: " . $clinic_image);

// If validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header("Location: ../Home.php");
    exit;
}

// Start transaction
$conn->begin_transaction();

// try {
    // Update users table
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Failed to prepare users update: " . $conn->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Failed to update user information: " . $stmt->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->close();

    // Get doctor_id
    $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Failed to prepare doctor ID fetch: " . $conn->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Failed to fetch doctor ID: " . $stmt->error;
        header("Location: ../Home.php");
        exit;
    }
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $doctor_id = $doctor['doctor_id'];
    $stmt->close();

    // Update doctors table
    $stmt = $conn->prepare("UPDATE doctors SET specialty = ?, years_experience = ?, clinic_address = ?, profile_picture = ? WHERE doctor_id = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Failed to prepare doctors update: " . $conn->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->bind_param("sissi", $specialty, $years_experience, $clinic_address, $profile_picture, $doctor_id);
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Failed to update doctor information: " . $stmt->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->close();

    // Update or insert clinic information
    $stmt = $conn->prepare("SELECT clinic_id FROM doctors_clinic WHERE doctor_id = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Failed to prepare clinic check: " . $conn->error;
        header("Location: ../Home.php");
        exit;
    }
    $stmt->bind_param("i", $doctor_id);
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Failed to check clinic existence: " . $stmt->error;
        header("Location: ../Home.php");
        exit;
    }
    $result = $stmt->get_result();
    $clinicExists = $result->fetch_assoc();
    $stmt->close();

    if ($clinicExists) {
        // Update existing clinic
        $stmt = $conn->prepare("UPDATE doctors_clinic SET clinic_name = ?, short_bio = ?, description = ?, clinic_image = ? WHERE doctor_id = ?");
        if (!$stmt) {
            $_SESSION['error'] = "Failed to prepare clinic update: " . $conn->error;
            header("Location: ../Home.php");
            exit;
        }
        $stmt->bind_param("ssssi", $clinic_name, $short_bio, $description, $clinic_image, $doctor_id);
        if (!$stmt->execute()) {
            $_SESSION['error'] = "Failed to update clinic information: " . $stmt->error;
            header("Location: ../Home.php");
            exit;
        }
        error_log("Updated clinic image in database: " . $clinic_image);
    } else {
        // Insert new clinic
        $stmt = $conn->prepare("INSERT INTO doctors_clinic (doctor_id, clinic_name, short_bio, description, clinic_image, review) VALUES (?, ?, ?, ?, ?, NULL)");
        if (!$stmt) {
            $_SESSION['error'] = "Failed to prepare clinic insert: " . $conn->error;
            header("Location: ../Home.php");
            exit;
        }
        $stmt->bind_param("issss", $doctor_id, $clinic_name, $short_bio, $description, $clinic_image);
        if (!$stmt->execute()) {
            $_SESSION['error'] = "Failed to insert clinic information: " . $stmt->error;
            header("Location: ../Home.php");
            exit;
        }
        error_log("Inserted clinic image in database: " . $clinic_image);
    }
    $stmt->close();

    $conn->commit();
    $_SESSION['success'] = "Profile updated successfully.";
// } catch (Exception $e) {
//     $conn->rollback();
//     error_log("Profile update error: " . $e->getMessage());
//     error_log("SQL State: " . $conn->sqlstate);
//     error_log("Error Code: " . $conn->errno);
//     error_log("Error Message: " . $conn->error);
//     $_SESSION['error'] = "An error occurred while updating your profile. Please try again.";
// }

header("Location: ../Home.php");
exit;
