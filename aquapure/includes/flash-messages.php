<?php $flashNotices = pull_flashes(); ?>
<?php if ($flashNotices): ?>
    <div class="flash-region" aria-label="Messages">
        <?php $errorNotices = array_filter($flashNotices, static fn ($notice) => $notice['type'] === 'danger'); ?>
        <?php if ($errorNotices): ?>
            <section class="alert alert-danger" role="alert" tabindex="-1" data-error-summary aria-labelledby="serverErrorTitle">
                <h2 class="h6 mb-2" id="serverErrorTitle">Please review the following</h2>
                <ul class="mb-0">
                    <?php foreach ($errorNotices as $notice): ?><li><?= e($notice['message']) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php foreach ($flashNotices as $notice): ?>
            <?php if ($notice['type'] === 'danger') { continue; } ?>
            <?php $noticeType = in_array($notice['type'], ['success', 'warning', 'info'], true) ? $notice['type'] : 'info'; ?>
            <div class="alert alert-<?= e($noticeType) ?> alert-dismissible fade show" role="status">
                <?= e($notice['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
