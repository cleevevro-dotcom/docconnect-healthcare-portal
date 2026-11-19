<?php
session_start();
require '../../../database/DBconnect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch the patient_id corresponding to the logged-in user
$sql_patient = "SELECT patient_id FROM patients WHERE user_id = ?";
$stmt_patient = $conn->prepare($sql_patient);
if (!$stmt_patient) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database prepare error.']);
    exit;
}
$stmt_patient->bind_param("i", $user_id);
$stmt_patient->execute();
$result = $stmt_patient->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Patient record not found.']);
    exit;
}

$row = $result->fetch_assoc();
$patient_id = $row['patient_id'];
$stmt_patient->close();

// Retrieve and validate POST data
$doctor_id = $_POST['doctor_id'] ?? null;
$rating = isset($_POST['rating']) ? (float)$_POST['rating'] : null;
$comment = trim($_POST['comment'] ?? '');

if (!$doctor_id || !$rating || $comment === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!is_numeric($doctor_id) || $doctor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid doctor ID.']);
    exit;
}

if ($rating < 1.0 || $rating > 5.0) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value.']);
    exit;
}

$doctor_id = (int)$doctor_id;

// Prepare insert statement for doctor_reviews
$sql = "INSERT INTO doctor_reviews (doctor_id, patient_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database prepare error.']);
    exit;
}

$stmt->bind_param("iids", $doctor_id, $patient_id, $rating, $comment);

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to submit review: ' . $stmt->error]);
    exit;
}

$stmt->close();
$conn->close();

// Redirect back to the appointment page or wherever appropriate
header("Location: ../Appointment.php?doctor_id=$doctor_id");
exit;
?>
