<?php

session_start();

include "db.php";

// If already logged in, go to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

// Login form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if ($username == "" || $password == "") {

        $error = "Please enter username and password.";

    } else {

        // Find user
        $sql = "SELECT id, username, password, name, role
                FROM users
                WHERE username = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $username
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {

            $user = mysqli_fetch_assoc($result);

            // Verify password
            if (password_verify($password, $user["password"])) {

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Store logged-in user information
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] === "admin") {

    header("Location: admin_dashboard.php");

} else {

    header("Location: teacher_dashboard.php");

}

exit();

            } else {

                $error = "Invalid username or password.";

            }

        } else {

            $error = "Invalid username or password.";

        }

        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - Student Management System</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body class="login-page">

    <div class="login-background">

        <!-- Decorative shapes -->
        <div class="login-shape shape-one"></div>
        <div class="login-shape shape-two"></div>
        <div class="login-shape shape-three"></div>


        <div class="login-container">

            <div class="login-box">


                <!-- Logo -->
                <div class="login-logo">

                    <div class="login-logo-icon">
                        🎓
                    </div>

                    <div>

                        <h1>
                            Student MS
                        </h1>

                        <p>
                            Management System
                        </p>

                    </div>

                </div>


                <!-- Welcome -->
                <div class="login-heading">

                    <h2>
                        Welcome Back! 👋
                    </h2>

                    <p>
                        Sign in to access your dashboard
                    </p>

                </div>


                <!-- Error Message -->
                <?php if ($error != ""): ?>

                    <div class="login-error">

                        <span>⚠️</span>

                        <span>
                            <?php echo htmlspecialchars($error); ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- Login Form -->
                <form
                    method="POST"
                    action=""
                >


                    <!-- Username -->
                    <div class="login-form-group">

                        <label for="username">
                            Username
                        </label>

                        <div class="login-input-wrapper">

                            <span class="input-icon">
                                👤
                            </span>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Enter your username"
                                autocomplete="username"
                                value="<?php echo isset($_POST["username"]) ? htmlspecialchars($_POST["username"]) : ""; ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- Password -->
                    <div class="login-form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="login-input-wrapper">

                            <span class="input-icon">
                                🔒
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                    </div>


                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="login-btn"
                    >

                        <span>
                            Login to Dashboard
                        </span>

                        <span class="login-arrow">
                            →
                        </span>

                    </button>

                    <div class="register-link">
    <span>Don't have an account?</span>
    <a href="register.php">Register as Teacher</a>
</div>


                </form>


            </div>

        </div>

    </div>

</body>

</html>