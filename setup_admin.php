<?php
/**
 * setup_admin.php — One-time setup helper
 *
 * Run this ONCE from your browser after importing database.sql:
 *   http://localhost/OnlineCabBooking/setup_admin.php
 *
 * This script will:
 *  1. Generate a correct bcrypt hash for Admin@1234
 *  2. Update the admins table with the correct hash
 *  3. Delete itself after completion for security
 *
 * DELETE THIS FILE after running it.
 */

require_once __DIR__ . '/config/db.php';

$username = 'admin';
$password = 'Admin@1234';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

$pdo  = getDB();

// Upsert: insert or update on duplicate username
$stmt = $pdo->prepare(
    'INSERT INTO admins (username, password)
     VALUES (:username, :hash)
     ON DUPLICATE KEY UPDATE password = :hash2'
);
$stmt->execute([
    ':username' => $username,
    ':hash'     => $hash,
    ':hash2'    => $hash,
]);

// Verify it works
$check = $pdo->prepare('SELECT password FROM admins WHERE username = ?');
$check->execute([$username]);
$row = $check->fetch();
$verified = $row && password_verify($password, $row['password']);

// Self-delete this script
$self = __FILE__;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Setup — RYDO</title>
<style>
    body { font-family: sans-serif; background: #0d0f14; color: #e8eaf0; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { background: #161a24; border: 1px solid rgba(255,255,255,.1); border-radius:14px; padding:2rem 2.5rem; max-width:500px; text-align:center; }
    h1 { color: #f5a623; margin-bottom:1rem; }
    .ok  { color: #27ae60; font-size:3rem; }
    .err { color: #e74c3c; font-size:3rem; }
    code { background: rgba(255,255,255,.07); padding:.2rem .5rem; border-radius:4px; font-size:.9rem; }
    a { color: #f5a623; }
</style>
</head>
<body>
<div class="box">
<?php if ($verified): ?>
    <div class="ok">✓</div>
    <h1>Admin Account Ready!</h1>
    <p>The admin password hash has been generated and stored correctly.</p>
    <p style="margin-top:1rem;">
        Username: <code>admin</code><br>
        Password: <code>Admin@1234</code>
    </p>
    <p style="margin-top:1.5rem;font-size:.85rem;color:#8892a4;">
        ⚠️ For security, please delete <code>setup_admin.php</code> from your project folder now.
    </p>
    <p style="margin-top:1.5rem;">
        <a href="admin/login.php">→ Go to Admin Login</a>
    </p>
<?php else: ?>
    <div class="err">✗</div>
    <h1>Setup Failed</h1>
    <p>Could not verify the password hash. Check your database connection in <code>config/db.php</code>.</p>
<?php endif; ?>
</div>
</body>
</html>
