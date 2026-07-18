<?php
session_start();
include 'db.php';

// 1. STATE-LESS SECURITY AUTHENTICATION GATE
$authenticated = false;
$username = '';
$password = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = trim($_POST['username']); 
        $password = trim($_POST['password']);
        
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]); 
        $user = $stmt->fetch();
        
        if ($user && $password === $user['password']) {
            $authenticated = true;
        } else { 
            $error = "Invalid Credentials Verified."; 
        }
    }
}

// Redirect execution to block layout if authentication validation fails
if (!$authenticated) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification Required</title><link rel="stylesheet" href="style.css">
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0b0f17; padding:15px; margin:0;">
    <div class="card" style="width:100%; max-width:350px; padding:1.5rem;">
        <h3 style="color:var(--accent); text-align:center; margin-bottom:1.2rem;">Verify Identity</h3>
        <?php if(!empty($error)): ?><p style="color:#e74c3c; text-align:center; font-size:0.9rem;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form action="admin.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'applicants') ?>" method="POST">
            <div class="form-group"><label style="font-size:0.85rem;">Username</label><input type="text" name="username" required autocomplete="off"></div>
            <div class="form-group"><label style="font-size:0.85rem;">Password</label><input type="password" name="password" required autocomplete="off"></div>
            <button type="submit" class="btn" style="width:100%;">Verify & Access</button>
        </form>
    </div>
</body>
</html>
<?php 
exit; 
}

// 2. DATA TRANSACTIONS WRITING PIPELINES
// File Directory setup for Live Campaigns & Event Updates
$upload_dir = 'uploads/events/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Event system creation engine
if (isset($_POST['push_gym_event'])) {
    $title = htmlspecialchars($_POST['event_title']);
    $description = htmlspecialchars($_POST['event_desc']);
    
    if (isset($_FILES['event_img']) && $_FILES['event_img']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['event_img']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $unique_name = 'event_' . time() . '.' . $ext;
            $target = $upload_dir . $unique_name;
            
            if (move_uploaded_file($_FILES['event_img']['tmp_name'], $target)) {
                // Ensure table exists safely inline
                $pdo->exec("CREATE TABLE IF NOT EXISTS gym_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    image_path VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                $stmt = $pdo->prepare("INSERT INTO gym_events (title, description, image_path) VALUES (?, ?, ?)");
                $stmt->execute([$title, $description, $target]);
            }
        }
    }
}

// Event execution dropping route
if (isset($_GET['delete_event_id'])) {
    $del_ev_id = intval($_GET['delete_event_id']);
    $res = $pdo->prepare("SELECT image_path FROM gym_events WHERE id = ?");
    $res->execute([$del_ev_id]);
    $ev_data = $res->fetch();
    if ($ev_data) {
        if (file_exists($ev_data['image_path'])) {
            unlink($ev_data['image_path']);
        }
        $del_stmt = $pdo->prepare("DELETE FROM gym_events WHERE id = ?");
        $del_stmt->execute([$del_ev_id]);
    }
}

