<?php
/**
 * Shared "Member" form partial.
 *
 * This single template is rendered by BOTH:
 *   - index.php  (public "Join" page  -> writes to `bookings`)
 *   - admin.php  (admin manual entry  -> writes to `members`)
 *
 * Because both pages use this exact partial, the two forms look
 * identical and collect the same field set, which is what unifies
 * registration and admin management for data consistency.
 *
 * Expected variables (set before including):
 *   $form_action       string  - form action URL
 *   $form_submit_name  string  - name="" of the submit button
 *   $form_submit_label string  - submit button label
 *   $form_hidden_html  string  - extra hidden inputs (e.g. admin creds)
 *   $trainers          array   - rows from `trainers`
 *   $form_values       array   - optional prefill (key => value)
 *   $form_prefix       string  - id prefix to keep ids unique
 */
$form_prefix  = $form_prefix  ?? 'mf';
$trainers     = $trainers     ?? [];
$form_values  = $form_values  ?? [];
$form_hidden_html = $form_hidden_html ?? '';

$fv = function ($key, $default = '') use ($form_values) {
    return htmlspecialchars($form_values[$key] ?? $default, ENT_QUOTES);
};
?>
<form action="<?= htmlspecialchars($form_action, ENT_QUOTES) ?>" method="POST" class="member-form" novalidate>
    <?= $form_hidden_html ?>

    <div class="mf-grid">
        <div class="form-group mf-full">
            <label for="<?= $form_prefix ?>_full_name">Full Name</label>
            <input type="text" id="<?= $form_prefix ?>_full_name" name="full_name" value="<?= $fv('full_name') ?>" placeholder="e.g. Ram Sharma" required autocomplete="name">
        </div>

        <div class="form-group mf-full">
            <label for="<?= $form_prefix ?>_address">Address</label>
            <input type="text" id="<?= $form_prefix ?>_address" name="address" value="<?= $fv('address') ?>" placeholder="City / locality" required autocomplete="street-address">
        </div>

        <div class="form-group mf-full">
            <label for="<?= $form_prefix ?>_phone_no">Phone Number</label>
            <input type="tel" id="<?= $form_prefix ?>_phone_no" name="phone_no" value="<?= $fv('phone_no') ?>" placeholder="98XXXXXXXX" required autocomplete="tel">
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_shift">Preferred Shift</label>
            <select id="<?= $form_prefix ?>_shift" name="shift">
                <option value="Morning" <?= ($fv('shift', 'Morning') === 'Morning') ? 'selected' : '' ?>>Morning</option>
                <option value="Evening" <?= ($fv('shift') === 'Evening') ? 'selected' : '' ?>>Evening</option>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_starting_date">Starting Date</label>
            <input type="date" id="<?= $form_prefix ?>_starting_date" name="starting_date" value="<?= $fv('starting_date', date('Y-m-d')) ?>" required>
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_subscription_months">Subscription (Months)</label>
            <select id="<?= $form_prefix ?>_subscription_months" name="subscription_months" onchange="mfAutoCost('<?= $form_prefix ?>')">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= ((int)($fv('subscription_months', '1')) === $i) ? 'selected' : '' ?>><?= $i ?> Month<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_trainer_id">Assigned Trainer</label>
            <select id="<?= $form_prefix ?>_trainer_id" name="trainer_id">
                <option value="">Self Guided</option>
                <?php foreach ($trainers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= ((int)($fv('trainer_id')) === (int)$t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_package_cost">Package Cost (Rs.)</label>
            <input type="number" id="<?= $form_prefix ?>_package_cost" name="package_cost" value="<?= $fv('package_cost', '1500') ?>" min="0" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="<?= $form_prefix ?>_amount_paid">Amount Paid (Rs.)</label>
            <input type="number" id="<?= $form_prefix ?>_amount_paid" name="amount_paid" value="<?= $fv('amount_paid', '0') ?>" min="0" step="0.01" required>
        </div>
    </div>

    <button type="submit" name="<?= htmlspecialchars($form_submit_name, ENT_QUOTES) ?>" class="btn mf-submit"><?= htmlspecialchars($form_submit_label, ENT_QUOTES) ?></button>
</form>

<script>
    // Tiered pricing map shared by every member form instance.
    if (typeof window.MF_PRICING === 'undefined') {
        window.MF_PRICING = {1:1500,2:2800,3:3900,4:5000,5:6000,6:6600,7:7350,8:8000,9:8550,10:9000,11:9350,12:9450};
    }
    function mfAutoCost(prefix) {
        var months = document.getElementById(prefix + '_subscription_months').value;
        var costEl = document.getElementById(prefix + '_package_cost');
        if (costEl && window.MF_PRICING[months] && !costEl.dataset.touched) {
            costEl.value = window.MF_PRICING[months];
        }
    }
    // Mark package cost as manually edited so auto-fill stops overriding it.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.member-form input[name="package_cost"]').forEach(function (el) {
            el.addEventListener('input', function () { el.dataset.touched = '1'; });
        });
    });
</script>
