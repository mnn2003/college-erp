<?php
// Verify admin role
session_start(); // Ensure session is started
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/alpinejs@3.12.0/dist/cdn.min.js" defer></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Sidebar Wrapper -->
<div x-data="{ 
        open: false, 
        activeSection: null, 
        init() {
            // Parse the query string to determine the active section
            const urlParams = new URLSearchParams(window.location.search);
            this.activeSection = urlParams.get('section') || null;
        }
    }" 
    class="flex">

    <!-- Mobile Header -->
    <div class="md:hidden flex justify-between items-center p-4 bg-gray-800 text-white fixed top-0 left-0 right-0 z-40">
        <h2 class="text-lg font-semibold">College ERP</h2>
        <button @click="open = !open" class="focus:outline-none">
            <i class="fas fa-bars text-2xl"></i>
        </button>
    </div>

    <!-- Sidebar (Fixed) -->
    <div :class="{'translate-x-0': open, '-translate-x-full': !open}" 
        class="fixed top-0 left-0 w-64 h-screen bg-gray-800 text-white z-50 transition-transform duration-300 ease-in-out overflow-y-auto md:translate-x-0">
        <div class="p-4 border-b border-gray-700 hidden md:flex items-center space-x-3">
            <div class="bg-blue-500 rounded-full w-10 h-10 flex items-center justify-center">
                <span class="font-semibold text-lg">A</span>
            </div>
            <div>
                <h2 class="text-xl font-semibold">College ERP</h2>
                <p class="text-xs text-gray-400">Administrator</p>
            </div>
        </div>

        <nav class="p-4">
            <ul class="space-y-2">

                <!-- Dashboard -->
                <li>
                    <a href="dashboard.php"
                       class="flex items-center px-4 py-3 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-gray-700' : '' ?>">
                        <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                        Dashboard
                    </a>
                </li>

                <!-- User Management -->
                <li class="mt-4">
                    <div @click="activeSection === 'user' ? activeSection = null : activeSection = 'user'"
                         class="flex items-center justify-between px-4 py-2 cursor-pointer hover:bg-gray-700 rounded-lg transition">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">User Management</span>
                        <i :class="{'fa-chevron-down': activeSection === 'user', 'fa-chevron-right': activeSection !== 'user'}" class="fas text-sm"></i>
                    </div>
                    <ul x-show="activeSection === 'user'" x-collapse class="mt-2 space-y-1">
                        <li>
                            <a href="manage_students.php?section=user"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_students.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-user-graduate w-5 h-5 mr-3"></i>
                                Manage Students
                            </a>
                        </li>
                        <li>
                            <a href="manage_faculty.php?section=user"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_faculty.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-chalkboard-teacher w-5 h-5 mr-3"></i>
                                Manage Faculty
                            </a>
                        </li>
                        <li>
                            <a href="manage_staff.php?section=user"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_staff.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-users w-5 h-5 mr-3"></i>
                                Manage Staff
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Academic Management -->
                <li class="mt-4">
                    <div @click="activeSection === 'academic' ? activeSection = null : activeSection = 'academic'"
                         class="flex items-center justify-between px-4 py-2 cursor-pointer hover:bg-gray-700 rounded-lg transition">
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic Management</span>
                        <i :class="{'fa-chevron-down': activeSection === 'academic', 'fa-chevron-right': activeSection !== 'academic'}" class="fas text-sm"></i>
                    </div>
                    <ul x-show="activeSection === 'academic'" x-collapse class="mt-2 space-y-1">
                        <li>
                            <a href="manage_programs.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_programs.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-stream w-5 h-5 mr-3"></i>
                                Manage Programs
                            </a>
                        </li>
                        <li>
                            <a href="manage_courses.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_courses.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-book w-5 h-5 mr-3"></i>
                                Manage Courses
                            </a>
                        </li>
                        <li>
                            <a href="manage_classrooms.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_classrooms.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-location w-5 h-5 mr-3"></i>
                                Manage Classrooms
                            </a>
                        </li>
                        <li>
                            <a href="assign_faculty.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'assign_faculty.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-user-check w-5 h-5 mr-3"></i>
                                Assign Faculty
                            </a>
                        </li>
                        <li>
                            <a href="manage_timetable.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'manage_timetable.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-calendar-alt w-5 h-5 mr-3"></i>
                                Manage Timetable
                            </a>
                        </li>
                        <li>
                            <a href="attendance.php?section=academic"
                               class="flex items-center px-4 py-2 rounded-lg hover:bg-gray-700 transition <?= basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'bg-gray-700' : '' ?>">
                                <i class="fas fa-user-check w-5 h-5 mr-3"></i>
                                Attendance Management
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Logout -->
                <li class="pt-4 border-t border-gray-700 mt-4">
                    <a href="../logout.php" 
                       class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-red-600 transition bg-red-700 text-center justify-center">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span>Logout</span>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</div>