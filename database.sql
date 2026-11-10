CREATE DATABASE IF NOT EXISTS wellcare_clinic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wellcare_clinic;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','doctor','patient') NOT NULL DEFAULT 'patient',
  specialty VARCHAR(120) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES users(id),
  CONSTRAINT fk_appointment_doctor FOREIGN KEY (doctor_id) REFERENCES users(id),
  INDEX idx_doctor_schedule (doctor_id, appointment_date, appointment_time)
);

CREATE TABLE medical_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  appointment_id INT NULL,
  diagnosis TEXT NOT NULL,
  prescription TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_record_patient FOREIGN KEY (patient_id) REFERENCES users(id),
  CONSTRAINT fk_record_doctor FOREIGN KEY (doctor_id) REFERENCES users(id),
  CONSTRAINT fk_record_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);

-- Demo accounts. Password for all three is password (change immediately for any real deployment).
-- Hash below is PHP password_hash('password', PASSWORD_DEFAULT) compatible.
INSERT INTO users(name,email,phone,password,role,specialty) VALUES
('System Administrator','admin@wellcare.test','', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',NULL),
('Dr. Maria Santos','doctor@wellcare.test','09170000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','doctor','General Medicine');
