<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'DocCenter - Healthcare Portal'; ?></title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --accent: #3b82f6;
      --light: #f8fafc;
      --dark: #1e293b;
      --navbar-gradient: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
      --navbar-radius: 1.5rem;
      --navbar-shadow: 0 4px 24px rgba(37,99,235,0.10);
      --font-main: 'Poppins', 'Inter', Arial, sans-serif;
    }
    body {
      font-family: var(--font-main);
      background: linear-gradient(180deg, #eff6ff, #dbeafe);
      color: var(--dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .navbar {
      background: var(--navbar-gradient) !important;
      border-radius: var(--navbar-radius);
      box-shadow: var(--navbar-shadow);
      margin: 1.2rem 1.2rem 2.2rem 1.2rem;
      padding: 0.5rem 1.5rem;
      position: relative;
      z-index: 10;
      animation: fadeIn 0.7s cubic-bezier(.4,0,.2,1);
    }
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      font-size: 2rem;
      font-weight: 700;
      color: #fff !important;
      letter-spacing: 0.01em;
      text-shadow: 0 2px 8px rgba(37,99,235,0.13);
    }
    .navbar-brand .fa-heart-pulse {
      color: #fff;
      font-size: 2.2rem;
      margin-right: 0.2rem;
      animation: pulse 1.5s infinite alternate;
    }
    .navbar-tagline {
      font-size: 1.1rem;
      font-weight: 400;
      color: #e0e7ff;
      margin-left: 0.5rem;
      letter-spacing: 0.01em;
      opacity: 0.85;
      font-family: 'Poppins', 'Inter', Arial, sans-serif;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
      from { transform: scale(1); }
      to { transform: scale(1.12); }
    }
    @media (max-width: 992px) {
      .navbar { margin: 0.5rem 0.5rem 1.2rem 0.5rem; padding: 0.5rem 0.5rem; border-radius: 1rem; }
      .navbar-brand { font-size: 1.3rem; }
      .navbar-tagline { font-size: 0.95rem; }
    }
  </style>
</head>