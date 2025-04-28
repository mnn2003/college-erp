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
    if (isset($_POST['add_staff'])) {
        // Add new staff
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        $contact = trim($_POST['contact']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate inputs
        $errors = [];

        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($department)) $errors[] = "Department is required";
        if (empty($position)) $errors[] = "Position is required";
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
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'staff')");
                $stmt->execute([$username, $hashed_password, $email]);
                $user_id = $pdo->lastInsertId();

                // Create staff record
                $stmt = $pdo->prepare("INSERT INTO staff (user_id, full_name, department, position, contact) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $full_name, $department, $position, $contact]);

                $pdo->commit();
                $_SESSION['success'] = "Staff member added successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error adding staff: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['delete_staff'])) {
        // Delete staff
        $staff_id = (int)$_POST['staff_id'];

        try {
            // Get user_id first
            $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            $user_id = $stmt->fetchColumn();

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
            $stmt->execute([$staff_id]);

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $_SESSION['success'] = "Staff member deleted successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error deleting staff: " . $e->getMessage();
        }
    }

    header("Location: manage_staff.php");
    exit();
}

// Get all staff members
$staff = $pdo->query("
    SELECT s.*, u.email, u.username
    FROM staff s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.full_name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
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
            <h1 class="text-3xl font-bold">Manage Staff</h1>
            <button onclick="document.getElementById('addStaffModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Add Staff
            </button>
        </div>
        
        <?php include '../includes/messages.php'; ?>
        
        <!-- Staff Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Name</th>
                        <th class="py-3 px-4 text-left">Department</th>
                        <th class="py-3 px-4 text-left">Position</th>
                        <th class="py-3 px-4 text-left">Contact</th>
                        <th class="py-3 px-4 text-left">Email</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff)): ?>
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">No staff members found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staff as $member): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium"><?= htmlspecialchars($member['full_name']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['department']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['position']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['contact']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($member['email']) ?></td>
                            <td class="py-3 px-4">
                                <button onclick="openEditModal(
                                    <?= $member['staff_id'] ?>,
                                    '<?= htmlspecialchars(addslashes($member['full_name'])) ?>',
                                    '<?= htmlspecialchars(addslashes($member['department'])) ?>',
                                    '<?= htmlspecialchars(addslashes($member['position'])) ?>',
                                    '<?= htmlspecialchars(addslashes($member['contact'])) ?>',
                                    '<?= htmlspecialchars(addslashes($member['email'])) ?>',
                                    '<?= htmlspecialchars(addslashes($member['username'])) ?>'
                                )" class="text-blue-600 hover:text-blue-800 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                    <input type="hidden" name="staff_id" value="<?= $member['staff_id'] ?>">
                                    <button type="submit" name="delete_staff" class="text-red-600 hover:text-red-800">
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
    
    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Add New Staff Member</h2>
                <button onclick="document.getElementById('addStaffModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Full Name*</label>
                        <input type="text" name="full_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               value="<?= isset($_SESSION['form_data']['full_name']) ? htmlspecialchars($_SESSION['form_data']['full_name']) : '' ?>">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Department*</label>
                            <input type="text" name="department" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['department']) ? htmlspecialchars($_SESSION['form_data']['department']) : '' ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Position*</label>
                            <input type="text" name="position" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['position']) ? htmlspecialchars($_SESSION['form_data']['position']) : '' ?>">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Contact Number</label>
                        <input type="text" name="contact"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               value="<?= isset($_SESSION['form_data']['contact']) ? htmlspecialchars($_SESSION['form_data']['contact']) : '' ?>">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email*</label>
                        <input type="email" name="email" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               value="<?= isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : '' ?>">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Username*</label>
                            <input type="text" name="username" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   value="<?= isset($_SESSION['form_data']['username']) ? htmlspecialchars($_SESSION['form_data']['username']) : '' ?>">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Password*</label>
                            <input type="password" name="password" required minlength="8"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Confirm Password*</label>
                        <input type="password" name="confirm_password" required minlength="8"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('addStaffModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="add_staff"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Add Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Edit Staff Member</h2>
                <button onclick="document.getElementById('editStaffModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post" id="editStaffForm">
                <input type="hidden" name="staff_id" id="editStaffId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Full Name*</label>
                        <input type="text" name="full_name" id="editFullName" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Department*</label>
                            <input type="text" name="department" id="editDepartment" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Position*</label>
                            <input type="text" name="position" id="editPosition" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Contact Number</label>
                        <input type="text" name="contact" id="editContact"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email*</label>
                        <input type="email" name="email" id="editEmail" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Username*</label>
                        <input type="text" name="username" id="editUsername" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Leave password fields blank to keep current password
                        </p>
                        
                        <div class="mt-2">
                            <label class="block text-gray-700 mb-2">New Password</label>
                            <input type="password" name="password" minlength="8"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mt-2">
                            <label class="block text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" minlength="8"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('editStaffModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="edit_staff"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>    
    <script>
        // Open edit modal with staff data
        function openEditModal(id, fullName, department, position, contact, email, username) {
            document.getElementById('editStaffId').value = id;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editDepartment').value = department;
            document.getElementById('editPosition').value = position;
            document.getElementById('editContact').value = contact;
            document.getElementById('editEmail').value = email;
            document.getElementById('editUsername').value = username;
            
            document.getElementById('editStaffModal').classList.remove('hidden');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addStaffModal');
            const editModal = document.getElementById('editStaffModal');
            
            if (event.target === addModal) {
                addModal.classList.add('hidden');
            }
            if (event.target === editModal) {
                editModal.classList.add('hidden');
            }
        }
        
        // Handle edit form submission
        document.getElementById('editStaffForm').addEventListener('submit', function(e) {
            const password = this.elements['password'].value;
            const confirmPassword = this.elements['confirm_password'].value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>