<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$operation = student_post_text('operation');
$phoneFormat = '/^[0-9+().\s-]{7,20}$/D';

try {
    switch ($operation) {
        case 'profile':
            $schoolId = student_post_text('school_id_number');
            $course = student_post_text('course_program');
            $year = student_post_id('year_level');
            $phone = student_post_text('phone_number');
            if ($schoolId === '' || strlen($schoolId) > 50 || $course === '' || strlen($course) > 120 || $year < 1 || $year > 6) {
                throw new DomainException('Enter your school ID, course, and a year level from 1 to 6.');
            }
            if (!preg_match($phoneFormat, $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) {
                throw new DomainException('Enter a valid phone number.');
            }

            $pdo->beginTransaction();
            query('UPDATE student_profiles SET school_id_number = ?, course_program = ?, year_level = ? WHERE student_id = ?', [$schoolId, $course, $year, $studentId]);
            query('UPDATE users SET phone_number = ? WHERE user_id = ?', [$phone, $studentId]);
            $pdo->commit();
            flash('success', 'Your profile was updated.');
            break;

        case 'contact_save':
            $contactId = student_post_id('contact_id');
            $name = student_post_text('full_name');
            $relationship = student_post_text('relationship');
            $phone = student_post_text('phone_number');
            if ($name === '' || strlen($name) > 120 || $relationship === '' || strlen($relationship) > 50 ||
                !preg_match($phoneFormat, $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) {
                throw new DomainException('Enter your contact’s full name, relationship, and a valid phone number.');
            }

            if ($contactId) {
                $contact = row('SELECT contact_id FROM emergency_contacts WHERE contact_id = ? AND student_id = ?', [$contactId, $studentId]);
                if (!$contact) {
                    throw new DomainException('This emergency contact could not be found.');
                }
                query('UPDATE emergency_contacts SET full_name = ?, relationship = ?, phone_number = ? WHERE contact_id = ? AND student_id = ?', [$name, $relationship, $phone, $contactId, $studentId]);
            } else {
                query('INSERT INTO emergency_contacts (student_id, full_name, relationship, phone_number) VALUES (?, ?, ?, ?)', [$studentId, $name, $relationship, $phone]);
            }
            flash('success', 'Emergency contact saved.');
            break;

        case 'contact_remove':
            $deleted = query('DELETE FROM emergency_contacts WHERE contact_id = ? AND student_id = ?', [student_post_id('contact_id'), $studentId]);
            if (!$deleted->rowCount()) {
                throw new DomainException('This emergency contact could not be found.');
            }
            flash('success', 'Emergency contact removed.');
            break;

        default:
            throw new DomainException('This profile action is unavailable.');
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error instanceof DomainException) {
        $message = $error->getMessage();
    } elseif ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062 && $operation === 'profile') {
        $message = 'That school ID is already registered. Check the number and try again.';
    } else {
        error_log($error->getMessage());
        $message = 'Your changes could not be saved. Please try again.';
    }
    flash('error', $message);
}

redirect('student/profile.php');
