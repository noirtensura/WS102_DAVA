<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$student = require_role('student');
$profile = row('SELECT school_id_number, course_program, year_level FROM student_profiles WHERE student_id = ?', [$student['user_id']]);
$contacts = rows('SELECT * FROM emergency_contacts WHERE student_id = ? ORDER BY contact_id', [$student['user_id']]);
$pageTitle = 'My profile';
$pageDescription = 'Update your school details and emergency contacts.';
require __DIR__ . '/../includes/portal_header.php';
?>

<div class="grid-2">
    <section class="panel" aria-labelledby="student-information-heading">
        <h2 id="student-information-heading">Student information</h2>
        <form method="post" action="<?= e(url('actions/student_profile.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="profile">
            <div class="form-grid">
                <label class="form-field">Full name<input type="text" value="<?= e($student['full_name']) ?>" readonly></label>
                <label class="form-field">Account email<input type="email" value="<?= e($student['email']) ?>" readonly></label>
                <label class="form-field">School ID<input name="school_id_number" value="<?= e($profile['school_id_number'] ?? '') ?>" maxlength="50" required></label>
                <label class="form-field">Course / program<input name="course_program" value="<?= e($profile['course_program'] ?? '') ?>" maxlength="120" required></label>
                <label class="form-field">Year level
                    <select name="year_level" required>
                        <?php for ($year = 1; $year <= 6; $year++): ?>
                            <option value="<?= $year ?>" <?= $year === (int) ($profile['year_level'] ?? 1) ? 'selected' : '' ?>>Year <?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="form-field">Phone number<input type="tel" name="phone_number" value="<?= e($student['phone_number']) ?>" maxlength="20" autocomplete="tel" required></label>
            </div>
            <div class="actions"><button class="button" type="submit">Save student information</button></div>
        </form>
    </section>

    <section class="panel" aria-labelledby="emergency-contacts-heading">
        <h2 id="emergency-contacts-heading">Emergency contacts</h2>
        <?php if (!$contacts): ?><p class="muted">Add a parent, guardian, or other person to contact in an emergency.</p><?php endif; ?>
        <?php foreach ($contacts as $contact): ?>
            <details>
                <summary><strong><?= e($contact['full_name']) ?></strong> · <?= e($contact['relationship']) ?> · <?= e($contact['phone_number']) ?></summary>
                <form method="post" action="<?= e(url('actions/student_profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="operation" value="contact_save">
                    <input type="hidden" name="contact_id" value="<?= e($contact['contact_id']) ?>">
                    <label class="form-field">Full name<input name="full_name" value="<?= e($contact['full_name']) ?>" maxlength="120" required></label>
                    <div class="form-grid">
                        <label class="form-field">Relationship<input name="relationship" value="<?= e($contact['relationship']) ?>" maxlength="50" required></label>
                        <label class="form-field">Phone number<input type="tel" name="phone_number" value="<?= e($contact['phone_number']) ?>" maxlength="20" required></label>
                    </div>
                    <div class="actions"><button class="button small" type="submit">Update contact</button></div>
                </form>
                <form method="post" action="<?= e(url('actions/student_profile.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="operation" value="contact_remove">
                    <input type="hidden" name="contact_id" value="<?= e($contact['contact_id']) ?>">
                    <div class="actions"><button class="button danger small" type="submit" data-confirm="Remove <?= e($contact['full_name']) ?> from your emergency contacts?">Remove contact</button></div>
                </form>
            </details>
        <?php endforeach; ?>
        <h3>Add an emergency contact</h3>
        <form method="post" action="<?= e(url('actions/student_profile.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="contact_save">
            <label class="form-field">Full name<input name="full_name" maxlength="120" required></label>
            <div class="form-grid">
                <label class="form-field">Relationship<input name="relationship" maxlength="50" placeholder="e.g. Parent" required></label>
                <label class="form-field">Phone number<input type="tel" name="phone_number" maxlength="20" required></label>
            </div>
            <div class="actions"><button class="button secondary" type="submit">Add contact</button></div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
