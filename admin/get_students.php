<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$course_id = (int)$_GET['course_id'] ?? 0;
$date = $_GET['date'] ?? date('Y-m-d');

if ($course_id <= 0) {
    echo '<div class="text-red-500 p-4">Invalid course selected</div>';
    exit();
}

// Get students for this course
$stmt = $pdo->prepare("
    SELECT s.student_id, s.roll_number, s.full_name, a.status
    FROM students s
    JOIN student_subjects ss ON s.student_id = ss.student_id
    LEFT JOIN attendance a ON s.student_id = a.student_id 
        AND a.course_id = ? AND a.date = ?
    WHERE ss.subject_id = ?
    ORDER BY s.roll_number
");
$stmt->execute([$course_id, $date, $course_id]);
$students = $stmt->fetchAll();

if (empty($students)) {
    echo '<div class="text-gray-500 p-4">No students enrolled in this course</div>';
    exit();
}
?>

<table class="min-w-full">
    <thead class="bg-gray-100">
        <tr>
            <th class="py-2 px-4 text-left">Roll No.</th>
            <th class="py-2 px-4 text-left">Student Name</th>
            <th class="py-2 px-4 text-left">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($students as $student): ?>
        <tr class="border-t">
            <td class="py-2 px-4"><?= htmlspecialchars($student['roll_number']) ?></td>
            <td class="py-2 px-4"><?= htmlspecialchars($student['full_name']) ?></td>
            <td class="py-2 px-4">
                <select name="attendance[<?= $student['student_id'] ?>]"
                        class="px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="Present" <?= $student['status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Absent" <?= $student['status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="Late" <?= $student['status'] === 'Late' ? 'selected' : '' ?>>Late</option>
                </select>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>