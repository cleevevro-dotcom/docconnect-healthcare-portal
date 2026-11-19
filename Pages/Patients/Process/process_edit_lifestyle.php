<?php
session_start();
include '../../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die('Unauthorized');
}

$patient_id = $_SESSION['user_id'];

$recommendation_ids = $_POST['recommendation_id'] ?? [];
$recommendations = $_POST['recommendation'] ?? [];

for ($i = 0; $i < count($recommendations); $i++) {
    $id = intval($recommendation_ids[$i]);
    $rec = trim($recommendations[$i]);

    if (empty($rec)) continue;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE lifestyle_recommendations SET recommendation=? WHERE recommendation_id=? AND patient_id=?");
        $stmt->bind_param("sii", $rec, $id, $patient_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO lifestyle_recommendations (patient_id, recommendation) VALUES (?, ?)");
        $stmt->bind_param("is", $patient_id, $rec);
        $stmt->execute();
    }
}

header('Location: ../Records.php?success=lifestyle');
exit;
