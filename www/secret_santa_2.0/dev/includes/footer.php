<?php
// ============================================================
// footer.php
// Shared HTML footer. Include at the bottom of every page.
// ============================================================
?>
</main>

<footer class="site-footer">
    <p>
        Happy Holidays! 🎄
        &nbsp;&bull;&nbsp;
        <a href="<?= APP_URL ?>/pages/changelog.php">v<?= h(getConfig('APP_VERSION', '2.0')) ?></a>
    </p>
    <?php if (IS_DEV): ?>
    <p class="debug-badge">DEV &mdash; <?= h(DB_NAME) ?></p>
    <?php endif; ?>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
