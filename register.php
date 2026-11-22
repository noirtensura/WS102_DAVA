<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($user) {
    redirect(dashboard_path($user['role_name']));
}
$role = ($_GET['role'] ?? '') === 'landlord' ? 'landlord' : 'student';
$next = post_login_path(is_string($_GET['next'] ?? null) ? $_GET['next'] : '');
$old = $_SESSION['register_values'] ?? [];
unset($_SESSION['register_values']);
$pageTitle = 'Create an Account';
$authPage = true;
$pageScripts = ['assets/js/account.js'];
require __DIR__ . '/includes/document_head.php';
?>
<body class="account-body">
<a class="skip-link" href="#main">Skip to content</a>
<main id="main" class="account-layout register-layout">
    <section class="account-illustration">
        <?php require __DIR__ . '/includes/brand.php'; ?>
        <div class="account-story">
            <h2><?= $role === 'landlord' ? 'List Your Boarding House or Apartment' : 'Find Student Housing Made Simple' ?></h2>
            <p><?= $role === 'landlord'
                ? 'Post photos, choose which necessities and house info to show, and optionally add local Antique permits.'
                : 'Bookmark boarding houses & apartments, compare options side by side, and track rent across ' . e(DAYON_PROVINCE) . '.' ?></p>
            <ol class="signup-steps">
                <li aria-current="step"><span class="step-dot"><?= icon('check') ?></span><span>1. Create Free Account</span></li>
                <li><span class="step-dot">2</span><span><?= $role === 'landlord' ? 'Post Property Details & Photos' : 'Browse & Bookmark Listings' ?></span></li>
                <li><span class="step-dot">3</span><span><?= $role === 'landlord' ? 'Optional Antique Permits & Rooms' : 'Visit, Reserve & Track Rent' ?></span></li>
            </ol>
            <div class="signup-location"><?= icon('shield') ?><span><?= $role === 'landlord' ? 'No business permit required to list<br>Optional Antique permits earn trust badges' : 'Admin-reviewed boarding houses &amp; apartments<br>' . e(DAYON_PROVINCE_LABEL) ?></span></div>
        </div>
    </section>
    <div class="account-stage">
        <section class="account-card">
            <h1>Create your Dayon account</h1>
            <?php require __DIR__ . '/includes/flash.php'; ?>
            <nav class="role-tabs" aria-label="Choose account type">
                <?php foreach (['student' => ['Student / Tenant', 'Bookmark, compare & book rooms'], 'landlord' => ['Landlord / Owner', 'Post boarding houses & apartments']] as $value => [$label, $sub]): ?>
                    <a href="<?= e(url('register.php?' . http_build_query(['role' => $value, 'next' => $next]))) ?>"<?= $role === $value ? ' aria-current="page"' : '' ?>>
                        <strong><?= e($label) ?><?php if ($role === $value): ?><span class="role-check" aria-hidden="true"><?= icon('check') ?></span><?php endif; ?></strong>
                        <small><?= e($sub) ?></small>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="post" action="<?= e(url('actions/auth_register.php')) ?>" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="account-field-grid">
                    <label>First name<input name="first_name" value="<?= e($old['first_name'] ?? '') ?>" maxlength="80" autocomplete="given-name" required></label>
                    <label>Last name<input name="last_name" value="<?= e($old['last_name'] ?? '') ?>" maxlength="80" autocomplete="family-name" required></label>
                    <label class="mobile-span-2">Email address<input name="email" value="<?= e($old['email'] ?? '') ?>" type="email" maxlength="150" autocomplete="email" required></label>
                    <label class="mobile-span-2">Mobile number<input name="phone_number" value="<?= e($old['phone_number'] ?? '') ?>" type="tel" maxlength="20" autocomplete="tel" placeholder="09XX XXX XXXX" required></label>
                    <?php if ($role === 'student'): ?>
                        <label>Student ID<input name="school_id_number" value="<?= e($old['school_id_number'] ?? '') ?>" maxlength="50" required></label>
                        <label>Year level
                            <select name="year_level" required>
                                <option value="">Select year</option>
                                <?php for ($year = 1; $year <= 6; $year++): ?>
                                    <option value="<?= $year ?>"<?= (int) ($old['year_level'] ?? 0) === $year ? ' selected' : '' ?>>Year <?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label class="span-2">Course or program<input name="course_program" value="<?= e($old['course_program'] ?? '') ?>" maxlength="120" placeholder="e.g. BS Information Technology" required></label>
                    <?php endif; ?>
                    <?php foreach (['password' => 'Password', 'confirmation' => 'Confirm password'] as $field => $label): ?>
                        <div class="password-field mobile-span-2">
                            <label for="register-<?= e($field) ?>"><?= e($label) ?></label>
                            <input id="register-<?= e($field) ?>" name="<?= e($field) ?>" type="password" minlength="8" maxlength="72" autocomplete="new-password"<?= $field === 'password' ? ' aria-describedby="password-requirement"' : '' ?> required>
                            <button type="button" class="password-toggle" aria-label="Show password" aria-controls="register-<?= e($field) ?>" aria-pressed="false" data-password-toggle hidden>
                                <span data-password-hidden><?= icon('eye-off') ?></span><span data-password-visible hidden><?= icon('eye') ?></span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="muted" id="password-requirement">Use at least 8 characters. <?= $role === 'landlord' ? 'You can immediately add your boarding house or apartment after signing up.' : '' ?></small>
                <button class="button wide" type="submit">Create <?= $role === 'landlord' ? 'Landlord' : 'Student' ?> Account</button>
            </form>
            <p class="account-switch">Already have an account? <a href="<?= e(url('login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Log In</a></p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
