        </main>
        <footer class="app-footer">
            <span><i class="bi bi-droplet-fill" aria-hidden="true"></i> AquaPure Water Refilling Station</span>
            <span>&copy; <?= date("Y") ?></span>
        </footer>
    </div>
</div>
<?php require __DIR__ . '/confirmation-modal.php'; ?>
<script src="<?= e(
    url("assets/vendor/bootstrap/js/bootstrap.bundle.min.js"),
) ?>"></script>
<script src="<?= e(url("assets/js/app.js?v=8")) ?>"></script>
<?php unset($_SESSION['aq_failed_form']); ?>
</body>
</html>
