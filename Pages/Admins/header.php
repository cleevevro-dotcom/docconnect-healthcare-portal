<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Healthcare Admin Portal</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" 
    integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --header-gradient: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
      --header-radius: 1.5rem;
      --header-shadow: 0 4px 24px rgba(37,99,235,0.10);
      --header-link: #f8fafc;
      --header-link-active: #fff;
      --header-link-hover: #c7d2fe;
      --header-profile-bg: #fff;
      --header-profile-color: #2563eb;
      --font-main: 'Poppins', 'Inter', Arial, sans-serif;
    }
    body { font-family: var(--font-main); }
    .admin-navbar {
      background: var(--header-gradient) !important;
      border-radius: var(--header-radius);
      box-shadow: var(--header-shadow);
      margin: 1.2rem 1.2rem 2.2rem 1.2rem;
      padding: 0.5rem 1.5rem;
    }
    .admin-navbar .navbar-brand {
      font-size: 1.6rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      color: #fff !important;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .admin-navbar .navbar-brand .bi {
      font-size: 2rem;
      color: #fff;
      margin-right: 0.2rem;
    }
    .admin-navbar .navbar-nav .nav-link {
      color: var(--header-link) !important;
      font-weight: 500;
      font-size: 1.08rem;
      border-radius: 1.2rem;
      margin: 0 0.2rem;
      padding: 0.5rem 1.1rem !important;
      transition: background 0.2s, color 0.2s, transform 0.2s;
      position: relative;
    }
    .admin-navbar .navbar-nav .nav-link.active, .admin-navbar .navbar-nav .nav-link:focus {
      background: var(--primary-dark);
      color: var(--header-link-active) !important;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(37,99,235,0.10);
    }
    .admin-navbar .navbar-nav .nav-link:hover {
      background: var(--header-link-hover);
      color: var(--primary-dark) !important;
      transform: translateY(-2px) scale(1.03);
    }
    .admin-navbar .navbar-nav .dropdown-menu {
      border-radius: 1.2rem;
      box-shadow: 0 4px 24px rgba(37,99,235,0.10);
      font-size: 1.05rem;
      margin-top: 0.5rem;
    }
    .admin-navbar .navbar-nav .dropdown-item {
      border-radius: 0.8rem;
      padding: 0.6rem 1.2rem;
      transition: background 0.2s, color 0.2s;
    }
    .admin-navbar .navbar-nav .dropdown-item:hover, .admin-navbar .navbar-nav .dropdown-item:focus {
      background: var(--primary);
      color: #fff;
    }
    .admin-navbar .navbar-nav .dropdown-toggle {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .admin-navbar .navbar-nav .dropdown-toggle .bi-person-circle {
      font-size: 1.7rem;
      color: var(--header-profile-color);
      background: var(--header-profile-bg);
      border-radius: 50%;
      padding: 0.2rem;
      margin-right: 0.3rem;
    }
    @media (max-width: 992px) {
      .admin-navbar { margin: 0.5rem 0.5rem 1.2rem 0.5rem; padding: 0.5rem 0.5rem; border-radius: 1rem; }
      .admin-navbar .navbar-brand { font-size: 1.2rem; }
      .admin-navbar .navbar-nav .nav-link { font-size: 1rem; padding: 0.5rem 0.7rem !important; }
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg admin-navbar">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">
      <i class="bi bi-shield-lock-fill"></i>DocCenter Admin
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin" aria-controls="navbarAdmin" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarAdmin">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='dashboard.php'){echo 'active';} ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='Users.php'){echo 'active';} ?>" href="ManagePeople.php"><i class="bi bi-people-fill me-1"></i>Users/Patients</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if(basename($_SERVER['PHP_SELF'])=='Appointments.php'){echo 'active';} ?>" href="Appointments.php"><i class="bi bi-calendar-check me-1"></i>Appointments</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="../../index.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- Your page content starts here -->
