<?php

require_once "auth.php";
require_once "db.php";

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$message = "";
$error = "";


/* =========================================================
   ADD COURSE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_course"])) {

    $name = trim($_POST["name"]);
    $code = trim($_POST["code"]);
    $duration = (int) $_POST["duration_years"];

    if ($name === "" || $code === "" || $duration <= 0) {

        $error = "Please fill in all course fields.";

    } else {

        /* Get teacher's department */
        if ($role === "teacher") {

            $stmt = mysqli_prepare(
                $conn,
                "SELECT department_id
                 FROM users
                 WHERE id = ? AND role = 'teacher'"
            );

            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);

            $teacher_data = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );

            $department_id = $teacher_data["department_id"] ?? null;

            if (!$department_id) {
                $error = "You are not assigned to a department.";
            }

        } else {

            $department_id = (int) $_POST["department_id"];

            if ($department_id <= 0) {
                $error = "Please select a department.";
            }
        }


        if ($error === "") {

            /* Check duplicate course code */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM courses
                 WHERE code = ?"
            );

            mysqli_stmt_bind_param($stmt, "s", $code);
            mysqli_stmt_execute($stmt);

            $existing = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );


            if ($existing) {

                $error = "A course with this code already exists.";

            } else {

                /* Insert course */

                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO courses
                    (department_id, name, code, duration_years, created_by)
                    VALUES (?, ?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "issii",
                    $department_id,
                    $name,
                    $code,
                    $duration,
                    $user_id
                );

                if (mysqli_stmt_execute($stmt)) {

                    $message = "Course added successfully.";

                } else {

                    $error = "Failed to add course.";
                }
            }
        }
    }
}


/* =========================================================
   DELETE COURSE
========================================================= */

if (isset($_GET["delete"])) {

    $course_id = (int) $_GET["delete"];

    if ($course_id > 0) {

        if ($role === "teacher") {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM courses
                 WHERE id = ?
                 AND created_by = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $course_id,
                $user_id
            );

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM courses
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $course_id
            );
        }


        if (mysqli_stmt_execute($stmt)) {

            $message = "Course deleted successfully.";

        } else {

            $error = "Unable to delete course.";
        }
    }
}


/* =========================================================
   DEPARTMENTS
========================================================= */

$departments = [];

if ($role === "admin") {

    $result = mysqli_query(
        $conn,
        "SELECT id, name, code
         FROM departments
         ORDER BY name ASC"
    );

    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}


/* =========================================================
   COURSES
========================================================= */

if ($role === "admin") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            c.id,
            c.name,
            c.code,
            c.duration_years,
            c.created_at,
            c.created_by,
            d.name AS department_name,
            d.code AS department_code,
            u.name AS creator_name
         FROM courses c
         INNER JOIN departments d
            ON c.department_id = d.id
         LEFT JOIN users u
            ON c.created_by = u.id
         ORDER BY c.id DESC"
    );

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            c.id,
            c.name,
            c.code,
            c.duration_years,
            c.created_at,
            c.created_by,
            d.name AS department_name,
            d.code AS department_code,
            u.name AS creator_name
         FROM courses c
         INNER JOIN departments d
            ON c.department_id = d.id
         LEFT JOIN users u
            ON c.created_by = u.id
         INNER JOIN users teacher
            ON teacher.id = ?
         WHERE c.department_id = teacher.department_id
         ORDER BY c.id DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );
}


mysqli_stmt_execute($stmt);

$courses_result = mysqli_stmt_get_result($stmt);


/* =========================================================
   COURSE COUNT
========================================================= */

$course_count = mysqli_num_rows($courses_result);

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
        Courses | Student Management System
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>


<body class="dashboard-page courses-page">


