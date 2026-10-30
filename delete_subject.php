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

if ($role === "admin") {
    $sql = "SELECT s.id, s.name AS subject_name, s.code AS subject_code, s.semester,
                   s.max_marks, c.name AS course_name, c.code AS course_code,
                   d.name AS department_name, d.code AS department_code,
                   COUNT(m.id) AS marks_count
            FROM subjects s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            LEFT JOIN marks m ON m.subject_id = s.id
            WHERE s.id = ?
            GROUP BY s.id, s.name, s.code, s.semester, s.max_marks,
                     c.name, c.code, d.name, d.code
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
} else {
    $sql = "SELECT s.id, s.name AS subject_name, s.code AS subject_code, s.semester,
                   s.max_marks, c.name AS course_name, c.code AS course_code,
                   d.name AS department_name, d.code AS department_code,
                   COUNT(m.id) AS marks_count
            FROM subjects s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            LEFT JOIN marks m ON m.subject_id = s.id
            WHERE s.id = ? AND c.created_by = ?
            GROUP BY s.id, s.name, s.code, s.semester, s.max_marks,
                     c.name, c.code, d.name, d.code
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $subject_id, $user_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$subject = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$subject) {
    header("Location: subjects.php?delete_error=1");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm_delete"])) {
    // Re-check marks immediately before deletion. Deleting a subject with marks
    // would cascade-delete those marks, so we block it for data safety.
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM marks WHERE subject_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $subject_id);
    mysqli_stmt_execute($stmt);
    $mark_result = mysqli_stmt_get_result($stmt);
    $mark_data = mysqli_fetch_assoc($mark_result);
    $marks_count = (int) $mark_data["total"];
    mysqli_stmt_close($stmt);

    if ($marks_count > 0) {
        $error = "This subject cannot be deleted because it has " . $marks_count . " mark record(s). Remove the marks first.";
    } else {
        if ($role === "admin") {
            $stmt = mysqli_prepare($conn, "DELETE FROM subjects WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $subject_id);
        } else {
            $stmt = mysqli_prepare($conn, "DELETE s FROM subjects s INNER JOIN courses c ON s.course_id = c.id WHERE s.id = ? AND c.created_by = ?");
            mysqli_stmt_bind_param($stmt, "ii", $subject_id, $user_id);
        }

        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            header("Location: subjects.php?deleted=1");
            exit();
        }

        $error = "Unable to delete the subject. It may no longer be available to you.";
        mysqli_stmt_close($stmt);
    }
}

$marks_count = (int) $subject["marks_count"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Subject | Student Management System</title>
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
        max-width: 620px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        padding: 32px;
        box-shadow: 0 15px 45px rgba(15, 23, 42, .08)
    }

    .warning-icon {
        width: 68px;
        height: 68px;
        margin: 0 auto 20px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fef2f2;
        color: #dc2626;
        font-size: 28px
    }

    .warning-icon i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        line-height: 1
    }

    .title {
        text-align: center;
        margin: 0 0 8px;
        color: #111827;
        font-size: 24px
    }

    .subtitle {
        text-align: center;
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
        margin: 0 0 24px
    }

    .details {
        background: #f8fafc;
        border: 1px solid #e8edf5;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 20px
    }

    .detail {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 10px 0;
        border-bottom: 1px solid #e9eef5;
        font-size: 12px
    }

    .detail:last-child {
        border-bottom: 0
    }

    .detail span:first-child {
        color: #64748b;
        font-weight: 600
    }

    .detail span:last-child {
        color: #111827;
        font-weight: 700;
        text-align: right
    }

    .note {
        padding: 14px;
        border-radius: 11px;
        margin-bottom: 20px;
        font-size: 11px;
        line-height: 1.6
    }

    .danger {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412
    }

    .blocked {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b
    }

    .error {
        padding: 13px 15px;
        margin-bottom: 18px;
        border-radius: 11px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        font-size: 12px;
        font-weight: 600
    }

    .actions {
        display: flex;
        gap: 12px
    }

    .btn {
        flex: 1;
        height: 45px;
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

    .delete {
        background: #dc2626;
        color: #fff;
        border: 0
    }

    .delete:hover {
        background: #b91c1c
    }

    @media(max-width:600px) {
        .page {
            padding: 15px
        }

        .card {
            padding: 23px 18px
        }

        .detail {
            flex-direction: column;
            gap: 4px
        }

        .detail span:last-child {
            text-align: left
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
            <div class="warning-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h1 class="title">Delete Subject?</h1>
            <p class="subtitle">Review the subject information before continuing.</p>
            <?php if ($error !== ""): ?><div class="error"><i class="fa-solid fa-circle-exclamation"></i>
                <?=htmlspecialchars($error)?></div><?php endif; ?>
            <div class="details">
                <div class="detail"><span>Subject</span><span><?=htmlspecialchars($subject["subject_name"])?></span>
                </div>
                <div class="detail"><span>Subject
                        Code</span><span><?=htmlspecialchars($subject["subject_code"])?></span></div>
                <div class="detail">
                    <span>Course</span><span><?=htmlspecialchars($subject["course_name"] . " (" . $subject["course_code"] . ")")?></span>
                </div>
                <div class="detail"><span>Semester</span><span>Semester <?= (int)$subject["semester"] ?></span></div>
                <div class="detail"><span>Maximum Marks</span><span><?= (int)$subject["max_marks"] ?> marks</span></div>
                <div class="detail"><span>Marks Records</span><span><?= $marks_count ?></span></div>
                <?php if($role === "admin"): ?><div class="detail">
                    <span>Department</span><span><?=htmlspecialchars($subject["department_name"] . " (" . $subject["department_code"] . ")")?></span>
                </div><?php endif; ?>
            </div>
            <?php if($marks_count > 0): ?>
            <div class="note blocked"><i class="fa-solid fa-lock"></i> <strong>Deletion blocked.</strong> This subject
                has <?= $marks_count ?> mark record(s). Remove those marks first to protect student result data.</div>
            <div class="actions"><a class="btn cancel" href="subjects.php"><i class="fa-solid fa-arrow-left"></i> Back
                    to Subjects</a></div>
            <?php else: ?>
            <div class="note danger"><i class="fa-solid fa-circle-info"></i> This subject has no marks. It is safe to
                delete, but this action cannot be undone.</div>
            <form method="POST"><input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                <div class="actions"><a class="btn cancel" href="subjects.php"><i class="fa-solid fa-arrow-left"></i>
                        Cancel</a><button type="submit" name="confirm_delete" class="btn delete"><i
                            class="fa-solid fa-trash"></i> Delete Subject</button></div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>