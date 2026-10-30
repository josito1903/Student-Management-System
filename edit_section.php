<?php
require_once "auth.php";
require_once "db.php";

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$section_id = (int) ($_GET["id"] ?? $_POST["section_id"] ?? 0);
if ($section_id <= 0) {
    header("Location: sections.php");
    exit();
}

$error = "";
$message = "";

/* Load section with course and teacher information. */
$stmt = mysqli_prepare($conn, "
    SELECT
        s.id, s.course_id, s.teacher_id, s.name, s.semester, s.academic_year,
        c.name AS course_name, c.code AS course_code, c.department_id,
        d.name AS department_name, d.code AS department_code,
        u.name AS teacher_name
    FROM sections s
    INNER JOIN courses c ON s.course_id = c.id
    INNER JOIN departments d ON c.department_id = d.id
    INNER JOIN users u ON s.teacher_id = u.id
    WHERE s.id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $section_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$section = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$section) {
    header("Location: sections.php");
    exit();
}

/* Permission check. Teachers may edit only sections assigned to themselves. */
if ($role === "teacher" && (int) $section["teacher_id"] !== $user_id) {
    header("Location: sections.php");
    exit();
}

if ($role !== "admin" && $role !== "teacher") {
    header("Location: logout.php");
    exit();
}

/* Admin can choose from courses and teachers; teacher can only keep own course/assignment. */
$courses = [];
$teachers = [];

