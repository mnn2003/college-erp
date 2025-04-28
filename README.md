
# College ERP System

A web-based **College ERP (Enterprise Resource Planning)** system built with **PHP**, **HTML**, **TailwindCSS**, and **MySQL**.  
This system manages core academic activities like student enrollment, faculty records, courses, attendance tracking, and departmental structures.

## 📂 Technologies Used
- **PHP** (Server-side logic)
- **MySQL** (Database Management)
- **HTML5** (Frontend structure)
- **TailwindCSS** (Frontend styling)

## 🏗️ Database Structure
The database (`college_erp.sql`) includes:
- **student**: Student details
- **faculty**: Faculty information
- **course**: Courses offered
- **department**: Departments in the college
- **attendance**: Attendance records
- **subject**: Subjects within each course
- **enrollment**: Students enrolled in subjects
- ...and more.

## 🚀 How to Install and Run Locally
1. **Clone the Repository**
   ```bash
   git clone https://github.com/mnn2003/college-erp.git
   cd college-erp
   ```

2. **Set up the Database**
   - Open **phpMyAdmin** or any MySQL client.
   - Create a new database (e.g., `college_erp`).
   - Import the `college_erp.sql` file:
     - Go to **Import** tab ➔ Upload `college_erp.sql` ➔ Click **Go**.

3. **Configure the Project**
   - Edit your PHP database connection file (usually something like `config.php`) with your database credentials:
     ```php
     $host = "localhost";
     $user = "root";
     $password = "";
     $database = "college_erp";
     ```

4. **Start Local Server**
   - Use **XAMPP**, **MAMP**, or any local server.
   - Place the project inside the `htdocs` (for XAMPP) or your server's root folder.
   - Start **Apache** and **MySQL**.

5. **Visit on Browser**
   ```
   http://localhost/college-erp/
   ```

## ✍️ Author
- **Aman**  
  [GitHub Profile](https://github.com/mnn2003)

## ⚖️ License
This project is licensed under the [MIT License](LICENSE).

---
> "A simple yet powerful ERP solution for educational institutions."
