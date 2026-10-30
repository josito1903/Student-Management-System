<?php
require_once "auth.php";
require_once "db.php";

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];

$subject_id = (int) ($_GET["id"] ?? $_POST["subject_id"] ?? 0);
if ($subject_id <= 0) {
    header("Location: subjects.php");
    exit();
}

$errors = [];

function get_subject(mysqli $conn, int $subject_id, string $role, int $user_id): ?array
{
    if ($role === "admin") {
        $sql = "SELECT s.id, s.course_id, s.name AS subject_name, s.code AS subject_code,
                       s.semester, s.max_marks, c.name AS course_name, c.code AS course_code,
                       d.name AS department_name, d.code AS department_code
                FROM subjects s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                WHERE s.id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $subject_id);
    } else {
        $sql = "SELECT s.id, s.course_id, s.name AS subject_name, s.code AS subject_code,
                       s.semester, s.max_marks, c.name AS course_name, c.code AS course_code,
                       d.name AS department_name, d.code AS department_code
                FROM subjects s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                WHERE s.id = ? AND c.created_by = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $subject_id, $user_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subject = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);
    return $subject;
}

$subject = get_subject($conn, $subject_id, $role, $user_id);
if (!$subject) {
    header("Location: subjects.php?delete_error=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_subject"])) {
    $course_id = (int) ($_POST["course_id"] ?? 0);
    $subject_name = trim($_POST["subject_name"] ?? "");
    $subject_code = trim($_POST["subject_code"] ?? "");
    $semester = (int) ($_POST["semester"] ?? 0);
    $max_marks = (int) ($_POST["max_marks"] ?? 0);

    if ($course_id <= 0) $errors[] = "Please select a course.";
    if ($subject_name === "") $errors[] = "Subject name is required.";
    if ($subject_code === "") $errors[] = "Subject code is required.";
    if ($semester < 1 || $semester > 12) $errors[] = "Semester must be between 1 and 12.";
    if ($max_marks < 1 || $max_marks > 99999) $errors[] = "Maximum marks must be between 1 and 99999.";

    if (empty($errors)) {
        if ($role === "admin") {
            $stmt = mysqli_prepare($conn, "SELECT id FROM courses WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $course_id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id FROM courses WHERE id = ? AND created_by = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ii", $course_id, $user_id);
        }
        mysqli_stmt_execute($stmt);
        $course_result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($course_result) === 0) $errors[] = "The selected course is not available to you.";
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM subjects WHERE course_id = ? AND code = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "isi", $course_id, $subject_code, $subject_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) $errors[] = "This subject code already exists for the selected course.";
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        if ($role === "admin") {
            $sql = "UPDATE subjects SET course_id = ?, name = ?, code = ?, semester = ?, max_marks = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issiii", $course_id, $subject_name, $subject_code, $semester, $max_marks, $subject_id);
        } else {
            $sql = "UPDATE subjects s INNER JOIN courses c ON s.course_id = c.id
                    SET s.course_id = ?, s.name = ?, s.code = ?, s.semester = ?, s.max_marks = ?
                    WHERE s.id = ? AND c.created_by = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issiiii", $course_id, $subject_name, $subject_code, $semester, $max_marks, $subject_id, $user_id);
        }

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) {
            mysqli_stmt_close($stmt);
            header("Location: subjects.php?updated=1");
            exit();
        }
        $errors[] = "Unable to update the subject. Please try again.";
        mysqli_stmt_close($stmt);
    }

    $subject["course_id"] = $course_id;
    $subject["subject_name"] = $subject_name;
    $subject["subject_code"] = $subject_code;
    $subject["semester"] = $semester;
    $subject["max_marks"] = $max_marks;
}

