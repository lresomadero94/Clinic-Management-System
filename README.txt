WELLCARE CLINIC MANAGEMENT SYSTEM
=================================

STACK
- HTML5, CSS3, JavaScript (responsive menu)
- PHP 8+
- MySQL / MariaDB
- XAMPP / Apache

INSTALLATION (XAMPP)
1. Extract the WellCare_Clinic_System folder into:
   C:\xampp\htdocs\
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open http://localhost/phpmyadmin
4. Choose Import and import database.sql.
5. Open config.php and adjust database username/password if yours differ.
6. Visit http://localhost/WellCare_Clinic_System/

DEMO LOGIN
Admin:  admin@wellcare.test
Doctor: doctor@wellcare.test
Password for both demo accounts: password

Patient:
- Click Create account on the website and register a patient account.

FEATURES
- Patient registration and login
- Role-based patient, doctor, and administrator navigation
- Patient appointment requests
- Doctor appointment list and status updates
- Medical record entry for authorized doctor/admin accounts
- Admin doctor account creation
- Dashboard summary cards
- Responsive mobile-friendly layout
- Prepared SQL schema with relationships and indexes

IMPORTANT NOTES
- This is an educational prototype, not a production electronic health record.
- Do not enter real patient or sensitive health information.
- Before real deployment, add stronger access controls, audit logs, backups,
  HTTPS, account recovery, consent/privacy notices, retention policies,
  and review against applicable Philippine data privacy and health regulations.
- Demo password hashes are for local demonstration only. Change demo passwords.
- Configure time zone and clinic operating hours before presentation.
