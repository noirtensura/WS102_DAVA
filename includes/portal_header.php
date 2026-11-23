<?php
declare(strict_types=1);
$portalRole = $user['role_name'];
$navBadges = [];
if ($portalRole === 'admin') {
    $navBadges['admin/verifications.php'] = (int) scalar("SELECT (SELECT COUNT(*) FROM landlord_profiles WHERE verification_status = 'pending') + (SELECT COUNT(*) FROM landlord_permits WHERE verification_status = 'submitted')");
    $navBadges['admin/properties.php'] = (int) scalar("SELECT COUNT(*) FROM properties WHERE approval_status = 'pending'");
    $navBadges['admin/reports.php'] = (int) scalar("SELECT COUNT(*) FROM reports WHERE status IN ('open', 'reviewed')");
    $reviewCount = $navBadges['admin/verifications.php'] + $navBadges['admin/properties.php'];
} elseif ($portalRole === 'landlord') {
    $navBadges['landlord/reservations.php'] = (int) scalar("SELECT COUNT(*) FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND q.status = 'pending'", [$user['user_id']]);
} elseif ($portalRole === 'student') {
    $navBadges['student/bookmarks.php'] = student_bookmark_count($user);
}

$menus = [
    'student' => [
        ['student/dashboard.php', 'Overview', 'home'],
        ['index.php#listings', 'Find Housing', 'search'],
        ['student/bookmarks.php', 'Bookmarked Houses', 'bookmark'],
        ['student/reservations.php', 'My Requests', 'calendar'],
        ['student/dashboard.php#active-lease', 'Active Lease', 'file'],
        ['student/billing.php', 'Rent & Receipts', 'wallet'],
        ['student/maintenance.php', 'Maintenance', 'tool'],
        ['student/profile.php', 'My Profile', 'user'],
    ],
    'landlord' => [
        ['landlord/dashboard.php', 'Dashboard', 'grid'],
        ['landlord/properties.php', 'Properties & Photos', 'building'],
        ['landlord/rooms.php', 'Rooms & Occupancy', 'bed'],
        ['landlord/reservations.php', 'Visit & Bed Requests', 'calendar'],
        ['landlord/leases.php', 'Tenant Leases', 'users'],
        ['landlord/billing.php', 'Billing & Utilities', 'wallet'],
        ['landlord/maintenance.php', 'Maintenance & Notices', 'tool'],
        ['landlord/verification.php', 'Permits & Trust', 'shield'],
    ],
    'admin' => [
        ['admin/dashboard.php', 'Platform Overview', 'grid'],
        ['admin/verifications.php', 'Landlords & Permits', 'shield'],
        ['admin/properties.php', 'Property Approvals', 'building'],
        ['admin/users.php', 'User Accounts', 'users'],
        ['admin/catalogs.php', 'Amenities & Areas', 'layers'],
        ['admin/reports.php', 'Flagged Reports', 'report'],
        ['admin/operations.php', 'Housing Operations', 'bed'],
        ['admin/audit_logs.php', 'Audit Logs', 'file'],
    ],
];
$portalRoute = $portalRole . '/' . basename($_SERVER['SCRIPT_NAME']);
$portalLease = $portalRole === 'student' ? ($lease ?? active_lease((int) $user['user_id'])) : null;
$studentLeaseRoutes = ['student/dashboard.php#active-lease', 'student/billing.php', 'student/maintenance.php'];
$portalLinks = $portalRole === 'student' && !$portalLease
    ? array_filter($menus[$portalRole], static fn (array $link): bool => !in_array($link[0], $studentLeaseRoutes, true))
    : $menus[$portalRole];
require __DIR__ . '/document_head.php';
?>
<body class="portal-body portal-<?= e($portalRole) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="portal-layout">
    <aside class="portal-sidebar" aria-label="<?= e(ucfirst($portalRole)) ?> workspace">
        <div class="portal-brand"><?php require __DIR__ . '/brand.php'; ?></div>
        <details class="portal-navigation navigation-disclosure" data-navigation="860" open>
            <summary><?= icon('menu') ?> <span>Menu</span> <?= icon('chevron', 'menu-chevron') ?></summary>
            <div class="portal-navigation-content">
                <nav class="portal-menu" aria-label="Workspace pages">
                    <?php foreach ($portalLinks as [$route, $label, $glyph]):
                        $count = $navBadges[$route] ?? 0;
                    ?>
                        <a href="<?= e(url($route)) ?>"<?= $portalRoute === $route ? ' aria-current="page"' : '' ?>><?= icon($glyph) ?><span><?= e($label) ?></span>
                            <?php if ($count > 0): ?><span class="menu-count"><?= $count ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="portal-sidebar-bottom">
                    <?php if ($portalRole !== 'student'): ?>
                        <a class="portal-public-link" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> Public finder</a>
                    <?php endif; ?>
                    <div class="portal-identity">
                        <span class="avatar" aria-hidden="true"><?= e(user_initials($user)) ?></span>
                        <div><strong><?= e($user['full_name']) ?></strong><span><?= e(ucfirst($portalRole)) ?> workspace</span></div>
                    </div>
                    <form method="post" action="<?= e(url('actions/auth_logout.php')) ?>" class="portal-logout">
                        <?= csrf_field() ?>
                        <button type="submit"><?= icon('logout') ?> Sign Out</button>
                    </form>
                </div>
            </div>
        </details>
    </aside>
    <main id="main" class="portal-main">
        <?php require __DIR__ . '/flash.php'; ?>
        <header class="portal-page-heading<?= !empty($pageHeaderClass) ? ' ' . e($pageHeaderClass) : '' ?>">
            <div>
                <h1><?= e($pageTitle) ?></h1>
                <?php if (!empty($pageDescription)): ?><p><?= e($pageDescription) ?></p><?php endif; ?>
            </div>
            <?php if (isset($pageActions)): ?><div class="portal-page-actions"><?php $pageActions(); ?></div><?php endif; ?>
        </header>
