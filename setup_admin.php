<?php

include "db.php";

$username = "admin";
$password = "admin123";
$name = "Administrator";
$email = "admin@college.com";
$role = "admin";

// Check whether admin already exists
$sql = "SELECT id FROM users WHERE username = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $username
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {

    echo "Admin account already exists.";

} else {

    // Hash password
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insert = "
        INSERT INTO users
        (username, password, name, email, role)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt2 = mysqli_prepare(
        $conn,
        $insert
    );

    mysqli_stmt_bind_param(
        $stmt2,
        "sssss",
        $username,
        $hashed_password,
        $name,
        $email,
        $role
    );

    if (mysqli_stmt_execute($stmt2)) {

        echo "<h2>Admin account created successfully.</h2>";

        echo "Username: admin<br>";
        echo "Password: admin123<br><br>";

        echo "<strong>";
        echo "Delete setup_admin.php now.";
        echo "</strong>";

    } else {

        echo "Error: " . mysqli_error($conn);

    }

    mysqli_stmt_close($stmt2);
}

mysqli_stmt_close($stmt);

?>