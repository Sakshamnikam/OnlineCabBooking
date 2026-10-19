<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/db.php';

// Already authenticated? Send the visitor to the appropriate page.
if (!empty($_SESSION['admin_id'])) { header('Location: admin/dashboard.php'); exit; }

if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
$error = '';
$email = '';
$role = $_POST['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $email = $role === 'user' ? strtolower($identity) : $identity;
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['auth_csrf'], $token)) {
        $error = 'Session expired. Refresh the page and try again.';
    } elseif ($identity === '' || $password === '') {
        $error = 'Enter your ' . ($role === 'admin' ? 'username' : 'email') . ' and password.';
    } elseif ($role === 'user' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            $pdo = getDB();
            if ($role === 'admin') {
                $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');
                $stmt->execute([$identity]);
                $account = $stmt->fetch();
                if ($account && password_verify($password, $account['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$account['id'];
                    $_SESSION['admin_username'] = $account['username'];
                    unset($_SESSION['auth_csrf']);
                    header('Location: admin/dashboard.php'); exit;
                }
            } else {
                $stmt = $pdo->prepare('SELECT id, full_name, email, password FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $account = $stmt->fetch();
                if ($account && password_verify($password, $account['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$account['id'];
                    $_SESSION['user_name'] = $account['full_name'];
                    $_SESSION['user_email'] = $account['email'];
                    unset($_SESSION['auth_csrf']);
                    header('Location: index.php'); exit;
                }
            }
            $error = 'Invalid ' . ($role === 'admin' ? 'username' : 'email') . ' or password.';
        } catch (PDOException $e) {
            error_log('RYDO login error: ' . $e->getMessage());
            $error = 'Could not access the account database. Check that MySQL is running and the users table exists.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | RYDO</title><link rel="stylesheet" href="assets/css/style.css">
<style>
.auth-page{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at top,rgba(255,170,0,.12),transparent 55%),var(--clr-bg)}
.auth-card{width:100%;max-width:470px;padding:clamp(24px,5vw,40px);background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);box-shadow:var(--shadow)}
.auth-brand{text-align:center;font-size:1.6rem;font-weight:800;margin-bottom:18px}.auth-brand span{color:var(--clr-primary)}
.auth-card h1{text-align:center;margin-bottom:8px}.auth-sub{text-align:center;color:var(--clr-muted);margin-bottom:24px}
.auth-card label{display:block;margin:0 0 7px;font-weight:600}.auth-card .form-control{width:100%;margin-bottom:16px}
.auth-card .btn-primary{width:100%;display:block;cursor:pointer}.auth-error{padding:12px;margin-bottom:16px;border:1px solid rgba(255,51,102,.4);border-radius:8px;color:#ff8ba8;background:rgba(255,51,102,.09)}
.auth-foot{text-align:center;margin-top:20px;color:var(--clr-muted);font-size:.9rem}.auth-switch{display:flex;gap:8px;margin-bottom:20px}.auth-switch label{flex:1;text-align:center;padding:11px;border:1px solid var(--clr-border);border-radius:9px;cursor:pointer;margin:0}.auth-switch input{margin-right:6px}
</style></head><body><main class="auth-page"><section class="auth-card">
<div class="auth-brand">⚡ <span>RYDO</span></div><h1>Welcome back</h1><p class="auth-sub">Log in to continue with your rides.</p>
<?php if ($error !== ''): ?><div class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" action="login.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['auth_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<div class="auth-switch"><label><input type="radio" name="role" value="user" <?= $role === 'user' ? 'checked' : '' ?> onchange="updateIdentity()"> Customer</label><label><input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?> onchange="updateIdentity()"> Admin</label></div>
<label id="identity-label" for="identity"><?= $role === 'admin' ? 'Admin username' : 'Email address' ?></label>
<input class="form-control" id="identity" name="identity" type="<?= $role === 'admin' ? 'text' : 'email' ?>" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $role === 'admin' ? 'Enter admin username' : 'you@example.com' ?>" autocomplete="username" required>
<label for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
<button class="btn btn-primary" type="submit">Log In</button></form>
<p class="auth-foot">Don't have a customer account? <a href="signin.php">Sign in / Create account</a></p>
</section></main><script>function updateIdentity(){const admin=document.querySelector('input[name="role"]:checked').value==='admin';const i=document.getElementById('identity');document.getElementById('identity-label').textContent=admin?'Admin username':'Email address';i.type=admin?'text':'email';i.placeholder=admin?'Enter admin username':'you@example.com';i.value='';}</script></body></html>
