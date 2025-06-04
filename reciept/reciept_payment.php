<?php
include '../db.php';
$success = $error = "";
$plate = "";
$vehicle_type = "";
$owner = "";
$amount = 0;
$user_id = $_SESSION['user_id'] ?? 0;

// ✅ Log function
function log_action($conn, $user_id, $action, $page, $details = '') {
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, page, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $page, $details);
    $stmt->execute();
    $stmt->close();
}

// ✅ Success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "✅ Receipt recorded successfully!";
}

// ✅ Search logic
if (isset($_GET['search_plate']) || isset($_GET['search_phone'])) {
    $plate = $_GET['search_plate'] ?? '';
    $phone = $_GET['search_phone'] ?? '';

    if ($phone && !$plate) {
        $stmt = $conn->prepare("SELECT platenumber FROM vehiclemanagement WHERE phone = ? LIMIT 1");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->bind_result($found_plate);
        $stmt->fetch();
        $stmt->close();
        $plate = $found_plate ?? '';
    }

    if ($plate) {
        $stmt = $conn->prepare("SELECT SUM(amount) FROM tblgenerate WHERE platenumber = ?");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $stmt->bind_result($gen_amount);
        $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare("SELECT SUM(amount) FROM tbl_reciept WHERE plate_number = ?");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $stmt->bind_result($paid_amount);
        $stmt->fetch();
        $stmt->close();

        $gen_amount = $gen_amount ?? 0;
        $paid_amount = $paid_amount ?? 0;
        $amount = $gen_amount - $paid_amount;

        $stmt = $conn->prepare("SELECT vehicletype FROM tblgenerate WHERE platenumber = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $stmt->bind_result($vehicle_type);
        $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare("SELECT owner FROM vehiclemanagement WHERE platenumber = ? LIMIT 1");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $stmt->bind_result($owner);
        $stmt->fetch();
        $stmt->close();
    }
}

// ✅ Insert logic (with redirect after save)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_receipt'])) {
    $vehicle_type = $_POST['vehicle_type'];
    $plate_number = $_POST['plate_number'];
    $owner = $_POST['owner'];
    $amount = $_POST['amount'];
    $due_date = $_POST['due_date'] ?: date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO tbl_reciept (vehicle_type, plate_number, owner, amount, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssds", $vehicle_type, $plate_number, $owner, $amount, $due_date);
    if ($stmt->execute()) {
        log_action($conn, $user_id, 'Add', 'tbl_reciept', "Receipt added for plate: $plate_number, amount: $amount");
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?success=1");
        exit;
    } else {
        $error = "❌ Error: " . $stmt->error;
    }
    $stmt->close();
}

// ✅ Delete logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_receipt'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("SELECT plate_number, amount FROM tbl_reciept WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($del_plate, $del_amount);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM tbl_reciept WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "✅ Receipt deleted successfully.";
        log_action($conn, $user_id, 'Delete', 'tbl_reciept', "Deleted receipt for plate: $del_plate, amount: $del_amount");
    } else {
        $error = "❌ Failed to delete receipt.";
    }
    $stmt->close();
}

// ✅ Load receipts
$receipts = $conn->query("SELECT r.*, v.phone FROM tbl_reciept r LEFT JOIN vehiclemanagement v ON r.plate_number = v.platenumber ORDER BY r.id DESC");
?>




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Receipt Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body {
    font-family: 'Segoe UI', sans-serif;
    background: #eef3fb;
    padding: 30px;
  }
  .card-container {
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgb(0 123 255 / 0.1);
    max-width: 1200px;
    margin: auto;
  }
  h3 {
    color: #0d6efd;
    font-weight: 700;
  }
  .filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
  }
  .filter-form input[type="text"] {
    flex: 1 1 200px;
  }
  .filter-form button {
    min-width: 140px;
  }
  table {
    font-size: 14px;
  }
  thead {
    background-color: #0d6efd;
    color: white;
  }
  tbody tr:hover {
    background-color: #e9f0ff;
  }
  .actions a {
    margin: 0 5px;
  }
</style>
</head>
<body>

