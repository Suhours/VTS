<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login");
  exit;
}
include '../db.php';
$success = $error = "";

// Save Vehicle and Generate 3 charges
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_vehicle'])) {
  $platenumber = trim($_POST['platenumber']);
  $vehicletype = trim($_POST['carname']);
  $fullname = trim($_POST['owner']);
  $model = trim($_POST['model']);
  $phone = trim($_POST['phone']);
  $registration = $_POST['registration'];
  $description = trim($_POST['description']);
  $user_id = $_SESSION['user_id'];

  if (!preg_match('/^\d+$/', $phone)) {
    $error = "❌ Phone must contain numbers only.";
  } else {
    $stmt = $conn->prepare("INSERT INTO vehiclemanagement (platenumber, vehicletype, carname, owner, model, phone, registration_date, description, user_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssi", $platenumber, $vehicletype, $vehicletype, $fullname, $model, $phone, $registration, $description, $user_id);

    if ($stmt->execute()) {
      $success = "✅ Vehicle registered successfully.";

      $stmt2 = $conn->prepare("SELECT amount FROM vehicle_types WHERE name = ?");
      $stmt2->bind_param("s", $vehicletype);
      $stmt2->execute();
      $stmt2->bind_result($amount);
      $stmt2->fetch();
      $stmt2->close();

      for ($i = 0; $i < 3; $i++) {
        $due_date = date("Y-m-d", strtotime("+$i months", strtotime($registration)));
        $status = 'pending';
        $stmt3 = $conn->prepare("INSERT INTO tblgenerate (platenumber, vehicletype, fullname, amount, due_date, status, user_id)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt3->bind_param("sssissi", $platenumber, $vehicletype, $fullname, $amount, $due_date, $status, $user_id);
        $stmt3->execute();
        $stmt3->close();
      }

    } else {
      $error = "❌ Failed to register vehicle.";
    }
    $stmt->close();
  }
}

$types = $conn->query("SELECT name FROM vehicle_types");

$search = $_GET['search'] ?? '';
$where = "WHERE 1=1";
if (!empty($search)) {
  $search_term = $conn->real_escape_string($search);
  $where .= " AND (platenumber LIKE '%$search_term%' OR phone LIKE '%$search_term%')";
}
$vehicles = $conn->query("SELECT * FROM vehiclemanagement $where ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Vehicle Register</title>
  <meta charset="UTF-8">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary">🚗 Vehicle Management</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerModal">+ Register Vehicle</button>
  </div>

  <form method="GET" class="mb-3 d-flex gap-2">
    <input type="text" name="search" class="form-control" placeholder="Search plate or phone" value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-outline-primary">Search</button>
  </form>

  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

  <div class="table-responsive shadow-sm rounded">
    <table class="table table-bordered table-hover text-center">
      <thead class="table-primary">
        <tr>
          <th>#</th>
          <th>Plate</th>
          <th>Type</th>
          <th>Owner</th>
          <th>Model</th>
          <th>Phone</th>
          <th>Registration</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; while ($row = $vehicles->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $row['platenumber'] ?></td>
          <td><?= $row['vehicletype'] ?></td>
          <td><?= $row['owner'] ?></td>
          <td><?= $row['model'] ?></td>
          <td><?= $row['phone'] ?></td>
          <td><?= $row['registration_date'] ?></td>
          <td><?= $row['description'] ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Register Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="register_vehicle" value="1">

        <div class="mb-2">
          <label class="form-label">Plate Number</label>
          <input type="text" name="platenumber" class="form-control" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Vehicle Type</label>
          <select name="carname" class="form-select" required>
            <option value="">-- Select Type --</option>
            <?php $types->data_seek(0); while ($row = $types->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mb-2">
          <label class="form-label">Owner (Full Name)</label>
          <input type="text" name="owner" class="form-control" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Model</label>
          <input type="text" name="model" class="form-control" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        </div>

        <div class="mb-2">
          <label class="form-label">Registration Date</label>
          <input type="date" name="registration" class="form-control" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Enter description (optional)" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary w-100">💾 Save Vehicle</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
