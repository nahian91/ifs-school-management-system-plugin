=== IFSEdu - School Management System ===
Contributors: DevNahian
Tags: school management, school erp, student management, attendance, school fees
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A standalone, high-performance school management system featuring student admissions, attendance, fees, exams, results, and HR/staff management.

== Description ==

**IFSEdu - School Management System (EduCore)** is a powerful, enterprise-grade ERP designed specifically for schools, colleges, and educational institutes. It streamlines administrative workflows, academic operations, and parent-teacher coordination through a unified, secure dashboard.

### Key Features
* **Student Information System (SIS):** Comprehensive student records, profile tracking, dynamic class/section sorting, and promotional status management.
* **Attendance Tracking:** Daily and monthly attendance rosters for both students and staff with live analytics counters.
* **Fee Collection & Invoicing:** Automated fee billing, tracking, and receipt management.
* **Accounting & Ledger:** Master financial tracking, transaction recording, and expense management.
* **Exams & Results:** Exam setup, mark registries, grade calculations, and report card generation.
* **Staff & HR Management:** Multi-role staff profiles, payroll tracking, and designation management.
* **Academic Setup:** Unified classes, section management, class-wise subjects, and scheduling.
* **Notices & Events:** Official notice board and academic event directory with file/banner attachments.
* **White-Label Dashboard:** Clean, standalone interface hiding default WordPress menus for non-administrator roles with role-based access control (RBAC).

== Installation ==

1. Upload the plugin folder `ifsedu-school-management` to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Upon activation, the plugin automatically creates all required database tables and routing endpoints.
4. Navigate to the new **School ERP** menu in your WordPress admin sidebar to start configuring your institution.

== Frequently Asked Questions ==

= Does this plugin work with any WordPress theme? =
Yes! The plugin features a custom white-label dashboard layout that operates independently of your active front-end theme, ensuring a sleek, distraction-free ERP interface.

= Can teachers access the system? =
Yes. The plugin includes a robust Role-Based Access Control (RBAC) engine that safely scopes teachers to their assigned classes, subjects, and attendance rosters.

== Screenshots ==

1. **Dashboard Overview:** Centralized analytics and module navigation.
2. **Student Directory:** Advanced filters, search, and profile management.
3. **Attendance Roster:** Fast daily marking with automated present/absent/late counts.

== Changelog ==

= 1.2.2 =
* Enhanced WordPress Coding Standards (WPCS) and PluginCheck compliance.
* Optimized database queries with secure table prefixing and prepared statements.
* Improved security checks, nonce verifications, and input sanitization across all modules.

= 1.0.0 =
* Initial release of IFSEdu - School Management System.