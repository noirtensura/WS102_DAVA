<?php $pageTitle ??= 'Student boarding near campus'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find verified student boarding houses and live vacancies in <?= e(DAYON_LOCATION) ?>.">
    <title><?= e($pageTitle) ?> · Dayon</title>
    <link rel="icon" href="<?= e(url('assets/images/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <?php if (!empty($portalRole)): ?><link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>"><?php endif; ?>
    <?php if (!empty($authPage)): ?><link rel="stylesheet" href="<?= e(url('assets/css/account.css')) ?>"><?php endif; ?>
</head>
