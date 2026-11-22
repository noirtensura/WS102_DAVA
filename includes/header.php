<?php require __DIR__ . '/document_head.php'; ?>
<body class="public-body<?= !empty($isPropertyPage) ? ' detail-page' : '' ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="site-nav">
        <div class="nav-brand">
            <?php require __DIR__ . '/brand.php'; ?>
            <?php if (!empty($isPropertyPage)): ?>
                <a class="nav-back" href="<?= e(url('index.php#listings')) ?>"><?= icon('arrow-left') ?> Back to Search</a>
            <?php endif; ?>
        </div>
        <nav class="public-nav" aria-label="Main navigation">
            <a href="<?= e(url('index.php#listings')) ?>">Find Rooms</a>
            <?php if (empty($isPropertyPage)): ?><a href="<?= e(url('index.php#how-it-works')) ?>">How It Works</a><?php endif; ?>
            <a href="<?= e(url(($user['role_name'] ?? '') === 'landlord' ? 'landlord/properties.php' : 'index.php#for-landlords')) ?>">For Landlords</a>
            <?php if (($user['role_name'] ?? '') === 'student'): ?>
                <a href="<?= e(url(!empty($isPropertyPage) ? 'student/reservations.php' : 'student/bookmarks.php')) ?>"><?= empty($isPropertyPage) ? 'Saved Rooms' : 'My Requests' ?></a>
            <?php endif; ?>
        </nav>
        <div class="nav-account">
            <?php if ($user): ?>
                <?php if (!empty($isPropertyPage)): ?>
                    <a class="avatar nav-avatar" href="<?= e(url(dashboard_path($user['role_name']))) ?>" aria-label="Go to dashboard"><?= icon('user') ?></a>
                <?php else: ?>
                    <a class="button button-nav" href="<?= e(url(dashboard_path($user['role_name']))) ?>">Dashboard <?= icon('arrow') ?></a>
                <?php endif; ?>
            <?php else: ?>
                <a class="button button-nav secondary" href="<?= e(url('login.php')) ?>">Log In</a>
                <a class="button button-nav" href="<?= e(url('register.php')) ?>">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="container"><?php require __DIR__ . '/flash.php'; ?></div>
