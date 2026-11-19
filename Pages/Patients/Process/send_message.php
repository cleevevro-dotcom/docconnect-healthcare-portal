<?php
session_start();
include '../../../database/DBconnect.php';
include '../../../includes/image_handler.php';

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get patient_id
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($patient_id);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Patient profile not found']);
    exit;
}
$stmt->close();

// Get doctor_id from POST data
$doctor_user_id = $_POST['doctor_id'] ?? null;
if (!$doctor_user_id) {
    echo json_encode(['success' => false, 'message' => 'Doctor ID required']);
    exit;
}

// Verify doctor exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'doctor'");
$stmt->bind_param("i", $doctor_user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor']);
    exit;
}
$stmt->close();

$file_path = null;

// Handle file upload if present
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $result = handleImageUpload(
        $_FILES['attachment'],
        'Healthcare_portal/Pages/Patients/uploads/messages',
        'msg_' . $_SESSION['user_id']
    );
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['error']]);
        exit;
    }
    
    $file_path = $result['path'];
}

// Insert message into database
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
$message_type = $file_path ? 'image' : 'text';

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $_SESSION['user_id'], $doctor_user_id, $message_text, $message_type, $file_path);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    // If message insert fails, delete uploaded file
    if ($file_path) {
        deleteImage($file_path);
    }
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}

$stmt->close();
?> 