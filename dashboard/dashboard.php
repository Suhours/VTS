<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

include '../db.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Responsive Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <style>
  body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background-color: #f4f9ff;
  }

  .sidebar {
    width: 250px;
    background-color: #007bff;
    position: fixed;
    height: 100%;
    color: white;
    padding-top: 30px;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    overflow-y: auto;
    transition: left 0.3s ease;
  }

  .sidebar a {
    padding: 14px 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
    text-decoration: none;
    font-weight: 500;
    font-size: 15px;
    transition: 0.3s;
    border-left: 4px solid transparent;
  }

  .sidebar a:hover {
    background-color: rgba(255,255,255,0.1);
    border-left: 4px solid #fff;
    cursor: pointer; /* 🔁 This makes the hand cursor appear */
  }

  .dropdown-container {
    display: none;
    background-color: #3399ff;
  }

  .dropdown-container a {
    padding-left: 45px;
    font-weight: normal;
    font-size: 14px;
  }

  .main {
    margin-left: 250px;
    transition: margin-left 0.3s ease;
  }

  iframe {
    width: 100%;
    height: 100vh;
    border: none;
  }

  .logo-box {
    text-align: center;
    margin-bottom: 30px;
  }

  .logo-box img {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    border: 2px solid white;
  }

  .logo-box div {
    margin-top: 5px;
    font-size: 15px;
  }

  .menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    font-size: 26px;
    color: #007bff;
    background: white;
    border-radius: 6px;
    padding: 4px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    cursor: pointer;
    z-index: 1001;
  }

  @media (max-width: 768px) {
    .sidebar {
      left: -250px;
      top: 0;
      z-index: 1000;
    }
    .sidebar.active {
      left: 0;
    }
    .main {
      margin-left: 0;
    }
    .menu-toggle {
      display: block;
    }
  }
</style>

</head>
<body>

<div class="menu-toggle" onclick="toggleMenu()">☰</div>

<div class="sidebar" id="sidebar">
  <div class="logo-box">
    <img src="img/logo2.png" alt="Logo">
    <div><b>ROAD-TAX MS</b></div>
    <div style="font-size: 12px;">SSC-KHAATUMO MOF</div>
  </div>

  <a onclick="loadPage('dashboard_home')"><i class="bi bi-speedometer2"></i> Dashboard</a>
  <a onclick="loadPage('form')"><i class="bi bi-truck"></i> Vehicle Management</a>

  <a onclick="toggleDropdown('paymentDropdown')"><i class="bi bi-cash-stack"></i> Payment Recording</a>
  <div class="dropdown-container" id="paymentDropdown">
    <a onclick="loadPage('../generate/generate_payment')">Generate Payment</a>
    <a onclick="loadPage('../reciept/reciept_payment')">Receipt Payment</a>
  </div>

  <a onclick="toggleDropdown('reportDropdown')"><i class="bi bi-bar-chart-fill"></i> Reports</a>
  <div class="dropdown-container" id="reportDropdown">
    <a onclick="loadPage('reports')">Report Vehicle</a>
  
    <a onclick="loadPage('../reciept/reciept_report')">Receipt Report</a>
  </div>

  <a onclick="loadPage('Vehiclestatement')"><i class="bi bi-file-earmark-text"></i> Vehicle Statement</a>
 <a onclick="toggleDropdown('settingsDropdown')"><i class="bi bi-gear"></i> Setings</a>
  <div class="dropdown-container" id="settingsDropdown">
    <a onclick="loadPage('settings')">Role</a>
    <a onclick="loadPage('manage_users')">Manage Users </a>
    <a onclick="loadPage('../audit_log')">Audit Log </a>
  </div>
  <!--- <a onclick="loadPage('../audit_log')" class="text-decoration-none text-primary fw-semibold">
 <i class="bi bi-journal-text me-1"></i> Audit Log 
</a>  --->
  <a href="../logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main">
  <iframe id="contentFrame" src="dashboard_home.php"></iframe>
</div>

<script>
function toggleDropdown(id) {
  const dropdowns = document.getElementsByClassName("dropdown-container");
  for (let i = 0; i < dropdowns.length; i++) {
    if (dropdowns[i].id !== id) {
      dropdowns[i].style.display = "none";
    }
  }
  const el = document.getElementById(id);
  el.style.display = (el.style.display === "block") ? "none" : "block";
}

function loadPage(page) {
  document.getElementById('contentFrame').src = page;
  localStorage.setItem('lastPage', page);
}

function toggleMenu() {
  document.getElementById("sidebar").classList.toggle("active");
}

window.onload = function() {
  const saved = localStorage.getItem('lastPage');
  const frame = document.getElementById('contentFrame');
  frame.src = saved ? saved : "dashboard_home.php";
};
</script>

</body>
</html>
