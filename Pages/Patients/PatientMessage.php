<?php
session_start();
include '../../database/DBconnect.php';

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get patient_id
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($patient_id);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Patient profile not found.");
}
$stmt->close();

// Fetch doctors assigned to this patient (based on appointments)
$sql = "
    SELECT DISTINCT d.doctor_id, u.first_name, u.last_name, d.profile_picture
    FROM doctors d
    INNER JOIN users u ON d.user_id = u.user_id
    INNER JOIN appointments a ON d.doctor_id = a.doctor_id
    WHERE a.patient_id = ?
    ORDER BY u.first_name, u.last_name
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$doctors_result = $stmt->get_result();
$doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle selected doctor
$selected_doctor_id = isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$selected_doctor_name = 'Select a doctor';
$selected_doctor_user_id = null;

if ($selected_doctor_id) {
    $sql = "
        SELECT u.first_name, u.last_name, u.user_id
        FROM doctors d
        INNER JOIN users u ON d.user_id = u.user_id
        INNER JOIN appointments a ON d.doctor_id = a.doctor_id
        WHERE d.doctor_id = ? AND a.patient_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $selected_doctor_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($doctor = $result->fetch_assoc()) {
        $selected_doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
        $selected_doctor_user_id = $doctor['user_id'];
    } else {
        $selected_doctor_id = null;
    }
    $stmt->close();
}

// Fetch messages for selected doctor
$messages = [];
if ($selected_doctor_id && $selected_doctor_user_id) {
    $sql = "
        SELECT m.message_id, m.sender_id, m.message_text, m.sent_at, m.is_read,
               m.message_type, m.file_path,
               u.first_name, u.last_name
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $selected_doctor_user_id, $selected_doctor_user_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Mark messages from doctor as read
    $sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $selected_doctor_user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_doctor_id && $selected_doctor_user_id && isset($_POST['message'])) {
    $message_text = trim($_POST['message']);
    if (!empty($message_text)) {
        if (strlen($message_text) > 1000) {
            echo "<script>alert('Message is too long (max 1000 characters).');</script>";
        } else {
            $sql = "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $selected_doctor_user_id, $message_text);
            $stmt->execute();
            $stmt->close();
            header("Location: PatientMessage.php?doctor_id=$selected_doctor_id");
            exit;
        }
    }
}
?>

<?php include 'header.php'; ?>