if ($role === "admin") {
    $result = mysqli_query($conn, "
        SELECT c.id, c.name, c.code, c.department_id, d.name AS department_name, d.code AS department_code
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        ORDER BY d.name ASC, c.name ASC
    ");
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }

    $result = mysqli_query($conn, "
        SELECT u.id, u.name, u.department_id, d.name AS department_name, d.code AS department_code
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.role = 'teacher'
        ORDER BY u.name ASC
    ");
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT c.id, c.name, c.code, c.department_id, d.name AS department_name, d.code AS department_code
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        WHERE c.created_by = ?
        ORDER BY c.name ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    mysqli_stmt_close($stmt);
}

/* Handle update. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_section"])) {
    $course_id = (int) ($_POST["course_id"] ?? 0);
    $section_name = trim($_POST["section_name"] ?? "");
    $semester = (int) ($_POST["semester"] ?? 0);
    $academic_year = trim($_POST["academic_year"] ?? "");
    $teacher_id = $role === "admin" ? (int) ($_POST["teacher_id"] ?? 0) : $user_id;

    if ($course_id <= 0 || $section_name === "" || $semester < 1 || $semester > 12 || $academic_year === "") {
        $error = "Please fill in all section details correctly.";
    } elseif (strlen($section_name) > 50) {
        $error = "Section name is too long.";
    } elseif (strlen($academic_year) > 20) {
        $error = "Academic year is too long.";
    } else {
        /* Validate course and teacher relationship. */
        if ($role === "teacher") {
            $check = mysqli_prepare($conn, "
                SELECT c.id
                FROM courses c
                WHERE c.id = ? AND c.created_by = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($check, "ii", $course_id, $user_id);
        } else {
            $check = mysqli_prepare($conn, "
                SELECT c.id
                FROM courses c
                WHERE c.id = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($check, "i", $course_id);
        }
        mysqli_stmt_execute($check);
        $check_result = mysqli_stmt_get_result($check);
        $course_valid = mysqli_num_rows($check_result) > 0;
        mysqli_stmt_close($check);

        if (!$course_valid) {
            $error = $role === "teacher"
                ? "You can only use courses created by you."
                : "The selected course does not exist.";
        } else {
            /* Validate teacher. Admin cannot assign a teacher from another department. */
            if ($role === "teacher") {
                $teacher_valid = true;
            } else {
                $check = mysqli_prepare($conn, "
                    SELECT u.id
                    FROM users u
                    INNER JOIN courses c ON c.department_id = u.department_id
                    WHERE u.id = ? AND u.role = 'teacher' AND c.id = ?
                    LIMIT 1
                ");
                mysqli_stmt_bind_param($check, "ii", $teacher_id, $course_id);
                mysqli_stmt_execute($check);
                $check_result = mysqli_stmt_get_result($check);
                $teacher_valid = mysqli_num_rows($check_result) > 0;
                mysqli_stmt_close($check);
            }

            if (!$teacher_valid) {
                $error = "The selected teacher must belong to the course's department.";
            } else {
                /* Prevent duplicate course + section + semester + year. */
                $duplicate = mysqli_prepare($conn, "
                    SELECT id
                    FROM sections
                    WHERE course_id = ?
                      AND name = ?
                      AND semester = ?
                      AND academic_year = ?
                      AND id <> ?
                    LIMIT 1
                ");
                mysqli_stmt_bind_param($duplicate, "isisi", $course_id, $section_name, $semester, $academic_year, $section_id);
                mysqli_stmt_execute($duplicate);
                $duplicate_result = mysqli_stmt_get_result($duplicate);
                $already_exists = mysqli_num_rows($duplicate_result) > 0;
                mysqli_stmt_close($duplicate);

                if ($already_exists) {
                    $error = "Another section already uses this course, section name, semester and academic year.";
                } else {
                    $update = mysqli_prepare($conn, "
                        UPDATE sections
                        SET course_id = ?, teacher_id = ?, name = ?, semester = ?, academic_year = ?
                        WHERE id = ?
                    ");
                    mysqli_stmt_bind_param($update, "iisisi", $course_id, $teacher_id, $section_name, $semester, $academic_year, $section_id);
                    // Correct the parameter type string without changing the values.
                    mysqli_stmt_close($update);
                    $update = mysqli_prepare($conn, "
                        UPDATE sections
                        SET course_id = ?, teacher_id = ?, name = ?, semester = ?, academic_year = ?
                        WHERE id = ?
                    ");
                    mysqli_stmt_bind_param($update, "iisisi", $course_id, $teacher_id, $section_name, $semester, $academic_year, $section_id);
                    if (mysqli_stmt_execute($update)) {
                        $message = "Section updated successfully.";
                        $section["course_id"] = $course_id;
                        $section["teacher_id"] = $teacher_id;
                        $section["name"] = $section_name;
                        $section["semester"] = $semester;
                        $section["academic_year"] = $academic_year;
                    } else {
                        $error = "Unable to update the section. Please try again.";
                    }
                    mysqli_stmt_close($update);
                }
            }
        }
    }
}

/* Refresh selected course/teacher display after a successful update. */
$stmt = mysqli_prepare($conn, "
    SELECT
        s.id, s.course_id, s.teacher_id, s.name, s.semester, s.academic_year,
        c.name AS course_name, c.code AS course_code, c.department_id,
        d.name AS department_name, d.code AS department_code,
        u.name AS teacher_name
    FROM sections s
    INNER JOIN courses c ON s.course_id = c.id
    INNER JOIN departments d ON c.department_id = d.id
    INNER JOIN users u ON s.teacher_id = u.id
    WHERE s.id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $section_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$section = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

/* Count students for a useful safety notice. */
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM students WHERE section_id = ?");
mysqli_stmt_bind_param($stmt, "i", $section_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student_count = (int) mysqli_fetch_assoc($result)["total"];
mysqli_stmt_close($stmt);

$initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Section | Student Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0
    }

    body {
        font-family: Inter, sans-serif;
        background: #f5f7fb;
        color: #172033
    }

    a {
        text-decoration: none
    }

    .page {
        min-height: 100vh
    }

    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #172554, #1e3a8a 55%, #2563eb);
        color: #fff;
        padding: 24px 16px;
        z-index: 10;
        overflow-y: auto;
        box-shadow: 5px 0 25px rgba(15, 23, 42, .12)
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 10px 28px;
        color: #fff
    }

    .brand-icon {
        width: 45px;
        height: 45px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, .15);
        font-size: 21px
    }

    .brand strong {
        display: block;
        font-size: 15px;
        font-weight: 800
    }

    .brand span {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        opacity: .72
    }

    .nav-title {
        color: rgba(255, 255, 255, .55);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 700;
        margin: 8px 12px 10px
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        color: rgba(255, 255, 255, .82);
        padding: 12px 13px;
        border-radius: 11px;
        margin-bottom: 5px;
        font-size: 13px;
        font-weight: 600;
        transition: .2s
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 14px
    }

    .nav-link:hover,
    .nav-link.active {
        background: rgba(255, 255, 255, .15);
        color: #fff;
        transform: translateX(2px)
    }

    .nav-link.active {
        box-shadow: inset 3px 0 #fff
    }

    .logout {
        margin-top: 18px;
        color: #fecaca
    }

    .main {
        margin-left: 260px;
        min-height: 100vh;
        padding: 30px 34px 50px
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px
    }

    .crumb {
        font-size: 12px;
        color: #64748b;
        font-weight: 600
    }

    .heading {
        font-size: 27px;
        color: #111827;
        margin-top: 5px
    }

    .user {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        border: 1px solid #e8edf5;
        padding: 8px 13px 8px 8px;
        border-radius: 14px;
        box-shadow: 0 5px 20px rgba(15, 23, 42, .05)
    }

    .avatar {
        width: 39px;
        height: 39px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        font-weight: 800
    }

    .user strong {
        display: block;
        font-size: 12px
    }

    .user span {
        font-size: 10px;
        color: #64748b;
        text-transform: capitalize
    }

    .card {
        max-width: 900px;
        background: #fff;
        border: 1px solid #e8edf5;
        border-radius: 20px;
        box-shadow: 0 8px 28px rgba(15, 23, 42, .05);
        overflow: hidden
    }

    .card-head {
        padding: 23px 25px;
        border-bottom: 1px solid #eef2f7
    }

    .card-head h2 {
        font-size: 18px;
        color: #111827
    }

    .card-head p {
        font-size: 11px;
        color: #64748b;
        margin-top: 6px
    }

    .body {
        padding: 25px
    }

    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px
    }

    .field {
        margin-bottom: 3px
    }

    .field.full {
        grid-column: 1/-1
    }

    .field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 7px
    }

    .control {
        width: 100%;
        height: 44px;
        border: 1px solid #dbe3ee;
        border-radius: 10px;
        padding: 0 13px;
        background: #fff;
        color: #1e293b;
        font: inherit;
        font-size: 12px;
        outline: none
    }

    .control:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .1)
    }

    .info {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8fafc;
        border: 1px solid #e7edf5;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 20px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.5
    }

    .info i {
        color: #4f46e5;
        font-size: 16px
    }

    .warning {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        border-radius: 12px;
        padding: 13px;
        margin-top: 20px;
        font-size: 11px;
        line-height: 1.6
    }

    .warning i {
        margin-right: 6px
    }

    .actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 43px;
        padding: 0 18px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer
    }

    .cancel {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #dbe3ee
    }

    .save {
        border: 0;
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .2)
    }

    .alert {
        max-width: 900px;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 17px;
        font-size: 11px;
        font-weight: 600
    }

    .success {
        color: #166534;
        background: #f0fdf4;
        border: 1px solid #bbf7d0
    }

    .error {
        color: #991b1b;
        background: #fef2f2;
        border: 1px solid #fecaca
    }

    .readonly {
        background: #f8fafc;
        color: #64748b
    }

    .tag {
        display: inline-block;
        margin-left: 7px;
        background: #eef2ff;
        color: #4f46e5;
        border-radius: 6px;
        padding: 4px 7px;
        font-size: 9px;
        font-weight: 800
    }

    @media(max-width:900px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            padding: 12px
        }

        .brand {
            padding: 5px 8px 13px
        }

        .nav-title {
            display: none
        }

        .nav-link {
            display: inline-flex;
            width: auto;
            margin: 2px;
            padding: 9px 10px
        }

        .nav-link.active {
            box-shadow: none
        }

        .logout {
            margin-top: 0
        }

        .main {
            margin-left: 0;
            padding: 22px 18px 40px
        }
    }

    @media(max-width:650px) {
        .topbar {
            align-items: flex-start;
            flex-direction: column
        }

        .user {
            width: 100%
        }

        .grid {
            grid-template-columns: 1fr
        }

        .field.full {
            grid-column: auto
        }

        .card-head,
        .body {
            padding: 20px
        }

        .actions {
            flex-direction: column-reverse
        }

        .btn {
            width: 100%
        }
    }

    @media(max-width:430px) {
        .main {
            padding: 17px 12px 30px
        }

        .heading {
            font-size: 23px
        }
    }
    </style>
