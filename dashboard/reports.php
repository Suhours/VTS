<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

include '../db.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$carname_result = $conn->query("SELECT DISTINCT carname FROM vehiclemanagement ORDER BY carname");

$search = $_GET['search'] ?? "";
$car_filter = $_GET['carname'] ?? "";
$month_filter = $_GET['month'] ?? "";

$where = [];
if (!empty($search)) $where[] = "(platenumber LIKE '%$search%' OR phone LIKE '%$search%')";
if (!empty($car_filter)) $where[] = "carname = '$car_filter'";
if (!empty($month_filter)) $where[] = "MONTH(registration_date) = '$month_filter'";

$where_clause = count($where) ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM vehiclemanagement $where_clause ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vehicle Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      overflow: hidden; /* ❌ Prevent page scroll */
      font-family: "Segoe UI", sans-serif;
      background: linear-gradient(120deg, #e0f7fa, #ffffff);
    }
    .wrapper {
      height: 100vh;
      overflow-y: auto; /* ✅ Scroll only this section */
      padding: 30px;
    }
    .title {
      color: #0d6efd;
      font-weight: bold;
    }
    .card-style {
      background-color: white;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
      padding: 30px;
      animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .btn-export {
      border-radius: 30px;
      font-weight: bold;
    }
    .form-control, .form-select {
      border-radius: 10px;
    }
    .filter-bar {
      background: #f1f9ff;
      padding: 15px;
      border-radius: 12px;
      margin-bottom: 20px;
    }
    .alert-info {
      background: #d1ecf1;
      color: #0c5460;
      font-weight: bold;
    }
    table th {
      background-color: #007bff;
      color: white;
    }
  </style>
</head>
<body>

<div class="wrapper">
  <div class="container card-style">
    <h2 class="text-center mb-4 title">🚘 Vehicle Reports</h2>

    <form method="GET" class="row g-3 filter-bar">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="🔍 Plate or Phone" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <select name="carname" class="form-select">
          <option value="">🚗 Filter by Car Name</option>
          <?php while ($row = $carname_result->fetch_assoc()): ?>
            <option value="<?= $row['carname'] ?>" <?= $car_filter == $row['carname'] ? 'selected' : '' ?>>
              <?= $row['carname'] ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="month" class="form-select">
          <option value="">📅 Filter by Month</option>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $month_filter == $m ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-primary">Apply</button>
      </div>
    </form>

    <div class="d-flex justify-content-between mb-3">
      <div>
        <button onclick="confirmAndDownload('pdf')" class="btn btn-success btn-export">🧾 PDF</button>
        <button onclick="confirmAndDownload('excel')" class="btn btn-warning text-dark btn-export">📊 Excel</button>
      </div>
      <div class="alert alert-info mb-0 py-2 px-3">
        Total Records: <?= $result->num_rows ?>
      </div>
    </div>

    <div class="table-responsive">
      <table id="vehicleTable" class="table table-striped table-bordered text-center align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Plate Number</th>
            <th>Owner</th>
            <th>Phone</th>
            <th>Car Name</th>
            <th>Registration Date</th>
            <th>Registered By</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['platenumber']) ?></td>
              <td><?= htmlspecialchars($row['owner']) ?></td>
              <td><?= htmlspecialchars($row['phone']) ?></td>
              <td><?= htmlspecialchars($row['carname']) ?></td>
              <td><?= htmlspecialchars($row['registration_date']) ?></td>
              <td><?= htmlspecialchars($row['user_id']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function confirmAndDownload(type) {
  if (confirm("Ma doonaysaa in aad soo dejiso warbixinta?")) {
    if (type === 'pdf') downloadPDF();
    else if (type === 'excel') downloadExcel();
  }
}

function downloadPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.text("Vehicle Report", 14, 15);
  const headers = [["#", "Plate", "Owner", "Phone", "Car", "Date", "User"]];
  const data = [];
  document.querySelectorAll("#vehicleTable tbody tr").forEach(row => {
    const cells = row.querySelectorAll("td");
    if (cells.length >= 7) {
      data.push(Array.from(cells).map(cell => cell.innerText));
    }
  });
  doc.autoTable({ head: headers, body: data, startY: 20 });
  doc.save("vehicle_report.pdf");
}

function downloadExcel() {
  const table = document.getElementById("vehicleTable").cloneNode(true);
  const wb = XLSX.utils.table_to_book(table, { sheet: "Vehicle Report" });
  XLSX.writeFile(wb, "vehicle_report.xlsx");
}
</script>

</body>
</html>

<?php $conn->close(); ?>