<div class="container mt-4 shadow p-4 bg-white rounded">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="text-primary fw-bold">🧾 Receipt Report</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#receiptModal">➕ Add Receipt</button>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success text-center"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger text-center"><?= $error ?></div>
  <?php endif; ?>

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="search_plate" placeholder="Search Plate" class="form-control" value="<?= htmlspecialchars($_GET['search_plate'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <input type="text" name="search_phone" placeholder="Search Phone" class="form-control" value="<?= htmlspecialchars($_GET['search_phone'] ?? '') ?>">
    </div>
    <div class="col-md-4">
      <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover table-bordered align-middle mb-0">
      <thead class="table-primary text-center">
        <tr>
          <th>#</th>
          <th>Plate</th>
          <th>Phone</th>
          <th>Owner</th>
          <th>Vehicle Type</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Due Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody class="table-group-divider">
        <?php $i = 1; if ($receipts->num_rows > 0): while ($row = $receipts->fetch_assoc()): ?>
          <tr class="text-center">
            <td><?= $i++ ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($row['plate_number']) ?></td>
            <td><?= htmlspecialchars($row['phone']) ?></td>
            <td><?= htmlspecialchars($row['owner']) ?></td>
            <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
            <td class="text-success fw-bold">$<?= number_format($row['amount'], 2) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= date('d M Y, H:i', strtotime($row['due_date'])) ?></td>
           
            <td>
  <a href="edit_receipt.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">✏️ Edit</a>
  <button class="btn btn-sm btn-outline-danger btn-delete" 
          data-id="<?= $row['id'] ?>" 
          data-bs-toggle="modal" 
          data-bs-target="#deleteModal">🗑️</button>
</td>
          </tr>
        <?php endwhile; else: ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">🚫 No records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="delete_receipt" value="1">
      <input type="hidden" name="delete_id" id="delete_id">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this receipt?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>


<!-- Add Receipt Modal -->
<div class="modal fade <?= !empty($plate) ? 'show d-block' : '' ?>" id="receiptModal" tabindex="-1" aria-modal="true" role="dialog" <?= !empty($plate) ? 'style="background: rgba(0,0,0,0.5);"' : '' ?>>
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm border-0">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title fw-bold">🔍 Search or Record Receipt</h5>
        <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="btn-close btn-close-white"></a>
      </div>
      <div class="modal-body p-4">
        
        <!-- Search Form -->
        <form method="GET" autocomplete="off" class="row g-3 mb-4">
          <div class="col-md-6">
            <input type="text" name="search_plate" class="form-control" placeholder="Search Plate Number" value="<?= htmlspecialchars($_GET['search_plate'] ?? '') ?>" />
          </div>
          <div class="col-md-6">
            <input type="text" name="search_phone" class="form-control" placeholder="Search Phone Number" value="<?= htmlspecialchars($_GET['search_phone'] ?? '') ?>" />
          </div>
          <div class="col-12">
            
            <button type="submit" class="btn btn-primary w-100">🔍 Search</button>
          </div>
        </form>

        <?php if (!empty($plate)): ?>
          <?php if ($amount <= 0): ?>
            <div class="alert alert-danger text-center">❌ No unpaid charges found for this vehicle.</div>
          <?php else: ?>
            <!-- Receipt Form -->
            <form method="POST" class="row g-3">
              <input type="hidden" name="plate_number" value="<?= htmlspecialchars($plate) ?>" />
              
              <div class="col-md-6">
                <label class="form-label fw-semibold">Vehicle Type</label>
                <input type="text" name="vehicle_type" class="form-control" value="<?= htmlspecialchars($vehicle_type) ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Owner Name</label>
                <input type="text" name="owner" class="form-control" value="<?= htmlspecialchars($owner) ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($amount) ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Due Date</label>
                <input type="datetime-local" name="due_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required />
              </div>
              
              <div class="col-12">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x"></i>Cancel</button>
                <button type="submit" name="submit_receipt" class="btn btn-success w-100"><i class="bi bi-save"></i> Save Receipt</button>
              </div>
            </form>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- Delete Handler Script -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = this.getAttribute('data-id');
        document.getElementById('delete_id').value = id;
      });
    });
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
