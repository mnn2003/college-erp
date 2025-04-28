<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only faculty can access
if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Get faculty details
$stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify faculty owns this assignment
$stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE assignment_id = ? AND faculty_id = ?");
$stmt->execute([$assignment_id, $faculty['faculty_id']]);
if ($stmt->fetchColumn() == 0) {
    $_SESSION['error'] = "You don't have permission to view this assignment";
    header("Location: assignments.php");
    exit();
}

// Get assignment details
$assignment = $pdo->prepare("
    SELECT a.*, s.subject_code, s.subject_name
    FROM assignments a
    JOIN subjects s ON a.course_id = s.subject_id
    WHERE a.assignment_id = ?
");
$assignment->execute([$assignment_id]);
$assignment = $assignment->fetch();

// Handle form submission (grading)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $marks = (int)$_POST['marks'];
    $feedback = trim($_POST['feedback']);
    
    // Validate marks
    if ($marks < 0 || $marks > $assignment['max_marks']) {
        $_SESSION['error'] = "Marks must be between 0 and " . $assignment['max_marks'];
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET marks = ?, feedback = ? 
                WHERE submission_id = ?
            ");
            $stmt->execute([$marks, $feedback, $submission_id]);
            $_SESSION['success'] = "Submission graded successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error grading submission: " . $e->getMessage();
        }
    }
    
    header("Location: assignment_submissions.php?id=$assignment_id");
    exit();
}

