<?php
/**
 * admin/dashboard.php — RYDO Central Admin Operations Console
 * SRS Section 1.4, 3.1 & 5.5: Live monitoring, Commission analytics, Captain KYC verification
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Financial & Operations Metrics ───────────────────────────
$metrics_stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_bookings,
        SUM(service_type = 'ride')   AS total_rides,
        SUM(service_type = 'parcel') AS total_parcels,
        SUM(status = 'Completed')   AS completed_trips,
        SUM(status IN ('Accepted', 'In_Progress')) AS active_trips,
        COALESCE(SUM(fare_estimate), 0) AS gross_gmv,
        COALESCE(SUM(commission_amount), 0) AS total_commission
     FROM bookings"
);
$metrics = $metrics_stmt->fetch();

// ── Captain Stats ────────────────────────────────────────────
$cap_stats_stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_captains,
        SUM(is_online = 1) AS online_captains,
        SUM(kyc_status = 'Pending') AS pending_kyc
     FROM captains"
);
$cap_stats = $cap_stats_stmt->fetch();

// ── Fetch Captains for KYC Queue ─────────────────────────────
$captains_stmt = $pdo->query("SELECT * FROM captains ORDER BY FIELD(kyc_status, 'Pending', 'Approved', 'Rejected'), created_at DESC");
$captains = $captains_stmt->fetchAll();

// ── Fetch Bookings with Filters ──────────────────────────────
$filter_service = $_GET['service'] ?? '';
$filter_status  = $_GET['status']  ?? '';

$where_clauses = [];
$params = [];

if ($filter_service && in_array($filter_service, ['ride', 'parcel'], true)) {
    $where_clauses[] = 'b.service_type = ?';
    $params[] = $filter_service;
}
if ($filter_status && in_array($filter_status, ['Pending', 'Accepted', 'In_Progress', 'Completed', 'Cancelled'], true)) {
    $where_clauses[] = 'b.status = ?';
    $params[] = $filter_status;
}

$sql = "SELECT b.*, c.name AS captain_name, c.vehicle_number
        FROM bookings b
        LEFT JOIN captains c ON b.captain_id = c.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY b.booked_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$allowed_statuses = ['Pending', 'Accepted', 'In_Progress', 'Completed', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RYDO Admin Console — Fleet &amp; Operations</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<nav class="admin-navbar">
    <div class="container">
        <div style="display:flex; align-items:center; gap:0.6rem;">
            <span class="brand-icon" style="width:34px; height:34px; font-size:1.15rem;">⚡</span>
            <div>
                <span style="font-weight:800; font-size:1.15rem; color:#fff;">RYDO Admin Portal</span>
                <span class="college-badge" style="margin-left:0.5rem;">KITCoEK CSBS</span>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:1rem;">
            <span style="font-size:0.85rem; color:var(--clr-muted);">
                👤 <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
            </span>
            <a href="../index.php" class="btn btn-secondary btn-sm">🏠 Rider App</a>
            <a href="../captain/dashboard.php" class="btn btn-secondary btn-sm">🛵 Captain App</a>
            <a href="logout.php" class="btn btn-danger btn-sm">⏻ Logout</a>
        </div>
    </div>
</nav>

<div class="admin-page">
    <div class="container">

        <h1 class="admin-heading">Operations &amp; Platform Analytics</h1>
        <p class="admin-subheading">Live ride monitoring, commission earnings &amp; micro-mobility partner compliance.</p>

        <?php if ($flash_success): ?>
            <div class="alert alert-success" style="background:rgba(0,230,118,0.15); border:1px solid rgba(0,230,118,0.3); color:#00e676; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1.2rem;">
                <?= htmlspecialchars($flash_success) ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-error" style="background:rgba(255,51,102,0.15); border:1px solid rgba(255,51,102,0.3); color:#ff3366; padding:0.8rem 1.2rem; border-radius:8px; margin-bottom:1.2rem;">
                <?= htmlspecialchars($flash_error) ?>
            </div>
        <?php endif; ?>

        <!-- OPERATIONS & REVENUE METRICS (SRS Section 1.4 & 5.5) -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon total">📈</div>
                <div>
                    <div class="stat-value" style="color:var(--clr-primary);">₹<?= number_format((float)$metrics['total_commission'], 2) ?></div>
                    <div class="stat-label">RYDO Platform Commission (20%)</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon confirmed">💳</div>
                <div>
                    <div class="stat-value">₹<?= number_format((float)$metrics['gross_gmv'], 2) ?></div>
                    <div class="stat-label">Gross Ride GMV</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">⚡</div>
                <div>
                    <div class="stat-value" style="color:#00e5ff;"><?= (int)$metrics['active_trips'] ?></div>
                    <div class="stat-label">Active Ongoing Trips</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed">🛵</div>
                <div>
                    <div class="stat-value" style="color:var(--clr-success);"><?= (int)$cap_stats['online_captains'] ?> / <?= (int)$cap_stats['total_captains'] ?></div>
                    <div class="stat-label">Online Active Captains</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon cancelled">📋</div>
                <div>
                    <div class="stat-value" style="color:#ff9100;"><?= (int)$cap_stats['pending_kyc'] ?></div>
                    <div class="stat-label">Pending Captain KYCs</div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: CAPTAIN KYC VERIFICATION QUEUE (SRS Section 5.5) -->
        <div id="captains-section" style="margin: 2.5rem 0 3rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <div>
                    <h2 style="font-size:1.3rem; font-weight:800; color:#fff;">🛡️ Captain KYC &amp; Fleet Compliance Queue</h2>
                    <p style="font-size:0.82rem; color:var(--clr-muted);">Review and approve Captain Driving Licenses &amp; Vehicle RC documents.</p>
                </div>
            </div>

            <div class="card" style="padding:0; overflow:hidden;">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Captain Name</th>
                                <th>Contact</th>
                                <th>Vehicle Type</th>
                                <th>Vehicle Plate</th>
                                <th>License Number</th>
                                <th>Status</th>
                                <th>Duty</th>
                                <th>KYC Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($captains as $c): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td><?= htmlspecialchars($c['vehicle_type']) ?></td>
                                <td><code><?= htmlspecialchars($c['vehicle_number']) ?></code></td>
                                <td><code><?= htmlspecialchars($c['license_number']) ?></code></td>
                                <td>
                                    <span class="badge badge-<?= ($c['kyc_status'] === 'Approved') ? 'completed' : (($c['kyc_status'] === 'Rejected') ? 'cancelled' : 'pending') ?>">
                                        <?= htmlspecialchars($c['kyc_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $c['is_online'] ? '<span style="color:var(--clr-success); font-weight:700;">🟢 Online</span>' : '<span style="color:var(--clr-muted);">⚪ Offline</span>' ?>
                                </td>
                                <td>
                                    <form method="POST" action="update_status.php" style="display:inline-flex; gap:0.4rem;">
                                        <input type="hidden" name="update_kyc" value="1">
                                        <input type="hidden" name="captain_id" value="<?= $c['id'] ?>">
                                        <?php if ($c['kyc_status'] !== 'Approved'): ?>
                                            <button type="submit" name="new_kyc" value="Approved" class="btn btn-success btn-sm">Approve ✓</button>
                                        <?php endif; ?>
                                        <?php if ($c['kyc_status'] !== 'Rejected'): ?>
                                            <button type="submit" name="new_kyc" value="Rejected" class="btn btn-danger btn-sm">Reject ✕</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 2: LIVE BOOKINGS & PARCEL MONITORING -->
        <div style="margin: 2.5rem 0 1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.2rem;">
                <div>
                    <h2 style="font-size:1.3rem; font-weight:800; color:#fff;">📋 All Trips &amp; RYDO Parcels (<?= count($bookings) ?>)</h2>
                    <p style="font-size:0.82rem; color:var(--clr-muted);">Real-time monitoring across Bike Taxis, Cabs and Logistics.</p>
                </div>

                <div class="filter-bar" style="margin:0;">
                    <form method="GET" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <select name="service" onchange="this.form.submit()" class="form-control" style="width:auto; padding:0.4rem 0.8rem;">
                            <option value="">All Services</option>
                            <option value="ride" <?= $filter_service === 'ride' ? 'selected' : '' ?>>🛵 Rides Only</option>
                            <option value="parcel" <?= $filter_service === 'parcel' ? 'selected' : '' ?>>📦 RYDO Parcels Only</option>
                        </select>

                        <select name="status" onchange="this.form.submit()" class="form-control" style="width:auto; padding:0.4rem 0.8rem;">
                            <option value="">All Statuses</option>
                            <?php foreach ($allowed_statuses as $st): ?>
                                <option value="<?= $st ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($filter_service || $filter_status): ?>
                            <a href="dashboard.php" class="btn btn-secondary btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card" style="padding:0; overflow:hidden;">
                <div class="table-wrap">
                    <table class="data-table" id="bookings-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Service</th>
                                <th>User</th>
                                <th>Pickup → Drop</th>
                                <th>Distance</th>
                                <th>Fare</th>
                                <th>Comm (20%)</th>
                                <th>PIN</th>
                                <th>Assigned Captain</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="11" style="text-align:center; padding:3rem; color:var(--clr-muted);">
                                        No bookings found matching current filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>
                                        <a href="../confirmation.php?bid=<?= urlencode($b['booking_id']) ?>" style="font-family:monospace; font-weight:700; color:var(--clr-primary);">
                                            <?= htmlspecialchars($b['booking_id']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?= ($b['service_type'] === 'parcel') ? '📦 Parcel' : '🛵 ' . htmlspecialchars($b['vehicle_type']) ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($b['passenger_name']) ?></strong><br>
                                        <small style="color:var(--clr-muted);"><?= htmlspecialchars($b['phone']) ?></small>
                                    </td>
                                    <td>
                                        <div style="font-size:0.82rem;">
                                            🟢 <?= htmlspecialchars($b['pickup']) ?><br>
                                            🔴 <?= htmlspecialchars($b['destination']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($b['distance_km']) ?> km</td>
                                    <td style="color:var(--clr-primary); font-weight:700;">
                                        ₹<?= number_format((float)$b['fare_estimate'], 2) ?>
                                    </td>
                                    <td style="color:var(--clr-success); font-weight:600;">
                                        ₹<?= number_format((float)$b['commission_amount'], 2) ?>
                                    </td>
                                    <td>
                                        <code style="background:rgba(255,170,0,0.1); color:var(--clr-primary); padding:0.2rem 0.4rem; border-radius:4px;">
                                            <?= htmlspecialchars($b['otp_pin']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($b['captain_name'] ?? 'Unassigned') ?><br>
                                        <small style="color:var(--clr-muted);"><?= htmlspecialchars($b['vehicle_number'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($b['status']) ?>">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="update_status.php" style="display:flex; gap:0.3rem;">
                                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($b['booking_id']) ?>">
                                            <select name="new_status" class="status-select" style="padding:0.25rem 0.4rem;">
                                                <?php foreach ($allowed_statuses as $st): ?>
                                                    <option value="<?= $st ?>" <?= ($b['status'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm" style="padding:0.25rem 0.5rem;">Update</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
