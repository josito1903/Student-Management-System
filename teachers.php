<?php

include "auth.php";
include "db.php";

// =========================================
// ADMIN ONLY
// =========================================

if ($_SESSION["role"] !== "admin") {

    header("Location: teacher_dashboard.php");
    exit();

}


// =========================================
// GET TEACHERS
// =========================================

$sql = "
    SELECT
        u.id,
        u.name,
        u.username,
        u.email,
        u.created_at,
        d.name AS department_name,
        d.code AS department_code
    FROM users u

    LEFT JOIN departments d
        ON u.department_id = d.id

    WHERE u.role = 'teacher'

    ORDER BY u.id DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {

    die(
        "Error loading teachers: " .
        mysqli_error($conn)
    );

}


// =========================================
// TOTAL TEACHERS
// =========================================

$count_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'teacher'"
);

$count_row = mysqli_fetch_assoc($count_result);

$total_teachers = $count_row["total"];

/*
|--------------------------------------------------------------------------
| ACTION MESSAGES
|--------------------------------------------------------------------------
*/
$success_message = "";
$error_message = "";

if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $success_message = "Teacher account deleted successfully.";
}

if (isset($_GET["delete_error"]) && $_GET["delete_error"] === "1") {
    $error_message = "The teacher could not be deleted. Please try again.";
}


?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Teachers - Student Management System
    </title>

    <link rel="stylesheet" href="style.css">

</head>


<body class="teachers-page">


    <!-- =========================================
     SIDEBAR
========================================= -->

    <div class="sidebar">


        <!-- LOGO -->

        <div class="sidebar-logo">

            <div class="logo-icon">
                🎓
            </div>

            <div>

                <h2>
                    Student MS
                </h2>

                <small>
                    Management System
                </small>

            </div>

        </div>



        <!-- MENU -->

        <div class="sidebar-menu">


            <a href="admin_dashboard.php">

                <span class="menu-icon">
                    ⌂
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="departments.php">

                <span class="menu-icon">
                    🏢
                </span>

                <span>
                    Departments
                </span>

            </a>


            <a href="teachers.php" class="active">

                <span class="menu-icon">
                    👨‍🏫
                </span>

                <span>
                    Teachers
                </span>

            </a>


            <a href="courses.php">

                <span class="menu-icon">
                    📚
                </span>

                <span>
                    Courses
                </span>

            </a>


            <a href="sections.php">

                <span class="menu-icon">
                    🧩
                </span>

                <span>
                    Sections
                </span>

            </a>


            <a href="students.php">

                <span class="menu-icon">
                    👨‍🎓
                </span>

                <span>
                    Students
                </span>

            </a>


            <a href="marks.php">

                <span class="menu-icon">
                    📝
                </span>

                <span>
                    Marks & Results
                </span>

            </a>


            <a href="result.php">

                <span class="menu-icon">
                    📊
                </span>

                <span>
                    Results
                </span>

            </a>


        </div>



        <!-- LOGOUT -->

        <div class="sidebar-bottom">

            <a href="logout.php">

                <span class="menu-icon">
                    ⇥
                </span>

                <span>
                    Logout
                </span>

            </a>

        </div>


    </div>



    <!-- =========================================
     MAIN CONTENT
