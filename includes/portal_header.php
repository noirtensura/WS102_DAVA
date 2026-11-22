<?php
$portalRole = $user['role_name'];
$menus = [
    'student' => [
        ['student/dashboard.php', 'Overview', 'home'],
        ['index.php#listings', 'Find Rooms', 'search'],
        ['student/bookmarks.php', 'Saved & Compare', 'bookmark'],
        ['student/reservations.php', 'My Reservations', 'calendar'],
        ['student/dashboard.php#active-lease', 'Active Lease', 'file'],
        ['student/billing.php', 'Rent & Receipts', 'wallet'],
        ['student/maintenance.php', 'Maintenance', 'tool'],
        ['student/profile.php', 'My Profile', 'user'],
    ],
    'landlord' => [
        ['landlord/dashboard.php', 'Dashboard', 'grid'],
        ['landlord/properties.php', 'Properties & Rooms', 'home'],
        ['landlord/rooms.php', 'Occupancy Board', 'bed'],
        ['landlord/reservations.php', 'Visit & Slot Requests', 'calendar'],
        ['landlord/leases.php', 'Tenant Leases', 'users'],
        ['landlord/billing.php', 'Billing & Utilities', 'wallet'],
        ['landlord/maintenance.php', 'Maintenance', 'tool'],
        ['landlord/verification.php', 'Verification', 'shield'],
    ],
    'admin' => [
        ['admin/dashboard.php', 'Platform Overview', 'grid'],
        ['admin/dashboard.php#verification-queue', 'Verification Queue', 'shield'],
        ['admin/verifications.php', 'Landlord Review', 'users'],
        ['admin/properties.php', 'Property Approvals', 'home'],
        ['admin/users.php', 'User Management', 'user'],
        ['admin/reports.php', 'Flagged Reports', 'report'],
        ['admin/operations.php', 'Housing Operations', 'bed'],
        ['admin/audit_logs.php', 'Audit Logs', 'file'],
    ],
];
$portalRoute = $portalRole . '/' . basename($_SERVER['SCRIPT_NAME']);
$portalLease = $portalRole === 'student' ? ($lease ?? active_lease((int) $user['user_id'])) : null;
require __DIR__ . '/document_head.php';
?>
<body class="portal-body portal-<?= e($portalRole) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="portal-layout">
    <aside class="portal-sidebar" aria-label="<?= e(ucfirst($portalRole)) ?> workspace">
        <div class="portal-brand"><?php require __DIR__ . '/brand.php'; ?></div>
        <nav class="portal-menu" aria-label="Workspace pages">
            <?php foreach ($menus[$portalRole] as [$route, $label, $glyph]): ?>
                <?php if ($label === 'Active Lease' && !$portalLease) continue; ?>
                <a href="<?= e(url($route)) ?>"<?= $portalRoute === $route ? ' aria-current="page"' : '' ?>><?= icon($glyph) ?><span><?= e($label) ?></span>
                    <?php if ($label === 'Verification Queue' && !empty($reviewCount)): ?><span class="menu-count"><?= (int) $reviewCount ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="portal-sidebar-bottom">
            <?php if ($portalRole !== 'student'): ?>
                <a class="portal-public-link" href="<?= e(url()) ?>"><?= icon('arrow-left') ?> Public finder</a>
            <?php endif; ?>
            <div class="portal-identity">
                <span class="avatar" aria-hidden="true"><?= e(user_initials($user)) ?></span>
                <div><strong><?= e($user['full_name']) ?></strong><span><?= e(ucfirst($portalRole)) ?></span></div>
            </div>
            <form method="post" action="<?= e(url('actions/auth_logout.php')) ?>" class="portal-logout">
                <?= csrf_field() ?>
                <button type="submit"><?= icon('logout') ?> Sign Out</button>
            </form>
        </div>
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
