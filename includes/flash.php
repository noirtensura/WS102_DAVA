<?php
declare(strict_types=1);
(static function (): void {
    $notices = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!$notices) {
        return;
    }
    ?>
    <div class="flash-stack" aria-live="polite">
        <?php foreach ($notices as $notice): ?>
            <div class="flash flash-<?= e($notice['type']) ?>" role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($notice['message']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php
})();