========================================= -->

    <div class="main teachers-main">


        <!-- =====================================
         TOP BAR
    ====================================== -->

        <div class="dashboard-topbar">


            <div>

                <div class="dashboard-label">
                    COLLEGE ADMINISTRATION
                </div>


                <h1>
                    Teachers
                </h1>

            </div>



            <!-- ADMIN PROFILE -->

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



        <!-- =====================================
         HERO
    ====================================== -->

        <section class="teachers-hero">


            <div class="teachers-hero-content">


                <div class="teachers-badge">
                    👨‍🏫 Faculty Management
                </div>


                <h2>
                    Manage College Teachers
                </h2>


                <p>
                    View registered teachers and their
                    departments. Teachers create their
                    own accounts and manage their
                    academic work from their dashboard.
                </p>


            </div>


            <div class="teachers-hero-icon">

                <div class="teachers-icon-circle">
                    👨‍🏫
                </div>

            </div>


        </section>



        <!-- =====================================
         STATISTICS
    ====================================== -->

        <div class="teacher-stat-grid">


            <div class="teacher-stat-card">

                <div class="teacher-stat-icon blue">
                    👨‍🏫
                </div>


                <div>

                    <span>
                        Total Teachers
                    </span>

                    <strong>
                        <?php
                    echo $total_teachers;
                    ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-stat-card">

                <div class="teacher-stat-icon purple">
                    🏢
                </div>


                <div>

                    <span>
                        Faculty System
                    </span>

                    <strong>
                        Multi-Department
                    </strong>

                </div>

            </div>


            <div class="teacher-stat-card">

                <div class="teacher-stat-icon green">
                    🔐
                </div>


                <div>

                    <span>
                        Account Access
                    </span>

                    <strong>
                        Role Based
                    </strong>

                </div>

            </div>


        </div>



        <!-- =====================================
         TEACHERS LIST
    ====================================== -->

        <div class="teachers-list-card">


            <div class="teachers-list-header">


                <div>

                    <span>
                        REGISTERED FACULTY
                    </span>


                    <h2>
                        College Teachers
                    </h2>

                </div>


                <div class="teacher-count-badge">

                    <?php
                echo $total_teachers;
                ?>

                    Teachers

                </div>


            </div>



            <!-- TEACHER LIST -->

            <div class="teachers-list">


                <?php if (
                mysqli_num_rows($result) > 0
            ): ?>


                <?php while (
                    $teacher =
                    mysqli_fetch_assoc($result)
                ): ?>


                <div class="teacher-item">


                    <!-- AVATAR -->

                    <div class="teacher-avatar">

                        <?php

                            echo strtoupper(
                                substr(
                                    $teacher["name"],
                                    0,
                                    1
                                )
                            );

                            ?>

                    </div>



                    <!-- INFORMATION -->

                    <div class="teacher-info">


                        <h3>

                            <?php

                                echo htmlspecialchars(
                                    $teacher["name"]
                                );

                                ?>

                        </h3>


                        <div class="teacher-username">

                            @<?php

                                echo htmlspecialchars(
                                    $teacher["username"]
                                );

                                ?>

                        </div>


                        <div class="teacher-email">

                            ✉️

                            <?php

                                echo htmlspecialchars(
                                    $teacher["email"]
                                );

                                ?>

                        </div>


                    </div>



                    <!-- DEPARTMENT -->

                    <div class="teacher-department">


                        <span>
                            DEPARTMENT
                        </span>


                        <?php if (
                                !empty(
                                    $teacher["department_name"]
                                )
                            ): ?>


                        <strong>

                            🏢

                            <?php

                                    echo htmlspecialchars(
                                        $teacher["department_name"]
                                    );

                                    ?>

                        </strong>


                        <small>

                            <?php

                                    echo htmlspecialchars(
                                        $teacher["department_code"]
                                    );

                                    ?>

                        </small>


                        <?php else: ?>


                        <strong class="unassigned">
                            Not Assigned
                        </strong>


                        <?php endif; ?>


                    </div>



                    <!-- DATE -->

                    <div class="teacher-date">


                        <span>
                            JOINED
                        </span>


                        <strong>

                            <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $teacher["created_at"]
                                    )
                                );

                                ?>

                        </strong>


                    </div>



                    <!-- ACTIONS -->

                    <div class="teacher-actions">


                        <a href="edit_teacher.php?id=<?php echo (int) $teacher["id"]; ?>" class="teacher-action edit"
                            title="Edit Teacher">

                            ✏️

                        </a>


                        <a href="delete_teacher.php?id=<?php echo (int) $teacher["id"]; ?>"
                            class="teacher-action delete" title="Delete Teacher">

                            🗑️

                        </a>


                    </div>


                </div>


                <?php endwhile; ?>


                <?php else: ?>


                <div class="teacher-empty">


                    <div class="teacher-empty-icon">
                        👨‍🏫
                    </div>


                    <h3>
                        No Teachers Registered
                    </h3>


                    <p>
                        Teachers will appear here after
                        creating their accounts.
                    </p>


                    <a href="register.php">
                        View Registration Page
                    </a>


                </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</body>

</html>