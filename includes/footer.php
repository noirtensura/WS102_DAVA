<?php if (empty($portalRole) && empty($authPage)): ?>
<footer class="site-footer">
    <div class="container footer-inner">
        <?php require __DIR__ . '/brand.php'; ?>
        <span>Student housing in <?= e(DAYON_LOCATION) ?></span>
        <a href="<?= e(url('index.php#listings')) ?>">Find Rooms <?= icon('arrow') ?></a>
    </div>
</footer>
<?php endif; ?>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= e(url($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
