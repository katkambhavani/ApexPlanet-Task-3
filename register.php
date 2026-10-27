
<?php
session_start();
require_once __DIR__ . "/db.php";

$name = "";
$email = "";
$message = "";
$messageType = "error";
$fieldErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $termsAccepted = isset($_POST["terms"]);

    // Validate name.
    if ($name === "") {
        $fieldErrors["name"] = "Please enter your full name.";
    } elseif (mb_strlen($name) < 2) {
        $fieldErrors["name"] = "Name must contain at least 2 characters.";
    } elseif (mb_strlen($name) > 100) {
        $fieldErrors["name"] = "Name must not exceed 100 characters.";
    }

    // Validate email.
    if ($email === "") {
        $fieldErrors["email"] = "Please enter your email address.";
    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $fieldErrors["email"] = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $fieldErrors["email"] =
                    "This email is already registered.";
            }

            $stmt->close();
        } else {
            $message = "Unable to check your email. Please try again.";
        }
    }

    // Validate password.
    if ($password === "") {
        $fieldErrors["password"] = "Please enter a password.";
    } elseif (strlen($password) < 8) {
        $fieldErrors["password"] =
            "Password must be at least 8 characters.";
    }

    // Validate password confirmation.
    if ($confirmPassword === "") {
        $fieldErrors["confirm_password"] =
            "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $fieldErrors["confirm_password"] =
            "Passwords do not match.";
    }

    // Validate terms.
    if (!$termsAccepted) {
        $fieldErrors["terms"] =
            "Please accept the terms to continue.";
    }

    // Validate optional profile picture.
    $profilePicture = null;
    $uploadedProfileFile = null;

    if (
        isset($_FILES["profile_picture"]) &&
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $fieldErrors["profile_picture"] =
                "Profile picture upload failed.";
        } elseif ($file["size"] > 2 * 1024 * 1024) {
            $fieldErrors["profile_picture"] =
                "Image must be 2 MB or smaller.";
        } elseif (!is_uploaded_file($file["tmp_name"])) {
            $fieldErrors["profile_picture"] =
                "Invalid uploaded file.";
        } else {
            $imageInfo = @getimagesize($file["tmp_name"]);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file["tmp_name"]);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (!$imageInfo || !isset($allowedTypes[$mime])) {
                $fieldErrors["profile_picture"] =
                    "Choose a valid JPG, PNG, or WebP image.";
            } else {
                $uploadDir = __DIR__ . "/uploads/profiles/";

                $filename = bin2hex(random_bytes(16))
                    . "." . $allowedTypes[$mime];

                if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                    $fieldErrors["profile_picture"] =
                        "The profile picture folder is not writable.";
                } elseif (
                    move_uploaded_file(
                        $file["tmp_name"],
                        $uploadDir . $filename
                    )
                ) {
                    $profilePicture = "uploads/profiles/" . $filename;
                    $uploadedProfileFile = $uploadDir . $filename;
                } else {
                    $fieldErrors["profile_picture"] =
                        "Could not save the profile picture.";
                }
            }
        }
    }

    // Create the regular user account if validation passes.
    if (empty($fieldErrors) && $message === "") {
        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        // Public registration always creates a regular user.
        $roleId = 2;

        $stmt = $conn->prepare(
            "INSERT INTO users
                (name, email, password, role_id, profile_picture)
             VALUES (?, ?, ?, ?, ?)"
        );

        if ($stmt) {
            $stmt->bind_param(
                "sssis",
                $name,
                $email,
                $hashedPassword,
                $roleId,
                $profilePicture
            );

            if ($stmt->execute()) {
                $stmt->close();

                header("Location: login.php?registered=1");
                exit;
            }

            if ($stmt->errno === 1062) {
                $fieldErrors["email"] =
                    "This email is already registered.";
            } else {
                $message = "Registration failed. Please try again.";
            }

            $stmt->close();
        } else {
            $message =
                "Unable to create your account. Please try again.";
        }
    }

    if (!empty($fieldErrors) && $message === "") {
        $message = "Please correct the highlighted fields.";
    }

    // Remove an uploaded image if account creation failed.
    if (
        (!empty($fieldErrors) || $message !== "") &&
        $uploadedProfileFile !== null &&
        is_file($uploadedProfileFile)
    ) {
        unlink($uploadedProfileFile);
    }
}

