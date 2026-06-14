<?php
// ============================================================
// footer.php
// Shared HTML footer. Include at the bottom of every page.
// ============================================================
?>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= h(APP_NAME) ?> &mdash; Happy Holidays! 🎄</p>
    <?php if (IS_DEV): ?>
    <p class="debug-badge">DEV &mdash; <?= h(DB_NAME) ?></p>
    <?php endif; ?>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
