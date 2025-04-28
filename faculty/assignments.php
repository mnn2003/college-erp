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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_assignment'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $course_id = (int)$_POST['course_id'];
        $due_date = $_POST['due_date'];
        $max_marks = (int)$_POST['max_marks'];
        
        // Validate inputs
        $errors = [];
        
        if (empty($title)) $errors[] = "Title is required";
        if (empty($course_id)) $errors[] = "Please select a course";
        if (empty($due_date)) $errors[] = "Due date is required";
        if ($max_marks <= 0) $errors[] = "Max marks must be positive";
        
        // Verify faculty is assigned to this course
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ?");
        $stmt->execute([$faculty['faculty_id'], $course_id]);
        if ($stmt->fetchColumn() == 0) $errors[] = "You are not assigned to this course";
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assignments 
                    (title, description, course_id, faculty_id, due_date, max_marks) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $course_id, $faculty['faculty_id'], $due_date, $max_marks]);
                $_SESSION['success'] = "Assignment created successfully!";
                header("Location: assignments.php");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error creating assignment: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['grade_assignment'])) {
        $submission_id = (int)$_POST['submission_id'];
        $marks = (int)$_POST['marks'];
        $feedback = trim($_POST['feedback']);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET marks = ?, feedback = ?, graded_at = NOW() 
                WHERE submission_id = ?
            ");
            $stmt->execute([$marks, $feedback, $submission_id]);
            $_SESSION['success'] = "Assignment graded successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error grading assignment: " . $e->getMessage();
        }
        
        header("Location: assignments.php");
        exit();
    }
}

