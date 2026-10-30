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

$error = "";
$success = "";


// =========================================
// ADD DEPARTMENT
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $code = strtoupper(trim($_POST["code"] ?? ""));
    $description = trim($_POST["description"] ?? "");

    if ($name === "" || $code === "") {

        $error = "Department name and code are required.";

    } else {

        // Check duplicate department
        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM departments
             WHERE name = ? OR code = ?"
        );

        mysqli_stmt_bind_param(
            $check,
            "ss",
            $name,
            $code
        );

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_result) > 0) {

            $error = "Department name or code already exists.";

        } else {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO departments
                (name, code, description)
                VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $name,
                $code,
                $description
            );

            if (mysqli_stmt_execute($stmt)) {

                $success = "Department added successfully.";

                $name = "";
                $code = "";
                $description = "";

            } else {

                $error = "Unable to add department.";

            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}


// =========================================
// GET DEPARTMENTS
// =========================================

$result = mysqli_query(
    $conn,
    "SELECT id, name, code, description
     FROM departments
     ORDER BY name ASC"
);

if (!$result) {
    die("Error loading departments: " . mysqli_error($conn));
}


// =========================================
// TOTAL DEPARTMENTS
// =========================================

$count_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM departments"
);

$count_row = mysqli_fetch_assoc($count_result);
$total_departments = $count_row["total"];

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
        Departments - Student Management System
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="departments-page">


<!-- =========================================
     SIDEBAR
========================================= -->

<div class="sidebar">

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


    <div class="sidebar-menu">

        <a href="admin_dashboard.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="departments.php"
            class="active"
        >

            <span class="menu-icon">
                🏢
            </span>

            <span>
                Departments
            </span>

        </a>


        <a href="teachers.php">

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

    </div>


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
     MAIN
========================================= -->

