<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-describedby="confirmMessage" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="confirmTitle">Confirm action</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close confirmation"></button>
            </div>
            <div class="modal-body" id="confirmMessage">Review the consequence before continuing.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep current</button>
                <form method="post" id="confirmForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action">
                    <input type="hidden" name="id">
                    <button type="submit" class="btn btn-danger">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>