<!-- Enhanced UI Styles -->
<style>
  :root {
    --primary: #0d6efd;
    --secondary: #6c757d;
    --success: #198754;
    --info: #0dcaf0;
    --warning: #ffc107;
    --danger: #dc3545;
    --light: #f8f9fa;
    --dark: #212529;
    --gradient-start: #e3f0ff;
    --gradient-end: #f7fafd;
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
    --border-radius: 1.25rem;
    --border-radius-sm: 0.75rem;
    --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  body {
    font-family: var(--font-primary);
    background: linear-gradient(180deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
    min-height: 100vh;
    color: var(--dark);
  }

  .container {
    max-width: 1400px;
    padding: 2rem 1rem;
  }

  /* Enhanced Doctor List Card */
  .doctor-list-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    overflow: hidden;
    height: 100%;
  }

  .doctor-list-card .card-header {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    color: white;
    padding: 1.25rem 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .doctor-list-card .search-box {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
  }

  .doctor-list-card .search-box input {
    border-radius: var(--border-radius-sm);
    padding-left: 2.5rem;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
  }

  .doctor-list-card .search-box i {
    position: absolute;
    left: 1.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary);
  }

  .doctor-list-card .list-group-item {
    border: none;
    padding: 1rem 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
  }

  .doctor-list-card .list-group-item:hover {
    background: var(--gradient-start);
    transform: translateX(5px);
  }

  .doctor-list-card .list-group-item.active {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    color: white;
  }

  .doctor-list-card .list-group-item .unread-badge {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
  }

  .doctor-list-card img {
    object-fit: cover;
    border: 2px solid var(--primary);
    transition: transform 0.3s ease;
  }

  .doctor-list-card .list-group-item:hover img {
    transform: scale(1.1);
  }

  /* Enhanced Chat Card */
  .chat-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  .chat-card .card-header {
    background: linear-gradient(135deg, var(--info) 0%,rgb(47, 61, 255) 100%);
    color: white;
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .chat-card .card-header .doctor-info {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .chat-card .card-header .doctor-info img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid white;
  }

  .chat-card .card-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f9fbfc;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-height: 600px; /* Set maximum height */
    scrollbar-width: thin;
    scrollbar-color: var(--primary) #f0f0f0;
  }

  /* Custom scrollbar for Webkit browsers */
  .chat-card .card-body::-webkit-scrollbar {
    width: 8px;
  }

  .chat-card .card-body::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 4px;
  }

  .chat-card .card-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 4px;
  }

  .chat-card .card-body::-webkit-scrollbar-thumb:hover {
    background:rgb(22, 86, 223);
  }

  .message {
    max-width: 70%;
    margin-bottom: 1rem;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .message.sent {
    margin-left: auto;
    text-align: right;
  }

  .message.received .bubble {
    background: white;
    border-radius: var(--border-radius-sm) var(--border-radius-sm) var(--border-radius-sm) 0;
    box-shadow: var(--shadow-sm);
  }

  .message.sent .bubble {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    color: white;
    border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 var(--border-radius-sm);
  }

  .bubble {
    padding: 1rem 1.25rem;
    display: inline-block;
    box-shadow: var(--shadow-sm);
    position: relative;
  }

  .message-time {
    font-size: 0.8rem;
    color: var(--secondary);
    margin-bottom: 0.5rem;
  }

  .message-status {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
  }

  .message-status i {
    font-size: 0.875rem;
  }

  /* Enhanced Form */
  .chat-form {
    display: flex;
    gap: 0.75rem;
    align-items: center;
  }

  .chat-form .input-group {
    flex: 1;
    background: #f8fafc;
    border-radius: var(--border-radius-sm);
    padding: 0.5rem;
  }

  .chat-form input {
    border: none;
    background: transparent;
    padding: 0.5rem;
  }

  .chat-form input:focus {
    box-shadow: none;
  }

  .chat-form .input-group-text {
    background: transparent;
    border: none;
    padding: 0.5rem;
  }

  .chat-form .input-group-text button {
    background: transparent;
    border: none;
    color: var(--secondary);
    padding: 0.25rem;
    transition: all 0.2s ease;
  }

  .chat-form .input-group-text button:hover {
    color: var(--primary);
    transform: scale(1.1);
  }

  .chat-form .send-button {
    background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
    border: none;
    border-radius: var(--border-radius-sm);
    padding: 0.75rem 1.5rem;
    color: white;
    transition: all 0.3s ease;
  }

  .chat-form .send-button:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
  }

  /* File Attachment Preview */
  .attachment-preview {
    background: #f8fafc;
    border-radius: var(--border-radius-sm);
    padding: 1rem;
    margin-top: 1rem;
    display: none;
  }

  .attachment-preview.show {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .attachment-preview .file-info {
    flex: 1;
  }

  .attachment-preview .file-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
  }

  .attachment-preview .file-size {
    font-size: 0.875rem;
    color: var(--secondary);
  }

  .attachment-preview .remove-file {
    color: var(--danger);
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .attachment-preview .remove-file:hover {
    transform: scale(1.1);
  }

  /* Image preview styles */
  .message-image {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
  }

  .message-image:hover {
    transform: scale(1.05);
  }

  .image-preview {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }

  .image-preview img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
  }

  .image-preview .close-preview {
    position: absolute;
    top: 20px;
    right: 20px;
    color: white;
    font-size: 30px;
    cursor: pointer;
  }

  /* Responsive Design */
  @media (max-width: 992px) {
    .doctor-list-card {
      margin-bottom: 1.5rem;
      height: auto;
      max-height: 400px;
    }
    .chat-card {
      height: auto;
      min-height: 500px;
    }
  }

  @media (max-width: 576px) {
    .doctor-list-card .list-group-item {
      padding: 0.75rem 1rem;
    }
    .chat-card .card-body {
      padding: 1rem;
    }
    .message {
      max-width: 85%;
    }
    .chat-form {
      flex-direction: column;
    }
    .chat-form .send-button {
      width: 100%;
    }
  }

  .send-box {
    background: rgba(224, 231, 255, 0.85); /* Soft blue with transparency */
    border-radius: 1.25rem;
    padding: 1.5rem 1.2rem 1.2rem 1.2rem;
    box-shadow: 0 8px 32px rgba(37,99,235,0.10), 0 1.5px 6px rgba(59,130,246,0.07);
    border: 1.5px solid rgba(59,130,246,0.13);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    margin-bottom: 0.5rem;
    transition: box-shadow 0.3s, border 0.3s;
  }

  .send-box:hover, .send-box:focus-within {
    box-shadow: 0 12px 36px rgba(37,99,235,0.16), 0 2px 8px rgba(59,130,246,0.10);
    border: 1.5px solid #2563eb;
  }

  .send-box input[type=\"text\"] {
    background: rgba(255,255,255,0.85);
    border: 1.5px solid #c7d2fe;
    border-radius: 0.7rem;
    padding: 0.7rem 1rem;
    font-size: 1rem;
    color: #1e293b;
    transition: border 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(59,130,246,0.04);
  }

  .send-box input[type=\"text\"]:focus {
    border: 1.5px solidrgb(18, 57, 141);
    outline: none;
    box-shadow: 0 2px 8px rgba(59,130,246,0.10);
  }

  .send-box button[type=\"submit\"] {
    background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
    color: #fff;
    border: none;
    border-radius: 0.7rem;
    padding: 0.7rem 1.5rem;
    font-weight: 600;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(59,130,246,0.10);
    transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
  }

  .send-box button[type=\"submit\"]:hover {
    background: linear-gradient(90deg, #1d4ed8 0%, #3b82f6 100%);
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 4px 16px rgba(37,99,235,0.13);
  }

  .send-box label[for=\"chatImage\"] {
    background: rgba(255,255,255,0.7);
    border-radius: 0.7rem;
    padding: 0.6rem 0.9rem;
    margin-left: 0.5rem;
    cursor: pointer;
    transition: background 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(59,130,246,0.04);
    display: flex;
    align-items: center;
  }

  .send-box label[for=\"chatImage\"]:hover {
    background: #e0e7ff;
    box-shadow: 0 2px 8px rgba(59,130,246,0.10);
  }
</style>

<div class="container">
  <div class="row g-4">
    <!-- Doctors List -->
    <div class="col-lg-3 col-md-4">
      <div class="doctor-list-card">
        <div class="card-header">
          <i class="bi bi-people-fill"></i>
          <span>Doctors</span>
        </div>
        <div class="search-box">
          <div class="position-relative">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" placeholder="Search doctors..." id="doctorSearch">
          </div>
        </div>
        <ul class="list-group list-group-flush" id="doctorsList">
          <?php if (empty($doctors)): ?>
            <li class="list-group-item text-muted">No doctors found.</li>
          <?php else: ?>
            <?php foreach ($doctors as $doctor): 
                $avatar = !empty($doctor['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Doctors/uploads/' . $doctor['profile_picture'])
                    ? '/Healthcare_portal/Pages/Doctors/uploads/' . $doctor['profile_picture']
                    : '/Healthcare_portal/Pages/Doctors/uploads/default_profile.png';
            ?>
              <li class="list-group-item d-flex align-items-center <?= $selected_doctor_id === $doctor['doctor_id'] ? 'active' : '' ?>">
                <a href="?doctor_id=<?= $doctor['doctor_id'] ?>" class="d-flex align-items-center text-decoration-none w-100" style="color: inherit;">
                  <img src="<?= htmlspecialchars($avatar) ?>" class="rounded-circle me-2" width="40" height="40" alt="<?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?>">
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                      <span><?= htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) ?></span>
                      <span class="unread-badge" style="display: none;">0</span>
                    </div>
                  </div>
                </a>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <!-- Chat System -->
    <div class="col-lg-9 col-md-8">
      <div class="chat-card">
        <div class="card-header">
          <div class="doctor-info">
            <?php if ($selected_doctor_id): 
                $avatar = !empty($doctor['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Doctors/uploads/' . $doctor['profile_picture'])
                    ? '/Healthcare_portal/Pages/Doctors/uploads/' . $doctor['profile_picture']
                    : '/Healthcare_portal/Pages/Doctors/uploads/default_profile.png';
            ?>
              <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($selected_doctor_name) ?>">
              <div>
                <h5 class="mb-0"><?= htmlspecialchars($selected_doctor_name) ?></h5>
                <small>Online</small>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <?php if (!$selected_doctor_id): ?>
            <div class="text-center text-muted py-5">
              <i class="bi bi-chat-dots fs-1"></i>
              <h5 class="mt-3">Select a Doctor</h5>
              <p>Choose a doctor from the list to start chatting.</p>
            </div>
          <?php else: ?>
            <?php foreach ($messages as $message): $is_sent = $message['sender_id'] === $user_id; $is_image = isset($message['file_path']) && $message['file_path'] && (isset($message['message_type']) ? $message['message_type'] === 'image' : true); ?>
              <div class="message <?= $is_sent ? 'sent' : 'received' ?> slide-in">
                <div class="message-time">
                  <?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?>,
                  <?= date('M j, Y h:i A', strtotime($message['sent_at'])) ?>
                </div>
                <div class="bubble">
                  <?php if ($is_image): ?>
                    <a href="/<?= htmlspecialchars($message['file_path']); ?>" target="_blank">
                      <img src="/<?= htmlspecialchars($message['file_path']); ?>" alt="Sent image" style="max-width:220px; max-height:180px; border-radius:0.7rem; box-shadow:0 2px 8px rgba(94,137,231,0.10); margin-bottom:0.3rem; display:block;">
                    </a>
                  <?php endif; ?>
                  <?= nl2br(htmlspecialchars($message['message_text'])) ?>
                  <?php if ($is_sent): ?>
                    <div class="message-status">
                      <i class="bi bi-check2-all"></i>
                      <span>Delivered</span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?>
              <div class="text-center text-muted py-5">
                <i class="bi bi-chat-dots fs-1"></i>
                <h5 class="mt-3">No Messages Yet</h5>
                <p>Start the conversation by sending a message!</p>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($selected_doctor_id): ?>
          <div class="mt-3 send-box">
          <form method="POST" enctype="multipart/form-data" class="chat-form flex gap-2 w-100" id="chatSendForm">
          <input type="hidden" name="doctor_id" value="<?= $selected_doctor_user_id ?>">
              <input type="text" name="message" class="flex-grow-1" placeholder="Type your message..." autocomplete="off" style="min-width:0;">
              <label for="chatImage" style="cursor:pointer; margin:0; display:flex; align-items:center;">
                <i class="fas fa-image" style="font-size:1.3rem; color:var(--primary); margin-right:0.2rem;"></i>
                <input type="file" name="attachment" id="chatImage" accept="image/*" style="display:none;">
              </label>
              <button type="submit" class="btn btn-primary send-button">Send</button>
            </form>
            <div id="imagePreview" style="display:none; margin-top:0.5rem;"></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add image preview overlay -->
<div class="image-preview" id="imagePreview">
  <span class="close-preview">&times;</span>
  <img src="" alt="Preview">
</div>

<!-- Bootstrap Icons and Bootstrap JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Google Fonts for Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Auto-scroll to bottom of chat
const chatBody = document.querySelector('.chat-card .card-body');
if (chatBody) {
  chatBody.scrollTop = chatBody.scrollHeight;
}

// Doctor search functionality
document.getElementById('doctorSearch').addEventListener('input', function(e) {
  const searchText = e.target.value.toLowerCase();
  const doctors = document.querySelectorAll('#doctorsList .list-group-item');
  
  doctors.forEach(doctor => {
    const name = doctor.textContent.toLowerCase();
    doctor.style.display = name.includes(searchText) ? '' : 'none';
  });
});

// Image preview before sending
const chatImage = document.getElementById('chatImage');
const imagePreview = document.getElementById('imagePreview');
if (chatImage && imagePreview) {
  chatImage.addEventListener('change', function() {
    imagePreview.innerHTML = '';
    if (this.files && this.files[0]) {
      const file = this.files[0];
      if (!file.type.match('image.*')) {
        imagePreview.style.display = 'none';
        return;
      }
      const reader = new FileReader();
      reader.onload = function(e) {
        imagePreview.innerHTML = '<img src="' + e.target.result + '" style="max-width:120px; max-height:90px; border-radius:0.5rem; box-shadow:0 2px 8px rgba(94,137,231,0.10);">';
        imagePreview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      imagePreview.style.display = 'none';
    }
  });
}

// AJAX form submission for chat
const chatSendForm = document.getElementById('chatSendForm');
if (chatSendForm) {
  chatSendForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(chatSendForm);
    const sendBtn = chatSendForm.querySelector('button[type="submit"]');
    const originalContent = sendBtn.innerHTML;
    sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    sendBtn.disabled = true;
    fetch('Process/send_message.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to send message. Please try again.');
        sendBtn.innerHTML = originalContent;
        sendBtn.disabled = false;
      }
    })
    .catch(error => {
      alert('Failed to send message. Please try again.');
      sendBtn.innerHTML = originalContent;
      sendBtn.disabled = false;
    });
  });
}
</script>

<?php include 'footer.php'; ?>