if ($role === "admin") {
    $course_sql = "SELECT c.id, c.name, c.code, d.code AS department_code
                   FROM courses c INNER JOIN departments d ON c.department_id = d.id
                   ORDER BY d.name, c.name";
    $course_stmt = mysqli_prepare($conn, $course_sql);
} else {
    $course_sql = "SELECT c.id, c.name, c.code, d.code AS department_code
                   FROM courses c INNER JOIN departments d ON c.department_id = d.id
                   WHERE c.created_by = ? ORDER BY c.name";
    $course_stmt = mysqli_prepare($conn, $course_sql);
    mysqli_stmt_bind_param($course_stmt, "i", $user_id);
}
mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subject | Student Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
    * {
        box-sizing: border-box
    }

    body {
        margin: 0;
        font-family: Inter, Arial, sans-serif;
        background: #f5f7fb;
        color: #172033
    }

    .page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 25px
    }

    .card {
        width: 100%;
        max-width: 650px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        padding: 30px;
        box-shadow: 0 15px 45px rgba(15, 23, 42, .08)
    }

    .icon {
        width: 58px;
        height: 58px;
        border-radius: 16px;
        background: #eef2ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        font-size: 22px
    }

    .icon i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        line-height: 1
    }

    .title {
        margin: 0;
        color: #182033;
        font-size: 26px
    }

    .subtitle {
        margin: 7px 0 24px;
        color: #7d8798;
        font-size: 13px;
        line-height: 1.6
    }

    .alert {
        padding: 13px 15px;
        border-radius: 11px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 12px;
        line-height: 1.6;
        margin-bottom: 18px
    }

    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px
    }

    .group {
        margin-bottom: 16px
    }

    .full {
        grid-column: 1/-1
    }

    .group label {
        display: block;
        margin-bottom: 7px;
        font-size: 12px;
        font-weight: 700;
        color: #374151
    }

    .input,
    .select {
        width: 100%;
        height: 46px;
        padding: 0 13px;
        border: 1px solid #dfe4ed;
        border-radius: 11px;
        background: #fff;
        color: #20283a;
        font-size: 13px;
        outline: none
    }

    .input:focus,
    .select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .1)
    }

    .readonly {
        background: #f8fafc;
        color: #64748b
    }

    .help {
        margin-top: 5px;
        color: #98a1b1;
        font-size: 10px
    }

    .actions {
        display: flex;
        gap: 12px;
        margin-top: 8px
    }

    .btn {
        flex: 1;
        height: 46px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer
    }

    .cancel {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0
    }

    .save {
        background: #4f46e5;
        color: #fff;
        border: 0
    }

    .save:hover {
        background: #4338ca
    }

    .course-info {
        padding: 12px;
        border-radius: 11px;
        background: #f8fafc;
        border: 1px solid #e8ebf2;
        color: #64748b;
        font-size: 11px;
        margin-bottom: 18px
    }

    @media(max-width:600px) {
        .page {
            padding: 15px
        }

        .card {
            padding: 22px 18px
        }

        .grid {
            grid-template-columns: 1fr
        }

        .full {
            grid-column: auto
        }

        .actions {
            flex-direction: column
        }
    }
    </style>
</head>

<body>
    <div class="page">
        <div class="card">
            <div class="icon"><i class="fa-solid fa-pen"></i></div>
            <h1 class="title">Edit Subject</h1>
            <p class="subtitle">Update the subject information while keeping course ownership and permissions secure.
            </p>
            <?php if (!empty($errors)): ?><div class="alert"><i class="fa-solid fa-circle-exclamation"></i>
                <?php foreach($errors as $e): ?><div><?=htmlspecialchars($e)?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                <div class="grid">
                    <div class="group full"><label for="course_id">Course</label><select class="select" name="course_id"
                            id="course_id" required>
                            <option value="">Select course</option>
                            <?php while($course=mysqli_fetch_assoc($course_result)): ?><option
                                value="<?= (int)$course['id'] ?>"
                                <?=((int)$subject['course_id']===(int)$course['id'])?'selected':''?>>
                                <?=htmlspecialchars($course['name'].' ('.$course['code'].') — '.$course['department_code'])?>
                            </option><?php endwhile; ?>
                        </select></div>
                    <div class="group"><label for="subject_name">Subject Name</label><input class="input" type="text"
                            name="subject_name" id="subject_name" maxlength="100"
                            value="<?=htmlspecialchars($subject['subject_name'])?>" required></div>
                    <div class="group"><label for="subject_code">Subject Code</label><input class="input" type="text"
                            name="subject_code" id="subject_code" maxlength="20"
                            value="<?=htmlspecialchars($subject['subject_code'])?>" required>
                        <div class="help">Unique within the selected course.</div>
                    </div>
                    <div class="group"><label for="semester">Semester</label><select class="select" name="semester"
                            id="semester" required><?php for($i=1;$i<=12;$i++): ?><option value="<?=$i?>"
                                <?=((int)$subject['semester']===$i)?'selected':''?>>Semester <?=$i?></option>
                            <?php endfor; ?></select></div>
                    <div class="group"><label for="max_marks">Maximum Marks</label><input class="input" type="number"
                            name="max_marks" id="max_marks" min="1" max="99999"
                            value="<?=htmlspecialchars($subject['max_marks'])?>" required></div>
                </div>
                <div class="course-info"><strong>Current course:</strong>
                    <?=htmlspecialchars($subject['course_name'].' ('.$subject['course_code'].')')?></div>
                <div class="actions"><a class="btn cancel" href="subjects.php"><i class="fa-solid fa-arrow-left"></i>
                        Cancel</a><button class="btn save" type="submit" name="update_subject"><i
                            class="fa-solid fa-floppy-disk"></i> Save Changes</button></div>
            </form>
        </div>
    </div>
</body>

</html>