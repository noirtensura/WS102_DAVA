<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
$admin = require_role('admin');

$search = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
$roleFilter = is_string($_GET['role'] ?? null) && in_array($_GET['role'], ['student', 'landlord', 'admin'], true) ? $_GET['role'] : '';
$searchPattern = '%' . $search . '%';
$params = [$searchPattern, $searchPattern];
$roleClause = $roleFilter ? 'AND r.role_name = ?' : '';
if ($roleFilter) {
    $params[] = $roleFilter;
}

$accounts = rows("SELECT u.*, r.role_name, s.school_id_number, s.course_program, lp.verification_status
    FROM users u JOIN roles r ON r.role_id = u.role_id
    LEFT JOIN student_profiles s ON s.student_id = u.user_id
    LEFT JOIN landlord_profiles lp ON lp.landlord_id = u.user_id
    WHERE (u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?) {$roleClause}
    ORDER BY FIELD(r.role_name, 'admin', 'landlord', 'student'), u.created_at DESC, u.user_id DESC", $params);

$pageTitle = 'User Accounts';
$pageDescription = 'Manage student, landlord, and administrator accounts, reset passwords, or add a new admin.';
require __DIR__ . '/../includes/portal_header.php';
?>

<section class="panel">
    <details class="form-disclosure">
        <summary><?= icon('plus') ?> Add New Administrator Account</summary>
        <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="create_admin">
            <label class="form-field">First name <input name="first_name" required maxlength="80"></label>
            <label class="form-field">Last name <input name="last_name" required maxlength="80"></label>
            <label class="form-field">Email address <input type="email" name="email" required maxlength="150"></label>
            <label class="form-field">Phone number <input type="tel" name="phone_number" required maxlength="20" placeholder="09XX XXX XXXX"></label>
            <label class="form-field span-2">Password (at least 8 characters) <input type="password" name="password" required minlength="8" maxlength="72"></label>
            <div class="actions span-2"><button type="submit" class="button small">Create Admin Account</button></div>
        </form>
    </details>

    <form method="get" class="form-toolbar" action="<?= e(url('admin/users.php')) ?>" style="margin-top:14px;">
        <label class="form-field">Search name or email <input type="search" name="q" value="<?= e($search) ?>" maxlength="150" placeholder="Name or email..."></label>
        <label class="form-field">Role
            <select name="role">
                <option value="">All roles</option>
                <option value="student"<?= $roleFilter === 'student' ? ' selected' : '' ?>>Students</option>
                <option value="landlord"<?= $roleFilter === 'landlord' ? ' selected' : '' ?>>Landlords</option>
                <option value="admin"<?= $roleFilter === 'admin' ? ' selected' : '' ?>>Administrators</option>
            </select>
        </label>
        <button type="submit" class="button secondary"><?= icon('search') ?> Filter</button>
        <?php if ($search !== '' || $roleFilter !== ''): ?><a class="button secondary" href="<?= e(url('admin/users.php')) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if ($accounts === []): ?>
        <p class="empty-state">No accounts match your search.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Account</th><th scope="col">Role &amp; Details</th><th scope="col">Status</th><th scope="col">Management</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><strong><?= e($account['first_name'] . ' ' . $account['last_name']) ?></strong><br><span class="muted"><?= e($account['email']) ?></span></td>
                        <td>
                            <span class="badge"><?= e(admin_status_label($account['role_name'])) ?></span>
                            <?php if ($account['school_id_number'] !== null): ?><br><span class="muted"><?= e($account['school_id_number']) ?> · <?= e($account['course_program']) ?></span><?php endif; ?>
                            <?php if ($account['verification_status'] !== null): ?><br><span class="muted">Landlord: <?= e(admin_status_label($account['verification_status'])) ?></span><?php endif; ?>
                            <br><span class="muted"><?= e($account['phone_number']) ?></span>
                        </td>
                        <td><span class="badge <?= e($account['account_status']) ?>"><?= e(admin_status_label($account['account_status'])) ?></span><br><span class="muted">Joined <?= e(substr($account['created_at'], 0, 10)) ?></span></td>
                        <td>
                            <?php if ($account['role_name'] === 'admin'): ?>
                                <span class="muted"><?= (int) $account['user_id'] === (int) $admin['user_id'] ? 'Current session' : 'Administrator' ?></span>
                            <?php else: ?>
                                <?php admin_status_form('user', (int) $account['user_id'], [
                                    $account['account_status'] === 'active' ? 'suspended' : 'active' => $account['account_status'] === 'active' ? 'Suspend account' : 'Activate account',
                                ], true, $search); ?>
                                <details style="margin-top:8px;">
                                    <summary><small>Reset password</small></summary>
                                    <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="decision-form" style="margin-top:6px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="operation" value="reset_password">
                                        <input type="hidden" name="id" value="<?= (int) $account['user_id'] ?>">
                                        <input type="hidden" name="account_search" value="<?= e($search) ?>">
                                        <input type="password" name="new_password" minlength="8" maxlength="72" required placeholder="New temp password (8+ chars)">
                                        <button type="submit" class="button small secondary">Set password</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
