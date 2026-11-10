<?php
/** @var string $pageTitle */
use App\Core\Session;

$errors = Session::pull('_import_errors', []);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Account governance</span>
        <h2>Import users from CSV</h2>
        <p>Create multiple accounts in one operation. Each row is validated independently; valid rows are saved and failing rows are reported without stopping the import.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/users') ?>"><i class="bi bi-arrow-left"></i> Back to users</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Upload CSV</strong><span class="text-muted small">UTF-8, comma-separated</span></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/users/import') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="csvFile">CSV file <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="csvFile" name="csv_file" accept=".csv,text/csv" required>
                        <div class="form-text">Maximum file size: <?= e(number_format((int) config('app.csv_max_bytes', 5242880) / 1048576, 1)) ?> MB.</div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Import accounts</button>
                </form>
            </div>
            <div class="card-body border-top small text-muted">
                <strong>Import rules</strong>
                <ul class="mb-0 mt-2">
                    <li>Required columns: <code>username</code>, <code>email</code>, <code>password</code>, <code>first_name</code>, <code>last_name</code>, <code>role</code>.</li>
                    <li>Roles must be one of <code>dean</code>, <code>academic_head</code>, <code>instructor</code>, <code>student</code>.</li>
                    <li>Student rows require a <code>student_number</code>.</li>
                    <li><code>department_code</code>, <code>program_code</code> and <code>section_code</code> must already exist in the active data scope.</li>
                    <li>Passwords must be at least 8 characters and meet the system password policy.</li>
                    <li>Accounts are created in the <?= \App\Core\Setting::demoMode() ? 'synthetic demonstration' : 'live institutional' ?> scope.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Column reference</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 small">
                        <thead><tr><th>Column</th><th>Required</th><th>Notes</th></tr></thead>
                        <tbody>
                        <tr><td><code>username</code></td><td>Yes</td><td>Unique login name</td></tr>
                        <tr><td><code>email</code></td><td>Yes</td><td>Unique email address</td></tr>
                        <tr><td><code>password</code></td><td>Yes</td><td>Minimum 8 characters</td></tr>
                        <tr><td><code>first_name</code></td><td>Yes</td><td>Given name</td></tr>
                        <tr><td><code>middle_name</code></td><td>No</td><td>Middle name</td></tr>
                        <tr><td><code>last_name</code></td><td>Yes</td><td>Family name</td></tr>
                        <tr><td><code>suffix</code></td><td>No</td><td>Jr., III, etc.</td></tr>
                        <tr><td><code>role</code></td><td>Yes</td><td>dean / academic_head / instructor / student</td></tr>
                        <tr><td><code>employee_number</code></td><td>No</td><td>Faculty/staff identifier</td></tr>
                        <tr><td><code>student_number</code></td><td>Students</td><td>Required for student accounts</td></tr>
                        <tr><td><code>department_code</code></td><td>No</td><td>Existing department code</td></tr>
                        <tr><td><code>program_code</code></td><td>No</td><td>Existing program code</td></tr>
                        <tr><td><code>section_code</code></td><td>No</td><td>Existing section code</td></tr>
                        <tr><td><code>year_level</code></td><td>No</td><td>1–5</td></tr>
                        <tr><td><code>phone</code></td><td>No</td><td>Contact number</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body border-top">
                <a class="btn btn-outline-primary w-100" href="<?= url('dean/users/import-template') ?>"><i class="bi bi-download"></i> Download CSV template</a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="card mt-4 border-danger">
        <div class="card-header bg-danger-subtle"><strong>Import report</strong><span class="text-muted small"><?= count($errors) ?> row problem(s)</span></div>
        <div class="card-body">
            <p class="small text-muted">The following rows were skipped. Correct the source rows and import the file again; existing accounts are not duplicated.</p>
            <ol class="small mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
<?php endif; ?>
