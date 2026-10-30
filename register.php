<?php

session_start();

include "db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $department_id = (int) $_POST["department_id"];


    // -------------------------------
    // Validation
    // -------------------------------

    if (
        $name == "" ||
        $username == "" ||
        $email == "" ||
        $password == "" ||
        $department_id <= 0
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        // -------------------------------
        // Check username
        // -------------------------------

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ?"
        );

        mysqli_stmt_bind_param(
            $check,
            "s",
            $username
        );

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);


        if (mysqli_num_rows($check_result) > 0) {

            $error = "Username already exists.";

        } else {

            // -------------------------------
            // Check email
            // -------------------------------

            $check_email = mysqli_prepare(
                $conn,
                "SELECT id FROM users WHERE email = ?"
            );

            mysqli_stmt_bind_param(
                $check_email,
                "s",
                $email
            );

            mysqli_stmt_execute($check_email);

            $email_result = mysqli_stmt_get_result(
                $check_email
            );


            if (mysqli_num_rows($email_result) > 0) {

                $error = "Email is already registered.";

            } else {

                // -------------------------------
                // Check department
                // -------------------------------

                $dept = mysqli_prepare(
                    $conn,
                    "SELECT id FROM departments WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $dept,
                    "i",
                    $department_id
                );

                mysqli_stmt_execute($dept);

                $dept_result = mysqli_stmt_get_result(
                    $dept
                );


                if (mysqli_num_rows($dept_result) == 0) {

                    $error = "Invalid department selected.";

                } else {

                    // -------------------------------
                    // Create account
                    // -------------------------------

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $role = "teacher";

                    $insert = "
                        INSERT INTO users
                        (username, password, name, email, role, department_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ";

                    $stmt = mysqli_prepare(
                        $conn,
                        $insert
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssssi",
                        $username,
                        $hashed_password,
                        $name,
                        $email,
                        $role,
                        $department_id
                    );


                    if (mysqli_stmt_execute($stmt)) {

                        $success =
                            "Account created successfully. You can now login.";

                    } else {

                        $error =
                            "Unable to create account.";

                    }

                    mysqli_stmt_close($stmt);
                }

                mysqli_stmt_close($dept);
            }

            mysqli_stmt_close($check_email);
        }

        mysqli_stmt_close($check);
    }
}


// =========================================
// LOAD DEPARTMENTS
// =========================================

$departments = mysqli_query(
    $conn,
    "SELECT id, name, code
     FROM departments
     ORDER BY name ASC"
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Teacher Registration - Student Management System
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body class="login-page">

<div class="login-background">

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


            <!-- Heading -->

            <div class="login-heading">

                <h2>
                    Teacher Registration 👨‍🏫
                </h2>

                <p>
                    Create your teacher account
                </p>

            </div>


            <!-- Error -->

            <?php if ($error != ""): ?>

                <div class="login-error">

                    <span>⚠️</span>

                    <span>
                        <?php
                        echo htmlspecialchars($error);
                        ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- Success -->

            <?php if ($success != ""): ?>

                <div class="login-success">

                    <span>✅</span>

                    <span>
                        <?php
                        echo htmlspecialchars($success);
                        ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- Registration Form -->

            <form
                method="POST"
                action=""
            >


                <!-- Full Name -->

                <div class="login-form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <div class="login-input-wrapper">

                        <span class="input-icon">
                            👤
                        </span>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Enter your full name"
                            required
                        >

                    </div>

                </div>


                <!-- Username -->

                <div class="login-form-group">

                    <label for="username">
                        Username
                    </label>

                    <div class="login-input-wrapper">

                        <span class="input-icon">
                            🔑
                        </span>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Choose a username"
                            required
                        >

                    </div>

                </div>


                <!-- Email -->

                <div class="login-form-group">

                    <label for="email">
                        Email
                    </label>

                    <div class="login-input-wrapper">

                        <span class="input-icon">
                            ✉️
                        </span>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            required
                        >

                    </div>

                </div>


                <!-- Department -->

                <div class="login-form-group">

                    <label for="department_id">
                        Department
                    </label>

                    <div class="login-input-wrapper">

                        <span class="input-icon">
                            🏢
                        </span>

                        <select
                            id="department_id"
                            name="department_id"
                            required
                        >

                            <option value="">
                                Select your department
                            </option>

                            <?php
                            while (
                                $department =
                                mysqli_fetch_assoc($departments)
                            ):
                            ?>

                                <option
                                    value="<?php
                                    echo $department["id"];
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $department["name"]
                                    );
                                    ?>

                                    (<?php
                                    echo htmlspecialchars(
                                        $department["code"]
                                    );
                                    ?>)

                                </option>

                            <?php endwhile; ?>

                        </select>

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
                            placeholder="Create a password"
                            required
                        >

                    </div>

                </div>


                <!-- Confirm Password -->

                <div class="login-form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <div class="login-input-wrapper">

                        <span class="input-icon">
                            🔐
                        </span>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required
                        >

                    </div>

                </div>


                <!-- Button -->

                <button
                    type="submit"
                    class="login-btn"
                >

                    <span>
                        Create Teacher Account
                    </span>

                    <span class="login-arrow">
                        →
                    </span>

                </button>


            </form>


            <!-- Login link -->

            <div
                style="
                    text-align:center;
                    margin-top:20px;
                "
            >

                Already have an account?

                <a
                    href="login.php"
                    style="
                        font-weight:700;
                        text-decoration:none;
                    "
                >
                    Login
                </a>

            </div>


        </div>

    </div>

</div>

</body>

</html>