</head>

<body>
    <div class="page">
        <aside class="sidebar">
            <a class="brand" href="<?= $role === 'admin' ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>">
                <div class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div><strong>Student Management</strong><span><?= ucfirst($role) ?> Portal</span></div>
            </a>
            <div class="nav-title">Main Menu</div>
            <?php if($role==='admin'): ?>
            <a class="nav-link" href="admin_dashboard.php"><i class="fa-solid fa-chart-pie"></i>Dashboard</a>
            <a class="nav-link" href="departments.php"><i class="fa-solid fa-building-columns"></i>Departments</a>
            <a class="nav-link" href="teachers.php"><i class="fa-solid fa-chalkboard-user"></i>Teachers</a>
            <a class="nav-link" href="courses.php"><i class="fa-solid fa-book-open"></i>Courses</a>
            <a class="nav-link active" href="sections.php"><i class="fa-solid fa-layer-group"></i>Sections</a>
            <a class="nav-link" href="students.php"><i class="fa-solid fa-user-graduate"></i>Students</a>
            <a class="nav-link" href="marks.php"><i class="fa-solid fa-square-poll-vertical"></i>Marks & Results</a>
            <?php else: ?>
            <a class="nav-link" href="teacher_dashboard.php"><i class="fa-solid fa-chart-pie"></i>Dashboard</a>
            <a class="nav-link" href="courses.php"><i class="fa-solid fa-book-open"></i>My Courses</a>
            <a class="nav-link active" href="sections.php"><i class="fa-solid fa-layer-group"></i>My Sections</a>
            <a class="nav-link" href="students.php"><i class="fa-solid fa-user-graduate"></i>My Students</a>
            <a class="nav-link" href="marks.php"><i class="fa-solid fa-square-poll-vertical"></i>Marks</a>
            <a class="nav-link" href="result.php"><i class="fa-solid fa-award"></i>Results</a>
            <?php endif; ?>
            <a class="nav-link logout" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </aside>
        <main class="main">
            <div class="topbar">
                <div>
                    <div class="crumb">Student Management System / Sections / Edit</div>
                    <h1 class="heading">Edit Section</h1>
                </div>
                <div class="user">
                    <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                    <div><strong><?= htmlspecialchars($user_name) ?></strong><span><?= htmlspecialchars($role) ?></span>
                    </div>
                </div>
            </div>
            <?php if($message): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="card">
                <div class="card-head">
                    <h2><i class="fa-solid fa-pen-to-square"></i> Section Information</h2>
                    <p>Update the academic section without changing its students or marks.</p>
                </div>
                <div class="body">
                    <div class="info"><i class="fa-solid fa-circle-info"></i>
                        <div><strong>Current section:</strong> <?= htmlspecialchars($section["course_name"]) ?>
                            (<?= htmlspecialchars($section["course_code"]) ?>) — Section
                            <?= htmlspecialchars($section["name"]) ?>, Semester <?= (int)$section["semester"] ?>,
                            <?= htmlspecialchars($section["academic_year"]) ?></div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="section_id" value="<?= $section_id ?>">
                        <div class="grid">
                            <div class="field"><label for="course_id">Course</label><select class="control"
                                    id="course_id" name="course_id" required>
                                    <?php foreach($courses as $course): ?><option value="<?= (int)$course['id'] ?>"
                                        <?= (int)$course['id']===(int)$section['course_id']?'selected':'' ?>>
                                        <?= htmlspecialchars($course['name']) ?> —
                                        <?= htmlspecialchars($course['code']) ?><?php if($role==='admin'): ?>
                                        (<?= htmlspecialchars($course['department_code']) ?>)<?php endif; ?></option>
                                    <?php endforeach; ?></select></div>
                            <div class="field"><label for="section_name">Section Name</label><input class="control"
                                    id="section_name" name="section_name" maxlength="50" required
                                    value="<?= htmlspecialchars($section['name']) ?>" placeholder="e.g. A"></div>
                            <div class="field"><label for="semester">Semester</label><select class="control"
                                    id="semester" name="semester" required><?php for($i=1;$i<=12;$i++): ?><option
                                        value="<?= $i ?>" <?= (int)$section['semester']===$i?'selected':'' ?>>Semester
                                        <?= $i ?></option><?php endfor; ?></select></div>
                            <div class="field"><label for="academic_year">Academic Year</label><input class="control"
                                    id="academic_year" name="academic_year" maxlength="20" required
                                    value="<?= htmlspecialchars($section['academic_year']) ?>" placeholder="2026-27">
                            </div>
                            <?php if($role==='admin'): ?><div class="field full"><label for="teacher_id">Assigned
                                    Teacher</label><select class="control" id="teacher_id" name="teacher_id" required>
                                    <option value="">Select teacher</option><?php foreach($teachers as $teacher): ?>
                                    <option value="<?= (int)$teacher['id'] ?>"
                                        <?= (int)$teacher['id']===(int)$section['teacher_id']?'selected':'' ?>>
                                        <?= htmlspecialchars($teacher['name']) ?> —
                                        <?= htmlspecialchars($teacher['department_code'] ?? 'No Department') ?></option>
                                    <?php endforeach; ?>
                                </select></div><?php else: ?><div class="field full"><label>Assigned
                                    Teacher</label><input class="control readonly" readonly
                                    value="<?= htmlspecialchars($user_name) ?> — You are assigned to this section">
                            </div><?php endif; ?>
                        </div>
                        <?php if($student_count>0): ?><div class="warning"><i
                                class="fa-solid fa-triangle-exclamation"></i><strong><?= $student_count ?>
                                student<?= $student_count===1?'':'s' ?> currently assigned.</strong> Editing the section
                            is safe; existing students and marks remain connected to this section.</div><?php endif; ?>
                        <div class="actions"><a class="btn cancel" href="sections.php"><i
                                    class="fa-solid fa-arrow-left"></i> Cancel</a><button class="btn save" type="submit"
                                name="update_section"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>

</html>