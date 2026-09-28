<?php $u = current_user(); ?>
<?php if ($u): ?>
    </main>
    <footer class="footer">
      <div>
        &copy; <?= date('Y') ?> <strong><?= htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME)) ?></strong> &mdash; <?= htmlspecialchars(get_setting($pdo, 'app_subtitle', 'Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip')) ?>
      </div>
      <div>
        Craft by <strong style="color:var(--gold);">Letda Czi Fris Wardani</strong>
      </div>
    </footer>
  </div>
</div>
<?php else: ?>
  <footer style="text-align:center;padding:20px 16px 28px;font-size:12px;color:var(--text-dim);line-height:1.6;">
    <div>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME)) ?></strong> &mdash; <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?></div>
    <div style="margin-top:3px;">Craft by <strong style="color:var(--gold);">Letda Czi Fris Wardani</strong></div>
  </footer>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
