<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$search = substr($search, 0, 150);
$searchPattern = '%' . $search . '%';
$accounts = rows("SELECT u.*, r.role_name, s.school_id_number, s.course_program, lp.verification_status
    FROM users u JOIN roles r ON r.role_id = u.role_id
    LEFT JOIN student_profiles s ON s.student_id = u.user_id
    LEFT JOIN landlord_profiles lp ON lp.landlord_id = u.user_id
    WHERE r.role_name IN ('landlord', 'student')
      AND (u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
    ORDER BY u.created_at DESC, u.user_id DESC", [$searchPattern, $searchPattern]);
$pageTitle = 'User accounts';
$pageDescription = 'Look up student and landlord accounts and manage their access.';
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <form method="get" class="form-grid" action="<?= e(url('admin/users.php')) ?>">
        <label class="form-field">Name or email <input type="search" name="q" value="<?= e($search) ?>" maxlength="150"></label>
        <div><button type="submit" class="button secondary small">Search accounts</button></div>
    </form>
    <?php if ($accounts === []): ?>
        <p class="empty-state">No accounts match your search.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Account</th><th scope="col">Details</th><th scope="col">Access</th><th scope="col">Management</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><strong><?= e($account['first_name'] . ' ' . $account['last_name']) ?></strong><br><span class="muted"><?= e($account['email']) ?></span></td>
                        <td>
                            <?= e(admin_status_label($account['role_name'])) ?>
                            <?php if ($account['school_id_number'] !== null): ?><br><span class="muted"><?= e($account['school_id_number']) ?> · <?= e($account['course_program']) ?></span><?php endif; ?>
                            <?php if ($account['verification_status'] !== null): ?><br><span class="muted">Verification <?= e(admin_status_label($account['verification_status'])) ?></span><?php endif; ?>
                            <br><span class="muted"><?= e($account['phone_number']) ?></span>
                        </td>
                        <td><span class="badge"><?= e(admin_status_label($account['account_status'])) ?></span><br><span class="muted">Joined <?= e(substr($account['created_at'], 0, 10)) ?></span></td>
                        <td><?php admin_status_form('user', (int) $account['user_id'], [
                            $account['account_status'] === 'active' ? 'suspended' : 'active' => $account['account_status'] === 'active' ? 'Suspend account' : 'Activate account',
                        ], true); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
