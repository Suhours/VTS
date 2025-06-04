<?php

// ✅ SOO BANDHIG ERROR-KASTA
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ DB CONNECTION
include '../db.php';
if (!$conn || $conn->connect_error) {
  die("❌ Connection error: " . $conn->connect_error);
}

// ✅ TOTAL VEHICLES
$vehicle_q = $conn->query("SELECT COUNT(*) AS total FROM vehiclemanagement");
if (!$vehicle_q) die("Error vehiclemanagement: " . $conn->error);
$vehicle_total = $vehicle_q->fetch_assoc()['total'];

// ✅ TOTAL REVENUE
$revenue_q = $conn->query("SELECT SUM(amount) AS total FROM tbl_reciept");
if (!$revenue_q) die("Error tbl_reciept total: " . $conn->error);
$total_revenue = $revenue_q->fetch_assoc()['total'] ?? 0;

// ✅ PENDING AMOUNT
$pending_q = $conn->query("SELECT SUM(amount) AS total FROM tblgenerate WHERE status = 'pending'");
if (!$pending_q) die("Error tblgenerate pending: " . $conn->error);
$pending_amount_total = $pending_q->fetch_assoc()['total'] ?? 0;

$collected_amount = max(0, $total_revenue - $pending_amount_total);

// ✅ MONTHLY REVENUE
$monthly_data = [];
$year = date('Y');
for ($m = 1; $m <= 12; $m++) {
  $monthly_q = $conn->query("SELECT SUM(amount) AS total FROM tbl_reciept WHERE MONTH(due_date) = $m AND YEAR(due_date) = $year");
  if (!$monthly_q) die("Error monthly data (month $m): " . $conn->error);
  $row = $monthly_q->fetch_assoc();
  $monthly_data[] = $row['total'] ?? 0;
}

// ✅ PIE CHART
$pie_result = $conn->query("SELECT carname, COUNT(*) as count FROM vehiclemanagement GROUP BY carname");
if (!$pie_result) die("Error pie chart query: " . $conn->error);
$pie_labels = $pie_counts = [];
while ($row = $pie_result->fetch_assoc()) {
  $pie_labels[] = $row['carname'];
  $pie_counts[] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      background: linear-gradient(to bottom right, #eaf3fb, #ffffff);
      font-family: 'Segoe UI', sans-serif;
    }
    .wrapper {
      max-width: 1200px;
      margin: auto;
      padding: 30px 20px;
    }
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    .topbar h2 {
      color: #0d6efd;
      font-weight: bold;
    }
    .icons a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      background: #0d6efd;
      color: white;
      font-size: 18px;
      text-decoration: none;
      border-radius: 50%;
      transition: 0.3s;
    }
    .icons a:hover {
      background: #0b5ed7;
      transform: scale(1.1);
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      padding: 25px;
      text-align: center;
    }
    .card .icon-label {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      color: #555;
    }
    .card p {
      font-size: 28px;
      font-weight: bold;
      color: #0d6efd;
      margin: 0;
    }
 .charts {
  display: flex;
  gap: 20px; /* Yaree farqiga u dhexeeya box-yada */
  flex-wrap: wrap;
  margin-top: 20px;
}

.chart-box {
  flex: 1;
  background: #fff;
  border-radius: 12px;
  padding: 20px; /* Yaree padding-ka gudaha */
  box-shadow: 0 4px 10px rgba(0,0,0,0.07);
  min-width: 340px; /* Yaree width minimum */
  min-height: 200px; /* Yaree dhererka box-ka */
}

.chart-box canvas {
  height: 500px !important;
  max-height: 250px !important;
}

