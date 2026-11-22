<?php $pageTitle ??= 'Boarding Houses & Apartments in Antique'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find verified student boarding houses, apartments, dormitories, and bedspaces in <?= e(DAYON_PROVINCE_LABEL) ?>.">
    <title><?= e($pageTitle) ?> · Dayon</title>
    <link rel="icon" href="<?= e(url('assets/images/logo.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <?php if (!empty($portalRole)): ?><link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>"><?php endif; ?>
    <?php if (!empty($authPage)): ?><link rel="stylesheet" href="<?= e(url('assets/css/account.css')) ?>"><?php endif; ?>
</head>
