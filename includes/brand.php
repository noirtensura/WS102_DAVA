<a class="brand" href="<?= e(url()) ?>" aria-label="<?= ($portalRole ?? '') === 'admin' ? 'Dayon Admin home' : 'Dayon home' ?>">
    <img src="<?= e(url('assets/images/logo.svg')) ?>" width="43" height="43" alt="">
    <span>Dayon<?php if (($portalRole ?? '') === 'admin'): ?><small> Admin</small><?php endif; ?></span>
</a>
