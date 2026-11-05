            </main>
        </div>
    </div>

    <?php require_once __DIR__ . '/vendor-assets.php'; ?>
    <script src="<?= htmlspecialchars(rhu_asset_url('bootstrap_js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= htmlspecialchars(rhu_asset_url('sweetalert2_js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js')) ?>"></script>
    <script>const BASE_URL = '<?= BASE_URL ?>';</script>
    <?php if (!empty($user)): ?>
    <script>window.RHU_USER = <?= json_encode(['role' => $user['role'] ?? '']) ?>;</script>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/patient-search.js?v=10"></script>
    <?php if (!empty($extraJS)): ?>
        <?php foreach ((array)$extraJS as $js): ?>
            <?php if (substr((string) $js, -8) === '/main.js') continue; ?>
            <script src="<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
