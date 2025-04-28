<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_faculty'])) {
        // Add new faculty
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $contact = trim($_POST['contact']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate inputs
        $errors = [];

        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($department)) $errors[] = "Department is required";
        if (empty($username)) $errors[] = "Username is required";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match";

        // Check for existing email/username
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Email or username already exists";

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'faculty')");
                $stmt->execute([$username, $hashed_password, $email]);
                $user_id = $pdo->lastInsertId();

                // Create faculty record
                $stmt = $pdo->prepare("INSERT INTO faculty (user_id, full_name, department, contact) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $full_name, $department, $contact]);

                $pdo->commit();
                $_SESSION['success'] = "Faculty added successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error adding faculty: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['delete_faculty'])) {
        // Delete faculty
        $faculty_id = (int)$_POST['faculty_id'];

        try {
            // Check if faculty is assigned to any courses
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ?");
            $stmt->execute([$faculty_id]);
            $assignment_count = $stmt->fetchColumn();

            if ($assignment_count > 0) {
                $_SESSION['error'] = "Cannot delete faculty - they are assigned to $assignment_count courses!";
            } else {
                // Get user_id first
                $stmt = $pdo->prepare("SELECT user_id FROM faculty WHERE faculty_id = ?");
                $stmt->execute([$faculty_id]);
                $user_id = $stmt->fetchColumn();

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM faculty WHERE faculty_id = ?");
                $stmt->execute([$faculty_id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);

                $pdo->commit();
                $_SESSION['success'] = "Faculty deleted successfully!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error deleting faculty: " . $e->getMessage();
        }
    }

    header("Location: manage_faculty.php");
    exit();
}

// Get all faculty
$faculty = $pdo->query("
    SELECT f.*, COUNT(fs.subject_id) AS assigned_courses
    FROM faculty f
    LEFT JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
    GROUP BY f.faculty_id
    ORDER BY f.full_name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty</title>
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
            <h1 class="text-3xl font-bold">Manage Faculty</h1>
            <button onclick="document.getElementById('addFacultyModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Add Faculty
            </button>
        </div>
        
        <?php include '../includes/messages.php'; ?>
        
        <!-- Faculty Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Name</th>
                        <th class="py-3 px-4 text-left">Department</th>
                        <th class="py-3 px-4 text-left">Contact</th>
                        <th class="py-3 px-4 text-left">Assigned Courses</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faculty)): ?>
                        <tr>
                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">No faculty members found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faculty as $member): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium"><?= htmlspecialchars($member['full_name']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['department']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['contact']) ?></td>
                            <td class="py-3 px-4"><?= $member['assigned_courses'] ?></td>
                            <td class="py-3 px-4">
                                <a href="assign_faculty.php?faculty_id=<?= $member['faculty_id'] ?>"
                                   class="text-blue-600 hover:text-blue-800 mr-3" title="Assign Courses">
                                    <i class="fas fa-book"></i>
                                </a>
                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this faculty member?');">
                                    <input type="hidden" name="faculty_id" value="<?= $member['faculty_id'] ?>">
                                    <button type="submit" name="delete_faculty" class="text-red-600 hover:text-red-800" title="Delete">
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
    
    <!-- Add Faculty Modal -->
    <div id="addFacultyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Add New Faculty Member</h2>
                <button onclick="document.getElementById('addFacultyModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Full Name*</label>
                        <input type="text" name="full_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Department*</label>
                        <input type="text" name="department" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Contact Number</label>
                        <input type="text" name="contact"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email*</label>
                        <input type="email" name="email" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Username*</label>
                        <input type="text" name="username" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Password* (min 8 chars)</label>
                        <input type="password" name="password" required minlength="8"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Confirm Password*</label>
                        <input type="password" name="confirm_password" required minlength="8"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('addFacultyModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="add_faculty"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Add Faculty
                    </button>
                </div>
            </form>
        </div>
    </div>
 </div>   
    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addFacultyModal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        }
    </script>
</body>
</html>