<?php
include 'db.php';

$success = '';
$error = '';

// Post Intake Booking Request (unified field set -> mirrors members table)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_booking'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $phone_no  = trim($_POST['phone_no'] ?? '');
    $shift     = ($_POST['shift'] ?? 'Morning') === 'Evening' ? 'Evening' : 'Morning';
    $starting_date         = $_POST['starting_date'] ?? date('Y-m-d');
    $subscription_months   = max(1, (int)($_POST['subscription_months'] ?? 1));
    $package_cost          = max(0.0, (float)($_POST['package_cost'] ?? 0));
    $amount_paid           = max(0.0, (float)($_POST['amount_paid'] ?? 0));
    $trainer_id            = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;

    if ($full_name === '' || $address === '' || $phone_no === '') {
        $error = 'Please fill in your name, address and phone number.';
    } elseif ($amount_paid > $package_cost) {
        $error = 'Amount paid cannot be greater than the package cost.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO bookings
                    (full_name, address, phone_no, shift, starting_date, subscription_months, package_cost, amount_paid, trainer_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$full_name, $address, $phone_no, $shift, $starting_date, $subscription_months, $package_cost, $amount_paid, $trainer_id]);
            $due = $package_cost - $amount_paid;
            $success = "Registration Successful! Your application is pending admin approval. Remaining balance: Rs. " . number_format($due, 2);
        } catch (PDOException $e) {
            $error = 'We could not submit your application. Please try again later.';
        }
    }
}

// Fetch dynamic gym events for notices and marquee sections
try {
    $stmt_events = $pdo->query("SELECT * FROM gym_events ORDER BY created_at DESC");
    $live_events = $stmt_events->fetchAll();
} catch (Exception $e) {
    $live_events = [];
}

