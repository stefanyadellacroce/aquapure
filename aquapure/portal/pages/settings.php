<?php
if (!is_admin()) {
    http_response_code(403);
    exit("Admin access required.");
}

$defaults = [
    "station_name" => APP_NAME,
    "station_contact" => "",
    "station_address" => "",
    "business_hours" => "",
];
$rows = db()
    ->query("SELECT setting_key, setting_value FROM settings")
    ->fetchAll();
$settings = $defaults;
foreach ($rows as $row) {
    if (array_key_exists($row["setting_key"], $settings)) {
        $settings[$row["setting_key"]] = (string) $row["setting_value"];
    }
}
$settings = aq_form_values('save_settings', $settings);
?>
<header class="page-header">
    <div>
    <p class="eyebrow mb-1">Administrator only</p>
    <h1 class="section-title mb-1">System settings</h1>
    <p class="text-secondary mb-0">Update the station details shown in internal records and reports.</p>
    </div>
</header>

<section class="panel-card operations-form-sm">
    <div class="mb-4"><h2 class="h5 mb-1">Station information</h2><p class="small text-secondary mb-0">These details appear in public station information and reports.</p></div>
    <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">
        <div class="col-12">
            <label class="form-label" for="station_name">Station name <span class="text-danger" aria-hidden="true">*</span></label>
            <input class="form-control" id="station_name" name="station_name" value="<?= e($settings["station_name"]) ?>" maxlength="120" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="station_contact">Contact number</label>
            <input class="form-control" id="station_contact" name="station_contact" value="<?= e($settings["station_contact"]) ?>" maxlength="30" placeholder="09171234567" inputmode="tel" autocomplete="tel">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="business_hours">Business hours</label>
            <input class="form-control" id="business_hours" name="business_hours" value="<?= e($settings["business_hours"]) ?>" maxlength="120">
        </div>
        <div class="col-12">
            <label class="form-label" for="station_address">Station address</label>
            <textarea class="form-control" id="station_address" name="station_address" rows="3" maxlength="255"><?= e($settings["station_address"]) ?></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary">Save settings</button>
        </div>
    </form>
</section>
