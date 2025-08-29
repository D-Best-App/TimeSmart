<?php
require_once '../auth/db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Add CSRF token generation for secure form submissions
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $id = (int) $_POST['delete'];
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: manage_admins.php");
        exit;
    }
    // Optionally handle CSRF failure, e.g., show an error.
}

// Fetch all admins
$admins = [];
$result = $conn->query("SELECT id, username FROM admins ORDER BY id");
if ($result) {
    $admins = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins</title>
    <link rel="icon" type="image/png" href="../images/D-Best-favicon.png">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
    <link rel="apple-touch-icon" href="../images/D-Best-favicon.png">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <header>
        <img src="/images/D-Best.png" alt="Logo" class="logo">
        <h1>Manage Admins</h1>
        <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="view_punches.php">Timesheets</a>
        <a href="summary.php">Summary</a>
        <a href="reports.php">Reports</a>
        <a href="manage_users.php">Users</a>
        <a href="attendance.php">Attendance</a>
        <a href="manage_admins.php" class="active">Admins</a>
        <a href="../logout.php">Logout</a>
    </nav>
    </header>

    <div class="container">
        <div class="summary-filter">
            <div class="row">
                <div class="field">
                    <h2 style="margin-bottom: 1rem;">Admin Accounts</h2>
                </div>
                <div class="buttons">
                    <a href="add_admin.php" class="btn-reset" style="background-color: var(--primary-color); color: white;">+ Add Admin</a>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?= htmlspecialchars($admin['id']) ?></td>
                        <td><?= htmlspecialchars($admin['username']) ?></td>
                        <td>
                            <a class="btn-reset" style="background-color: var(--primary-color); color: white; margin-right: 0.5rem;" href="edit_admin.php?id=<?= $admin['id'] ?>">Edit</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
                                <input type="hidden" name="delete" value="<?= $admin['id'] ?>">
                                <button type="submit" class="btn-reset" style="background-color: #dc3545; color: white;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
