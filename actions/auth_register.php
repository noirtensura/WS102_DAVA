<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_post();
if ($user) {
    redirect(dashboard_path($user['role_name']));
}

$role = ($_POST['role'] ?? '') === 'landlord' ? 'landlord' : 'student';
$next = post_login_path((string) filter_input(INPUT_POST, 'next', FILTER_UNSAFE_RAW));
$back = 'register.php?' . http_build_query(['role' => $role, 'next' => $next]);
$limits = ['first_name' => 80, 'last_name' => 80, 'email' => 150, 'phone_number' => 20];
if ($role === 'student') {
    $limits += ['school_id_number' => 50, 'course_program' => 120, 'year_level' => 1];
}
$details = [];
$old = [];
foreach ($limits as $key => $limit) {
    $details[$key] = trim((string) filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW));
    $old[$key] = mb_substr($details[$key], 0, $limit);
}
$_SESSION['register_values'] = $old;
$password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
$confirmation = (string) filter_input(INPUT_POST, 'confirmation', FILTER_UNSAFE_RAW);

try {
    foreach ($limits as $key => $limit) {
        if ($details[$key] === '' || mb_strlen($details[$key]) > $limit) {
            throw new DomainException('Check that each required field is filled in and within its length limit.');
        }
    }
    if (!filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Enter a valid email address.');
    }
    if (!preg_match('/^[+0-9(). -]{7,20}$/D', $details['phone_number']) || strlen(preg_replace('/\D/', '', $details['phone_number'])) < 7) {
        throw new DomainException('Enter a phone number with at least seven digits.');
    }
    if (strlen($password) < 8 || strlen($password) > 72 || $password !== $confirmation || str_contains($password, "\0")) {
        throw new DomainException('Use matching passwords between 8 and 72 bytes long.');
    }
    if ($role === 'student' && !preg_match('/^[1-6]$/D', $details['year_level'])) {
        throw new DomainException('Select a valid year level.');
    }

    $pdo->beginTransaction();
    query(
        'INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone_number)
         VALUES ((SELECT role_id FROM roles WHERE role_name = ?), ?, ?, ?, ?, ?)',
        [$role, $details['email'], password_hash($password, PASSWORD_DEFAULT), $details['first_name'], $details['last_name'], $details['phone_number']]
    );
    $accountId = (int) $pdo->lastInsertId();
    if ($role === 'student') {
        query(
            'INSERT INTO student_profiles (student_id, school_id_number, course_program, year_level) VALUES (?, ?, ?, ?)',
            [$accountId, $details['school_id_number'], $details['course_program'], $details['year_level']]
        );
    } else {
        query('INSERT INTO landlord_profiles (landlord_id, government_id_path) VALUES (?, ?)', [$accountId, '']);
    }
    $pdo->commit();
} catch (DomainException | PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception instanceof DomainException) {
        $message = $exception->getMessage();
    } elseif ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        $message = 'That email address or school ID is already registered.';
    } else {
        error_log($exception->getMessage());
        $message = 'Registration could not be completed. Please try again.';
    }
    flash('error', $message);
    redirect($back);
}

session_regenerate_id(true);
unset($_SESSION['register_values'], $_SESSION['csrf']);
$_SESSION['user_id'] = $accountId;
$_SESSION['role_name'] = $role;
$_SESSION['full_name'] = $details['first_name'] . ' ' . $details['last_name'];
flash('success', 'Your Dayon account is ready.');
redirect($role === 'landlord' ? 'landlord/verification.php' : ($next ?: dashboard_path($role)));
