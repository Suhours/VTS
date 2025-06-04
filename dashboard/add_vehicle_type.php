<?php
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_type'])) {
    $name = trim($_POST['name']);
    $amount_type = trim($_POST['amount_type']);
    $amount = trim($_POST['amount']);

    if (!empty($name) && !empty($amount_type)) {
        $base_amount = $amount;

        if (in_array($amount_type, ['6', '9', '12'])) {
            $base_type = '3';
            $stmt_check = $conn->prepare("SELECT amount FROM vehicle_types WHERE name = ? AND amount_type = ?");
            $stmt_check->bind_param("ss", $name, $base_type);
            $stmt_check->execute();
            $stmt_check->bind_result($amount_3);
            if ($stmt_check->fetch()) {
                $multiplier = intval($amount_type) / 3;
                $base_amount = $amount_3 * $multiplier;
            } else {
                $error = "❌ 3-bilood amount lama helin $name.";
                $stmt_check->close();
            }
            $stmt_check->close();
        }

        if (!$error) {
            $stmt = $conn->prepare("INSERT INTO vehicle_types (name, amount, amount_type) VALUES (?, ?, ?)");
            $stmt->bind_param("sds", $name, $base_amount, $amount_type);
            if ($stmt->execute()) {
                $success = "✅ Vehicle type added successfully!";
            } else {
                $error = "❌ Error: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $error = "❌ Please fill in all fields correctly.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_type'])) {
    $id = $_POST['edit_id'];
    $name = $_POST['edit_name'];
    $amount = $_POST['edit_amount'];
    $amount_type = $_POST['edit_amount_type'];

    $stmt = $conn->prepare("UPDATE vehicle_types SET name=?, amount=?, amount_type=? WHERE id=?");
    $stmt->bind_param("sdsi", $name, $amount, $amount_type, $id);
    if ($stmt->execute()) {
        $success = "✅ Vehicle type updated successfully!";
    } else {
        $error = "❌ Update failed.";
    }
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";
$sql = "SELECT * FROM vehicle_types";
if (!empty($search)) {
    $sql .= " WHERE name LIKE '%$search%' OR amount_type LIKE '%$search%'";
}
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vehicle Type Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light p-4">
<div class="container">
    <h2 class="text-center text-primary mb-4">Vehicle Type Management</h2>

    <?php if ($success) echo "<div class='alert alert-success text-center'>$success</div>"; ?>
    <?php if ($error) echo "<div class='alert alert-danger text-center'>$error</div>"; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">➕ Add Vehicle Type</button>
        <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-secondary ms-2">Search</button>
        </form>
    </div>

    <table class="table table-bordered bg-white">
        <thead class="table-primary">
            <tr>
                <th>Name</th>
                <th>Amount</th>
                <th>Amount Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td>$<?= number_format($row['amount'], 2) ?></td>
                <td><?= htmlspecialchars($row['amount_type']) ?> months</td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                            onclick="setEditData('<?= $row['id'] ?>', '<?= htmlspecialchars($row['name']) ?>', '<?= $row['amount'] ?>', '<?= $row['amount_type'] ?>')">
                        ✏️
                    </button>
                    <a href="delete_type.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">
  🗑️
</a>

                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="addModalLabel">Add Vehicle Type</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="text" name="name" class="form-control mb-3" placeholder="Vehicle type (e.g. Bajaaj, Car)" required>
            <input type="number" step="0.01" name="amount" class="form-control mb-3" placeholder="Amount for 3-months (USD)" required>
            <select name="amount_type" class="form-control" required>
                <option value="">Select Duration</option>
                <option value="3">3 Bilood</option>
                <option value="6">6 Bilood</option>
                <option value="9">9 Bilood</option>
                <option value="12">12 Bilood</option>
            </select>
        </div>
        <div class="modal-footer">
          <button type="submit" name="submit_type" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Vehicle Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id">
        <input type="text" class="form-control mb-3" name="edit_name" id="edit_name" placeholder="Vehicle Name" required>
        <input type="number" step="0.01" class="form-control mb-3" name="edit_amount" id="edit_amount" placeholder="Amount" required>
        <select name="edit_amount_type" id="edit_amount_type" class="form-control" required>
            <option value="3">3 Bilood</option>
            <option value="6">6 Bilood</option>
            <option value="9">9 Bilood</option>
            <option value="12">12 Bilood</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="submit" name="edit_type" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function setEditData(id, name, amount, type) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_amount_type').value = type;
}
</script>
</body>
</html>

<?php $conn->close(); ?>