<div class="dashboard-layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">


        <div class="sidebar-logo">

            <div class="sidebar-logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>

            <div>

                <h2>Student MS</h2>

                <span>
                    <?php
                    echo $role === "admin"
                        ? "Management System"
                        : "Teacher Portal";
                    ?>
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <?php if ($role === "admin"): ?>

                <a href="admin_dashboard.php">

                    <i class="fas fa-house"></i>

                    <span>Dashboard</span>

                </a>

            <?php else: ?>

                <a href="teacher_dashboard.php">

                    <i class="fas fa-house"></i>

                    <span>Dashboard</span>

                </a>

            <?php endif; ?>


            <a
                href="courses.php"
                class="active"
            >

                <i class="fas fa-book"></i>

                <span>
                    <?php
                    echo $role === "teacher"
                        ? "My Courses"
                        : "Courses";
                    ?>
                </span>

            </a>


            <a href="sections.php">

                <i class="fas fa-layer-group"></i>

                <span>
                    <?php
                    echo $role === "teacher"
                        ? "My Sections"
                        : "Sections";
                    ?>
                </span>

            </a>


            <a href="students.php">

                <i class="fas fa-user-graduate"></i>

                <span>
                    <?php
                    echo $role === "teacher"
                        ? "My Students"
                        : "Students";
                    ?>
                </span>

            </a>


            <a href="marks.php">

                <i class="fas fa-pen-to-square"></i>

                <span>
                    <?php
                    echo $role === "teacher"
                        ? "Marks"
                        : "Marks & Results";
                    ?>
                </span>

            </a>


            <?php if ($role === "teacher"): ?>

                <a href="result.php">

                    <i class="fas fa-chart-column"></i>

                    <span>Results</span>

                </a>

            <?php endif; ?>


            <?php if ($role === "admin"): ?>

                <a href="departments.php">

                    <i class="fas fa-building-columns"></i>

                    <span>Departments</span>

                </a>


                <a href="teachers.php">

                    <i class="fas fa-chalkboard-user"></i>

                    <span>Teachers</span>

                </a>

            <?php endif; ?>


        </nav>


        <div class="sidebar-bottom">

            <a href="logout.php">

                <i class="fas fa-right-from-bracket"></i>

                <span>Logout</span>

            </a>

        </div>


    </aside>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="dashboard-main">


        <!-- TOPBAR -->

        <header class="dashboard-topbar">

            <div>

                <h1>
                    <?php
                    echo $role === "teacher"
                        ? "My Courses"
                        : "Courses";
                    ?>
                </h1>

                <p>
                    <?php
                    echo $role === "teacher"
                        ? "Manage the courses for your department."
                        : "Manage all academic courses across the college.";
                    ?>
                </p>

            </div>


            <div class="admin-profile">

                <div class="admin-avatar">

                    <?php
                    echo strtoupper(
                        substr($user_name, 0, 1)
                    );
                    ?>

                </div>


                <div>

                    <strong>
                        <?php
                        echo htmlspecialchars($user_name);
                        ?>
                    </strong>

                    <span>
                        <?php
                        echo ucfirst($role);
                        ?>
                    </span>

                </div>

            </div>

        </header>


        <!-- HERO -->

        <section class="courses-hero">

            <div>

                <span class="courses-badge">

                    <i class="fas fa-book-open"></i>

                    Academic Management

                </span>


                <h2>

                    <?php
                    echo $role === "teacher"
                        ? "Manage Your Courses"
                        : "College Courses";
                    ?>

                </h2>


                <p>

                    <?php
                    echo $role === "teacher"
                        ? "Create and manage the courses you teach."
                        : "View and manage courses across all departments.";
                    ?>

                </p>

            </div>


            <div class="courses-hero-icon">

                <i class="fas fa-book"></i>

            </div>

        </section>


        <!-- MESSAGES -->

        <?php if ($message): ?>

            <div class="course-message success">

                <i class="fas fa-circle-check"></i>

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="course-message error">

                <i class="fas fa-circle-exclamation"></i>

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- CONTENT GRID -->

        <div class="courses-content-grid">


            <!-- ADD COURSE -->

            <section class="course-form-card">


                <div class="course-card-header">

                    <div>

                        <span class="course-kicker">
                            COURSE MANAGEMENT
                        </span>

                        <h2>
                            Add New Course
                        </h2>

                    </div>


                    <div class="course-header-icon">

                        <i class="fas fa-plus"></i>

                    </div>

                </div>


                <form
                    method="POST"
                    class="course-form"
                >


                    <div class="course-form-group">

                        <label>
                            Course Name
                        </label>

                        <div class="course-input-wrapper">

                            <i class="fas fa-book"></i>

                            <input
                                type="text"
                                name="name"
                                placeholder="e.g. Bachelor of Computer Applications"
                                required
                            >

                        </div>

                    </div>


                    <div class="course-form-group">

                        <label>
                            Course Code
                        </label>

                        <div class="course-input-wrapper">

                            <i class="fas fa-hashtag"></i>

                            <input
                                type="text"
                                name="code"
                                placeholder="e.g. BCA"
                                required
                            >

                        </div>

                    </div>


                    <?php if ($role === "admin"): ?>

                        <div class="course-form-group">

                            <label>
                                Department
                            </label>

                            <div class="course-input-wrapper">

                                <i class="fas fa-building"></i>

                                <select
                                    name="department_id"
                                    required
                                >

                                    <option value="">
                                        Select Department
                                    </option>

                                    <?php foreach ($departments as $department): ?>

                                        <option
                                            value="<?php echo $department["id"]; ?>"
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $department["name"]
                                            );
                                            ?>

                                            (
                                            <?php
                                            echo htmlspecialchars(
                                                $department["code"]
                                            );
                                            ?>
                                            )

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="course-teacher-note">

                            <i class="fas fa-building"></i>

                            <div>

                                <span>Your Department</span>

                                <strong>
                                    Automatically assigned
                                </strong>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="course-form-group">

                        <label>
                            Duration
                        </label>

                        <div class="course-input-wrapper">

                            <i class="fas fa-calendar"></i>

                            <select
                                name="duration_years"
                                required
                            >

                                <option value="1">
                                    1 Year
                                </option>

                                <option value="2">
                                    2 Years
                                </option>

                                <option
                                    value="3"
                                    selected
                                >
                                    3 Years
                                </option>

                                <option value="4">
                                    4 Years
                                </option>

                                <option value="5">
                                    5 Years
                                </option>

                            </select>

                        </div>

                    </div>


                    <button
                        type="submit"
                        name="add_course"
                        class="course-submit-btn"
                    >

                        <i class="fas fa-plus"></i>

                        Add Course

                    </button>


                </form>


            </section>


            <!-- COURSE LIST -->

            <section class="course-list-card">


                <div class="course-list-header">

                    <div>

                        <span class="course-kicker">
                            AVAILABLE COURSES
                        </span>

                        <h2>
                            Course List
                        </h2>

                    </div>


                    <div class="course-count-badge">

                        <?php echo $course_count; ?>

                        Courses

                    </div>

                </div>


                <div class="course-list">


                    <?php if ($course_count > 0): ?>


                        <?php while (
                            $course = mysqli_fetch_assoc($courses_result)
                        ): ?>


                            <div class="course-item">


                                <div class="course-item-icon">

                                    <i class="fas fa-book"></i>

                                </div>


                                <div class="course-item-info">

                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $course["name"]
                                        );
                                        ?>

                                    </h3>


                                    <div class="course-meta">

                                        <span class="course-code">

                                            <?php
                                            echo htmlspecialchars(
                                                $course["code"]
                                            );
                                            ?>

                                        </span>


                                        <span>

                                            <i class="fas fa-building"></i>

                                            <?php
                                            echo htmlspecialchars(
                                                $course["department_name"]
                                            );
                                            ?>

                                        </span>


                                        <span>

                                            <i class="fas fa-clock"></i>

                                            <?php
                                            echo $course["duration_years"];
                                            ?>
                                            Years

                                        </span>

                                    </div>


                                    <?php if ($role === "admin"): ?>

                                        <small class="course-created-by">

                                            <i class="fas fa-user"></i>

                                            Created by
                                            <?php
                                            echo htmlspecialchars(
                                                $course["creator_name"]
                                                ?? "Unknown"
                                            );
                                            ?>

                                        </small>

                                    <?php endif; ?>


                                </div>


                                <div class="course-item-actions">


                                    <?php
                                    $can_delete =
                                        $role === "admin"
                                        ||
                                        (
                                            $role === "teacher"
                                            &&
                                            $course["created_by"]
                                            == $user_id
                                        );
                                    ?>


                                    <?php if ($can_delete): ?>

                                        <a
                                            href="courses.php?delete=<?php echo $course["id"]; ?>"
                                            class="course-delete"
                                            onclick="return confirm('Are you sure you want to delete this course?');"
                                            title="Delete Course"
                                        >

                                            <i class="fas fa-trash"></i>

                                        </a>

                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <div class="course-empty">

                            <div class="course-empty-icon">

                                <i class="fas fa-book-open"></i>

                            </div>

                            <h3>
                                No Courses Yet
                            </h3>

                            <p>
                                Add your first course using the form.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>


            </section>


        </div>


    </main>


</div>


</body>

</html>