if (isset($_POST['manual_entry'])) {
    $name = $_POST['full_name']; $address = $_POST['address']; $phone = $_POST['phone_no'];
    $shift = $_POST['shift']; $start_date = $_POST['starting_date'];
    $months = intval($_POST['subscription_months']); $cost = floatval($_POST['package_cost']);
    $paid = floatval($_POST['amount_paid']); $t_id = !empty($_POST['trainer_id']) ? $_POST['trainer_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO members (full_name, address, phone_no, shift, starting_date, subscription_months, package_cost, amount_paid, trainer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $address, $phone, $shift, $start_date, $months, $cost, $paid, $t_id]);
    
    if ($paid > 0) {
        $m_id = $pdo->lastInsertId();
        $log = $pdo->prepare("INSERT INTO payments (member_id, amount, payment_date) VALUES (?, ?, ?)");
        $log->execute([$m_id, $paid, $start_date . ' ' . date('H:i:s')]);
    }
}

if (isset($_POST['add_staff'])) {
    $t_name = $_POST['staff_name']; $role = $_POST['role']; $spec = $_POST['specialization'];
    $ins = $pdo->prepare("INSERT INTO trainers (name, role, specialization) VALUES (?, ?, ?)");
    $ins->execute([$t_name, $role, $spec]);
}

if (isset($_POST['update_payment'])) {
    $m_id = intval($_POST['member_id']); $new_pay = floatval($_POST['payment_increment']);
    $p_date = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("UPDATE members SET amount_paid = amount_paid + ? WHERE id = ?");
    $stmt->execute([$new_pay, $m_id]);
    
    $log = $pdo->prepare("INSERT INTO payments (member_id, amount, payment_date) VALUES (?, ?, ?)");
    $log->execute([$m_id, $new_pay, $p_date]);
}

// 3. REMOVALS & INTAKE STATE PROCESSING UPDATES
if (isset($_GET['approve_id'])) {
    $b_id = intval($_GET['approve_id']);
    $booking = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $booking->execute([$b_id]); $b_data = $booking->fetch();
    if ($b_data) {
        $pricingTier = [1=>1500, 2=>2800, 3=>3900, 4=>5000, 5=>6000, 6=>6600, 7=>7350, 8=>8000, 9=>8550, 10=>9000, 11=>9350, 12=>9350];
        $months = 1; 
        if(preg_match('/\d+/', $b_data['package_tier'], $matches)) { $months = intval($matches[0]); }
        $cost = isset($pricingTier[$months]) ? $pricingTier[$months] : 1500;
        
        $ins = $pdo->prepare("INSERT INTO members (full_name, address, phone_no, shift, starting_date, subscription_months, package_cost, amount_paid, trainer_id) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?)");
        $ins->execute([$b_data['client_name'], $b_data['address'], $b_data['phone_no'], $b_data['shift'], date('Y-m-d'), $months, $cost, $b_data['trainer_id']]);
        
        $del = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $del->execute([$b_id]);
    }
}

if (isset($_GET['delete_booking_id'])) {
    $del_id = intval($_GET['delete_booking_id']);
    $del = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $del->execute([$del_id]);
}

if (isset($_GET['delete_member_id'])) {
    $m_del_id = intval($_GET['delete_member_id']);
    $del_p = $pdo->prepare("DELETE FROM payments WHERE member_id = ?");
    $del_p->execute([$m_del_id]);
    $del_m = $pdo->prepare("DELETE FROM members WHERE id = ?");
    $del_m->execute([$m_del_id]);
}

// 4. RETRIEVE RE-RENDER ENGINE AGGREGATES
$trainers = $pdo->query("SELECT * FROM trainers ORDER BY id DESC")->fetchAll();
$bookings = $pdo->query("SELECT b.*, t.name as trainer_name FROM bookings b LEFT JOIN trainers t ON b.trainer_id = t.id WHERE b.status='Pending' ORDER BY b.id DESC")->fetchAll();
$members = $pdo->query("SELECT m.*, t.name as trainer_name FROM members m LEFT JOIN trainers t ON m.trainer_id = t.id ORDER BY m.id DESC")->fetchAll();
$payments = $pdo->query("SELECT p.*, m.full_name, m.package_cost FROM payments p JOIN members m ON p.member_id = m.id ORDER BY p.id ASC")->fetchAll();

// Catch gym campaigns logs safely
try {
    $gym_events = $pdo->query("SELECT * FROM gym_events ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $gym_events = [];
}

// Calculate total payments and total outstanding balance dues globally
$global_total_paid = 0;
$global_total_due = 0;
foreach($members as $m) {
    $global_total_paid += floatval($m['amount_paid']);
    $global_total_due += (floatval($m['package_cost']) - floatval($m['amount_paid']));
}
$total_members = count($members);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title><link rel="stylesheet" href="style.css">
    <style>
        :root { --p-bg: #111622; --border: #1e2638; }
        body { background:#0b0f17; font-size:14px; padding-bottom:70px; margin:0; font-family:-apple-system, BlinkMacSystemFont, sans-serif; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .nav-bar { position:fixed; bottom:0; left:0; width:100%; background:var(--p-bg); border-top:1px solid var(--border); display:flex; justify-content:space-around; padding:10px 0; z-index:999; }
        .nav-bar button { background:none; border:none; color:var(--text-muted); font-size:0.75rem; font-weight:600; cursor:pointer; display:flex; flex-direction:column; align-items:center; }
        .nav-bar button.active { color:var(--accent); }
        .mini-card { background:var(--p-bg); border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:10px; }
        .data-line { display:flex; justify-content:space-between; padding:3px 0; font-size:0.85rem; }
        .badge { padding:2px 6px; font-size:0.7rem; font-weight:bold; border-radius:3px; }
        .badge-pending { background:#e67e22; color:white; }
        .print-modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; color:black; padding:20px; width:95%; max-width:550px; border-radius:8px; z-index:1001; box-shadow:0 0 25px rgba(0,0,0,0.7); max-height:85vh; overflow-y:auto; }
        .overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000; }
        .dot-btn { background:none; border:none; color:#fff; font-size:1.4rem; cursor:pointer; padding:5px 10px; line-height:1; }
        .form-compact .form-group { margin-bottom: 6px; }
        .form-compact label { font-size: 0.8rem; margin-bottom: 2px; }
        .form-compact input, .form-compact select, .form-compact textarea { padding: 6px; font-size: 0.85rem; background:#161d2c; border:1px solid var(--border); color:#fff; border-radius:4px; width:100%; box-sizing:border-box; }
        
        @media print { 
            body * { display:none !important; } 
            #printBox, #printBox * { display:block !important; position:static !important; transform:none !important; box-shadow:none !important; background:white !important; color:black !important; }
            .no-print { display:none !important; }
            table { width:100% !important; border-collapse:collapse !important; }
            th, td { border:1px solid #000 !important; padding:6px !important; text-align:left !important; }
        }
    </style>
    <script>
        const pricing = { 1:1500, 2:2800, 3:3900, 4:5000, 5:6000, 6:6600, 7:7350, 8:8000, 9:8550, 10:9000, 11:9350, 12:9450 };
        function autoCost() {
            const m = document.getElementById('sub_m').value;
            document.getElementById('p_cost').value = pricing[m] || 1500;
        }
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-bar button').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            var btn = document.querySelector(`button[onclick="switchTab('${tabId}')"]`);
            if(btn) btn.classList.add('active');
            
            document.querySelectorAll('.tab-content form').forEach(form => {
                const currentAction = form.getAttribute('action').split('?')[0];
                form.setAttribute('action', currentAction + '?tab=' + tabId);
            });
        }
        function toggleAdminDropdown() {
            var menu = document.getElementById('adminMenuDropdown');
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
        function openPrint(html) {
            document.getElementById('m-body').innerHTML = html;
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('printBox').style.display = 'block';
        }
        function closePrint() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('printBox').style.display = 'none';
        }
        function confirmDeleteBooking(bookingId) {
            if(confirm("Are you sure you want to delete this applicant request?")) {
                submitActionWithCreds("admin.php?delete_booking_id=" + bookingId + "&tab=applicants");
            }
        }
        function confirmDeleteMember(memberId) {
            if(confirm("DANGER: Are you absolutely sure you want to delete this member account profile permanently? All local payment history logs will be purged.")) {
                submitActionWithCreds("admin.php?delete_member_id=" + memberId + "&tab=registry");
            }
        }
        function confirmDeleteEvent(eventId) {
            if(confirm("Remove this campaign banner from the public user board layout?")) {
                submitActionWithCreds("admin.php?delete_event_id=" + eventId + "&tab=applicants");
            }
        }
        function submitActionWithCreds(targetUrl) {
            var form = document.createElement("form");
            form.method = "POST";
            form.action = targetUrl;
            
            var userInp = document.createElement("input");
            userInp.type = "hidden"; userInp.name = "username"; userInp.value = "<?= htmlspecialchars($username) ?>";
            form.appendChild(userInp);
            
            var passInp = document.createElement("input");
            passInp.type = "hidden"; passInp.name = "password"; passInp.value = "<?= htmlspecialchars($password) ?>";
            form.appendChild(passInp);
            
            document.body.appendChild(form);
            form.submit();
        }
        window.onclick = function(event) {
            if (!event.target.matches('.dot-btn')) {
                var menu = document.getElementById('adminMenuDropdown');
                if(menu) menu.style.display = 'none';
            }
        }
    </script>
</head>
<body onload="const urlParams = new URLSearchParams(window.location.search); switchTab(urlParams.get('tab') || 'applicants');">

<!-- NAVIGATION MANAGEMENT HEADER BAR -->
<div style="background:var(--p-bg); border-bottom:1px solid var(--border); padding:12px 15px; display:flex; justify-content:space-between; align-items:center; position:relative; box-sizing:border-box;">
    <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:1.5rem;">💪</span>
        <div>
            <h3 style="color:#fff; margin:0; font-size:1.1rem; font-weight:800; letter-spacing:0.5px;">MUSCLE WORKSHOP</h3>
            <span style="color:var(--accent); font-size:0.75rem; font-weight:700;">TOTAL MEMBERS: <?= $total_members ?></span>
        </div>
    </div>

    <div style="position:relative;">
        <button class="dot-btn" onclick="toggleAdminDropdown()">⋮</button>
        <div id="adminMenuDropdown" style="display:none; position:absolute; right:0; top:35px; background:#171e2e; border:1px solid var(--border); border-radius:6px; width:180px; box-shadow:0 4px 12px rgba(0,0,0,0.5); z-index:1000;">
            <a href="index.php" style="display:block; padding:10px 12px; color:#fff; text-decoration:none; font-size:0.85rem;">🏠 User Dashboard</a>
            <hr style="border:0; border-top:1px solid var(--border); margin:0; padding:0;">
            <div style="padding:10px 12px; color:#a0aec0; font-size:0.75rem; line-height:1.3;">
                <span style="color:var(--accent); font-weight:bold; display:block; margin-bottom:2px;">Web Developer:</span>
                Anup Dahal<br>
                9804902634
            </div>
        </div>
    </div>
</div>

<div class="container" style="padding:12px; box-sizing:border-box;">

       <div id="applicants" class="tab-content">
        <h4 style="color:#fff; margin-bottom:10px; font-size:0.95rem;">Pending Public Applications</h4>
        <?php foreach($bookings as $b): ?>
            <div class="mini-card" style="border-left:3px solid var(--accent);">
                <div class="data-line"><strong><?= htmlspecialchars($b['client_name']) ?></strong> <span class="badge badge-pending"><?= htmlspecialchars($b['shift']) ?></span></div>
                <div class="data-line" style="color:#a0aec0;">📞 <?= htmlspecialchars($b['phone_no']) ?> | 📍 <?= htmlspecialchars($b['address']) ?></div>
                <div class="data-line" style="color:#cbd5e0;">Term Preference: <?= htmlspecialchars($b['package_tier']) ?></div>
                
                <div style="margin-top:10px; display:flex; width:100%; gap:8px;">
                    <button onclick="confirmDeleteBooking(<?= $b['id'] ?>)" class="btn" style="width:50%; padding:6px 0; font-size:0.8rem; background:#e74c3c; border-radius:4px; font-weight:bold; cursor:pointer;">Delete Request</button>
                    <form action="admin.php?approve_id=<?= $b['id'] ?>&tab=applicants" method="POST" style="width:50%; margin:0; padding:0;">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                        <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                        <button type="submit" class="btn" style="width:100%; padding:6px 0; font-size:0.8rem; border-radius:4px; font-weight:bold; cursor:pointer;">Approve Profile</button>
                    </form>
                </div>
            </div>
        <?php endforeach; if(count($bookings)==0) echo "<p style='color:var(--text-muted);text-align:center;'>No pending intake forms available.</p>"; ?><br>
    <hr><br>
     <!-- TAB 1: INTAKE PIPELINE & PUSH EVENTS SYSTEM -->
        <!-- PUSH EVENTS HUB FORM CONTEXT -->
        <div class="mini-card" style="border-bottom: 2px solid var(--accent); margin-bottom: 20px; padding: 14px;">
            <h4 style="color:var(--accent); margin:0 0 10px 0; font-size:0.95rem; text-transform:uppercase;">📣 Add Announcements</h4>
            <form action="admin.php?tab=applicants" method="POST" enctype="multipart/form-data" class="form-compact">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="event_title" placeholder="Date or Subject" required>
                </div>
                <div class="form-group">
                    <label> Content Details</label>
                    <textarea name="event_desc" rows="2" placeholder="Write description notes..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Feature Photo Banner</label>
                    <input type="file" name="event_img" accept="image/*" required>
                </div>
                
                <button type="submit" name="push_gym_event" class="btn" style="width:100%; padding:8px; margin-top:6px; background:var(--accent); color:#000; font-weight:bold;">Push Event Live</button>
            </form>

            <!-- LIVE EVENTS EDIT/DELETE CONTROL STRIP -->
            <?php if (count($gym_events) > 0): ?>
                <div style="margin-top:15px; border-top:1px solid var(--border); padding-top:10px; max-height:160px; overflow-y:auto;">
                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:bold; display:block; margin-bottom:6px;">ACTIVE BROADCASTS:</span>
                    <?php foreach($gym_events as $ev): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:#161d2c; padding:6px 10px; border-radius:4px; margin-bottom:4px;">
                            <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                                <img src="<?= $ev['image_path'] ?>" style="width:30px; height:30px; object-fit:cover; border-radius:3px;">
                                <span style="font-size:0.8rem; color:#fff; text-truncate; white-space:nowrap;"><?= htmlspecialchars($ev['title']) ?></span>
                            </div>
                            <button onclick="confirmDeleteEvent(<?= $ev['id'] ?>)" style="background:none; border:none; color:#e74c3c; font-weight:bold; cursor:pointer; font-size:0.8rem;">[Delete]</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- TAB 2: REGISTER SYSTEM (JOIN WORKSPACE) -->
    <div id="register" class="tab-content">
        <div class="mini-card" style="padding:10px 14px;">
            <h4 style="color:var(--accent); margin:0 0 10px 0; font-size:0.95rem;">Direct Enrollment Onboarding</h4>
            <form action="admin.php?tab=register" method="POST" class="form-compact">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="form-group"><label>Address</label><input type="text" name="address" required></div>
                <div class="form-group"><label>Phone Axis</label><input type="text" name="phone_no" required></div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group"><label>Plan Duration</label>
                        <select id="sub_m" name="subscription_months" onchange="autoCost()">
                            <?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>"><?=$i?> Month<?=$i>1?'s':''?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Assigned Coach</label>
                        <select name="trainer_id"><option value="">Self Guided</option><?php foreach($trainers as $t): ?><option value="<?=$t['id']?>"><?=$t['name']?></option><?php endforeach; ?></select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group"><label>Package Cost</label><input type="number" id="p_cost" name="package_cost" value="1500"></div>
                    <div class="form-group"><label>Amount Paid Now</label><input type="number" name="amount_paid" value="0"></div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group"><label>Shift</label><select name="shift"><option>Morning</option><option>Evening</option></select></div>
                    <div class="form-group"><label>Starting Date</label><input type="date" name="starting_date" value="<?=date('Y-m-d')?>" required></div>
                </div>
                <button type="submit" name="manual_entry" class="btn" style="width:100%; padding:8px; margin-top:6px; font-weight:bold;">Register Member</button>
            </form>
        </div>
    </div>

    <!-- TAB 3: STAFF RECRUITMENT -->
    <div id="staff" class="tab-content">
        <div class="mini-card">
            <h4 style="color:var(--accent); margin-top:0; font-size:0.95rem;">Onboard Staff & Corporate Entities</h4>
            <form action="admin.php?tab=staff" method="POST" class="form-compact">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                <div class="form-group"><label>Staff Member Name</label><input type="text" name="staff_name" required></div>
                <div class="form-group"><label>Role Category</label>
                    <select name="role">
                        <option value="Trainer">Trainer Professional</option>
                        <option value="Staff">Administrative Staff</option>
                        <option value="Founder">Workshop Founder</option>
                        <option value="Shareholder">Share Holder</option>
                    </select>
                </div>
                <div class="form-group"><label>Domain Specialization</label><input type="text" name="specialization" placeholder="Execution / Corporate Systems"></div>
                <button type="submit" name="add_staff" class="btn" style="width:100%; background:#9b59b6; padding:8px; font-weight:bold; margin-top:4px;">Confirm Hire</button>
            </form>
        </div>
    </div>

    <!-- TAB 4: PAYMENT LEDGER BALANCE SHEET -->
    <div id="ledger" class="tab-content">
        <!-- GLOBAL VOLUME SUMMARIES COUNTER BLOCK -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
            <div style="background:#27ae60; border-radius:6px; padding:10px; text-align:center; color:white;">
                <div style="font-size:0.75rem; text-transform:uppercase; font-weight:bold; opacity:0.9;">Total Collected</div>
                <div style="font-size:1.15rem; font-weight:800; margin-top:2px;">Rs. <?= number_format($global_total_paid, 2) ?></div>
            </div>
            <div style="background:#d35400; border-radius:6px; padding:10px; text-align:center; color:white;">
                <div style="font-size:0.75rem; text-transform:uppercase; font-weight:bold; opacity:0.9;">Total Due Balance</div>
                <div style="font-size:1.15rem; font-weight:800; margin-top:2px;">Rs. <?= number_format($global_total_due, 2) ?></div>
            </div>
        </div>

        <div class="mini-card">
            <h4 style="color:var(--accent); margin-top:0; font-size:0.95rem;">Post Collected Membership Fees</h4>
            <form action="admin.php?tab=ledger" method="POST" class="form-compact">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                <div class="form-group"><label>Select Profile</label>
                    <select name="member_id" required><option value="">Select Account Profile</option><?php foreach($members as $m): ?><option value="<?=$m['id']?>"><?=$m['full_name']?> (ID: <?=$m['id']?>)</option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label>Collected Cash Volume (Rs.)</label><input type="number" name="payment_increment" required></div>
                <button type="submit" name="update_payment" class="btn" style="width:100%; background:#27ae60; padding:8px; font-weight:bold; margin-top:4px;">Post Transaction</button>
            </form>
        </div>
        
        <h4 style="color:#fff; margin:15px 0 8px 0; font-size:0.95rem;">Auditable Transaction History Logs (Read-Only)</h4>
        <div style="max-height:300px; overflow-y:auto;">
            <?php 
            $running_member_totals = [];
            foreach($payments as $p): 
                $m_id = $p['member_id'];
                if (!isset($running_member_totals[$m_id])) {
                    $running_member_totals[$m_id] = 0;
                }
                $running_member_totals[$m_id] += floatval($p['amount']);
                $current_due_snapshot = floatval($p['package_cost']) - $running_member_totals[$m_id];
            ?>
                <div class="mini-card" style="border-left:3px solid #2ecc71; margin-bottom:6px; padding:8px 12px; background:#161d2c;">
                    <div class="data-line"><strong><?= htmlspecialchars($p['full_name']) ?></strong> <span style="color:#2ecc71; font-weight:bold;">Rs. <?= number_format($p['amount'], 2) ?></span></div>
                    <div class="data-line" style="font-size:0.75rem; color:#a0aec0; margin-top:4px;">
                        <span>📅 <?= $p['payment_date'] ?></span>
                    </div>
                    <div style="border-top:1px solid #243147; margin-top:6px; padding-top:4px; display:flex; justify-content:between; font-size:0.72rem; color:#cbd5e0;">
                        <span style="width:50%;">Total Paid: Rs.<?= number_format($running_member_totals[$m_id], 2) ?></span>
                        <span style="width:50%; text-align:right; color:<?= $current_due_snapshot > 0 ? '#e74c3c' : '#2ecc71' ?>;">Due Snapshot: Rs.<?= number_format($current_due_snapshot, 2) ?></span>
                    </div>
                </div>
            <?php endforeach; if(count($payments)==0) echo "<p style='color:var(--text-muted); text-align:center;'>No financial transactions posted yet.</p>"; ?>
        </div>
    </div>

    <!-- TAB 5: GYM REGISTRY REPORTS -->
    <div id="registry" class="tab-content">
        
        <?php 
        $shifts = ['Morning', 'Evening'];
        foreach($shifts as $current_shift): 
        ?>
            <h4 style="color:var(--accent); margin:15px 0 8px 0; font-size:0.95rem; border-bottom:1px solid var(--border); padding-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">
                ⚡ <?= $current_shift ?> Shift Registry
            </h4>
            
            <?php 
            $shift_count = 0;
            foreach($members as $m): 
                if(strtolower($m['shift']) !== strtolower($current_shift)) continue;
                $shift_count++;
                
                $start = new DateTime($m['starting_date']); 
                $end = clone $start; 
                $end->modify("+".$m['subscription_months']." months");
                $due = floatval($m['package_cost']) - floatval($m['amount_paid']);
                
                $stmt_rows_html = "";
                $running_calc = 0;
                foreach($payments as $p) { 
                    if($p['member_id'] == $m['id']) { 
                        $running_calc += floatval($p['amount']);
                        $snap_due = floatval($m['package_cost']) - $running_calc;
                        $stmt_rows_html .= "<tr>
                            <td style='border:1px solid #000; padding:6px; font-size:11px;'>".$p['payment_date']."</td>
                            <td style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Rs. ".number_format($p['amount'], 2)."</td>
                            <td style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Rs. ".number_format($running_calc, 2)."</td>
                            <td style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Rs. ".number_format($snap_due, 2)."</td>
                        </tr>"; 
                    } 
                }
                if(empty($stmt_rows_html)) { 
                    $stmt_rows_html = "<tr><td colspan='4' style='border:1px solid #000; padding:6px; text-align:center; font-size:11px; color:#555;'>No payments processed on ledger.</td></tr>"; 
                }

                // UNIFIED PROFESSIONAL STATEMENT PRINT SCHEMATIC
                $profileReceiptPrint = "
                <div style='font-family: Arial, sans-serif; color: #000; padding: 10px; box-sizing: border-box;'>
                    <table style='width:100%; border:none; margin-bottom:15px;'>
                        <tr>
                            <td style='width:70px; border:none; vertical-align:middle;'>
                                <div style='width:60px; height:60px; border:2px solid #000; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:bold;'>💪</div>
                            </td>
                            <td style='border:none; vertical-align:middle; padding-left:10px;'>
                                <h2 style='margin:0; font-size:20px; font-weight:900; letter-spacing:0.5px; text-transform:uppercase;'>MUSCLE WORKSHOP GYM</h2>
                                <p style='margin:2px 0 0 0; font-size:11px; color:#333; font-weight:600;'>Corporate Fitness & Strength Arena</p>
                                <p style='margin:1px 0 0 0; font-size:10px; color:#555;'>Gauradaha, Koshi Province, Nepal | PAN No: 302456789</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style='text-align:center; background:#000; color:#fff; font-size:12px; font-weight:bold; padding:4px 0; margin-bottom:15px; text-transform:uppercase; letter-spacing:1px;'>
                        Profile & Payment Receipt Document
                    </div>
                    
                    <table style='width:100%; border-collapse:collapse; margin-bottom:15px;'>
                        <tr>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:25%; background:#f2f2f2;'><b>Member ID:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:25%;'>MW-00".$m['id']."</td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:25%; background:#f2f2f2;'><b>Active Shift:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:25%;'>".$m['shift']."</td>
                        </tr>
                        <tr>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; background:#f2f2f2;'><b>Full Name:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px;'>".htmlspecialchars($m['full_name'])."</td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; background:#f2f2f2;'><b>Address Location:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px;'>".htmlspecialchars($m['address'])."</td>
                        </tr>
                        <tr>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; background:#f2f2f2;'><b>Term Schedule:</b></td>
                            <td colspan='3' style='border:1px solid #000; padding:5px; font-size:11px;'>
                                Since <b>".$start->format('Y-M-d')."</b> to <b>".$end->format('Y-M-d')."</b> (".$m['subscription_months']." Month Subscription)
                            </td>
                        </tr>
                    </table>
                    
                    <h4 style='margin:10px 0 5px 0; font-size:12px; text-transform:uppercase;'>Financial Ledger Statements</h4>
                    <table style='width:100%; border-collapse:collapse;'>
                        <thead>
                            <tr style='background:#f2f2f2;'>
                                <th style='border:1px solid #000; padding:6px; font-size:11px;'>Payment Date/Time</th>
                                <th style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Amount Credited</th>
                                <th style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Cumulative Paid</th>
                                <th style='border:1px solid #000; padding:6px; font-size:11px; text-align:right;'>Balance Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            ".$stmt_rows_html."
                        </tbody>
                    </table>
                    
                    <table style='width:100%; border-collapse:collapse; margin-top:10px;'>
                        <tr>
                            <td style='border:none; width:50%;'></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:30%; background:#f2f2f2;'><b>Total Package Cost:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:20%; text-align:right;'><b>Rs. ".number_format($m['package_cost'], 2)."</b></td>
                        </tr>
                        <tr>
                            <td style='border:none;'></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; background:#f2f2f2;'><b>Total Paid Till Now:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:20%; text-align:right; color:green;'><b>Rs. ".number_format($m['amount_paid'], 2)."</b></td>
                        </tr>
                        <tr>
                            <td style='border:none;'></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; background:#f2f2f2;'><b>Outstanding Balance Due:</b></td>
                            <td style='border:1px solid #000; padding:5px; font-size:11px; width:20%; text-align:right; color:red;'><b>Rs. ".number_format($due, 2)."</b></td>
                        </tr>
                    </table>
                    
                    <div style='margin-top:40px; display:flex; justify-content:space-between; align-items:flex-end;'>
                        <div style='font-size:10px; color:#555; line-height:1.4;'>
                            <span>Generated Via Admin Portal</span><br>
                            <span>Print Date: ".date('Y-m-d H:i:s')."</span>
                        </div>
                        <div style='text-align:center; width:180px;'>
                            <div style='border-bottom:1px solid #000; width:100%; height:30px;'></div>
                            <span style='font-size:10px; font-weight:bold; text-transform:uppercase; margin-top:3px; display:block;'>Authorized Provider Signature</span>
                        </div>
                    </div>
                </div>";
            ?>
                <div class="mini-card" style="border-left:3px solid #3498db; background:#141b29;">
                    <div class="data-line"><strong><?= htmlspecialchars($m['full_name']) ?></strong> <span style="color:var(--text-muted); font-size:0.75rem;">ID: MW-<?= $m['id'] ?></span></div>
                    <div class="data-line" style="font-size:0.75rem; color:#a0aec0;">Period: <?= $start->format('Y-M-d') ?> to <?= $end->format('Y-M-d') ?></div>
                    <div class="data-line" style="font-size:0.8rem;">Paid: Rs.<?= number_format($m['amount_paid'], 2) ?> / Total: Rs.<?= number_format($m['package_cost'], 2) ?></div>
                    <?php if($due>0): ?><div class="data-line" style="color:#e74c3c; font-weight:bold; font-size:0.75rem;">Balance Due: Rs. <?= number_format($due, 2) ?></div><?php endif; ?>
                    
                    <div style="margin-top:10px; display:flex; gap:6px; justify-content:flex-end; border-top:1px solid #1e273a; padding-top:8px;">
                        <button class="btn" style="padding:4px 10px; font-size:0.75rem; background:#e74c3c;" onclick="confirmDeleteMember(<?= $m['id'] ?>)">Delete Profile</button>
                        <button class="btn" style="padding:4px 12px; font-size:0.75rem; background:#27ae60;" onclick="openPrint(`<?= addslashes(preg_replace("/\r|\n/", "", $profileReceiptPrint)) ?>`)">📄 Print Profile & Receipt</button>
                    </div>
                </div>
            <?php 
            endforeach; 
            if($shift_count == 0) echo "<p style='color:var(--text-muted); text-align:center; font-size:0.8rem;'>No members enrolled inside this shift tier.</p>"; 
            ?>
        <?php endforeach; ?>
    </div>

</div>

<!-- CREDENTIALS ROUTING PIPELINES BACKING THE INTERACTIVE NAVIGATION BAR -->
<div class="nav-bar">
    <form id="nav_applicants" action="admin.php?tab=applicants" method="POST" style="display:none;"><input type="hidden" name="username" value="<?=htmlspecialchars($username)?>"><input type="hidden" name="password" value="<?=htmlspecialchars($password)?>"></form>
    <form id="nav_register" action="admin.php?tab=register" method="POST" style="display:none;"><input type="hidden" name="username" value="<?=htmlspecialchars($username)?>"><input type="hidden" name="password" value="<?=htmlspecialchars($password)?>"></form>
    <form id="nav_staff" action="admin.php?tab=staff" method="POST" style="display:none;"><input type="hidden" name="username" value="<?=htmlspecialchars($username)?>"><input type="hidden" name="password" value="<?=htmlspecialchars($password)?>"></form>
    <form id="nav_ledger" action="admin.php?tab=ledger" method="POST" style="display:none;"><input type="hidden" name="username" value="<?=htmlspecialchars($username)?>"><input type="hidden" name="password" value="<?=htmlspecialchars($password)?>"></form>
    <form id="nav_registry" action="admin.php?tab=registry" method="POST" style="display:none;"><input type="hidden" name="username" value="<?=htmlspecialchars($username)?>"><input type="hidden" name="password" value="<?=htmlspecialchars($password)?>"></form>

    <button onclick="document.getElementById('nav_applicants').submit();"><span>📥</span>Intake</button>
    <button onclick="document.getElementById('nav_register').submit();"><span>📝</span>Join</button>
    <button onclick="document.getElementById('nav_staff').submit();"><span>💪</span>Staff</button>
    <button onclick="document.getElementById('nav_ledger').submit();"><span>💰</span>Ledger</button>
    <button onclick="document.getElementById('nav_registry').submit();"><span>📊</span>Reports</button>
</div>

<!-- FLOATING PRINT DIALOG OVERLAY HUB -->
<div id="overlay" class="overlay" onclick="closePrint()"></div>
<div id="printBox" class="print-modal">
    <div id="m-body" style="background:white; color:black;"></div>
    <div class="no-print" style="margin-top:15px; border-top:1px solid #eee; padding-top:10px; display:flex; gap:8px;">
        <button onclick="window.print()" style="width:70%; padding:8px; background:#2ecc71; color:white; border:none; font-weight:bold; cursor:pointer; border-radius:4px;">Execute Print Statement</button>
        <button onclick="closePrint()" style="width:30%; padding:8px; background:#bbb; color:white; border:none; cursor:pointer; border-radius:4px;">Dismiss</button>
    </div>
</div>
    

</body>
</html>
