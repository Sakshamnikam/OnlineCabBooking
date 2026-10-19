<?php
/**
 * admin/login.php — Admin Login Page
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect to dashboard if already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Successful login — regenerate session to prevent fixation
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            // Slight artificial delay to slow brute-force attempts
            sleep(1);
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — RYDO</title>
    <meta name="description" content="RYDO admin login portal. Authorized personnel only.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<div class="login-wrapper">
    <div class="card login-card">

        <div class="login-logo">⚡</div>
        <h1 class="login-title">RYDO Admin Login</h1>
        <p class="login-sub">Fleet &amp; Logistics Control Center &mdash; KITCoEK CSBS</p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="login-form">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       class="form-control" placeholder="Enter admin username"
                       autocomplete="username" maxlength="60"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       class="form-control" placeholder="Enter password"
                       autocomplete="current-password" maxlength="255" required>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:.5rem;">
                🔐 Login to Dashboard
            </button>

        </form>

        <p style="margin-top:1.5rem;font-size:.78rem;color:var(--clr-muted);text-align:center;">
            Default credentials: <code>admin</code> / <code>Admin@1234</code><br>
            <em>Change the password after first login.</em>
        </p>

        <p style="margin-top:1rem;text-align:center;">
            <a href="../index.php" style="font-size:.85rem;">← Back to Booking Page</a>
        </p>

    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
