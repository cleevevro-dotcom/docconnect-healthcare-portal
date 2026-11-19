<?php
session_start();
include '../../database/DBconnect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get doctor_id
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($doctor_id);
if (!$stmt->fetch()) {
    $stmt->close();
    die("Doctor profile not found.");
}
$stmt->close();

// Fetch patients assigned to this doctor (based on appointments)
$sql = "
    SELECT DISTINCT p.patient_id, u.first_name, u.last_name, p.profile_picture
    FROM patients p
    INNER JOIN users u ON p.user_id = u.user_id
    INNER JOIN appointments a ON p.patient_id = a.patient_id
    WHERE a.doctor_id = ?
    ORDER BY u.first_name, u.last_name
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients_result = $stmt->get_result();
$patients = $patients_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle selected patient
$selected_patient_id = isset($_GET['patient_id']) && is_numeric($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
$selected_patient_name = 'Select a patient';
$selected_patient_user_id = null;

if ($selected_patient_id) {
    $sql = "
        SELECT u.first_name, u.last_name, u.user_id
        FROM patients p
        INNER JOIN users u ON p.user_id = u.user_id
        INNER JOIN appointments a ON p.patient_id = a.patient_id
        WHERE p.patient_id = ? AND a.doctor_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("ii", $selected_patient_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($patient = $result->fetch_assoc()) {
        $selected_patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
        $selected_patient_user_id = $patient['user_id'];
    } else {
        $selected_patient_id = null;
    }
    $stmt->close();
}

// Fetch messages for selected patient
$messages = [];
if ($selected_patient_id && $selected_patient_user_id) {
    $sql = "
        SELECT m.message_id, m.sender_id, m.message_text, m.sent_at, m.is_read,
               u.first_name, u.last_name
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("iiii", $user_id, $selected_patient_user_id, $selected_patient_user_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Mark messages from patient as read
    $sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    $stmt->bind_param("ii", $user_id, $selected_patient_user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_patient_id && $selected_patient_user_id) {
    $message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
    $image_path = null;
    
    // Handle image upload if present
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/messages/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'msg_' . time() . '_' . $user_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/messages/' . $new_filename;
            } else {
                echo '<script>alert("Failed to upload image. Please try again.");</script>';
            }
        } else {
            echo '<script>alert("Invalid file type. Please upload JPG, JPEG, PNG, or GIF images only.");</script>';
        }
    }
    
    // Insert message if there's text or an image
    if (!empty($message_text) || $image_path) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message_text, file_path, message_type) VALUES (?, ?, ?, ?, ?)";
        $msg_type = $image_path ? 'image' : 'text';
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo '<script>alert("Database error: ' . $conn->error . '");</script>';
        } else {
            $stmt->bind_param("iisss", $user_id, $selected_patient_user_id, $message_text, $image_path, $msg_type);
            
            if ($stmt->execute()) {
                // Redirect to refresh the page and show the new message
                header("Location: Message.php?patient_id=" . $selected_patient_id);
                exit;
            } else {
                echo '<script>alert("Failed to send message. Please try again.");</script>';
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<?php include 'header.php'; ?>
<?php include_once '../../includes/image_handler.php'; ?>

<!-- Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
<!-- FontAwesome -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<style>
  :root {
    --primary: rgb(94, 137, 231);
    --primary-dark: rgb(77, 113, 212);
    --light: rgb(176, 216, 255);
    --chat-bg: #f4f8ff;
    --chat-bubble-sent: var(--primary);
    --chat-bubble-received: #fff;
    --chat-bubble-shadow: 0 2px 8px rgba(94,137,231,0.08);
    --chat-radius: 1.2rem;
    --chat-avatar-size: 2.5rem;
    --chat-font: 'Poppins', 'Inter', Arial, sans-serif;
  }
  body {
    font-family: var(--chat-font);
    background: linear-gradient(180deg, #eaf1fb, var(--light));
    color: #1e293b;
  }
  .container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 2rem 1rem;
  }
  .patient-list {
    position: sticky;
    top: 2rem;
  }
  .patient-list .bg-white {
    background: var(--light) !important;
    border-radius: 1.2rem;
    box-shadow: var(--chat-bubble-shadow);
    padding: 1.5rem 1rem;
  }
  .patient-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.7rem 0.8rem;
    border-radius: 0.9rem;
    background: #f6faff;
    color: #1e293b;
    font-weight: 500;
    font-size: 1rem;
    transition: background 0.18s, color 0.18s;
    margin-bottom: 0.2rem;
    cursor: pointer;
    border: 2px solid transparent;
  }
  .patient-item.selected, .patient-item.bg-blue-100 {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary-dark);
  }
  .patient-item:hover {
    background: var(--primary-light, #dbeafe);
    color: var(--primary-dark);
  }
  .patient-item img {
    width: var(--chat-avatar-size);
    height: var(--chat-avatar-size);
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(94,137,231,0.10);
  }
  .chat-body {
    max-height: 60vh;
    overflow-y: auto;
    background: var(--chat-bg);
    border-radius: 1.2rem;
    padding: 1.5rem 1rem 1rem 1rem;
    margin-bottom: 1rem;
    box-shadow: var(--chat-bubble-shadow);
  }
  .chat-body::-webkit-scrollbar {
    width: 4px;
  }
  .chat-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 2px;
  }
  .message {
    max-width: 75%;
    margin-bottom: 1.2rem;
    display: flex;
    flex-direction: column;
    animation: fadeIn 0.5s;
  }
  .message.sent {
    margin-left: auto;
    align-items: flex-end;
  }
  .message.received {
    align-items: flex-start;
  }
  .bubble {
    padding: 0.7rem 1.2rem;
    border-radius: var(--chat-radius);
    box-shadow: var(--chat-bubble-shadow);
    font-size: 1.08rem;
    line-height: 1.5;
    word-break: break-word;
    margin-bottom: 0.2rem;
    background: var(--chat-bubble-received);
    color: #1e293b;
    position: relative;
  }
  .message.sent .bubble {
    background: var(--chat-bubble-sent);
    color: #fff;
    border-bottom-right-radius: 0.3rem;
  }
  .message.received .bubble {
    background: var(--chat-bubble-received);
    color: #1e293b;
    border-bottom-left-radius: 0.3rem;
  }
  .message .meta {
    font-size: 0.85rem;
    color: #7b8794;
    margin-bottom: 0.1rem;
    margin-top: 0.1rem;
    text-align: right;
  }
  .message.received .meta {
    text-align: left;
  }
  .fade-in {
    animation: fadeIn 0.5s ease;
  }
  .slide-in {
    animation: slideIn 0.4s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
  }
  #patientSearch:focus {
    box-shadow: 0 0 0 3px rgba(94,137,231,0.18);
    border-color: var(--primary);
  }
  .send-box {
    display: flex;
    gap: 0.7rem;
    align-items: center;
    background: #fff;
    border-radius: 1.2rem;
    box-shadow: 0 2px 8px rgba(94,137,231,0.08);
    padding: 0.5rem 1rem;
  }
  .send-box input[type="text"] {
    flex-grow: 1;
    border: none;
    background: transparent;
    font-size: 1.08rem;
    padding: 0.7rem 0.5rem;
    outline: none;
    color: #1e293b;
  }
  .send-box button {
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 0.8rem;
    padding: 0.7rem 1.5rem;
    font-weight: 600;
    font-size: 1.08rem;
    transition: background 0.18s;
    box-shadow: 0 2px 8px rgba(94,137,231,0.10);
  }
  .send-box button:hover {
    background: var(--primary-dark);
  }
  @media (max-width: 992px) {
    .container {
      padding: 1rem 0.2rem;
    }
    .grid {
      grid-template-columns: 1fr !important;
    }
    .patient-list {
      position: static;
      margin-bottom: 2rem;
    }
  }
  @media (max-width: 576px) {
    .container {
      padding: 0.5rem 0.1rem;
    }
    .chat-body {
      padding: 0.7rem 0.2rem 0.5rem 0.2rem;
    }
    .send-box {
      padding: 0.3rem 0.5rem;
    }
    .patient-item img {
      width: 2rem;
      height: 2rem;
    }
  }
