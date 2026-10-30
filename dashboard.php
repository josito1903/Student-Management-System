<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["role"] === "admin") {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SESSION["role"] === "teacher") {
    header("Location: teacher_dashboard.php");
    exit();
}

/* Unknown role */
session_destroy();

header("Location: login.php");
exit();

?>