// Get faculty's courses
$faculty_courses = $pdo->prepare("
    SELECT s.subject_id, s.subject_code, s.subject_name
    FROM subjects s
    JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
    WHERE fs.faculty_id = ?
    ORDER BY s.subject_name
");
$faculty_courses->execute([$faculty['faculty_id']]);
$faculty_courses = $faculty_courses->fetchAll();

// Get faculty's assignments
$assignments = $pdo->prepare("
    SELECT a.*, s.subject_code, s.subject_name,
           COUNT(sub.submission_id) AS submissions_count,
           COUNT(CASE WHEN sub.marks IS NOT NULL THEN 1 END) AS graded_count
    FROM assignments a
    JOIN subjects s ON a.course_id = s.subject_id
    LEFT JOIN submissions sub ON a.assignment_id = sub.assignment_id
    WHERE a.faculty_id = ?
    GROUP BY a.assignment_id
    ORDER BY a.due_date DESC
");
$assignments->execute([$faculty['faculty_id']]);
$assignments = $assignments->fetchAll();

// Get submissions to grade
$submissions_to_grade = $pdo->prepare("
    SELECT sub.*, a.title AS assignment_title, a.max_marks,
           s.subject_code, s.subject_name,
           st.roll_number, st.full_name AS student_name
    FROM submissions sub
    JOIN assignments a ON sub.assignment_id = a.assignment_id
    JOIN subjects s ON a.course_id = s.subject_id
    JOIN students st ON sub.student_id = st.student_id
    WHERE a.faculty_id = ? AND sub.marks IS NULL
    ORDER BY sub.submitted_at
");
$submissions_to_grade->execute([$faculty['faculty_id']]);
$submissions_to_grade = $submissions_to_grade->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <h1 class="text-3xl font-bold mb-6">Manage Assignments</h1>
        
        <?php include '../includes/messages.php'; ?>
        
        <!-- Tabs -->
        <div class="flex border-b mb-6">
            <button id="assignmentsTab" class="px-4 py-2 font-medium border-b-2 border-blue-500 text-blue-600">
                My Assignments
            </button>
            <button id="gradeTab" class="px-4 py-2 font-medium text-gray-500">
                Grade Submissions
            </button>
            <button id="createTab" class="px-4 py-2 font-medium text-gray-500">
                Create Assignment
            </button>
        </div>
        
        <!-- Assignments Section -->
        <div id="assignmentsSection">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left">Assignment</th>
                            <th class="py-3 px-4 text-left">Course</th>
                            <th class="py-3 px-4 text-left">Due Date</th>
                            <th class="py-3 px-4 text-left">Submissions</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="5" class="py-4 px-4 text-center text-gray-500">No assignments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="font-medium"><?= htmlspecialchars($assignment['title']) ?></div>
                                    <div class="text-sm text-gray-600 line-clamp-2">
                                        <?= htmlspecialchars($assignment['description']) ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <?= htmlspecialchars($assignment['subject_code']) ?> - 
                                    <?= htmlspecialchars($assignment['subject_name']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?= date('M j, Y', strtotime($assignment['due_date'])) ?>
                                    <?php if (strtotime($assignment['due_date']) < time()): ?>
                                        <span class="text-xs bg-red-100 text-red-800 px-1 rounded">Past Due</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                            <div class="bg-green-600 h-2.5 rounded-full" 
                                                 style="width: <?= $assignment['submissions_count'] > 0 ? round(($assignment['graded_count'] / $assignment['submissions_count']) * 100) : 0 ?>%"></div>
                                        </div>
                                        <?= $assignment['graded_count'] ?>/<?= $assignment['submissions_count'] ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="assignment_submissions.php?id=<?= $assignment['assignment_id'] ?>"
                                       class="text-blue-600 hover:text-blue-800">
                                        View Submissions
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Grade Submissions Section (Hidden by default) -->
        <div id="gradeSection" class="hidden">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <?php if (empty($submissions_to_grade)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <p>No submissions to grade.</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-3 px-4 text-left">Student</th>
                                <th class="py-3 px-4 text-left">Assignment</th>
                                <th class="py-3 px-4 text-left">Course</th>
                                <th class="py-3 px-4 text-left">Submitted</th>
                                <th class="py-3 px-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions_to_grade as $submission): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="font-medium"><?= htmlspecialchars($submission['student_name']) ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($submission['roll_number']) ?></div>
                                </td>
                                <td class="py-3 px-4"><?= htmlspecialchars($submission['assignment_title']) ?></td>
                                <td class="py-3 px-4">
                                    <?= htmlspecialchars($submission['subject_code']) ?> - 
                                    <?= htmlspecialchars($submission['subject_name']) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <button onclick="openGradeModal(
                                        <?= $submission['submission_id'] ?>,
                                        '<?= htmlspecialchars(addslashes($submission['assignment_title'])) ?>',
                                        '<?= htmlspecialchars(addslashes($submission['student_name'])) ?>',
                                        <?= $submission['max_marks'] ?>
                                    )" class="text-blue-600 hover:text-blue-800">
                                        Grade Now
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Create Assignment Section (Hidden by default) -->
        <div id="createSection" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4">Create New Assignment</h2>
                <form method="post">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Title*</label>
                            <input type="text" name="title" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['title']) ? htmlspecialchars($_SESSION['form_data']['title']) : '' ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= isset($_SESSION['form_data']['description']) ? htmlspecialchars($_SESSION['form_data']['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Course*</label>
                                <select name="course_id" required
                                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Course</option>
                                    <?php foreach ($faculty_courses as $course): ?>
                                    <option value="<?= $course['subject_id'] ?>"
                                        <?= (isset($_SESSION['form_data']['course_id']) && $_SESSION['form_data']['course_id'] == $course['subject_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['subject_code']) ?> - 
                                        <?= htmlspecialchars($course['subject_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Due Date*</label>
                                <input type="datetime-local" name="due_date" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="<?= isset($_SESSION['form_data']['due_date']) ? htmlspecialchars($_SESSION['form_data']['due_date']) : '' ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Max Marks*</label>
                            <input type="number" name="max_marks" min="1" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['max_marks']) ? htmlspecialchars($_SESSION['form_data']['max_marks']) : '100' ?>">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="button" onclick="showSection('assignments')"
                                class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                            Cancel
                        </button>
                        <button type="submit" name="add_assignment"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            Create Assignment
                        </button>
                    </div>
                </form>
            </div>
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
                        <label class="block text-gray-700 mb-2">Marks</label>
                        <input type="number" name="marks" id="modalMarks" min="0" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <small id="maxMarksNote" class="text-sm text-gray-500"></small>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Feedback</label>
                        <textarea name="feedback" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="closeGradeModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="grade_assignment"
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
        function showSection(section) {
            const sections = ['assignments', 'grade', 'create'];
            sections.forEach(s => {
                document.getElementById(`${s}Section`).classList.add('hidden');
                document.getElementById(`${s}Tab`).classList.remove('text-blue-600', 'border-blue-500');
                document.getElementById(`${s}Tab`).classList.add('text-gray-500');
            });

            document.getElementById(`${section}Section`).classList.remove('hidden');
            document.getElementById(`${section}Tab`).classList.add('text-blue-600', 'border-blue-500');
            document.getElementById(`${section}Tab`).classList.remove('text-gray-500');
        }

        document.getElementById('assignmentsTab').addEventListener('click', () => showSection('assignments'));
        document.getElementById('gradeTab').addEventListener('click', () => showSection('grade'));
        document.getElementById('createTab').addEventListener('click', () => showSection('create'));

        function openGradeModal(submissionId, assignmentTitle, studentName, maxMarks) {
            document.getElementById('modalSubmissionId').value = submissionId;
            document.getElementById('modalAssignmentTitle').textContent = assignmentTitle;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalMarks').setAttribute('max', maxMarks);
            document.getElementById('maxMarksNote').textContent = `Max marks: ${maxMarks}`;
            document.getElementById('gradeModal').classList.remove('hidden');
        }

        function closeGradeModal() {
            document.getElementById('gradeModal').classList.add('hidden');
        }
    </script>
</body>
</html>
