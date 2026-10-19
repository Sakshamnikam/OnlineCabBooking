<?php
/**
 * captain/dashboard.php — RYDO Captain (Driver) Partner Portal
 * SRS Section 1.4 & 3.1: Online/Offline toggle, OTP verification, trip execution & earnings
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

// Default to Captain #1 (Vikram Patil) or allow switcher
$captain_id = intval($_GET['cid'] ?? 1);

// Handle Online / Offline toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_online'])) {
    $new_status = intval($_POST['is_online'] ?? 0);
    $upd = $pdo->prepare('UPDATE captains SET is_online = ? WHERE id = ?');
    $upd->execute([$new_status, $captain_id]);
    $_SESSION['cap_flash'] = "Status updated to: " . ($new_status ? "🟢 Online & Receiving Trips" : "⚪ Offline");
    header("Location: dashboard.php?cid={$captain_id}");
    exit;
}

// Handle OTP Verification & Start Trip (SRS Section 4.2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp_start'])) {
    $booking_id = trim($_POST['booking_id'] ?? '');
    $input_otp  = trim($_POST['otp_pin'] ?? '');

    $chk = $pdo->prepare('SELECT id, otp_pin, status FROM bookings WHERE booking_id = ?');
    $chk->execute([$booking_id]);
    $booking = $chk->fetch();

    if ($booking && $booking['otp_pin'] === $input_otp) {
        $st = $pdo->prepare("UPDATE bookings SET status = 'In_Progress', captain_id = ? WHERE booking_id = ?");
        $st->execute([$captain_id, $booking_id]);
        $_SESSION['cap_flash'] = "✅ PIN Verified! Trip started successfully.";
    } else {
        $_SESSION['cap_error'] = "❌ Invalid 4-Digit PIN. Please ask the passenger for the correct code.";
    }
    header("Location: dashboard.php?cid={$captain_id}");
    exit;
}

// Handle Trip Completion (SRS Section 4.4 & 5.5)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_trip'])) {
    $booking_id = trim($_POST['booking_id'] ?? '');
    
    // Fetch booking details for earnings calculation
    $b_stmt = $pdo->prepare('SELECT captain_earning FROM bookings WHERE booking_id = ?');
    $b_stmt->execute([$booking_id]);
    $b_data = $b_stmt->fetch();
    $earning = $b_data ? floatval($b_data['captain_earning']) : 0.00;

    $st = $pdo->prepare("UPDATE bookings SET status = 'Completed', captain_id = ? WHERE booking_id = ?");
    $st->execute([$captain_id, $booking_id]);

    // Update Captain cumulative earnings
    $upd_earn = $pdo->prepare("UPDATE captains SET total_earnings = total_earnings + ? WHERE id = ?");
    $upd_earn->execute([$earning, $captain_id]);

    $_SESSION['cap_flash'] = "🏁 Trip completed! ₹{$earning} credited to your Captain wallet (80% net payout).";
    header("Location: dashboard.php?cid={$captain_id}");
    exit;
}

// Fetch Captain profile
$cap_stmt = $pdo->prepare('SELECT * FROM captains WHERE id = ?');
$cap_stmt->execute([$captain_id]);
$captain = $cap_stmt->fetch();

if (!$captain) {
    // Fallback to first available captain
    $cap_stmt = $pdo->query('SELECT * FROM captains ORDER BY id ASC LIMIT 1');
    $captain = $cap_stmt->fetch();
    $captain_id = $captain ? $captain['id'] : 1;
}

// Fetch active & pending bookings for this captain or general pool
$active_stmt = $pdo->prepare(
    "SELECT * FROM bookings
     WHERE (captain_id = :cid OR (captain_id IS NULL AND status IN ('Pending', 'Accepted')))
       AND status IN ('Pending', 'Accepted', 'In_Progress')
     ORDER BY booked_at DESC"
);
$active_stmt->execute([':cid' => $captain_id]);
$active_trips = $active_stmt->fetchAll();

// Fetch completed trips for this captain
$comp_stmt = $pdo->prepare(
    "SELECT * FROM bookings WHERE captain_id = ? AND status = 'Completed' ORDER BY updated_at DESC LIMIT 5"
);
$comp_stmt->execute([$captain_id]);
$completed_trips = $comp_stmt->fetchAll();

$flash = $_SESSION['cap_flash'] ?? '';
$error = $_SESSION['cap_error'] ?? '';
unset($_SESSION['cap_flash'], $_SESSION['cap_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captain Partner Dashboard — RYDO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<nav class="admin-navbar">
    <div class="container">
        <div style="display:flex; align-items:center; gap:0.6rem;">
            <span class="brand-icon" style="width:32px; height:32px; font-size:1.1rem;">🛵</span>
            <span style="font-weight:800; font-size:1.2rem; color:#fff;">RYDO Captain Partner</span>
        </div>
        <div style="display:flex; align-items:center; gap:1rem;">
            <a href="../index.php" class="btn btn-secondary btn-sm">🏠 Rider App</a>
            <a href="../admin/login.php" class="btn btn-secondary btn-sm">🔒 Admin Panel</a>
        </div>
    </div>
</nav>

<div class="admin-page">
    <div class="container">

        <?php if ($flash): ?>
            <div class="alert alert-success" style="background:rgba(0,230,118,0.15); border:1px solid rgba(0,230,118,0.3); color:#00e676; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1.2rem;">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="background:rgba(255,51,102,0.15); border:1px solid rgba(255,51,102,0.3); color:#ff3366; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1.2rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- CAPTAIN PROFILE & DUTY TOGGLE CARD -->
        <div class="card" style="margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.2rem;">
            <div style="display:flex; align-items:center; gap:1rem;">
                <div style="width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg, var(--clr-primary), #ff6d00); color:#000; font-size:1.6rem; display:flex; align-items:center; justify-content:center; font-weight:800;">
                    <?= strtoupper(substr($captain['name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 style="font-size:1.2rem; font-weight:800; color:#fff;"><?= htmlspecialchars($captain['name']) ?></h2>
                    <p style="font-size:0.82rem; color:var(--clr-muted);">
                        Vehicle: <strong><?= htmlspecialchars($captain['vehicle_number']) ?></strong> (<?= htmlspecialchars($captain['vehicle_type']) ?>) •
                        Lic: <code><?= htmlspecialchars($captain['license_number']) ?></code>
                    </p>
                    <div style="margin-top:0.3rem;">
                        <span class="badge badge-<?= ($captain['kyc_status'] === 'Approved') ? 'completed' : 'pending' ?>">
                            KYC: <?= htmlspecialchars($captain['kyc_status']) ?>
                        </span>
                        <span style="font-size:0.82rem; color:#ffc44d; font-weight:600; margin-left:0.5rem;">
                            ⭐ <?= htmlspecialchars($captain['rating']) ?> Rating
                        </span>
                    </div>
                </div>
            </div>

            <!-- DUTY TOGGLE FORM (SRS Section 3.1) -->
            <form method="POST" style="display:flex; align-items:center; gap:0.8rem;">
                <input type="hidden" name="toggle_online" value="1">
                <input type="hidden" name="is_online" value="<?= $captain['is_online'] ? '0' : '1' ?>">
                <button type="submit" class="btn <?= $captain['is_online'] ? 'btn-success' : 'btn-secondary' ?>" style="padding:0.6rem 1.4rem;">
                    <?= $captain['is_online'] ? '🟢 ON DUTY (Online)' : '⚪ OFF DUTY (Offline)' ?>
                </button>
            </form>
        </div>

        <!-- EARNINGS STATS (SRS Section 1.4 & 5.5) -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon total">💰</div>
                <div>
                    <div class="stat-value" style="color:var(--clr-primary);">₹<?= number_format((float)$captain['total_earnings'], 2) ?></div>
                    <div class="stat-label">Total Earnings (80% Net)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon confirmed">🛵</div>
                <div>
                    <div class="stat-value"><?= count($completed_trips) ?></div>
                    <div class="stat-label">Recent Completed Trips</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">⚡</div>
                <div>
                    <div class="stat-value" style="color:#00e5ff;"><?= count($active_trips) ?></div>
                    <div class="stat-label">Active / Incoming Requests</div>
                </div>
            </div>
        </div>

        <!-- ACTIVE & INCOMING TRIPS -->
        <h2 style="font-size:1.3rem; font-weight:800; margin: 2rem 0 1rem; color:#fff;">
            ⚡ Active Trips &amp; Assigned Requests
        </h2>

        <?php if (empty($active_trips)): ?>
            <div class="card" style="text-align:center; padding:3rem; color:var(--clr-muted);">
                <div style="font-size:2.5rem; margin-bottom:0.8rem;">🛵</div>
                <p>No active ride requests at this moment. Stay online to receive incoming trips!</p>
            </div>
        <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.2rem;">
                <?php foreach ($active_trips as $t): ?>
                    <div class="card" style="border:1.5px solid rgba(255,170,0,0.3); background:rgba(20,25,35,0.7);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.8rem;">
                            <span style="font-size:0.82rem; font-weight:700; color:var(--clr-primary);">
                                <?= ($t['service_type'] === 'parcel') ? '📦 RYDO PARCEL' : '🛵 ' . strtoupper($t['vehicle_type']) ?>
                            </span>
                            <span class="badge badge-<?= strtolower($t['status']) ?>">
                                <?= htmlspecialchars($t['status']) ?>
                            </span>
                        </div>

                        <h3 style="font-size:1.15rem; font-weight:800; margin-bottom:0.4rem; color:#fff;">
                            <?= htmlspecialchars($t['passenger_name']) ?>
                        </h3>
                        <p style="font-size:0.85rem; color:var(--clr-muted); margin-bottom:0.8rem;">
                            📱 <?= htmlspecialchars($t['phone']) ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($t['distance_km']) ?> km
                        </p>

                        <div style="background:rgba(255,255,255,0.03); padding:0.8rem; border-radius:8px; margin-bottom:1rem; font-size:0.85rem;">
                            <div style="margin-bottom:0.3rem;">🟢 <strong>Pickup:</strong> <?= htmlspecialchars($t['pickup']) ?></div>
                            <div>🔴 <strong>Drop:</strong> <?= htmlspecialchars($t['destination']) ?></div>
                            <?php if ($t['service_type'] === 'parcel'): ?>
                                <div style="margin-top:0.4rem; color:var(--clr-accent);">
                                    📦 <strong>Recipient:</strong> <?= htmlspecialchars($t['receiver_name']) ?> (<?= htmlspecialchars($t['receiver_phone']) ?>)
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <span style="font-size:0.82rem; color:var(--clr-muted);">Captain Net Payout (80%):</span>
                            <strong style="color:var(--clr-success); font-size:1.1rem;">₹<?= number_format((float)$t['captain_earning'], 2) ?></strong>
                        </div>

                        <!-- OTP VERIFICATION TO START TRIP (SRS Section 4.2) -->
                        <?php if ($t['status'] === 'Accepted' || $t['status'] === 'Pending'): ?>
                            <form method="POST" style="background:rgba(255,170,0,0.06); border:1px dashed var(--clr-primary); border-radius:8px; padding:0.8rem; margin-top:0.6rem;">
                                <input type="hidden" name="verify_otp_start" value="1">
                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($t['booking_id']) ?>">
                                <label style="font-size:0.75rem; color:var(--clr-primary); margin-bottom:0.4rem;">
                                    🔒 Enter Passenger's 4-Digit PIN to Start:
                                </label>
                                <div style="display:flex; gap:0.5rem;">
                                    <input type="text" name="otp_pin" class="form-control" placeholder="4-digit PIN" maxlength="4" style="text-align:center; letter-spacing:0.2em; font-weight:800; padding:0.4rem;" required>
                                    <button type="submit" class="btn btn-primary btn-sm" style="width:auto; white-space:nowrap;">
                                        ⚡ Start Trip
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($t['status'] === 'In_Progress'): ?>
                            <form method="POST" style="margin-top:0.6rem;">
                                <input type="hidden" name="complete_trip" value="1">
                                <input type="hidden" name="booking_id" value="<?= htmlspecialchars($t['booking_id']) ?>">
                                <button type="submit" class="btn btn-success" style="width:100%;">
                                    🏁 Complete Trip / Confirm Delivery
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