.chart-box canvas#pendingChart {
  height: 200px !important;
  max-height: 200px !important;
}

    .chart-box h5 {
      font-size: 16px;
      margin-bottom: 15px;
      text-align: center;
      font-weight: 600;
    }
    canvas {
      width: 100% !important;
      height: 300px !important;
    }
    canvas.spark {
      height: 45px !important;
      margin-top: 10px;
    }
    @media (max-width: 768px) {
      .charts {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="topbar">
    <h2>📊 Dashboard Overview</h2>
    <div class="icons">
      <a href="settings"><i class="bi bi-gear"></i></a>
      <a href="add_vehicle_type"><i class="bi bi-car-front-fill"></i></a>
      <a href="manage_users"><i class="bi bi-people"></i></a>
      <a href="profile_admin"><i class="bi bi-person-circle"></i></a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-4 col-sm-6">
      <div class="card">
        <div class="icon-label"><i class="bi bi-car-front-fill"></i> Total Vehicles</div>
        <p><?= $vehicle_total; ?></p>
        <canvas id="spark1" class="spark"></canvas>
      </div>
    </div>
    <div class="col-md-4 col-sm-6">
      <div class="card">
        <div class="icon-label"><i class="bi bi-car-front-fill"></i> Total Revenue</div>
        <p>$<?= number_format($total_revenue, 2); ?></p>
          <canvas id="spark2" class="spark"></canvas>
      </div>
    </div>
    <div class="col-md-4 col-sm-6">
      <div class="card">
        <div class="icon-label"><i class="bi bi-hourglass-split"></i> Pending</div>
        <p>$<?= number_format($pending_amount_total, 2); ?></p>
        <canvas id="spark3" class="spark"></canvas>
      </div>
    </div>
  </div>

  <div class="charts">
    <div class="chart-box">
      <h5>Vehicle Types (Pie Chart)</h5>
      <canvas id="barChart"></canvas>
    </div>

    <div class="chart-box">
      <h5 class="d-flex justify-content-between align-items-center">
        <span>Monthly Revenue</span>
        <button class="btn btn-sm btn-outline-primary" onclick="toggleDemo()">View: <span id="modeLabel">Real</span></button>
      </h5>
      <canvas id="lineChart"></canvas>
    </div>

    <div class="chart-box">
      <h5>Pending vs Collected</h5>
      <canvas id="pendingChart"></canvas>
    </div>
  </div>
</div>

<script>
const demoData = [1200, 1800, 2100, 1600, 2300, 2800, 1900, 2400, 3000, 2700, 2200, 3100];
let isDemo = false;

// Sparkline options
const sparkOptions = {
  type: 'line',
  options: {
    responsive: false,
    elements: {
      point: { radius: 0 },
      line: { tension: 0.3, borderWidth: 2 }
    },
    plugins: {
      legend: { display: false },
      tooltip: { enabled: false }
    },
    scales: {
      x: { display: false },
      y: { display: false }
    }
  }
};

// Spark 1
new Chart(document.getElementById('spark1'), {
  ...sparkOptions,
  data: {
    labels: Array.from({length: 12}, (_, i) => i + 1),
    datasets: [{
      data: <?= json_encode(array_map(fn($v) => rand(30, 100), $monthly_data)); ?>,
      borderColor: '#ced4da',
      backgroundColor: 'transparent'
    }]
  }
});

// Spark 2
new Chart(document.getElementById('spark2'), {
  ...sparkOptions,
  data: {
    labels: Array.from({length: 12}, (_, i) => i + 1),
    datasets: [{
      data: <?= json_encode($monthly_data); ?>,
      borderColor: '#adb5bd',
      backgroundColor: 'transparent'
    }]
  }
});

// Spark 3
new Chart(document.getElementById('spark3'), {
  ...sparkOptions,
  data: {
    labels: Array.from({length: 12}, (_, i) => i + 1),
    datasets: [{
      data: <?= json_encode(array_map(fn($v) => $v * 0.5, $monthly_data)); ?>,
      borderColor: '#0d6efd',
      backgroundColor: 'rgba(13,110,253,0.08)'
    }]
  }
});

// PIE CHART
new Chart(document.getElementById('barChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($pie_labels); ?>,
    datasets: [{
      data: <?= json_encode($pie_counts); ?>,
      backgroundColor: [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
        '#9966FF', '#FF9F40', '#A3E1D7', '#F6C23E',
        '#E74A3B', '#1CC88A', '#858796', '#2C9FAF'
      ],
      borderWidth: 1,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' },
      title: { display: false }
    }
  }
});

// BAR CHART
const lineChart = new Chart(document.getElementById('lineChart'), {
  type: 'bar',
  data: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets: [{
      label: 'Monthly Revenue',
      data: <?= json_encode($monthly_data); ?>,
      backgroundColor: '#0d6efd',
      borderRadius: 10,
      barThickness: 20
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return '$' + value;
          }
        }
      }
    }
  }
});

function toggleDemo() {
  isDemo = !isDemo;
  const label = document.getElementById("modeLabel");
  label.textContent = isDemo ? "Demo" : "Real";
  lineChart.data.datasets[0].data = isDemo ? demoData : <?= json_encode($monthly_data); ?>;
  lineChart.update();
}

// STACKED CHART
new Chart(document.getElementById('pendingChart'), {
  type: 'bar',
  data: {
    labels: ['Status'],
    datasets: [
      {
        label: 'Pending',
        data: [<?= $pending_amount_total; ?>],
        backgroundColor: '#dc3545'
      },
      {
        label: 'Collected',
        data: [<?= $collected_amount; ?>],
        backgroundColor: '#198754'
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' }
    },
    scales: {
      x: { stacked: true },
      y: { stacked: true, beginAtZero: true }
    }
  }
});
</script>
</body>
</html>
