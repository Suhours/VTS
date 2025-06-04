<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

include '../db.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";

$vehicle = null;
$total_paid = 0;
$total_charged = 0;
$balance = 0;
$last_payment = null;
$charge_data = [];
$payment_data = [];

if (!empty($search)) {
    $vehicle_sql = "SELECT * FROM vehiclemanagement WHERE platenumber LIKE '%$search%' OR owner LIKE '%$search%' LIMIT 1";
    $vehicle_result = $conn->query($vehicle_sql);

    if ($vehicle_result && $vehicle_result->num_rows > 0) {
        $vehicle = $vehicle_result->fetch_assoc();
        $plate = $vehicle['platenumber'];

        $charged_sql = "SELECT amount, due_date FROM tblgenerate WHERE platenumber = '$plate' ORDER BY due_date DESC";
        $charged_result = $conn->query($charged_sql);
        if ($charged_result) {
            while ($row = $charged_result->fetch_assoc()) {
                $total_charged += $row['amount'];
                $charge_data[] = $row;
            }
        }

        $payment_sql = "SELECT amount, due_date FROM tbl_reciept WHERE plate_number = '$plate' ORDER BY due_date DESC";
        $payment_result = $conn->query($payment_sql);
        if ($payment_result) {
            while ($row = $payment_result->fetch_assoc()) {
                $total_paid += $row['amount'];
                $payment_data[] = $row;
                if (!$last_payment) $last_payment = $row;
            }
        }

        $balance = $total_charged - $total_paid;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vehicle Statement</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    .text-blue { color: #0d6efd; font-weight: bold; }
    .text-red { color: #dc3545; font-weight: bold; }
    .modal-body ul { padding-left: 1.2rem; }
    .text-bold { font-weight: bold; }
  </style>
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="card shadow border-0">
    <div class="card-body">
      <h2 class="text-center text-primary mb-4">🚗 Vehicle Statement</h2>

      <form method="get" class="row g-2 justify-content-center mb-4">
        <div class="col-md-6">
          <input type="text" name="search" class="form-control" placeholder="Enter Plate Number or Owner Name" value="<?= htmlspecialchars($search) ?>" required>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary px-4">Search</button>
        </div>
      </form>

      <?php if ($vehicle): ?>
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="card border-primary">
              <div class="card-body">
                <h5 class="card-title text-primary">🚘 Vehicle Info</h5>
                <p><span class="text-bold">Owner:</span> <?= $vehicle['owner'] ?></p>
                <p><span class="text-bold">Plate Number:</span> <?= $vehicle['platenumber'] ?></p>
                <p><span class="text-bold">Vehicle Type:</span> <?= $vehicle['carname'] ?></p>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-success">
              <div class="card-body">
                <h5 class="card-title text-success">📊 Summary</h5>
                <p><span class="text-bold">Total Charged:</span> <span class="text-red">USD <?= number_format($total_charged, 2) ?></span></p>
                <p><span class="text-bold">Total Paid:</span> <span class="text-blue">USD <?= number_format($total_paid, 2) ?></span></p>
                <p><span class="text-bold">Remaining:</span> <span class="text-danger text-bold">USD <?= number_format($balance, 2) ?></span></p>
                <?php if ($last_payment): ?>
                  <p><span class="text-bold">Last Payment:</span> USD <?= number_format($last_payment['amount'], 2) ?> on <?= date("F Y", strtotime($last_payment['due_date'])) ?></p>
                <?php endif; ?>
                <button class="btn btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#viewModal">📂 View Monthly Records</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="viewModalLabel">📅 Monthly Breakdown</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <h6 class="text-danger text-bold">Charged Months</h6>
                    <ul class="list-group">
                      <?php foreach ($charge_data as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <?= date("F Y", strtotime($row['due_date'])) ?>
                          <span class="text-red">USD <?= number_format($row['amount'], 2) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <p class="text-end mt-2 text-red">Total: USD <?= number_format($total_charged, 2) ?></p>
                  </div>
                  <div class="col-md-6 mb-3">
                    <h6 class="text-blue text-bold">Paid Months</h6>
                    <ul class="list-group">
                      <?php foreach ($payment_data as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <?= date("F Y", strtotime($row['due_date'])) ?>
                          <span class="text-blue">USD <?= number_format($row['amount'], 2) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <p class="text-end mt-2 text-blue">Total: USD <?= number_format($total_paid, 2) ?></p>
                  </div>
                </div>
                <hr>
                <p class="text-center fw-bold fs-5">Remaining Balance: <span class="text-danger">USD <?= number_format($balance, 2) ?></span></p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
      <?php elseif (!empty($search)): ?>
        <div class="alert alert-warning text-center mt-4">
          ❗ No data found for "<strong><?= htmlspecialchars($search) ?></strong>"
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
