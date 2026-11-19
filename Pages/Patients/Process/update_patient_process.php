<?php
session_start();
require '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];

    // Sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? null;
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $height_cm = !empty($_POST['height_cm']) ? floatval($_POST['height_cm']) : null;
    $weight_kg = !empty($_POST['weight_kg']) ? floatval($_POST['weight_kg']) : null;
    $blood_type = trim($_POST['blood_type'] ?? '');
    $bmi = !empty($_POST['bmi']) ? floatval($_POST['bmi']) : null;

    // Get current profile picture path from DB
    $stmtPic = $conn->prepare("SELECT profile_picture FROM patients WHERE user_id = ?");
    $stmtPic->bind_param("i", $user_id);
    $stmtPic->execute();
    $resultPic = $stmtPic->get_result();
    $profile_picture = '';
    if ($row = $resultPic->fetch_assoc()) {
        $profile_picture = $row['profile_picture'];
    }

    $removeProfilePic = isset($_POST['remove_picture']) && $_POST['remove_picture'] == '1';

    $errors = [];

    // Validation
    if ($first_name === '') $errors[] = "First name is required.";
    if ($last_name === '') $errors[] = "Last name is required.";
    if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = "Invalid gender selected.";
    if ($date_of_birth && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
        $errors[] = "Invalid date of birth format.";
    }

    // Handle profile picture removal
    if ($removeProfilePic) {
        if ($profile_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_picture)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $profile_picture);
        }
        $profile_picture = '';
    } else {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients/uploads/profile_pics/';
            $webPathPrefix = '/Healthcare_portal/Pages/Patients/uploads/profile_pics/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $tmpName = $_FILES['profile_picture']['tmp_name'];
            $originalName = basename($_FILES['profile_picture']['name']);
            $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExt, $allowedExts)) {
                $errors[] = "Invalid file type. Allowed types: jpg, jpeg, png, gif.";
            } else {
                $newFileName = 'user_' . $user_id . '_' . uniqid() . '.' . $fileExt;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $destination)) {
                    // Delete old picture
                    if ($profile_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_picture)) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . $profile_picture);
                    }
                    $profile_picture = $webPathPrefix . $newFileName;
                } else {
                    $errors[] = "Failed to upload profile picture.";
                }
            }
        }
    }

    // Show errors if any
    if (!empty($errors)) {
        $_SESSION['update_errors'] = $errors;
        header("Location: ../Home.php?updated=0");
        exit;
    }

    // Update users table
    $stmt1 = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, gender = ? WHERE user_id = ?");
    $stmt1->bind_param("sssi", $first_name, $last_name, $gender, $user_id);
    $stmt1->execute();

    // Check if patient record exists
    $stmtCheck = $conn->prepare("SELECT user_id FROM patients WHERE user_id = ?");
    $stmtCheck->bind_param("i", $user_id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows === 0) {
        $stmt2 = $conn->prepare("INSERT INTO patients (user_id, date_of_birth, height_cm, weight_kg, blood_type, bmi, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("isdddds", $user_id, $date_of_birth, $height_cm, $weight_kg, $blood_type, $bmi, $profile_picture);
    } else {
        $stmt2 = $conn->prepare("UPDATE patients SET date_of_birth = ?, height_cm = ?, weight_kg = ?, blood_type = ?, bmi = ?, profile_picture = ? WHERE user_id = ?");
        $stmt2->bind_param("sddsdsi", $date_of_birth, $height_cm, $weight_kg, $blood_type, $bmi, $profile_picture, $user_id);
    }

    $stmt2->execute();

    if ($stmt1->error || $stmt2->error) {
        $_SESSION['update_errors'] = ["Database error: " . ($stmt1->error ?: $stmt2->error)];
        header("Location: ../Home.php?updated=0");
        exit;
    }

    $_SESSION['update_success'] = "Profile updated successfully.";
    header("Location: ../Home.php?updated=1");
    exit;
}
?>
