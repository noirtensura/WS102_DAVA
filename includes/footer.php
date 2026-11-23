<?php
declare(strict_types=1);
if (empty($portalRole) && empty($authPage)): ?>
<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <?php require __DIR__ . '/brand.php'; ?>
            <p class="muted">Boarding houses, apartments, dormitories &amp; student rentals across <?= e(DAYON_PROVINCE_LABEL) ?>.</p>
        </div>
        <nav class="footer-links" aria-label="Footer">
            <a href="<?= e(url('index.php#listings')) ?>">Find Housing</a>
            <a href="<?= e(url('register.php?role=landlord')) ?>">List a Property</a>
            <a href="<?= e(url('login.php')) ?>">Sign In</a>
            <a href="<?= e(url('login.php?portal=admin')) ?>">Admin Portal</a>
        </nav>
    </div>
</footer>
<?php endif; ?>
<?php if (!empty($portalRole)): ?>
    <dialog class="confirmation-dialog" role="alertdialog" aria-labelledby="confirm-title" aria-describedby="confirm-message" data-confirm-dialog>
        <form method="dialog">
            <h2 id="confirm-title">Confirm this action</h2>
            <p id="confirm-message"></p>
            <div class="actions">
                <button class="button secondary" value="cancel" autofocus>Keep unchanged</button>
                <button class="button danger" value="confirm" data-confirm-submit>Confirm</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= e(url($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