function escapeOutput($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function renderFieldError($errors, $field) {
    $error = $errors[$field] ?? "";
    $hidden = $error === "" ? " hidden" : "";

    echo '<small id="' . escapeOutput($field) .
        '-error" class="field-error" role="alert"' .
        $hidden . '>' .
        escapeOutput($error) .
        '</small>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-container">
        <h1 class="auth-heading">Create Account</h1>
        <p class="auth-description">
            Fill in the details below to register.
        </p>

        <form
            method="POST"
            action="register.php"
            id="registerForm"
            enctype="multipart/form-data"
            novalidate
        >
            <div class="auth-field">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    autocomplete="name"
                    value="<?php echo escapeOutput($name); ?>"
                    aria-describedby="name-error"
                    required
                >
                <?php renderFieldError($fieldErrors, "name"); ?>
            </div>

            <div class="auth-field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="email"
                    value="<?php echo escapeOutput($email); ?>"
                    aria-describedby="email-error"
                    required
                >
                <?php renderFieldError($fieldErrors, "email"); ?>
                <small
                    id="email-status"
                    class="field-success"
                    role="status"
                    hidden
                ></small>
            </div>

            <div class="auth-field">
                <label for="password">Password</label>

                <div class="password-row">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        aria-describedby="password-error"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-pressed="false"
                    >Show</button>
                </div>

                <small>Use at least 8 characters.</small>
                <?php renderFieldError($fieldErrors, "password"); ?>
            </div>

            <div class="auth-field">
                <label for="confirm_password">Confirm Password</label>

                <div class="password-row">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        autocomplete="new-password"
                        aria-describedby="confirm_password-error"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="confirm_password"
                        aria-pressed="false"
                    >Show</button>
                </div>

                <?php renderFieldError($fieldErrors, "confirm_password"); ?>
            </div>

            <div class="auth-field">
                <label for="profile_picture">
                    Profile Picture (optional)
                </label>
                <input
                    type="file"
                    id="profile_picture"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/webp"
                    aria-describedby="profile_picture-error"
                >
                <small>JPG, PNG, or WebP. Maximum size: 2 MB.</small>
                <?php renderFieldError($fieldErrors, "profile_picture"); ?>
            </div>

            <div class="auth-field">
                <label for="role">Role</label>
                <select id="role" name="role" disabled>
                    <option value="2" selected>User</option>
                </select>
                <small>
                    Public registration creates a regular User account.
                </small>
            </div>

            <div class="terms-group">
                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    <?php echo isset($_POST["terms"]) ? "checked" : ""; ?>
                >
                <label for="terms">
                    I agree to the terms and conditions.
                </label>
            </div>
            <?php renderFieldError($fieldErrors, "terms"); ?>

            <button
                type="submit"
                class="auth-button"
                id="createAccountButton"
            >
                Create Account
            </button>
        </form>

        <?php if ($message !== ""): ?>
            <div
                class="auth-message <?php
                    echo !empty($fieldErrors) || $messageType === "error"
                        ? "auth-message--error"
                        : "auth-message--success";
                ?>"
                role="alert"
            >
                <?php echo escapeOutput($message); ?>
            </div>
        <?php endif; ?>

        <div class="auth-links">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("registerForm");
    const nameInput = document.getElementById("name");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmInput = document.getElementById("confirm_password");
    const termsInput = document.getElementById("terms");

    let emailAvailable = false;
    let emailChecking = false;
    let emailTimer = null;
    let emailRequestNumber = 0;

    function setError(input, message) {
        const error = document.getElementById(input.id + "-error");
        if (!error) return;

        error.textContent = message;
        error.hidden = !message;
        input.setAttribute("aria-invalid", message ? "true" : "false");
    }

    function setEmailStatus(message, isError) {
        const error = document.getElementById("email-error");
        const status = document.getElementById("email-status");

        if (error) {
            error.textContent = isError ? message : "";
            error.hidden = !isError || !message;
        }

        if (status) {
            status.textContent = !isError ? message : "";
            status.hidden = isError || !message;
        }

        emailInput.setAttribute(
            "aria-invalid",
            isError ? "true" : "false"
        );
    }

    function validateName() {
        const value = nameInput.value.trim();
        let message = "";

        if (!value) {
            message = "Please enter your full name.";
        } else if (value.length < 2) {
            message = "Name must contain at least 2 characters.";
        } else if (value.length > 100) {
            message = "Name must not exceed 100 characters.";
        }

        setError(nameInput, message);
        return !message;
    }

    function validateEmailFormat() {
        const value = emailInput.value.trim();
        let message = "";

        if (!value) {
            message = "Please enter your email address.";
        } else if (
            value.length > 150 ||
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
        ) {
            message = "Please enter a valid email address.";
        }

        if (message) {
            setEmailStatus(message, true);
            return false;
        }

        return true;
    }

    async function checkEmailAvailability() {
        clearTimeout(emailTimer);

        const email = emailInput.value.trim();
        const requestNumber = ++emailRequestNumber;

        emailAvailable = false;

        if (!validateEmailFormat()) {
            emailChecking = false;
            return false;
        }

        emailChecking = true;
        setEmailStatus("Checking email availability...", false);

        try {
            const response = await fetch(
                "check_email.php?email=" + encodeURIComponent(email),
                { cache: "no-store" }
            );

            if (!response.ok) {
                throw new Error("Email check failed");
            }

            const result = await response.json();

            if (
                requestNumber !== emailRequestNumber ||
                email !== emailInput.value.trim()
            ) {
                return false;
            }

            emailChecking = false;

            if (!result.valid) {
                setEmailStatus(
                    "Please enter a valid email address.",
                    true
                );
            } else if (!result.available) {
                setEmailStatus(
                    result.message || "This email is already registered.",
                    true
                );
            } else {
                emailAvailable = true;
                setEmailStatus("Email address is available.", false);
            }

            return emailAvailable;
        } catch (error) {
            if (requestNumber === emailRequestNumber) {
                emailChecking = false;
                emailAvailable = false;

                setEmailStatus(
                    "Could not verify email. Check your connection and try again.",
                    true
                );
            }

            return false;
        }
    }

    function validatePassword() {
        let message = "";

        if (!passwordInput.value) {
            message = "Please enter a password.";
        } else if (passwordInput.value.length < 8) {
            message = "Password must be at least 8 characters.";
        }

        setError(passwordInput, message);

        if (confirmInput.value !== "") {
            validateConfirmPassword();
        }

        return !message;
    }

    function validateConfirmPassword() {
        let message = "";

        if (!confirmInput.value) {
            message = "Please confirm your password.";
        } else if (confirmInput.value !== passwordInput.value) {
            message = "Passwords do not match.";
        }

        setError(confirmInput, message);
        return !message;
    }

    function validateTerms() {
        const error = document.getElementById("terms-error");
        const message = termsInput.checked
            ? ""
            : "Please accept the terms to continue.";

        if (error) {
            error.textContent = message;
            error.hidden = !message;
        }

        return !message;
    }

    nameInput.addEventListener("input", validateName);

    emailInput.addEventListener("input", function () {
        clearTimeout(emailTimer);
        emailRequestNumber++;
        emailAvailable = false;
        emailChecking = false;

        if (!validateEmailFormat()) return;

        setEmailStatus("Checking email availability...", false);
        emailTimer = setTimeout(checkEmailAvailability, 300);
    });

    emailInput.addEventListener("blur", function () {
        if (validateEmailFormat() && !emailAvailable && !emailChecking) {
            checkEmailAvailability();
        }
    });

    passwordInput.addEventListener("input", validatePassword);
    confirmInput.addEventListener("input", validateConfirmPassword);
    termsInput.addEventListener("change", validateTerms);

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

    form.addEventListener("submit", async function (event) {
        const nameValid = validateName();
        const emailValid = validateEmailFormat();
        const passwordValid = validatePassword();
        const confirmValid = validateConfirmPassword();
        const termsValid = validateTerms();

        if (
            !nameValid ||
            !emailValid ||
            !passwordValid ||
            !confirmValid ||
            !termsValid
        ) {
            event.preventDefault();
            return;
        }

        if (emailChecking || !emailAvailable) {
            event.preventDefault();

            const available = await checkEmailAvailability();

            if (available) {
                form.submit();
            }
        }
    });
});
</script>

</body>
</html>
