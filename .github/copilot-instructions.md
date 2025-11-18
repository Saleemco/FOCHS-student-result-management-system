# Student Result Management System - AI Agent Guidelines

## Project Overview
A PHP-based web application for managing student examination results. Admins add classes, students, and results; students search their results by class and roll number.

**Stack:** PHP 7.2, MySQL (MariaDB), HTML/CSS/JavaScript, XAMPP Server

## Architecture & Data Flow

### Three-Layer Structure
1. **Presentation** (`*.php` files with embedded HTML) - Server-side rendered pages
2. **Business Logic** - Inline SQL queries and form processing in PHP files
3. **Database** (`database/srms.sql`) - Four main tables: `admin_login`, `class`, `students`, `result`

### Key Tables & Relationships
- `class` (name, id) - Master list of classes
- `students` (name, rno, class_name) - Student records, FK to class
- `result` (name, rno, class, p1-p5, marks, percentage) - Marks for 5 papers, computed total/percentage
- `admin_login` (userid, password) - Single admin (hardcoded: admin/123)

### Page Routing Patterns
- **Public Entry:** `index.html` → `login.php` or `student.php`
- **Admin Flow:** Login → `dashboard.php` → CRUD pages (`add_*`, `manage_*`)
- **Student Flow:** `student.php` (GET: class, rn) → displays result from `result` table

## Critical Conventions & Patterns

### Database Connection & Session
- All files using DB: `include('init.php')` (connection without error handling)
- Protected pages: `include('session.php')` checks `$_SESSION['login_user']` from `admin_login.userid`
- No prepared statements or parameterized queries - **SQL injection vulnerable**

### Form Processing Pattern
```php
// 1. Include init.php (connection), include session.php (auth check)
// 2. HTML form with POST method
// 3. PHP at bottom: isset($_POST[...]) checks, direct queries, alert() feedback
// Examples: add_students.php, manage_results.php (delete/update), add_results.php
```

### Navigation & Styling
- All admin pages share: common title bar, dropdown nav menu (toggleDisplay JS function)
- CSS: `css/home.css` (nav, shared layout), `css/form.css` (forms), `css/student.css` (result display)
- Font Awesome 4.7.0 icons for nav icons and logout button

### Results Display
- `student.php` renders 5 papers (p1-p5) as separate rows, calculates percentage dynamically
- `manage_results.php` provides delete and update forms for admin
- Marks calculation: `total = p1+p2+p3+p4+p5`, `percentage = total/5`

## Development Workflows

### Local Setup
1. Place project in `C:\xampp\htdocs\` 
2. Import `database/srms.sql` into MySQL via phpMyAdmin
3. Start Apache + MySQL via XAMPP Control Panel
4. Access via `http://localhost/Student-Result-Management-System/`

### Testing Common Flows
- **Add Class:** `add_classes.php` → form submitted → INSERT into `class` table
- **Add Student:** `add_students.php` → FK validates class exists
- **Add Result:** `add_results.php` → must have student in `students` table (FK enforced)
- **Student Search:** `student.php?class=FirstYear&rn=1011` → validate inputs, join students + result

### Database Validation
- Foreign keys enforced: students.class_name → class.name, result.class → class.name, result.(name,rno) → students.(name,rno)
- Composite key on students: (name, rno); on result: (name, rno, class)

## Project-Specific Conventions

### Variable Naming
- POST variables: snake_case (`student_name`, `roll_no`, `class_name`)
- Database columns: snake_case (`class_name`, `userid`)
- Query variables: descriptive suffixes (`$name_sql`, `$class_result`, `$update_sql`)

### Error Handling
- No try-catch; errors shown via JavaScript `alert()` boxes
- Missing validation: SQL injection, XSS (directly echo POST/GET data)
- Validation only in `student.php`: regex check for roll number (no letters)

### Common Gotchas
1. **Column name inconsistency:** Students table uses `class_name`, but result table uses `class` - both FK to `class.name`
2. **Session tied to username:** $_SESSION['login_user'] = userid string (not encrypted)
3. **5-Paper Limitation:** Schema hardcoded for 5 papers (p1-p5); extending requires schema migration
4. **No Result Cascade:** Deleting a student doesn't delete their results (no ON DELETE CASCADE)

## File Organization

```
Root level: Entry points (index.html, login.php, student.php), admin pages (dashboard.php, add_*, manage_*)
css/        CSS files + Font Awesome library
database/   srms.sql dump
images/     Logo files
init.php    DB connection (include in every file)
session.php Auth check (include in admin pages)
```

## Next Steps for New Contributors
1. Read `database/srms.sql` to understand table structure
2. Trace one full flow: e.g., login.php → session.php → dashboard.php → add_students.php
3. Note security issues (SQL injection, no password hashing) for future refactoring
4. Always include init.php first, session.php second in admin pages
