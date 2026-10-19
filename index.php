<?php
/**
 * index.php — RYDO Rider & Logistics Booking Portal
 * Mini-Project-I (UCBIL0371) | Dept. of CSBS, KITCoEK
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Require a logged-in customer before showing the booking page.
// Without this, index.php was reachable directly and nothing ever
// sent a first-time visitor to login.php.
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$fares = require __DIR__ . '/config/fares.php';

$locations = [
    'KIT College of Engg',
    'Central Bus Stand',
    'Railway Station',
    'Mahalaxmi Temple',
    'Rankala Lake',
    'DYP City Mall',
    'Shivaji University',
    'CPR Hospital',
    'Shiroli MIDC',
    'Airport'
];

$error   = $_SESSION['booking_error']   ?? '';
$success = $_SESSION['booking_success'] ?? '';
unset($_SESSION['booking_error'], $_SESSION['booking_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RYDO — On-Demand Bike Taxi, Cab &amp; Parcel Delivery</title>
    <meta name="description" content="RYDO: Safe, convenient, and affordable Bike Taxi and intra-city parcel logistics platform. Developed for KITCoEK CSBS Mini-Project-I.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-icon">⚡</span>
            <span>RYDO</span>
            <span class="college-badge">KITCoEK • CSBS</span>
        </a>
        <ul class="navbar-links">
            <li><a href="index.php" class="active">Book Ride / Parcel</a></li>
            <li><a href="captain/dashboard.php">Captain Portal 🛵</a></li>
            <li><a href="login.php">Admin Panel 🔒</a></li>
            <li style="color:var(--clr-muted);">Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Rider') ?></li>
            <li><a href="logout.php">Log Out</a></li>
        </ul>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="container">
        <div class="hero-tag">⚡ Fast, Safe &amp; Affordable Transportation</div>
        <h1>Your City Move, <span>Simplified with RYDO</span></h1>
        <p>Book high-speed micro-mobility Bike Taxis, Cabs, or dispatch intra-city RYDO Parcels in under 3 taps with 4-digit security PIN verification.</p>

        <!-- SERVICE SWITCHER (SRS 1.4 & 4.4) -->
        <div class="service-tabs-wrapper">
            <div class="service-tabs">
                <button type="button" class="service-tab active" id="tab-ride" onclick="switchService('ride')">
                    🛵 Bike Taxi &amp; Cabs
                </button>
                <button type="button" class="service-tab" id="tab-parcel" onclick="switchService('parcel')">
                    📦 RYDO Parcel (≤ 5kg)
                </button>
            </div>
        </div>
    </div>
</section>

<!-- MAIN BOOKING SECTION -->
<section style="padding: 1rem 0 5rem;">
    <div class="container">
        <div class="booking-grid">

            <!-- BOOKING FORM CARD -->
            <div class="card">
                <h2 class="section-title" id="form-heading">Request a Ride</h2>
                <p class="section-subtitle">Real-time driver matching &amp; estimated fare calculation.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert" style="background:rgba(255,51,102,0.15); border:1px solid rgba(255,51,102,0.3); color:#ff3366; padding:0.8rem 1rem; border-radius:8px; margin-bottom:1.2rem;">
                        ⚠️ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form id="booking-form" action="book.php" method="POST" novalidate>
                    <!-- Hidden field to track ride vs parcel -->
                    <input type="hidden" name="service_type" id="service_type" value="ride">

                    <!-- SENDER / PASSENGER INFO -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="passenger_name">Your Name</label>
                            <input type="text" id="passenger_name" name="passenger_name"
                                   class="form-control" placeholder="e.g. Aryan Chougule"
                                   value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>"
                                   maxlength="100" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Mobile Number</label>
                            <input type="tel" id="phone" name="phone"
                                   class="form-control" placeholder="10-digit mobile number"
                                   maxlength="10" required>
                        </div>
                    </div>

                    <!-- ROUTE (PICKUP & DESTINATION) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pickup">Pickup Location</label>
                            <select id="pickup" name="pickup" class="form-control" required>
                                <option value="">— Select Pickup Point —</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="destination">Drop Location</label>
                            <select id="destination" name="destination" class="form-control" required>
                                <option value="">— Select Destination —</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- RIDE VEHICLE SELECTION (Only visible in Ride Mode) -->
                    <div id="ride-vehicle-options">
                        <label>Select Ride Option</label>
                        <div class="vehicle-selector">
                            <label class="vehicle-radio">
                                <input type="radio" name="vehicle_type" value="Bike" checked>
                                <div class="vehicle-card">
                                    <div class="vehicle-icon">🛵</div>
                                    <div class="vehicle-name">RYDO Bike</div>
                                    <div class="vehicle-rate">₹20 base + ₹8/km</div>
                                    <div class="vehicle-desc">Fastest micro-mobility</div>
                                </div>
                            </label>
                            <label class="vehicle-radio">
                                <input type="radio" name="vehicle_type" value="Auto">
                                <div class="vehicle-card">
                                    <div class="vehicle-icon">🛺</div>
                                    <div class="vehicle-name">RYDO Auto</div>
                                    <div class="vehicle-rate">₹30 base + ₹12/km</div>
                                    <div class="vehicle-desc">Budget 3-seater</div>
                                </div>
                            </label>
                            <label class="vehicle-radio">
                                <input type="radio" name="vehicle_type" value="Cab">
                                <div class="vehicle-card">
                                    <div class="vehicle-icon">🚗</div>
                                    <div class="vehicle-name">RYDO Cab</div>
                                    <div class="vehicle-rate">₹50 base + ₹16/km</div>
                                    <div class="vehicle-desc">AC Prime comfort</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- PARCEL SPECIFIC DETAILS (SRS 4.4 - Hidden by default) -->
                    <div id="parcel-details-group" style="display:none; background:rgba(0,229,255,0.04); border:1px solid rgba(0,229,255,0.2); border-radius:12px; padding:1.2rem; margin-bottom:1.4rem;">
                        <h4 style="color:var(--clr-accent); font-size:0.95rem; margin-bottom:0.8rem; display:flex; align-items:center; gap:0.4rem;">
                            📦 Receiver &amp; Package Information
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="receiver_name">Receiver Name</label>
                                <input type="text" id="receiver_name" name="receiver_name" class="form-control" placeholder="Recipient's full name">
                            </div>
                            <div class="form-group">
                                <label for="receiver_phone">Receiver Mobile</label>
                                <input type="tel" id="receiver_phone" name="receiver_phone" class="form-control" placeholder="10-digit mobile">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="parcel_weight">Package Weight (Max 5.0 kg)</label>
                                <input type="number" id="parcel_weight" name="parcel_weight" class="form-control" min="0.1" max="5.0" step="0.1" value="1.0">
                            </div>
                            <div class="form-group">
                                <label for="receiver_address">Delivery Landmark / Flat No.</label>
                                <input type="text" id="receiver_address" name="receiver_address" class="form-control" placeholder="e.g. Near Campus Gate 2">
                            </div>
                        </div>
                    </div>

                    <!-- PAYMENT METHOD (SRS 2.2) -->
                    <div class="form-group">
                        <label for="payment_method">Payment Mode</label>
                        <select id="payment_method" name="payment_method" class="form-control">
                            <option value="UPI">📱 UPI / QR Code (Google Pay, PhonePe, Paytm)</option>
                            <option value="Cash">💵 Cash to Captain</option>
                            <option value="Wallet">⚡ RYDO Digital Wallet</option>
                            <option value="Card">💳 Debit / Credit Card</option>
                        </select>
                    </div>

                    <!-- FARE ESTIMATE PREVIEW -->
                    <div class="form-group">
                        <label>Estimated Trip Fare</label>
                        <div class="fare-preview" id="fare-preview">
                            <p class="text-muted">Select pickup and drop location to view estimated fare.</p>
                        </div>
                    </div>

                    <!-- Hidden fields for distance & fare -->
                    <input type="hidden" id="distance_km" name="distance_km" value="">
                    <input type="hidden" id="fare_estimate" name="fare_estimate" value="">

                    <button type="submit" id="submit-btn" class="btn btn-primary">
                        ⚡ Confirm &amp; Request Captain
                    </button>
                </form>
            </div>

            <!-- RIGHT INFO PANEL -->
            <div>
                <!-- ACTIVE LOCATIONS CARD -->
                <div class="card" style="margin-bottom: 1.3rem;">
                    <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.8rem; display:flex; align-items:center; gap:0.4rem;">
                        📍 Kolhapur Service Corridors
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--clr-muted); margin-bottom: 1rem;">
                        Instant captain matching available across key hubs:
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                        <?php foreach ($locations as $loc): ?>
                            <span style="background:rgba(255,170,0,0.1); border:1px solid rgba(255,170,0,0.25); color:var(--clr-primary); font-size:0.75rem; font-weight:600; padding:0.25rem 0.65rem; border-radius:99px;">
                                <?= htmlspecialchars($loc) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SAFETY & FEATURES CARD (SRS Section 4.3 & 5.2) -->
                <div class="card" style="margin-bottom: 1.3rem;">
                    <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.8rem; display:flex; align-items:center; gap:0.4rem;">
                        🛡️ Safety First by Design
                    </h3>
                    <ul style="list-style:none; font-size:0.84rem; color:var(--clr-muted); display:flex; flex-direction:column; gap:0.6rem;">
                        <li>🔒 <strong>4-Digit OTP Security:</strong> Every trip is protected by a confidential PIN shared only when captain arrives.</li>
                        <li>🛵 <strong>Helmet &amp; KYC Verified:</strong> Captain driving licenses and background checks verified by Admin.</li>
                        <li>🚨 <strong>Live Safety SOS:</strong> Instant 1-tap emergency location sharing.</li>
                        <li>📦 <strong>Parcel Delivery Proof:</strong> Digital delivery confirmation.</li>
                    </ul>
                </div>

                <!-- QUICK ACCESS LINKS -->
                <div class="card">
                    <h3 style="font-size: 1rem; font-weight:700; margin-bottom:0.7rem;">⚡ Quick Portals</h3>
                    <div style="display:flex; flex-direction:column; gap:0.6rem;">
                        <a href="captain/dashboard.php" class="btn btn-secondary btn-sm" style="justify-content:flex-start;">
                            🛵 Captain Partner App &amp; Accept Rides
                        </a>
                        <a href="admin/login.php" class="btn btn-secondary btn-sm" style="justify-content:flex-start;">
                            🔒 Admin Control Center &amp; KYC Queue
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="container">
        <p><strong>RYDO</strong> — On-Demand Bike Taxi &amp; Intra-City Logistics Platform</p>
        <p class="team-credits">
            Academic Project: Mini-Project-I (Course Code: UCBIL0371) | Department of CSBS<br>
            <strong>Kolhapur Institute of Technology's College of Engineering (KITCoEK)</strong>
        </p>
        <p style="margin-top:0.4rem; font-size:0.75rem; color:#525d72;">
            Team: Aryan Chougule (06) • Sainath Chavan (05) • Sumeet Keroor (25) • Varad Akolkar (02) • Atif Gulbarga (34) | Guide: Prof. Sheetal Gaikwad
        </p>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
