<?php
// ============================================================
// footer.php
// Shared HTML footer. Include at the bottom of every page.
// ============================================================
?>
</main>

<footer class="site-footer">
    <p>
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> &mdash; Happy Holidays! 🎄
        &nbsp;&bull;&nbsp;
        <a href="<?= APP_URL ?>/pages/changelog.php" class="version-link">v<?= h(getConfig('APP_VERSION', '2.0')) ?></a>
    </p>
    <?php if (IS_DEV): ?>
    <p class="debug-badge">DEV &mdash; <?= h(DB_NAME) ?></p>
    <?php endif; ?>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>