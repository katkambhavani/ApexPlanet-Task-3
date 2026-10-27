
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(403);
    exit("Access denied. Admins only.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

$userId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    exit("Invalid user ID.");
}

if ($userId === (int) $_SESSION["user_id"]) {
    exit("You cannot delete your own Admin account.");
}

// Check whether the selected account exists and is a regular user.
$check = $conn->prepare(
    "SELECT role_id FROM users WHERE id = ?"
);
$check->bind_param("i", $userId);
$check->execute();
$check->bind_result($targetRoleId);

if (!$check->fetch()) {
    $check->close();
    header("Location: admin.php?error=delete");
    exit;
}

$check->close();

if ((int) $targetRoleId !== 2) {
    http_response_code(403);
    exit("Admin accounts cannot be deleted.");
}

// Delete only regular user accounts.
$stmt = $conn->prepare(
    "DELETE FROM users WHERE id = ? AND role_id = 2"
);
$stmt->bind_param("i", $userId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $stmt->close();
    header("Location: admin.php?deleted=1");
    exit;
}

$stmt->close();
header("Location: admin.php?error=delete");
exit;
?>
