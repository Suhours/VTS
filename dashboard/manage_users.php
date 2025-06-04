<?php
include '../db.php';

$success = $error = "";

// Handle registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_user'])) {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $role     = $_POST['role'];

    if (strlen($password) < 8) {
        $error = "❌ Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "❌ Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $username, $email, $hashed, $role);
        if ($stmt->execute()) {
            $success = "✅ User registered successfully!";
        } else {
            $error = "❌ Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $id = $_POST['id'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 8) {
        $error = "❌ Password must be at least 8 characters.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "❌ Passwords do not match.";
    } else {
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$hashed_pass' WHERE id = $id");
        $success = "✅ Password updated successfully.";
    }
}

// Handle dropout
if (isset($_GET['dropout'])) {
    $id = $_GET['dropout'];
    $conn->query("UPDATE users SET status='dropout' WHERE id=$id");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Fetch users
$active_users = $conn->query("SELECT * FROM users WHERE status = 'active'");
$dropout_users = $conn->query("SELECT * FROM users WHERE status = 'dropout'");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage & Register Users</title>
  <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: #f4f9ff;
        margin: 0;
        padding: 40px;
    }
    h2 {
        text-align: center;
        color: #007bff;
        margin-bottom: 20px;
    }
    .top-actions {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .btn {
        background: #007bff;
        color: white;
        padding: 10px 20px;
        border: none;
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
    }
    .btn:hover {
        background: #0056b3;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }
    th, td {
        padding: 12px;
        border-bottom: 1px solid #ccc;
        text-align: left;
    }
    th {
        background: #007bff;
        color: white;
    }
    input[type="password"] {
        width: 130px;
        padding: 6px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .modal {
        display: none;
        position: fixed;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.4);
        justify-content: center;
        align-items: center;
        z-index: 999;
    }
    .modal-content {
        background: white;
        padding: 30px;
        width: 400px;
        border-radius: 10px;
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal h3 {
        margin-top: 0;
        text-align: center;
        color: #007bff;
    }
    .modal input, .modal select {
        width: 100%;
        padding: 10px;
        margin: 10px 0 15px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .close {
        position: absolute;
        top: 10px; right: 15px;
        font-size: 20px;
        color: #333;
        cursor: pointer;
    }
    .msg {
        text-align: center;
        font-weight: bold;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .success { background: #e6ffee; color: #1a7f2b; border: 1px solid #b2e2c4; }
    .error   { background: #ffe6e6; color: #b30000; border: 1px solid #f5b2b2; }
    .action-buttons a {
        margin-right: 10px;
        text-decoration: none;
        padding: 6px 12px;
        background: #ffc107;
        color: black;
        border-radius: 5px;
    }
    .action-buttons a:hover {
        background: #e0a800;
    }
  </style>
</head>
<body>

<h2>User Management</h2>

<?php if ($success) echo "<p class='msg success'>$success</p>"; ?>
<?php if ($error) echo "<p class='msg error'>$error</p>"; ?>

<div class="top-actions">
  <button class="btn" onclick="document.getElementById('registerModal').style.display='flex'">➕ Add New User</button>
  <button class="btn" onclick="document.getElementById('dropoutModal').style.display='flex'">🚫 View Dropout Users</button>
</div>

<!-- Modal Form -->
<div class="modal" id="registerModal">
  <div class="modal-content">
    <span class="close" onclick="document.getElementById('registerModal').style.display='none'">&times;</span>
    <h3>Register New User</h3>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password (min 8 chars)" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <select name="role" required>
            <option value="">-- Select Role --</option>
            <option value="Admin">Admin</option>
            <option value="User">User</option>
        </select>
        <input type="submit" name="register_user" value="Register" class="btn">
    </form>
  </div>
</div>

<!-- Dropout Modal -->
<div class="modal" id="dropoutModal">
  <div class="modal-content">
    <span class="close" onclick="document.getElementById('dropoutModal').style.display='none'">&times;</span>
    <h3>Dropout Users</h3>
    <table>
      <tr>
        <th>ID</th><th>Username</th><th>Email</th><th>Role</th>
      </tr>
      <?php while ($d = $dropout_users->fetch_assoc()): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['username']) ?></td>
          <td><?= htmlspecialchars($d['email']) ?></td>
          <td><?= $d['role'] ?></td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>

<!-- User Table -->
<table>
  <tr>
    <th>ID</th>
    <th>Username</th>
    <th>Email</th>
    <th>Role</th>
    <th>New Password</th>
    <th>Confirm</th>
    <th>Actions</th>
  </tr>
  <?php while ($row = $active_users->fetch_assoc()): ?>
  <tr>
    <form method="POST">
      <td><?= $row['id'] ?><input type="hidden" name="id" value="<?= $row['id'] ?>"></td>
      <td><?= htmlspecialchars($row['username']) ?></td>
      <td><?= htmlspecialchars($row['email']) ?></td>
      <td><?= $row['role'] ?></td>
      <td><input type="password" name="new_password" placeholder="New Password"></td>
      <td><input type="password" name="confirm_password" placeholder="Confirm"></td>
   <td class="action-buttons" style="display: flex; gap: 10px; align-items: center;">
  <button type="submit" name="update_password" style="background:#28a745; color:white; border:none; padding:5px 10px; border-radius:4px;">
    Save
  </button>
  
  <a href="?dropout=<?= $row['id'] ?>" onclick="return confirm('Are you sure to dropout this user?')" style="color: red; font-size: 20px;">
    🚫
  </a>
</td>

    </form>
  </tr>
  <?php endwhile; ?>
</table>

<script>
  window.onclick = function(event) {
    ['registerModal', 'dropoutModal'].forEach(id => {
      const modal = document.getElementById(id);
      if (event.target == modal) modal.style.display = "none";
    });
  }
</script>

</body>
</html>
