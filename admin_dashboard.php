<?php

include "auth.php";
include "db.php";

// Only admin can access this page
if ($_SESSION["role"] !== "admin") {
    header("Location: teacher_dashboard.php");
    exit();
}

// Statistics

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'"
);
$teacher_count = mysqli_fetch_assoc($result)["total"];


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM departments"
);
$department_count = mysqli_fetch_assoc($result)["total"];


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM courses"
);
$course_count = mysqli_fetch_assoc($result)["total"];


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM students"
);
$student_count = mysqli_fetch_assoc($result)["total"];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard - Student Management System</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body class="dashboard-page">

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="sidebar-logo">

        <div class="logo-icon">
            🎓
        </div>

        <div>
            <h2>Student MS</h2>
            <small>Management System</small>
        </div>

    </div>


    <div class="sidebar-menu">

        <a
            href="admin_dashboard.php"
            class="active"
        >
            <span class="menu-icon">⌂</span>
            <span>Dashboard</span>
        </a>


        <a href="departments.php">
            <span class="menu-icon">🏢</span>
            <span>Departments</span>
        </a>


        <a href="teachers.php">
            <span class="menu-icon">👨‍🏫</span>
            <span>Teachers</span>
        </a>


        <a href="courses.php">
            <span class="menu-icon">📚</span>
            <span>Courses</span>
        </a>


        <a href="students.php">
            <span class="menu-icon">👨‍🎓</span>
            <span>Students</span>
        </a>


        <a href="marks.php">
            <span class="menu-icon">📝</span>
            <span>Marks & Results</span>
        </a>

    </div>


    <div class="sidebar-bottom">

        <a href="logout.php">

            <span class="menu-icon">⇥</span>

            <span>Logout</span>

        </a>

    </div>

</div>


<!-- MAIN -->

<div class="main">


    <!-- TOP BAR -->

    <div class="dashboard-topbar">

        <div>

            <div class="dashboard-label">
                Administration
            </div>

            <h1>
                Admin Dashboard
            </h1>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <?php
                echo strtoupper(
                    substr(
                        $_SESSION["user_name"],
                        0,
                        1
                    )
                );
                ?>

            </div>

            <div>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION["user_name"]
                    );
                    ?>
                </strong>

                <small>
                    Administrator
                </small>

            </div>

        </div>

    </div>


    <!-- WELCOME -->

    <section class="welcome-banner">

        <div class="welcome-content">

            <span class="welcome-badge">
                👑 Administrator
            </span>

            <h2>
                Welcome back,
                <?php
                echo htmlspecialchars(
                    $_SESSION["user_name"]
                );
                ?>! 👋
            </h2>

            <p>
                Manage the college's departments,
                teachers, courses, students and
                academic records.
            </p>


            <div class="welcome-buttons">

                <a
                    href="departments.php"
                    class="welcome-primary-btn"
                >
                    🏢 Manage Departments
                </a>

                <a
                    href="teachers.php"
                    class="welcome-secondary-btn"
                >
                    👨‍🏫 Manage Teachers
                </a>

            </div>

        </div>


        <div class="welcome-illustration">

            <div class="illustration-circle">
                🎓
            </div>

            <div class="floating-card card-one">
                👨‍🏫
            </div>

            <div class="floating-card card-two">
                📚
            </div>

            <div class="floating-card card-three">
                👨‍🎓
            </div>

        </div>

    </section>


    <!-- STATISTICS -->

    <div class="dashboard-section-title">

        <div>

            <span>
                COLLEGE OVERVIEW
            </span>

            <h2>
                System Statistics
            </h2>

        </div>

    </div>


    <div class="dashboard-cards">


        <div class="dashboard-card students-card">

            <div class="dashboard-card-top">

                <div class="dashboard-icon">
                    👨‍🏫
                </div>

                <span class="dashboard-card-label">
                    Teachers
                </span>

            </div>

            <div class="dashboard-card-number">
                <?php echo $teacher_count; ?>
            </div>

            <p>
                Registered teachers
            </p>

        </div>


        <div class="dashboard-card courses-card">

            <div class="dashboard-card-top">

                <div class="dashboard-icon">
                    🏢
                </div>

                <span class="dashboard-card-label">
                    Departments
                </span>

            </div>

            <div class="dashboard-card-number">
                <?php echo $department_count; ?>
            </div>

            <p>
                College departments
            </p>

        </div>


        <div class="dashboard-card subjects-card">

            <div class="dashboard-card-top">

                <div class="dashboard-icon">
                    📚
                </div>

                <span class="dashboard-card-label">
                    Courses
                </span>

            </div>

            <div class="dashboard-card-number">
                <?php echo $course_count; ?>
            </div>

            <p>
                Academic courses
            </p>

        </div>


        <div class="dashboard-card results-card">

            <div class="dashboard-card-top">

                <div class="dashboard-icon">
                    👨‍🎓
                </div>

                <span class="dashboard-card-label">
                    Students
                </span>

            </div>

            <div class="dashboard-card-number">
                <?php echo $student_count; ?>
            </div>

            <p>
                Registered students
            </p>

        </div>

    </div>


    <!-- QUICK ACTIONS -->

    <div class="dashboard-grid">

        <div class="quick-actions">

            <div class="section-heading">

                <div>

                    <span>
                        ADMINISTRATION
                    </span>

                    <h2>
                        Quick Actions
                    </h2>

                </div>

            </div>


            <div class="quick-action-list">


                <a
                    href="departments.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">
                        🏢
                    </div>

                    <div>

                        <strong>
                            Manage Departments
                        </strong>

                        <small>
                            Add and manage departments
                        </small>

                    </div>

                    <span>→</span>

                </a>


                <a
                    href="teachers.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">
                        👨‍🏫
                    </div>

                    <div>

                        <strong>
                            Manage Teachers
                        </strong>

                        <small>
                            View registered teachers
                        </small>

                    </div>

                    <span>→</span>

                </a>


                <a
                    href="courses.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">
                        📚
                    </div>

                    <div>

                        <strong>
                            Manage Courses
                        </strong>

                        <small>
                            Manage college courses
                        </small>

                    </div>

                    <span>→</span>

                </a>


                <a
                    href="students.php"
                    class="quick-action"
                >

                    <div class="quick-action-icon">
                        👨‍🎓
                    </div>

                    <div>

                        <strong>
                            View Students
                        </strong>

                        <small>
                            View college students
                        </small>

                    </div>

                    <span>→</span>

                </a>

            </div>

        </div>


        <!-- INFORMATION -->

        <div class="recent-dashboard">

            <div class="section-heading">

                <div>

                    <span>
                        SYSTEM
                    </span>

                    <h2>
                        Administration
                    </h2>

                </div>

            </div>


            <div class="empty-dashboard">

                <div>
                    🏛️
                </div>

                <p>
                    Central management for the entire
                    college.
                </p>

                <small>
                    Departments → Courses → Sections
                    → Students → Marks → Results
                </small>

            </div>

        </div>

    </div>

</div>

</body>

</html>