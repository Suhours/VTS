<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo "<div style='padding:20px;color:red;font-family:sans-serif;'>Access denied. Admin only.</div>";
    exit;
}


include '../db.php';

$pages = [
    'Main Pages' => [
        '../dashboard/dashboard_home.php',
        '../dashboard/form.php',
        '../generate/generate_payment.php',
        '../reciept/reciept_payment.php',
        '../dashboard/reports.php',
        '../generate/generate_report.php',
        '../reciept/reciept_report.php',
        '../dashboard/Vehiclestatement.php',
        '../dashboard/settings.php'
    ]
];

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['role_user_id'];
    $new_role = $_POST['new_role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();
    $message = "🔁 User role updated to <strong>$new_role</strong>.";
}

// Handle page assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pages'])) {
    $user_id = $_POST['user_id'];
    $selected_pages = $_POST['pages'];

    foreach ($selected_pages as $page) {
        $parts = explode('/', trim($page, '/'));
        $folder = $parts[1] ?? 'dashboard';
        $file = $parts[2] ?? $parts[1];
        $clean_page = $folder . '/' . $file;

        $check = $conn->prepare("SELECT id FROM tbl_user_pages WHERE user_id = ? AND page_name = ?");
        $check->bind_param("is", $user_id, $clean_page);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO tbl_user_pages (user_id, page_name) VALUES (?, ?)");
            $insert->bind_param("is", $user_id, $clean_page);
            $insert->execute();
        }
    }
    $message = "✅ Pages assigned successfully.";
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['uid'])) {
    $del = $conn->prepare("DELETE FROM tbl_user_pages WHERE user_id = ? AND page_name = ?");
    $del->bind_param("is", $_GET['uid'], $_GET['delete']);
    $del->execute();
    $message = "🗑️ Page deleted successfully.";
}

$users = $conn->query("SELECT id, username, role FROM users");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Manage Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #eef3fb;
            font-family: 'Segoe UI', sans-serif;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 255, 0.07);
        }
        h2, h4 {
            font-weight: 700;
        }
        .btn-primary, .btn-outline-danger {
            border-radius: 20px;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .form-check {
            transition: background 0.2s ease-in-out;
        }
        .form-check:hover {
            background: #e0eaff;
        }
        .form-label {
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card p-4">
        <h2 class="text-center text-primary mb-4">🔐 User Settings & Access Control</h2>

        <?php if (isset($message)): ?>
            <div class="alert alert-info text-center fw-bold"><?= $message ?></div>
        <?php endif; ?>

        <!-- Role Update Section -->
        <form method="POST" class="mb-5">
            <h4 class="text-primary mb-3">🔁 Update User Role</h4>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Select User</label>
                    <select name="role_user_id" class="form-select" required>
                        <option value="">-- Choose --</option>
                        <?php $users->data_seek(0); while ($user = $users->fetch_assoc()): ?>
                            <option value="<?= $user['id'] ?>"><?= $user['username'] ?> (<?= $user['role'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Select Role</label>
                    <select name="new_role" class="form-select" required>
                        <option value="">-- Choose Role --</option>
                        <option value="Admin">Admin</option>
                        <option value="User">User</option>
                    </select>
                </div>
 <div class="col-md-3 d-grid">
  <button type="submit" name="update_role"
    class="btn btn-outline-primary"
    style="padding: 2px 8px; font-size: 11px; line-height: 1; border-radius: 6px;">
    🔁 Update
  </button>
</div>





            </div>
        </form>

        <!-- Assign Pages Section -->
        <form method="POST">
            <h4 class="text-primary mb-3">🧩 Assign Pages</h4>
            <div class="mb-3">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">-- Choose --</option>
                    <?php
                    $users->data_seek(0);
                    $selected_user = $_POST['user_id'] ?? $_GET['uid'] ?? "";
                    while ($user = $users->fetch_assoc()):
                        $selected = ($user['id'] == $selected_user) ? "selected" : "";
                    ?>
                        <option value="<?= $user['id'] ?>" <?= $selected ?>><?= $user['username'] ?> (<?= $user['role'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if (!empty($selected_user)): ?>
                <div class="row">
                    <?php foreach ($pages['Main Pages'] as $page): 
                        $label = ucfirst(str_replace([".php", "_", "-"], ["", " ", " "], basename($page)));
                    ?>
                        <div class="col-md-6 mb-2">
                            <div class="form-check px-3 py-2 border rounded bg-light">
                                <input class="form-check-input" type="checkbox" name="pages[]" value="<?= $page ?>" id="<?= md5($page) ?>">
                                <label class="form-check-label" for="<?= md5($page) ?>"><?= $label ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-3">💾 Save Pages</button>
            <?php endif; ?>
        </form>

        <!-- Assigned Pages Table -->
        <?php if (!empty($selected_user)): 
            $assigned = $conn->query("SELECT * FROM tbl_user_pages WHERE user_id = $selected_user");
        ?>
            <h4 class="mt-5 text-primary">📋 Assigned Pages</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>Page</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $assigned->fetch_assoc()): ?>
                            <tr>
                                <td><?= ucfirst(str_replace([".php", "_", "-"], ["", " ", " "], basename($row['page_name']))) ?></td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-danger" href="?delete=<?= urlencode($row['page_name']) ?>&uid=<?= $selected_user ?>" onclick="return confirm('Are you sure to remove this page?');">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>