</style>

<div class="container mx-auto py-8 px-8 max-w-7xl">
  <h2 class="text-2xl font-semibold text-center text-blue-900 mb-6 fade-in">Messages</h2>

  <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Patient List -->
    <div class="patient-list">
      <div class="bg-white rounded-lg shadow-md p-4 fade-in">
        <h4 class="text-base font-semibold mb-3 flex items-center text-blue-800 bg-gradient-to-r from-blue-100 to-blue-200 px-3 py-2 rounded">
          <i class="fas fa-users mr-2"></i> Patients
        </h4>
        <div class="mb-3">
          <div class="relative">
            <input type="text" id="patientSearch" class="w-full border-gray-300 rounded-md text-sm focus:ring-blue-400 focus:border-blue-400 pl-10 pr-4 py-2" placeholder="Search patients...">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          </div>
        </div>
        <div class="space-y-2" style="max-height: 60vh; overflow-y: auto;">
          <?php if (empty($patients)): ?>
            <p class="text-center text-gray-500 text-sm">No patients found.</p>
          <?php else: ?>
            <?php foreach ($patients as $patient): 
              $avatar = !empty($patient['profile_picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Healthcare_portal/Pages/Patients' . $patient['profile_picture'])
                ? '/Healthcare_portal/Pages/Patients' . $patient['profile_picture']
                : '/Healthcare_portal/Pages/Patients/uploads/default_profile.png';
            ?>
              <a href="?patient_id=<?php echo (int)$patient['patient_id'] ?>" class="patient-item<?php echo $selected_patient_id === $patient['patient_id'] ? ' selected' : '' ?> slide-in">
                <img src="<?php echo htmlspecialchars($avatar) ?>" alt="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>" class="object-cover">
                <span><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Chat System -->
    <div class="lg:col-span-3">
      <div class="bg-white rounded-lg shadow-md p-4 fade-in flex flex-col h-full">
        <h4 class="text-base font-semibold mb-3 flex items-center text-teal-800 bg-gradient-to-r from-teal-100 to-teal-200 px-3 py-2 rounded">
          <i class="fas fa-comments mr-2"></i> Chat with <?php echo htmlspecialchars($selected_patient_name) ?>
        </h4>
        <div class="chat-body flex-grow bg-gray-50 rounded-md p-3">
          <?php if (!$selected_patient_id): ?>
            <div class="text-center text-gray-500 py-5">
              <i class="fas fa-comment-alt text-3xl mb-2"></i>
              <p class="text-sm">Select a patient to start chatting.</p>
            </div>
          <?php else: ?>
            <div id="messages">
              <?php if (empty($messages)): ?>
                <div class="text-center text-gray-500 py-5">
                  <i class="fas fa-comment-alt text-3xl mb-2"></i>
                  <p class="text-sm">No messages yet. Start the conversation!</p>
                </div>
              <?php else: ?>
                <?php foreach ($messages as $message): 
                  $is_sent = $message['sender_id'] === $user_id;
                  $is_image = isset($message['file_path']) && $message['file_path'] && (isset($message['message_type']) ? $message['message_type'] === 'image' : true);
                ?>
                  <div class="message <?php echo $is_sent ? 'sent' : 'received' ?> slide-in">
                    <div class="text-xs text-gray-500 mb-1">
                      <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?>,
                      <?php echo date('M j, Y h:i A', strtotime($message['sent_at'])) ?>
                    </div>
                    <div class="bubble">
                      <?php if ($is_image): ?>
                        <a href="/<?php echo htmlspecialchars($message['file_path']); ?>" target="_blank">
                          <img src="/<?php echo htmlspecialchars($message['file_path']); ?>" alt="Sent image" style="max-width:220px; max-height:180px; border-radius:0.7rem; box-shadow:0 2px 8px rgba(94,137,231,0.10); margin-bottom:0.3rem; display:block;">
                        </a>
                      <?php endif; ?>
                      <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($selected_patient_id): ?>
          <div class="mt-3 send-box">
            <form method="POST" enctype="multipart/form-data" class="flex gap-2 w-100" id="chatSendForm">
              <input type="text" name="message" placeholder="Type your message..." autocomplete="off" style="min-width:0;">
              <label for="chatImage" style="cursor:pointer; margin:0; display:flex; align-items:center;">
                <i class="fas fa-image" style="font-size:1.3rem; color:var(--primary); margin-right:0.2rem;"></i>
                <input type="file" name="image" id="chatImage" accept="image/*" style="display:none;">
              </label>
              <button type="submit" id="sendButton">Send</button>
            </form>
            <div id="imagePreview" style="display:none; margin-top:0.5rem;"></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="h-10"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-scroll to bottom of chat
  const chatBody = document.querySelector('.chat-body');
  if (chatBody) {
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  // Patient search
  const searchInput = document.getElementById('patientSearch');
  const patientItems = document.querySelectorAll('.patient-item');
  let debounceTimeout;

  function filterPatients() {
    const filter = searchInput.value.toLowerCase().trim();
    patientItems.forEach(item => {
      const name = item.querySelector('span').textContent.toLowerCase();
      item.style.display = name.includes(filter) ? '' : 'none';
    });
  }

  searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(filterPatients, 300);
  });

  // Basic polling for new messages (optional, can be disabled)
  const selectedPatientId = <?php echo json_encode($selected_patient_id); ?>;
  if (selectedPatientId) {
    setInterval(function() {
      fetch('fetch_messages.php?patient_id=' + selectedPatientId)
        .then(response => response.json())
        .then(data => {
          const messagesDiv = document.getElementById('messages');
          if (data.messages && data.messages.length > 0) {
            messagesDiv.innerHTML = '';
            data.messages.forEach(msg => {
              const isSent = msg.sender_id === <?php echo json_encode($user_id); ?>;
              const messageHtml = `
                <div class="message ${isSent ? 'sent' : 'received'} slide-in">
                  <div class="text-xs text-gray-500 mb-1">
                    ${msg.first_name} ${msg.last_name}, ${new Date(msg.sent_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}
                  </div>
                  <div class="bubble">${msg.message_text.replace(/\n/g, '<br>')}</div>
                </div>
              `;
              messagesDiv.insertAdjacentHTML('beforeend', messageHtml);
            });
            chatBody.scrollTop = chatBody.scrollHeight;
          }
        })
        .catch(error => console.error('Error fetching messages:', error));
    }, 5000); // Poll every 5 seconds
  }

  const chatForm = document.getElementById('chatSendForm');
  const sendButton = document.getElementById('sendButton');
  
  if (chatForm) {
    chatForm.addEventListener('submit', function(e) {
      const messageInput = this.querySelector('input[name="message"]');
      const imageInput = this.querySelector('input[name="image"]');
      
      if (!messageInput.value.trim() && (!imageInput.files || imageInput.files.length === 0)) {
        e.preventDefault();
        alert('Please enter a message or select an image to send.');
        return;
      }
      
      // Disable send button to prevent double submission
      sendButton.disabled = true;
      sendButton.textContent = 'Sending...';
    });
  }
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
</script>

<?php include 'footer.php'; ?>