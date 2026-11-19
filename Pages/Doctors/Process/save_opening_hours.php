<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../../../database/DBconnect.php';  // Adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
    exit;
}
$doctor_id = $res->fetch_assoc()['doctor_id'];

// Loop through days Sunday(0) to Saturday(6)
for ($day = 0; $day < 7; $day++) {
    $off = isset($_POST["off_$day"]) ? 1 : 0;

    if ($off) {
        $start_time = null;
        $end_time = null;
    } else {
        $start_time = $_POST["start_$day"] ?? null;
        $end_time = $_POST["end_$day"] ?? null;

        if (!$start_time || !$end_time) {
            echo json_encode(['success' => false, 'message' => "Start and end time are required for day $day"]);
            exit;
        }

        if ($start_time >= $end_time) {
            echo json_encode(['success' => false, 'message' => "Start time must be before end time for day $day"]);
            exit;
        }
    }

    // Check if schedule exists
    $checkStmt = $conn->prepare("SELECT id FROM doctor_schedule WHERE doctor_id = ? AND day_of_week = ? AND schedule_date IS NULL");
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $checkStmt->bind_param("ii", $doctor_id, $day);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();

    if ($checkRes->num_rows > 0) {
        $row = $checkRes->fetch_assoc();
        $updateStmt = $conn->prepare("UPDATE doctor_schedule SET start_time = ?, end_time = ?, is_off = ?, updated_at = NOW() WHERE id = ?");
        if (!$updateStmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $updateStmt->bind_param("ssii", $start_time, $end_time, $off, $row['id']);
        if (!$updateStmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule for day ' . $day]);
            exit;
        }
    } else {
        $insertStmt = $conn->prepare("INSERT INTO doctor_schedule (doctor_id, day_of_week, start_time, end_time, is_off, schedule_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NULL, NOW(), NOW())");
        if (!$insertStmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $insertStmt->bind_param("iissi", $doctor_id, $day, $start_time, $end_time, $off);
        if (!$insertStmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to insert schedule for day ' . $day]);
            exit;
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Weekly opening hours saved successfully']);