<div class="main departments-main">


    <!-- =====================================
         TOP BAR
    ====================================== -->

    <div class="dashboard-topbar">

        <div>

            <div class="dashboard-label">
                COLLEGE ADMINISTRATION
            </div>

            <h1>
                Departments
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



    <!-- =====================================
         HERO
    ====================================== -->

    <section class="departments-hero">


        <div class="departments-hero-content">


            <div class="departments-badge">
                🏢 College Structure
            </div>


            <h2>
                Manage College Departments
            </h2>


            <p>
                Create and manage the academic departments
                that organize teachers, courses, sections
                and students across the college.
            </p>


        </div>


        <div class="departments-hero-icon">

            <div class="departments-icon-circle">
                🏢
            </div>

        </div>


    </section>



    <!-- =====================================
         STATISTICS
    ====================================== -->

    <div class="department-stat-grid">


        <div class="department-stat-card">

            <div class="department-stat-icon blue">
                🏢
            </div>


            <div>

                <span>
                    Total Departments
                </span>

                <strong>
                    <?php echo $total_departments; ?>
                </strong>

            </div>

        </div>


        <div class="department-stat-card">

            <div class="department-stat-icon purple">
                👨‍🏫
            </div>


            <div>

                <span>
                    College Structure
                </span>

                <strong>
                    Departments
                </strong>

            </div>

        </div>


        <div class="department-stat-card">

            <div class="department-stat-icon green">
                📚
            </div>


            <div>

                <span>
                    Academic Organization
                </span>

                <strong>
                    Courses
                </strong>

            </div>

        </div>


    </div>



    <!-- =====================================
         MAIN DEPARTMENT AREA
    ====================================== -->

    <div class="departments-content-grid">


        <!-- =================================
             ADD DEPARTMENT
        ================================== -->

        <div class="department-form-card">


            <div class="department-card-header">


                <div class="department-heading-icon">
                    ➕
                </div>


                <div>

                    <span>
                        NEW DEPARTMENT
                    </span>

                    <h2>
                        Add Department
                    </h2>

                </div>


            </div>



            <!-- ERROR -->

            <?php if ($error !== ""): ?>

                <div class="department-message department-error">

                    <span>
                        ⚠️
                    </span>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $error
                        );

                        ?>

                    </span>

                </div>

            <?php endif; ?>



            <!-- SUCCESS -->

            <?php if ($success !== ""): ?>

                <div class="department-message department-success">

                    <span>
                        ✅
                    </span>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $success
                        );

                        ?>

                    </span>

                </div>

            <?php endif; ?>



            <form
                method="POST"
                class="department-form"
            >


                <!-- NAME -->

                <div class="department-form-group">

                    <label for="name">
                        Department Name
                    </label>


                    <div class="department-input-wrapper">

                        <span>
                            🏢
                        </span>


                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="e.g. Computer Science"
                            value="<?php
                            echo htmlspecialchars(
                                $name ?? ""
                            );
                            ?>"
                            required
                        >

                    </div>

                </div>



                <!-- CODE -->

                <div class="department-form-group">

                    <label for="code">
                        Department Code
                    </label>


                    <div class="department-input-wrapper">

                        <span>
                            🔖
                        </span>


                        <input
                            type="text"
                            id="code"
                            name="code"
                            placeholder="e.g. CS"
                            value="<?php
                            echo htmlspecialchars(
                                $code ?? ""
                            );
                            ?>"
                            required
                        >

                    </div>

                    <small>
                        Use a short unique code such as CS, COM or MGT.
                    </small>

                </div>



                <!-- DESCRIPTION -->

                <div class="department-form-group">

                    <label for="description">
                        Description
                    </label>


                    <div class="department-textarea-wrapper">

                        <span>
                            📝
                        </span>


                        <textarea
                            id="description"
                            name="description"
                            placeholder="Brief description of the department..."
                            rows="5"
                        ><?php

                        echo htmlspecialchars(
                            $description ?? ""
                        );

                        ?></textarea>

                    </div>

                </div>



                <!-- BUTTON -->

                <button
                    type="submit"
                    class="department-submit-btn"
                >

                    <span>
                        ➕ Add Department
                    </span>

                    <span>
                        →
                    </span>

                </button>


            </form>


        </div>



        <!-- =================================
             DEPARTMENT LIST
        ================================== -->

        <div class="department-list-card">


            <div class="department-list-header">


                <div>

                    <span>
                        COLLEGE DEPARTMENTS
                    </span>


                    <h2>
                        Existing Departments
                    </h2>

                </div>


                <div class="department-count-badge">

                    <?php
                    echo $total_departments;
                    ?>

                    Departments

                </div>


            </div>



            <!-- LIST -->

            <div class="department-list">


                <?php if (
                    mysqli_num_rows($result) > 0
                ): ?>


                    <?php while (
                        $department =
                        mysqli_fetch_assoc($result)
                    ): ?>


                        <div class="department-item">


                            <!-- ICON -->

                            <div class="department-item-icon">
                                🏢
                            </div>



                            <!-- DETAILS -->

                            <div class="department-item-info">


                                <h3>

                                    <?php

                                    echo htmlspecialchars(
                                        $department["name"]
                                    );

                                    ?>

                                </h3>


                                <div class="department-code">

                                    <span>
                                        CODE
                                    </span>

                                    <?php

                                    echo htmlspecialchars(
                                        $department["code"]
                                    );

                                    ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $department["description"]
                                    )
                                ): ?>

                                    <p>

                                        <?php

                                        echo htmlspecialchars(
                                            $department["description"]
                                        );

                                        ?>

                                    </p>

                                <?php else: ?>

                                    <p class="no-description">
                                        No description added.
                                    </p>

                                <?php endif; ?>


                            </div>



                            <!-- ACTIONS -->

                            <div class="department-item-actions">


                                <a
                                    href="edit_department.php?id=<?php echo (int) $department["id"]; ?>"
                                    class="department-action edit"
                                    title="Edit Department"
                                >

                                    <span>
                                        ✏️
                                    </span>

                                    <span>
                                        Edit
                                    </span>

                                </a>


                                <a
                                    href="delete_department.php?id=<?php echo (int) $department["id"]; ?>"
                                    class="department-action delete"
                                    title="Delete Department"
                                    onclick="return confirm('Are you sure you want to delete this department?');"
                                >

                                    <span>
                                        🗑️
                                    </span>

                                    <span>
                                        Delete
                                    </span>

                                </a>


                            </div>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="department-empty">

                        <div class="department-empty-icon">
                            🏢
                        </div>


                        <h3>
                            No Departments Yet
                        </h3>


                        <p>
                            Create your first college department
                            using the form.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>