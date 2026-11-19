<?php
session_start();
include '../../../database/DBconnect.php';
include '../header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die('Unauthorized access.');
}

$patient_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT test_name, result_summary, result_date, file_path, file_type, uploaded_at FROM lab_results WHERE patient_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$labResults = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container py-5">
  <h2>My Lab Results</h2>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Lab result uploaded successfully!</div>
  <?php endif; ?>

  <?php if (empty($labResults)): ?>
    <p class="text-muted">No lab results found.</p>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($labResults as $lab): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h5><?= htmlspecialchars($lab['test_name']) ?></h5>
            <p><?= nl2br(htmlspecialchars($lab['result_summary'])) ?></p>
            <small class="text-muted">Result Date: <?= date('F j, Y', strtotime($lab['result_date'])) ?> | Uploaded: <?= date('F j, Y, g:i A', strtotime($lab['uploaded_at'])) ?></small>
          </div>
          <div>
            <?php if (!empty($lab['file_path'])): ?>
              <a href="<?= htmlspecialchars($lab['file_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm me-2" title="View Report">
                <i class="bi bi-eye"></i> View
              </a>
              <a href="<?= htmlspecialchars($lab['file_path']) ?>" download class="btn btn-outline-success btn-sm" title="Download Report">
                <i class="bi bi-download"></i> Download
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include '../footer.php'; ?>
