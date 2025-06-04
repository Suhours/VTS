<?php
include '../db.php';

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? 'All';
$month_filter = $_GET['month'] ?? '';
$year_filter = $_GET['year'] ?? '';
$years = range(date("Y"), date("Y") - 10);

$types_result = $conn->query("SELECT DISTINCT vehicle_type FROM tbl_reciept");
$vehicle_types = [];
while ($row = $types_result->fetch_assoc()) {
    $vehicle_types[] = $row['vehicle_type'];
}

$where = "WHERE (r.plate_number LIKE ? OR v.phone LIKE ? OR r.vehicle_type LIKE ?)";
$params = ["sss", "%$search%", "%$search%", "%$search%"];

if ($type_filter !== 'All') {
    $where .= " AND r.vehicle_type = ?";
    $params[0] .= "s";
    $params[] = $type_filter;
}
if (!empty($month_filter)) {
    $where .= " AND MONTH(r.due_date) = ?";
    $params[0] .= "i";
    $params[] = (int)$month_filter;
}
if (!empty($year_filter)) {
    $where .= " AND YEAR(r.due_date) = ?";
    $params[0] .= "i";
    $params[] = (int)$year_filter;
}

$sql = "SELECT r.*, v.phone FROM tbl_reciept r 
        LEFT JOIN vehiclemanagement v ON r.plate_number = v.platenumber 
        $where ORDER BY r.due_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param(...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receipt Payment Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      overflow: hidden;
      font-family: "Segoe UI", sans-serif;
      background: linear-gradient(120deg, #f0f8ff, #ffffff);
    }
    .wrapper {
      height: 100vh;
      overflow-y: auto;
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
    table th {
      background-color: #007bff;
      color: white;
    }
  </style>
</head>
<body>

<div class="wrapper">
  <div class="container card-style">
    <h2 class="text-center mb-4 title">🧾 Receipt Payment Report</h2>

    <form method="GET" class="row g-3 filter-bar">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="🔍 Plate or Phone" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2">
        <select name="type" class="form-select">
          <option value="All">🚘 All Types</option>
          <?php foreach ($vehicle_types as $type): ?>
            <option value="<?= $type ?>" <?= $type_filter == $type ? 'selected' : '' ?>>
              <?= $type ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="month" class="form-select">
          <option value="">📅 Month</option>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $month_filter == $m ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="year" class="form-select">
          <option value="">📆 Year</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $year_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-grid">
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
      </div>
    </form>

    <div class="d-flex justify-content-between mb-3">
      <div>
        <button onclick="confirmAndRun('pdf')" class="btn btn-success btn-export">🧾 PDF</button>
        <button onclick="confirmAndRun('excel')" class="btn btn-warning text-dark btn-export">📊 Excel</button>
      </div>
      <div class="alert alert-info mb-0 py-2 px-3">
        Total Records: <?= $result->num_rows ?>
      </div>
    </div>

    <div class="table-responsive">
      <table id="receiptTable" class="table table-striped table-bordered text-center align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Plate</th>
            <th>Phone</th>
            <th>Owner</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Due Date</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($row['plate_number']) ?></td>
              <td><?= htmlspecialchars($row['phone']) ?></td>
              <td><?= htmlspecialchars($row['owner']) ?></td>
              <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
              <td>$<?= number_format($row['amount'], 2) ?></td>
              <td><?= date('d M Y - H:i', strtotime($row['due_date'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function confirmAndRun(type) {
  if (confirm("Ma doonaysaa in aad soo dejiso warbixinta?")) {
    if (type === 'pdf') downloadPDF();
    else if (type === 'excel') downloadExcel();
  }
}

function downloadPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.text("Receipt Payment Report", 14, 15);
  const headers = [["#", "Plate", "Phone", "Owner", "Type", "Amount", "Due Date"]];
  const data = [];
  document.querySelectorAll("#receiptTable tbody tr").forEach(row => {
    const cells = row.querySelectorAll("td");
    if (cells.length === 7) {
      data.push(Array.from(cells).map(cell => cell.innerText));
    }
  });
  doc.autoTable({ head: headers, body: data, startY: 20 });
  doc.save("receipt_report.pdf");
}

function downloadExcel() {
  const table = document.getElementById("receiptTable").cloneNode(true);
  const wb = XLSX.utils.table_to_book(table, { sheet: "Receipts" });
  XLSX.writeFile(wb, "receipt_report.xlsx");
}
</script>

</body>
</html>

<?php $conn->close(); ?>
