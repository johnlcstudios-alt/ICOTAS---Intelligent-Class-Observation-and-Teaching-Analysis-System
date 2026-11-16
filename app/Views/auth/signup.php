<?php
/** @var string $pageTitle */
?>
<div class="mb-4">
    <span class="eyebrow">Student self-registration</span>
    <h2 class="mt-1">Create your student account</h2>
    <p class="text-muted mb-0">Use your own details. Instructor and staff accounts are issued by the academic office.</p>
</div>

<form method="post" action="<?= url('signup') ?>" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="auth-honeypot" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="row g-2">
        <div class="col-sm-5">
            <label class="form-label" for="first_name">First name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" autocomplete="given-name" maxlength="80" required>
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="middle_name">Middle name <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= e(old('middle_name')) ?>" autocomplete="additional-name" maxlength="80">
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="suffix">Suffix</label>
            <input type="text" class="form-control" id="suffix" name="suffix" value="<?= e(old('suffix')) ?>" autocomplete="honorific-suffix" maxlength="20" placeholder="Jr., III">
        </div>
        <div class="col-12">
            <label class="form-label" for="last_name">Last name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" autocomplete="family-name" maxlength="80" required>
        </div>
    </div>

    <div class="mb-3 mt-3">
        <label class="form-label" for="student_number">Student number</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
            <input type="text" class="form-control" id="student_number" name="student_number" value="<?= e(old('student_number')) ?>" maxlength="40" autocomplete="off" required>
        </div>
        <div class="form-text">Use the student number issued by the college. It must be unique.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="username">Username</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="username" name="username" value="<?= e(old('username')) ?>" autocomplete="username" minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" required>
        </div>
        <div class="form-text">Letters, numbers, dots, dashes and underscores only.</div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" maxlength="190" required>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-sm-6">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8" required>
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show or hide password"><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
        </div>
    </div>
    <div class="form-text mb-3">At least 8 characters, including a letter and a number.</div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="accept_terms" name="accept_terms" <?= checked(old('accept_terms') === '1') ?> required>
        <label class="form-check-label small" for="accept_terms">
            I consent to the processing of my account details for ICOTAS academic evaluation purposes and understand that submitted evaluation responses are stored anonymously.
        </label>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-person-plus"></i> Create account</button>
</form>

<div class="disclaimer mt-4">
    <i class="bi bi-shield-check"></i>
    Creating an account does not enroll you in a class. Evaluation access still requires the instructor's eligible roster and an active QR session.
</div>

<p class="text-center text-muted small mt-4 mb-0">
    Already have an account? <a href="<?= url('login') ?>">Sign in</a>
</p>
