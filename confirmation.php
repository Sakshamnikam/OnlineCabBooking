<?php
/**
 * confirmation.php — RYDO Ride & Parcel Tracking / Confirmation
 * Displays 4-Digit Security PIN, assigned Captain details, Live GPS Simulation & SOS button
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';

$bid = trim($_GET['bid'] ?? '');
if (empty($bid)) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();

// Handle rating submission if completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_trip'])) {
    $stars = intval($_POST['rating'] ?? 5);
    $feedback = trim($_POST['feedback'] ?? '');
    $upd = $pdo->prepare('UPDATE bookings SET rating = ?, feedback = ? WHERE booking_id = ?');
    $upd->execute([$stars, $feedback, $bid]);
    header('Location: confirmation.php?bid=' . urlencode($bid) . '&rated=1');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, c.name AS captain_name, c.phone AS captain_phone,
            c.vehicle_number, c.rating AS captain_rating
     FROM bookings b
     LEFT JOIN captains c ON b.captain_id = c.id
     WHERE b.booking_id = ? LIMIT 1"
);
$stmt->execute([$bid]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: index.php');
    exit;
}

$is_parcel = ($booking['service_type'] === 'parcel');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Confirmed — <?= htmlspecialchars($booking['booking_id']) ?> | RYDO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-icon">⚡</span>
            <span>RYDO</span>
            <span class="college-badge">KITCoEK • CSBS</span>
        </a>
        <ul class="navbar-links">
            <li><a href="index.php">← New Booking</a></li>
            <li><a href="captain/dashboard.php">Captain Portal 🛵</a></li>
        </ul>
    </div>
</nav>

<section style="padding: 3rem 0 5rem;">
    <div class="container">
        <div class="card" style="max-width: 720px; margin: 0 auto;">

            <!-- HEADER & BADGES -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-bottom:1px solid var(--clr-border); padding-bottom:1.2rem;">
                <div>
                    <span style="font-size:0.8rem; color:var(--clr-muted); text-transform:uppercase; font-weight:700;">
                        <?= $is_parcel ? '📦 RYDO Intra-City Parcel' : '🛵 RYDO Ride' ?>
                    </span>
                    <h1 style="font-size:1.6rem; font-weight:800; color:#fff;">
                        <?= htmlspecialchars($booking['booking_id']) ?>
                    </h1>
                </div>
                <div style="display:flex; align-items:center; gap:0.6rem;">
                    <span class="badge badge-<?= strtolower($booking['status']) ?>">
                        ● <?= htmlspecialchars($booking['status']) ?>
                    </span>
                    <!-- SOS Safety Button (SRS 4.3) -->
                    <button type="button" class="sos-btn" onclick="triggerSOS()">
                        🚨 SOS Alert
                    </button>
                </div>
            </div>

            <!-- 4-DIGIT SECURITY PIN (SRS Section 4.2) -->
            <div class="otp-display-box">
                <span style="font-size:0.82rem; font-weight:700; color:var(--clr-muted); text-transform:uppercase; letter-spacing:0.05em;">
                    🔒 Share this 4-Digit Security PIN with Captain
                </span>
                <div class="otp-code"><?= htmlspecialchars($booking['otp_pin']) ?></div>
                <p style="font-size:0.78rem; color:#ffc44d;">
                    Required by Captain before starting the ride or picking up the parcel.
                </p>
            </div>

            <!-- SIMULATED LIVE GPS TRACKER (SRS Section 4.3) -->
            <div class="gps-tracker-card">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem;">
                    <span>📍 <strong>Pickup:</strong> <?= htmlspecialchars($booking['pickup']) ?></span>
                    <span>🏁 <strong>Drop:</strong> <?= htmlspecialchars($booking['destination']) ?></span>
                </div>
                <div class="gps-route-line">
                    <div class="gps-point active" title="Pickup Location"></div>
                    <div class="gps-bike-marker">
                        <?= ($booking['vehicle_type'] === 'Cab') ? '🚗' : (($booking['vehicle_type'] === 'Auto') ? '🛺' : '🛵') ?>
                    </div>
                    <div class="gps-point" title="Drop Location"></div>
                </div>
                <p style="font-size:0.75rem; color:var(--clr-accent); text-align:center; margin-top:0.4rem;">
                    ⚡ Live GPS broadcast active • Captain arriving in ~<?= max(2, round($booking['distance_km'] * 1.5)) ?> mins
                </p>
            </div>

            <!-- ASSIGNED CAPTAIN CARD -->
            <div style="background:var(--clr-surface-2); border:1px solid var(--clr-border); border-radius:12px; padding:1.2rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div style="display:flex; align-items:center; gap:0.9rem;">
                    <div style="width:48px; height:48px; border-radius:50%; background:var(--clr-primary); color:#000; font-size:1.4rem; display:flex; align-items:center; justify-content:center; font-weight:800;">
                        <?= strtoupper(substr($booking['captain_name'] ?? 'C', 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700; font-size:1rem; color:#fff;">
                            <?= htmlspecialchars($booking['captain_name'] ?? 'Matching Nearby Captain…') ?>
                        </div>
                        <div style="font-size:0.82rem; color:var(--clr-muted);">
                            <?= htmlspecialchars($booking['vehicle_number'] ?? 'Assigned soon') ?> • ⭐ <?= htmlspecialchars($booking['captain_rating'] ?? '4.8') ?>
                        </div>
                    </div>
                </div>
                <div>
                    <a href="tel:<?= htmlspecialchars($booking['captain_phone'] ?? '9822000000') ?>" class="btn btn-secondary btn-sm">
                        📞 Call Captain
                    </a>
                </div>
            </div>

            <!-- TRIP DETAILS TABLE -->
            <table style="width:100%; border-collapse:collapse; font-size:0.9rem; margin-bottom:1.5rem;">
                <tr style="border-bottom:1px solid var(--clr-border);">
                    <td style="padding:0.7rem 0; color:var(--clr-muted);">Passenger / Sender</td>
                    <td style="padding:0.7rem 0; font-weight:600; text-align:right;">
                        <?= htmlspecialchars($booking['passenger_name']) ?> (<?= htmlspecialchars($booking['phone']) ?>)
                    </td>
                </tr>
                <?php if ($is_parcel): ?>
                <tr style="border-bottom:1px solid var(--clr-border);">
                    <td style="padding:0.7rem 0; color:var(--clr-accent);">Parcel Recipient</td>
                    <td style="padding:0.7rem 0; font-weight:600; text-align:right;">
                        <?= htmlspecialchars($booking['receiver_name']) ?> (<?= htmlspecialchars($booking['receiver_phone']) ?>)<br>
                        <small style="color:var(--clr-muted);"><?= htmlspecialchars($booking['receiver_address']) ?> | <?= htmlspecialchars($booking['parcel_weight']) ?> kg</small>
                    </td>
                </tr>
                <?php endif; ?>
                <tr style="border-bottom:1px solid var(--clr-border);">
                    <td style="padding:0.7rem 0; color:var(--clr-muted);">Distance</td>
                    <td style="padding:0.7rem 0; font-weight:600; text-align:right;">
                        <?= htmlspecialchars($booking['distance_km']) ?> km
                    </td>
                </tr>
                <tr style="border-bottom:1px solid var(--clr-border);">
                    <td style="padding:0.7rem 0; color:var(--clr-muted);">Payment Mode</td>
                    <td style="padding:0.7rem 0; font-weight:600; text-align:right;">
                        <?= htmlspecialchars($booking['payment_method']) ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0.8rem 0; font-weight:700; font-size:1.05rem;">Estimated Fare</td>
                    <td style="padding:0.8rem 0; font-weight:800; font-size:1.3rem; color:var(--clr-primary); text-align:right;">
                        ₹<?= number_format((float)$booking['fare_estimate'], 2) ?>
                    </td>
                </tr>
            </table>

            <!-- POST TRIP RATING (SRS Section 2.2) -->
            <?php if ($booking['status'] === 'Completed'): ?>
                <div style="background:rgba(0,230,118,0.08); border:1px solid rgba(0,230,118,0.3); border-radius:12px; padding:1.2rem; margin-bottom:1.5rem;">
                    <h3 style="font-size:1rem; font-weight:700; color:var(--clr-success); margin-bottom:0.5rem;">
                        Trip Completed! How was your Captain?
                    </h3>
                    <?php if (!empty($booking['rating'])): ?>
                        <p style="font-size:0.88rem;">
                            Your Rating: <strong>⭐ <?= (int)$booking['rating'] ?>/5</strong><br>
                            <em>"<?= htmlspecialchars($booking['feedback'] ?? 'Great service!') ?>"</em>
                        </p>
                    <?php else: ?>
                        <form method="POST">
                            <div style="display:flex; gap:0.5rem; margin-bottom:0.8rem; align-items:center;">
                                <label style="margin:0; font-size:0.85rem;">Rating:</label>
                                <select name="rating" class="form-control" style="width:auto; padding:0.3rem 0.6rem;">
                                    <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                                    <option value="4">⭐⭐⭐⭐ (4 - Very Good)</option>
                                    <option value="3">⭐⭐⭐ (3 - Average)</option>
                                </select>
                            </div>
                            <input type="text" name="feedback" class="form-control" placeholder="Optional comments..." style="margin-bottom:0.8rem;">
                            <button type="submit" name="rate_trip" class="btn btn-success btn-sm">Submit Review</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ACTION BUTTONS -->
            <div style="display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
                <a href="index.php" class="btn btn-secondary">← Book Another</a>
                <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Receipt</button>
                <a href="captain/dashboard.php?sim_id=<?= urlencode($booking['booking_id']) ?>" class="btn btn-primary" style="width:auto;">
                    🛵 View in Captain App
                </a>
            </div>

        </div>
    </div>
</section>

<!-- EMERGENCY SOS POPUP SCRIPT -->
<script>
function triggerSOS() {
    const alertMsg = "🚨 RYDO EMERGENCY SOS ACTIVATED!\n\n" +
                     "• Immediate notification sent to Local Law Enforcement (Police 112)\n" +
                     "• Live GPS coordinates dispatched to RYDO 24x7 Safety Response Team\n" +
                     "• Booking ID: <?= htmlspecialchars($booking['booking_id']) ?>\n" +
                     "• Emergency contact alerted with live tracking URL.\n\n" +
                     "Please stay safe. A security representative will call you immediately.";
    alert(alertMsg);
}
</script>

<script src="assets/js/main.js"></script>
</body>
</html>
