<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get all programs for dropdown
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_course'])) {
        // Add new course
        $subject_code = trim($_POST['subject_code']);
        $subject_name = trim($_POST['subject_name']);
        $program_id = (int)$_POST['program_id'];
        $semester = (int)$_POST['semester'];
        $credits = (int)$_POST['credits'];

        // Validate inputs
        $errors = [];
        if (empty($subject_code)) $errors[] = "Subject code is required";
        if (empty($subject_name)) $errors[] = "Subject name is required";
        if ($program_id <= 0) $errors[] = "Please select a valid program";
        if ($semester < 1 || $semester > 8) $errors[] = "Invalid semester (1-8)";
        if ($credits < 1 || $credits > 5) $errors[] = "Invalid credits (1-5)";

        // Check for duplicate subject code
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = ?");
        $stmt->execute([$subject_code]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Subject code already exists";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, program_id, semester, credits) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$subject_code, $subject_name, $program_id, $semester, $credits]);
                $_SESSION['success'] = "Course added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding course: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['edit_course'])) {
        // Edit existing course
        $subject_id = (int)$_POST['subject_id'];
        $subject_code = trim($_POST['subject_code']);
        $subject_name = trim($_POST['subject_name']);
        $program_id = (int)$_POST['program_id'];
        $semester = (int)$_POST['semester'];
        $credits = (int)$_POST['credits'];

        // Validate inputs
        $errors = [];
        if (empty($subject_code)) $errors[] = "Subject code is required";
        if (empty($subject_name)) $errors[] = "Subject name is required";
        if ($program_id <= 0) $errors[] = "Please select a valid program";
        if ($semester < 1 || $semester > 8) $errors[] = "Invalid semester (1-8)";
        if ($credits < 1 || $credits > 5) $errors[] = "Invalid credits (1-5)";

        // Check for duplicate subject code (excluding the current course)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = ? AND subject_id != ?");
        $stmt->execute([$subject_code, $subject_id]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Subject code already exists";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, program_id = ?, semester = ?, credits = ? WHERE subject_id = ?");
                $stmt->execute([$subject_code, $subject_name, $program_id, $semester, $credits, $subject_id]);
                $_SESSION['success'] = "Course updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating course: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['delete_course'])) {
        // Delete course
        $subject_id = (int)$_POST['subject_id'];
        try {
            // Check if course has any enrollments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE subject_id = ?");
            $stmt->execute([$subject_id]);
            $enrollment_count = $stmt->fetchColumn();
            if ($enrollment_count > 0) {
                $_SESSION['error'] = "Cannot delete course - $enrollment_count students are enrolled!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                $_SESSION['success'] = "Course deleted successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
        }
    }
    header("Location: manage_courses.php");
    exit();
}

// Get all courses with program names
$courses = $pdo->query("
    SELECT s.*, p.program_name 
    FROM subjects s
    JOIN programs p ON s.program_id = p.program_id
    ORDER BY p.program_name, s.semester, s.subject_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Manage Courses</h1>
            <button onclick="document.getElementById('addCourseModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Add Course
            </button>
        </div>
        <?php include '../includes/messages.php'; ?>
        <!-- Courses Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Code</th>
                        <th class="py-3 px-4 text-left">Name</th>
                        <th class="py-3 px-4 text-left">Program</th>
                        <th class="py-3 px-4 text-left">Semester</th>
                        <th class="py-3 px-4 text-left">Credits</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">No courses found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4 font-mono"><?= htmlspecialchars($course['subject_code']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($course['subject_name']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($course['program_name']) ?></td>
                            <td class="py-3 px-4"><?= $course['semester'] ?></td>
                            <td class="py-3 px-4"><?= $course['credits'] ?></td>
                            <td class="py-3 px-4 space-x-2">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($course)) ?>)"
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this course?');">
                                    <input type="hidden" name="subject_id" value="<?= $course['subject_id'] ?>">
                                    <button type="submit" name="delete_course" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div id="addCourseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Add New Course</h2>
                <button onclick="document.getElementById('addCourseModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Subject Code*</label>
                        <input type="text" name="subject_code" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., CS101">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Subject Name*</label>
                        <input type="text" name="subject_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Introduction to Programming">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Program*</label>
                        <select name="program_id" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                            <option value="<?= $program['program_id'] ?>"><?= htmlspecialchars($program['program_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Semester*</label>
                            <select name="semester" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Credits*</label>
                            <select name="credits" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> credit<?= $i > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('addCourseModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="add_course"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Edit Course</h2>
                <button onclick="document.getElementById('editCourseModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" id="editCourseForm">
                <input type="hidden" name="subject_id" id="editSubjectId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Subject Code*</label>
                        <input type="text" name="subject_code" id="editSubjectCode" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., CS101">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Subject Name*</label>
                        <input type="text" name="subject_name" id="editSubjectName" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Introduction to Programming">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Program*</label>
                        <select name="program_id" id="editProgramId" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                            <option value="<?= $program['program_id'] ?>"><?= htmlspecialchars($program['program_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Semester*</label>
                            <select name="semester" id="editSemester" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Credits*</label>
                            <select name="credits" id="editCredits" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> credit<?= $i > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('editCourseModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="edit_course"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addCourseModal');
            const editModal = document.getElementById('editCourseModal');
            if (event.target === addModal) addModal.classList.add('hidden');
            if (event.target === editModal) editModal.classList.add('hidden');
        }

        // Function to open edit modal and populate fields
        function openEditModal(course) {
            document.getElementById('editSubjectId').value = course.subject_id;
            document.getElementById('editSubjectCode').value = course.subject_code;
            document.getElementById('editSubjectName').value = course.subject_name;
            document.getElementById('editProgramId').value = course.program_id;
            document.getElementById('editSemester').value = course.semester;
            document.getElementById('editCredits').value = course.credits;
            document.getElementById('editCourseModal').classList.remove('hidden');
        }
    </script>
</body>
</html>