// Analytics Metrics Parsing (prepared where user input could matter; counts are safe)
$m_count = $pdo->query("SELECT COUNT(*) FROM members WHERE shift='Morning'")->fetchColumn();
$e_count = $pdo->query("SELECT COUNT(*) FROM members WHERE shift='Evening'")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$trainers = $pdo->query("SELECT * FROM trainers ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muscle Workshop Gym</title><link rel="stylesheet" href="style.css">
    <style>
        :root { --p-bg: #111622; --border: #1e2638; }
        body { background:#0b0f17; font-size:14px; padding-bottom:70px; margin:0; font-family:-apple-system, BlinkMacSystemFont, sans-serif; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .nav-bar { position:fixed; bottom:0; left:0; width:100%; background:var(--p-bg); border-top:1px solid var(--border); display:flex; justify-content:space-around; padding:10px 0; z-index:999; }
        .nav-bar button { background:none; border:none; color:var(--text-muted); font-size:0.75rem; font-weight:600; cursor:pointer; display:flex; flex-direction:column; align-items:center; }
        .nav-bar button.active { color:var(--accent); }
        .metric-badge { background:#171e2e; border:1px solid var(--border); padding:10px; border-radius:8px; text-align:center; }
        .price-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #1e2638; font-size:0.85rem; }
        .price-row:last-child { border-bottom:none; }
        .mini-card { background:var(--p-bg); border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px; }
        
        /* Inline helper class for marquee wrapper structure */
        .tab-ticker-strip { background:#161d2c; border:1px solid var(--border); border-radius:8px; padding:6px 10px; margin-bottom:12px; overflow:hidden; white-space:nowrap; }
    </style>
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-bar button').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            var btn = document.querySelector(`button[onclick="switchTab('${tabId}')"]`);
            if(btn) btn.classList.add('active');
            window.scrollTo(0,0);
        }
        function toggleUserDropdown() {
            var menu = document.getElementById('userMenuDropdown');
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
        window.onclick = function(event) {
            if (!event.target.matches('.dot-btn')) {
                var menu = document.getElementById('userMenuDropdown');
                if(menu) menu.style.display = 'none';
            }
        }
    </script>
</head>
<body onload="switchTab((location.hash || '').replace('#','') || 'dashboard')">

<div style="background:var(--p-bg); border-bottom:1px solid var(--border); padding:12px 15px; display:flex; justify-content:space-between; align-items:center; position:relative; box-sizing:border-box;">
    <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:1.5rem;">💪</span>
        <div>
            <h3 style="color:#fff; margin:0; font-size:1.1rem; font-weight:800; letter-spacing:0.5px;">MUSCLE WORKSHOP</h3>
            <span style="color:var(--accent); font-size:0.75rem; font-weight:700;">ACTIVE ATHLETES: <?= $total_members ?> TILL NOW</span>
        </div>
    </div>
    
    <div style="position:relative;">
        <button class="dot-btn" onclick="toggleUserDropdown()" style="background:none; border:none; color:#fff; font-size:1.4rem; cursor:pointer; padding:5px 10px;">⋮</button>
        <div id="userMenuDropdown" style="display:none; position:absolute; right:0; top:35px; background:#171e2e; border:1px solid var(--border); border-radius:6px; width:180px; box-shadow:0 4px 12px rgba(0,0,0,0.5); z-index:1000;">
            <a href="admin.php" style="display:block; padding:10px 12px; color:#fff; text-decoration:none; font-size:0.85rem; border-bottom:1px solid var(--border);">🛡️ Admin Portal</a>
            <div style="padding:10px 12px; color:#a0aec0; font-size:0.75rem; line-height:1.3;">
                <span style="color:var(--accent); font-weight:bold; display:block; margin-bottom:2px;">Web Developer:</span>
                Anup Dahal<br>
                9804902634
            </div>
        </div>
    </div>
</div>

<!-- PHP Helper string to render the horizontal marquee content on top of each tab section -->
<?php
ob_start();
?>
<?php
$moving_ticker_html = ob_get_clean();
?>

<div class="container" style="padding:12px; box-sizing:border-box;">

    <!-- TAB 1: USER ANALYTICS DASHBOARD -->
    <div id="dashboard" class="tab-content">
        <!-- Horizontal Moving Ticker at top of tab -->
        <?= $moving_ticker_html ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:15px;">
            <div class="metric-badge">
                <span style="font-size:1.2rem;">🌅</span><br>
                <span style="color:var(--text-muted); font-size:0.75rem;">Morning Shift</span>
                <h3 style="color:#fff; margin:2px 0 0 0; font-size:1.2rem;"><?= $m_count ?> Active</h3>
            </div>
            <div class="metric-badge">
                <span style="font-size:1.2rem;">🌌</span><br>
                <span style="color:var(--text-muted); font-size:0.75rem;">Evening Shift</span>
                <h3 style="color:#fff; margin:2px 0 0 0; font-size:1.2rem;"><?= $e_count ?> Active</h3>
            </div>
        </div>

        <div class="mini-card">
            <h4 style="color:var(--accent); margin-top:0; font-size:0.9rem; border-bottom:1px solid var(--border); padding-bottom:5px;">Tiered Price Subscriptions</h4>
            <div class="price-row"><span>1 Month Plan</span><strong>Rs. 1,500</strong></div>
            <div class="price-row"><span>2 Months Plan</span><strong>Rs. 2,800</strong></div>
            <div class="price-row"><span>3 Months Plan</span><strong>Rs. 3,900</strong></div>
            <div class="price-row"><span>4 Months Plan</span><strong>Rs. 5,000</strong></div>
            <div class="price-row"><span>5 Months Plan</span><strong>Rs. 6,000</strong></div>
            <div class="price-row"><span>6 Months Plan</span><strong>Rs. 6,600</strong></div>
            <div class="price-row"><span>7 Months Plan</span><strong>Rs. 7,350</strong></div>
            <div class="price-row"><span>8 Months Plan</span><strong>Rs. 8,000</strong></div>
            <div class="price-row"><span>9 Months Plan</span><strong>Rs. 8,550</strong></div>
            <div class="price-row"><span>10 Months Plan</span><strong>Rs. 9,000</strong></div>
            <div class="price-row"><span>11 Months Plan</span><strong>Rs. 9,350</strong></div>
            <div class="price-row" style="color:var(--accent); font-weight:bold;"><span>12 Months (Full Year)</span><strong>Rs. 9,450</strong></div>
        </div>
    </div>

    <!-- TAB 2: PUBLIC ONLINE REGISTRATION -->
    <div id="register" class="tab-content">
        <!-- Horizontal Moving Ticker at top of tab -->
        <?= $moving_ticker_html ?>

        <div class="mini-card">
            <h4 style="color:var(--accent); margin-top:0; font-size:0.95rem;">Join Muscle Workshop</h4>
            <p style="color:var(--text-muted); font-size:0.8rem; margin:0 0 12px 0;">Fill in your details below. An admin will review and approve your membership.</p>
            <?php if ($success !== ''): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php
            $form_action       = 'index.php#register';
            $form_submit_name  = 'submit_booking';
            $form_submit_label = 'Submit Registration';
            $form_prefix       = 'pub';
            include 'member_form.php';
            ?>
        </div>
    </div>

    <!-- TAB 3: COACHES DIRECTORY -->
    <div id="staff" class="tab-content">
        <!-- Horizontal Moving Ticker at top of tab -->
        <?= $moving_ticker_html ?>

        <h4 style="color:#fff; margin-bottom:10px; font-size:0.95rem;">Our Professional Team</h4>
        <?php foreach($trainers as $t): ?>
            <div class="mini-card" style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong style="color:#fff;"><?= htmlspecialchars($t['name']) ?></strong><br>
                    <small style="color:var(--accent); font-weight:600;"><?= $t['role'] ?></small>
                </div>
                <span style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($t['specialization'] ?? 'General Mechanics') ?></span>
            </div>
        <?php endforeach; if(count($trainers)==0) echo "<p style='color:var(--text-muted); text-align:center;'>No staff listed yet.</p>"; ?>
    </div>

    <!-- TAB 4: CAMPAIGNS & SOCIAL ENGAGEMENTS -->
    <div id="campaigns" class="tab-content">
        <!-- Horizontal Moving Ticker at top of tab -->
        <?= $moving_ticker_html ?>

        <h4 style="color:#fff; margin-bottom:10px; font-size:0.95rem;">Workshop Community Contributions</h4>
        
        <?php if (count($live_events) > 0): ?>
            <?php foreach($live_events as $event): ?>
                <div class="mini-card" style="border-left:3px solid var(--accent); padding:0; overflow:hidden;">
                    <?php if(!empty($event['image_path'])): ?>
                        <img src="<?= htmlspecialchars($event['image_path']) ?>" style="width:100%; height:150px; object-fit:cover; display:block;">
                    <?php endif; ?>
                    <div style="padding:12px;">
                        <div style="display:flex; justify-content:between; align-items:center; margin-bottom:5px;">
                            <strong style="color:#fff; font-size:0.95rem;"><?= htmlspecialchars($event['title']) ?></strong>
                            <small style="color:var(--text-muted); font-size:0.75rem; margin-left:auto;"><?= date('M d, Y', strtotime($event['created_at'])) ?></small>
                        </div>
                        <p style="color:var(--text-muted); font-size:0.8rem; margin:5px 0 0 0; line-height:1.4; white-space:pre-line;"><?= htmlspecialchars($event['description']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="mini-card" style="border-left:3px solid #e74c3c;">
                <strong style="color:#fff; font-size:0.9rem;">🩸 Urgent Blood Donation Drive</strong>
                <p style="color:var(--text-muted); font-size:0.8rem; margin:5px 0 0 0;">
                    Organized by the Workshop Crew. Helping local emergency medical centers secure dynamic blood reserves. Connect at the front desk to join our active donor roster!
                </p>
            </div>
            <div class="mini-card" style="border-left:3px solid #3498db;">
                <strong style="color:#fff; font-size:0.9rem;">🏋️ Youth Fitness Open Camps</strong>
                <p style="color:var(--text-muted); font-size:0.8rem; margin:5px 0 0 0;">
                    Free introductory strength guidance sessions offered every Saturday to help local youth discover safe training mechanics.
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- USER NAVIGATION MENU BAR -->
<div class="nav-bar">
    <button onclick="switchTab('dashboard')"><span>🏠</span>Home</button>
    <button onclick="switchTab('register')"><span>📝</span>Register</button>
    <button onclick="switchTab('staff')"><span>💪</span>Staff Team</button>
    <button onclick="switchTab('campaigns')"><span>🤝</span>Events</button>
</div>

</body>
</html>
