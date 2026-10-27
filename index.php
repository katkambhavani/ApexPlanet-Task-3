
<?php
session_start();
require_once "db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApexPlanet User Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>ApexPlanet</h1>
        <p>User Management System</p>
    </header>

    <main class="container">
        <h2>Welcome!</h2>
        <p>Manage your account securely with our user management system.</p>

        <div class="buttons">
            <a href="register.php" class="btn">Create Account</a>
            <a href="login.php" class="btn">Login</a>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> ApexPlanet. All rights reserved.</p>
    </footer>
</body>
</html>
