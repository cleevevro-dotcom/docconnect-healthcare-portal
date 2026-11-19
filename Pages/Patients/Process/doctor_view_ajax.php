<?php
session_start();
require '../../../database/DBconnect.php';

$doctor_id = $_GET['doctor_id'] ?? null;
if (!$doctor_id || !is_numeric($doctor_id)) {
    echo '<div class="alert alert-danger">Invalid doctor ID.</div>';
    exit;
}
$doctor_id = (int)$doctor_id;

// Fetch doctor info
$sql = "SELECT 
            u.first_name, u.last_name, u.email, 
            d.specialty, d.years_experience, d.clinic_address, d.profile_picture,
            dc.clinic_name, dc.short_bio, dc.description, dc.clinic_image
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN doctors_clinic dc ON d.doctor_id = dc.doctor_id
        WHERE d.doctor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-danger">Doctor not found.</div>';
    exit;
}
$doc = $result->fetch_assoc();

function getImagePath($path, $fallback) {
    if ($path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/'))) {
        return $path;
    }
    return $fallback;
}

$profilePic = getImagePath($doc['profile_picture'], '/uploads/default_profile.png');
$clinicImg = getImagePath($doc['clinic_image'], '/uploads/default_clinic.png');

// Fetch reviews
$sqlReviews = "SELECT dr.rating, dr.comment, dr.created_at, u.first_name, u.last_name
               FROM doctor_reviews dr
               JOIN patients p ON dr.patient_id = p.patient_id
               JOIN users u ON p.user_id = u.user_id
               WHERE dr.doctor_id = ? 
               ORDER BY dr.created_at DESC";
$stmtReviews = $conn->prepare($sqlReviews);
$stmtReviews->bind_param("i", $doctor_id);
$stmtReviews->execute();
$reviewsResult = $stmtReviews->get_result();

$reviewsArray = $reviewsResult->fetch_all(MYSQLI_ASSOC);
$totalReviews = count($reviewsArray);
$avgRating = $totalReviews > 0 ? round(array_sum(array_column($reviewsArray, 'rating')) / $totalReviews, 1) : 0;
?>

<div class="container-fluid py-4 px-3" style="max-width:900px;">
  <div class="row g-4">
    <!-- Doctor Profile -->
    <div class="col-md-5">
      <div class="card shadow-lg border-0 rounded-4 p-4 text-center">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Doctor Photo" class="rounded-circle border border-3 border-primary shadow mb-3 mx-auto" style="width:110px; height:110px; object-fit:cover;" />
        <h3 class="fw-bold mb-1">Dr. <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></h3>
        <span class="badge bg-primary mb-3 fs-6"><?= htmlspecialchars($doc['specialty']) ?></span>
        <p><strong>Experience:</strong> <?= (int)$doc['years_experience'] ?> years</p>
        <p>
          <a href="mailto:<?= htmlspecialchars($doc['email']) ?>" class="text-primary text-decoration-none">
            <i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($doc['email']) ?>
          </a>
        </p>
        <p class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($doc['clinic_address']) ?></p>

        <div class="mt-4">
          <h5 class="mb-3">Patient Reviews (<?= $totalReviews ?>)</h5>
          <div class="mb-3">
            <strong>Average Rating:</strong>
            <span class="text-warning fs-5">
              <?php
                $fullStars = floor($avgRating);
                $halfStar = ($avgRating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;

                echo str_repeat('★', $fullStars);
                if ($halfStar) echo '☆';
                echo str_repeat('☆', $emptyStars);
              ?>
            </span>
            <span class="ms-2">(<?= number_format($avgRating, 1) ?> / 5)</span>
          </div>

          <?php if ($totalReviews === 0): ?>
            <p class="text-muted fst-italic">No reviews yet.</p>
          <?php else: ?>
            <div class="reviews-list" style="max-height: 250px; overflow-y: auto;">
              <?php foreach ($reviewsArray as $review): ?>
                <div class="mb-3 border-bottom pb-2">
                  <div class="d-flex justify-content-between">
                    <strong><?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                    <small class="text-muted"><?= date('M d, Y', strtotime($review['created_at'])) ?></small>
                  </div>
                  <div class="text-warning">
                    <?php
                      $stars = floor($review['rating']);
                      $halfStar = ($review['rating'] - $stars) >= 0.5 ? 1 : 0;
                      $emptyStars = 5 - $stars - $halfStar;

                      echo str_repeat('★', $stars);
                      if ($halfStar) echo '☆';
                      echo str_repeat('☆', $emptyStars);
                    ?>
                  </div>
                  <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Clinic Info and Review Form -->
    <div class="col-md-7">
      <div class="card shadow-lg border-0 rounded-4 p-4 h-100">
        <h4 class="fw-bold mb-3"><?= htmlspecialchars($doc['clinic_name']) ?></h4>
        <div class="text-center mb-3">
          <img src="<?= htmlspecialchars($clinicImg) ?>" alt="Clinic Image" class="rounded-3 img-fluid" style="max-width:300px;" />
        </div>
        <p class="text-muted small" style="white-space: pre-line;"><?= nl2br(htmlspecialchars($doc['description'])) ?></p>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
          <hr>
          <h6 class="mt-4 mb-3">Write a Review</h6>
          <form id="reviewForm" method="POST" action="/Healthcare_portal/Pages/Patients/Process/save_doctor_review.php" class="needs-validation" novalidate>
            <input type="hidden" name="doctor_id" value="<?= (int)$doctor_id ?>">
            <div class="mb-3">
              <label for="rating" class="form-label">Rating <span class="text-danger">*</span></label>
              <select name="rating" id="rating" class="form-select" required>
                <option value="" disabled selected>Select rating</option>
                <option value="5.0">5 - Excellent</option>
                <option value="4.5">4.5 - Very Good</option>
                <option value="4.0">4 - Good</option>
                <option value="3.5">3.5 - Above Average</option>
                <option value="3.0">3 - Average</option>
                <option value="2.5">2.5 - Below Average</option>
                <option value="2.0">2 - Fair</option>
                <option value="1.5">1.5 - Poor</option>
                <option value="1.0">1 - Very Poor</option>
              </select>
              <div class="invalid-feedback">Select a rating.</div>
            </div>
            <div class="mb-3">
              <label for="comment" class="form-label">Comment <span class="text-danger">*</span></label>
              <textarea name="comment" id="comment" rows="3" class="form-control" required></textarea>
              <div class="invalid-feedback">Comment is required.</div>
            </div>
            <button type="submit" id="submitReviewBtn" class="btn btn-primary">Submit Review</button>
          </form>
        <?php else: ?>
          <p class="text-muted mt-3">Please <a href="../../../index.php">log in</a> as a patient to write a review.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
  .reviews-list::-webkit-scrollbar { width: 8px; }
  .reviews-list::-webkit-scrollbar-thumb { background: rgba(13, 110, 253, 0.4); border-radius: 4px; }
  .reviews-list::-webkit-scrollbar-track { background: #e9ecef; }
</style>

<script>
  
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
