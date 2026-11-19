<!-- header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DocConnect - Patient Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary-color: #64748b;
      --accent-color: #3b82f6;
      --text-light: #f8fafc;
      --text-dark: #1e293b;
      --bg-light: #f1f5f9;
      --transition: all 0.3s ease;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-light);
    }

    .navbar {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
      padding: 1rem 2rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-light) !important;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
    }

    .navbar-brand:hover {
      transform: translateY(-1px);
    }

    .navbar-brand i {
      font-size: 1.8rem;
      color: #fff;
    }

    .nav-link {
      color: var(--text-light) !important;
      font-weight: 500;
      padding: 0.5rem 1rem !important;
      margin: 0 0.2rem;
      border-radius: 0.5rem;
      transition: var(--transition);
      position: relative;
      background-color: rgba(255, 255, 255, 0.1);
    }

    .nav-link:hover {
      background-color: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
      color: var(--text-light) !important;
    }

    .nav-link.active {
      background-color: rgba(255, 255, 255, 0.25);
      color: var(--text-light) !important;
    }

    .nav-link i {
      margin-right: 0.5rem;
      font-size: 1.1rem;
      color: var(--text-light);
    }

    .navbar-toggler {
      border: none;
      padding: 0.5rem;
    }

    .navbar-toggler:focus {
      box-shadow: none;
    }

    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .custom-modal-width {
      max-width: 900px;
      width: 90%;
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 0.8rem 1rem;
      }

      .nav-link {
        padding: 0.8rem 1rem !important;
        margin: 0.2rem 0;
      }

      .navbar-collapse {
        background: var(--primary-dark);
        padding: 1rem;
        border-radius: 0.5rem;
        margin-top: 1rem;
      }
    }

    /* User Profile Section */
    .user-profile {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      transition: var(--transition);
    }

    .user-profile:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 2px solid var(--text-light);
    }

    .user-name {
      color: var(--text-light);
      font-weight: 500;
      margin: 0;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-md navbar-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="Home.php">
      <i class="bi bi-heart-pulse-fill"></i>
      DocConnect
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center">
        <li class="nav-item">
          <a class="nav-link" href="Home.php">
            <i class="bi bi-house-door"></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="Appointment.php">
            <i class="bi bi-calendar-check"></i>Appointment
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="Records.php">
            <i class="bi bi-file-medical"></i>Records
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="PatientMessage.php">
            <i class="bi bi-chat-dots"></i>Messages
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link user-profile" href="../../index.php">
            <img src="../../assets/images/default-avatar.png" alt="User" class="user-avatar">
            <span class="user-name">Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
