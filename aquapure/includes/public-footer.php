<?php $station = station_settings(); ?>
<footer class="site-footer">
    <div class="container py-4">
        <div class="row g-3 align-items-start">
            <div class="col-md-5">
                <a class="brand text-white mb-2" href="<?= e(url()) ?>">
                    <img class="brand-logo" src="<?= e(url('assets/images/aquapure-logo-transparent.png')) ?>" width="40" height="40" alt="" aria-hidden="true">
                    <span>AquaPure</span>
                </a>
                <p class="mb-0 footer-muted">Clean water, dependable service, and simple ordering for every home and business.</p>
            </div>
            <div class="col-md-3">
                <h2 class="h6 text-white">Services</h2>
                <p class="mb-1 footer-muted">Water refill</p>
                <p class="mb-1 footer-muted">Pickup orders</p>
                <p class="mb-0 footer-muted">Local delivery</p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 text-white">Station information</h2>
                <?php if ($station['station_contact'] !== ''): ?>
                    <p class="mb-1 footer-muted"><i class="bi bi-telephone me-2"></i><?= e($station['station_contact']) ?></p>
                <?php endif; ?>
                <?php if ($station['station_address'] !== ''): ?>
                    <p class="mb-1 footer-muted"><i class="bi bi-geo-alt me-2"></i><?= e($station['station_address']) ?></p>
                <?php endif; ?>
                <?php if ($station['business_hours'] !== ''): ?>
                    <p class="mb-0 footer-muted"><i class="bi bi-clock me-2"></i><?= e($station['business_hours']) ?></p>
                <?php endif; ?>
                <?php if ($station['station_contact'] === '' && $station['station_address'] === '' && $station['business_hours'] === ''): ?>
                    <p class="mb-0 footer-muted">Station contact information is currently unavailable.</p>
                <?php endif; ?>
            </div>
        </div>
        <hr class="border-secondary my-3">
        <p class="small footer-muted mb-0">&copy; <?= date('Y') ?> <?= e($station['station_name'] ?: APP_NAME) ?>. All rights reserved.</p>
    </div>
</footer>
<script src="<?= e(url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js?v=8')) ?>"></script>
<?php unset($_SESSION['aq_failed_form']); ?>
</body>
</html>