// Get all submissions for this assignment
$submissions = $pdo->prepare("
    SELECT sub.*, 
           st.roll_number, st.full_name AS student_name,
           s.subject_code, s.subject_name
    FROM submissions sub
    JOIN students st ON sub.student_id = st.student_id
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    JOIN subjects s ON a.course_id = s.subject_id
    WHERE sub.assignment_id = ?
    ORDER BY 
        CASE WHEN sub.marks IS NULL THEN 0 ELSE 1 END,
        st.roll_number
");
$submissions->execute([$assignment_id]);
$submissions = $submissions->fetchAll();

// Calculate submission statistics
$total_students = $pdo->prepare("
    SELECT COUNT(*) 
    FROM student_subjects ss
    JOIN students s ON ss.student_id = s.student_id
    WHERE ss.subject_id = ?
");
$total_students->execute([$assignment['course_id']]);
$total_students = $total_students->fetchColumn();

$submitted_count = count($submissions);
$graded_count = 0;
$average_mark = 0;

foreach ($submissions as $sub) {
    if ($sub['marks'] !== null) {
        $graded_count++;
        $average_mark += $sub['marks'];
    }
}

$average_mark = $graded_count > 0 ? round($average_mark / $graded_count, 1) : 0;
$submission_rate = $total_students > 0 ? round(($submitted_count / $total_students) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Submissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold">Assignment Submissions</h1>
                <div class="text-lg text-gray-600">
                    <?= htmlspecialchars($assignment['subject_code']) ?> - <?= htmlspecialchars($assignment['subject_name']) ?>
                </div>
            </div>
            <a href="assignments.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Assignments
            </a>
        </div>
        
        <?php include '../includes/messages.php'; ?>
        
        <!-- Assignment Details Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($assignment['title']) ?></h2>
                    <p class="text-gray-700 mb-4"><?= htmlspecialchars($assignment['description']) ?></p>
                    
                    <div class="flex space-x-4 text-sm">
                        <div>
                            <span class="font-medium">Due:</span> 
                            <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?>
                            <?php if (strtotime($assignment['due_date']) < time()): ?>
                                <span class="text-xs bg-red-100 text-red-800 px-1 rounded">Past Due</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="font-medium">Max Marks:</span> <?= $assignment['max_marks'] ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="text-center text-blue-800 font-medium">Submissions</div>
                    <div class="flex justify-between space-x-4 mt-2">
                        <div class="text-center">
                            <div class="text-2xl font-bold"><?= $submitted_count ?></div>
                            <div class="text-xs text-gray-600">Submitted</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold"><?= $graded_count ?></div>
                            <div class="text-xs text-gray-600">Graded</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold"><?= $average_mark ?></div>
                            <div class="text-xs text-gray-600">Avg. Mark</div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $submission_rate ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-600 text-center mt-1"><?= $submission_rate ?>% submission rate</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Submissions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Student</th>
                        <th class="py-3 px-4 text-left">Submission</th>
                        <th class="py-3 px-4 text-left">Submitted On</th>
                        <th class="py-3 px-4 text-left">Marks</th>
                        <th class="py-3 px-4 text-left">Feedback</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">
                                No submissions yet
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                        <tr class="border-t hover:bg-gray-50 <?= $submission['marks'] === null ? 'bg-yellow-50' : '' ?>">
                            <td class="py-3 px-4">
                                <div class="font-medium"><?= htmlspecialchars($submission['student_name']) ?></div>
                                <div class="text-sm text-gray-600"><?= htmlspecialchars($submission['roll_number']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($submission['file_path']): ?>
                                    <a href="../uploads/assignments/<?= htmlspecialchars($submission['file_path']) ?>" 
                                       target="_blank" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-file-download mr-1"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No file</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?= date('M j, Y g:i A', strtotime($submission['submitted_at'])) ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($submission['marks'] !== null): ?>
                                    <span class="font-medium"><?= $submission['marks'] ?></span>/<?= $assignment['max_marks'] ?>
                                <?php else: ?>
                                    <span class="text-yellow-600">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($submission['feedback']): ?>
                                    <div class="text-sm line-clamp-2"><?= htmlspecialchars($submission['feedback']) ?></div>
                                <?php else: ?>
                                    <span class="text-gray-500">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <button onclick="openGradeModal(
                                    <?= $submission['submission_id'] ?>,
                                    '<?= htmlspecialchars(addslashes($assignment['title'])) ?>',
                                    '<?= htmlspecialchars(addslashes($submission['student_name'])) ?>',
                                    <?= $assignment['max_marks'] ?>,
                                    <?= $submission['marks'] ?: 'null' ?>,
                                    `<?= isset($submission['feedback']) ? htmlspecialchars(addslashes($submission['feedback'])) : '' ?>`
                                )" class="text-blue-600 hover:text-blue-800">
                                    <?= $submission['marks'] === null ? 'Grade' : 'Regrade' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Grade Modal -->
    <div id="gradeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative">
            <h2 class="text-xl font-semibold mb-4">Grade Submission</h2>
            <form method="post">
                <input type="hidden" name="submission_id" id="modalSubmissionId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-1">Student</label>
                        <div id="modalStudentName" class="font-medium"></div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1">Assignment</label>
                        <div id="modalAssignmentTitle" class="font-medium"></div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Marks*</label>
                        <input type="number" name="marks" id="modalMarks" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <small id="maxMarksNote" class="text-sm text-gray-500"></small>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Feedback</label>
                        <textarea name="feedback" id="modalFeedback" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="closeGradeModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="grade_submission"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Submit Grade
                    </button>
                </div>
            </form>
            <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-700" onclick="closeGradeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script>
        function openGradeModal(submissionId, assignmentTitle, studentName, maxMarks, currentMarks, currentFeedback) {
            document.getElementById('modalSubmissionId').value = submissionId;
            document.getElementById('modalAssignmentTitle').textContent = assignmentTitle;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalMarks').value = currentMarks || '';
            document.getElementById('modalMarks').setAttribute('max', maxMarks);
            document.getElementById('modalFeedback').value = currentFeedback || '';
            document.getElementById('maxMarksNote').textContent = `Max marks: ${maxMarks}`;
            document.getElementById('gradeModal').classList.remove('hidden');
        }

        function closeGradeModal() {
            document.getElementById('gradeModal').classList.add('hidden');
        }
    </script>
</body>
</html>