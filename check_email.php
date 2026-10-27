
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'available' => false,
        'error' => 'Invalid request method.'
    ]);
    exit;
}

$email = trim($_GET['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'available' => false,
        'valid' => false,
        'error' => 'Please enter a valid email address.'
    ]);
    exit;
}

$stmt = $conn->prepare(
    'SELECT id FROM users WHERE email = ? LIMIT 1'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'available' => false,
        'error' => 'Unable to verify email right now.'
    ]);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

$exists = $stmt->num_rows > 0;

$stmt->close();
$conn->close();

echo json_encode([
    'valid' => true,
    'available' => !$exists,
    'message' => $exists
        ? 'This email address is already registered.'
        : 'Email address is available.'
]);
exit;
