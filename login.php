
<?php
session_start();
require_once __DIR__ . "/db.php";

$email = "";
$message = "";
$fieldErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "") {
        $fieldErrors["email"] = "Please enter your email address.";
    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $fieldErrors["email"] = "Please enter a valid email address.";
    }

    if ($password === "") {
        $fieldErrors["password"] = "Please enter your password.";
    }

    if (empty($fieldErrors)) {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role_id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result(
                    $userId,
                    $userName,
                    $userEmail,
                    $passwordHash,
                    $roleId
                );
                $stmt->fetch();

                if (password_verify($password, $passwordHash)) {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int) $userId;
                    $_SESSION["name"] = $userName;
                    $_SESSION["email"] = $userEmail;
                    $_SESSION["role_id"] = (int) $roleId;

                    $stmt->close();
                    $conn->close();

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $fieldErrors["password"] =
                        "Incorrect password. Please try again.";
                }
            } else {
                $fieldErrors["email"] =
                    "This email address doesn't exist. Please try another email.";
            }

            $stmt->close();
        } else {
            $message = "Unable to log in right now. Please try again.";
        }
    }
}

function loginEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function loginFieldError($errors, $field) {
    $error = $errors[$field] ?? "";
    $hidden = $error === "" ? " hidden" : "";

    echo '<small id="' . loginEscape($field) .
        '-error" class="field-error" role="alert"' . $hidden . '>' .
        loginEscape($error) . '</small>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | User Management System</title>
    <link rel="stylesheet" href="style.css">

</head>
<body>

<main class="auth-page">
    <section class="auth-container">
        <h1 class="auth-heading">Welcome Back</h1>
        <p class="auth-description">Log in to access your account.</p>

        <?php if (isset($_GET["registered"])): ?>
            <div class="auth-message auth-message--success" role="status">
                Registration successful! Please log in.
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" novalidate>
            <div class="auth-field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="username"
                    value="<?php echo loginEscape($email); ?>"
                    aria-describedby="email-error"
                    aria-invalid="<?php echo isset($fieldErrors['email']) ? 'true' : 'false'; ?>"
                    required
                >
                <?php loginFieldError($fieldErrors, "email"); ?>
            </div>

            <div class="auth-field">
                <label for="password">Password</label>
                <div class="password-row">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        aria-describedby="password-error"
                        aria-invalid="<?php echo isset($fieldErrors['password']) ? 'true' : 'false'; ?>"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-pressed="false"
                    >Show</button>
                </div>
                <?php loginFieldError($fieldErrors, "password"); ?>
            </div>

            <button type="submit" class="btn auth-button">Login</button>
        </form>

        <?php if ($message !== ""): ?>
            <div class="auth-message" role="alert">
                <?php echo loginEscape($message); ?>
            </div>
        <?php endif; ?>

        <div class="auth-links">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </div>
    </section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("loginForm");
    const email = document.getElementById("email");
    const password = document.getElementById("password");

    function showError(input, message) {
        const error = document.getElementById(input.id + "-error");
        if (!error) return;

        error.textContent = message;
        error.hidden = !message;
        input.setAttribute("aria-invalid", message ? "true" : "false");
    }

    function validateEmail() {
        const value = email.value.trim();
        let message = "";

        if (!value) {
            message = "Please enter your email address.";
        } else if (
            value.length > 150 ||
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
        ) {
            message = "Please enter a valid email address.";
        }

        showError(email, message);
        return !message;
    }

    function validatePassword() {
        const message = password.value
            ? ""
            : "Please enter your password.";

        showError(password, message);
        return !message;
    }

    email.addEventListener("input", validateEmail);
    password.addEventListener("input", validatePassword);

    document.querySelectorAll(".password-toggle").forEach(function (button) {
        button.addEventListener("click", function () {
            const input = document.getElementById(button.dataset.target);
            if (!input) return;

            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.textContent = show ? "Hide" : "Show";
            button.setAttribute("aria-pressed", String(show));
        });
    });

    form.addEventListener("submit", function (event) {
        const emailValid = validateEmail();
        const passwordValid = validatePassword();

        if (!emailValid || !passwordValid) {
            event.preventDefault();
        }
    });
});
</script>

</body>
</html>
