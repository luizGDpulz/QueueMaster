-- seed_sample_data.sql
-- Sample seed data for QueueMaster testing and development
-- This file provides realistic test data for the queue and appointment management system

USE queue_master;

-- ============================================================================
-- USERS
-- ============================================================================
-- Create 3 users with different roles
-- Password for all users: 'password123'
-- Argon2id hash: $argon2id$v=19$m=65536,t=4,p=1$UVpXVUZKRnJYalo0TnJvLw$xWQqTGWICTraXYhpmlAibr4sV2kZIxtUixoRD2rmlOA
-- WARNING: This is for DEVELOPMENT/TESTING ONLY. Do NOT use in production!

INSERT INTO users (name, email, password_hash, role, created_at) VALUES
('Admin User', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UVpXVUZKRnJYalo0TnJvLw$xWQqTGWICTraXYhpmlAibr4sV2kZIxtUixoRD2rmlOA', 'admin', NOW()),
('Attendant User', 'attendant@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UVpXVUZKRnJYalo0TnJvLw$xWQqTGWICTraXYhpmlAibr4sV2kZIxtUixoRD2rmlOA', 'attendant', NOW()),
('Client User', 'client@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UVpXVUZKRnJYalo0TnJvLw$xWQqTGWICTraXYhpmlAibr4sV2kZIxtUixoRD2rmlOA', 'client', NOW());

-- ============================================================================
-- ESTABLISHMENTS
-- ============================================================================
-- Create a sample establishment (medical clinic)

INSERT INTO establishments (name, address, timezone, created_at) VALUES
('Central Medical Clinic', '123 Health Street, Downtown, City 12345', 'America/Sao_Paulo', NOW());

SET @establishment_id = LAST_INSERT_ID();

-- ============================================================================
-- SERVICES
-- ============================================================================
-- Create 2 services offered by the establishment

INSERT INTO services (establishment_id, name, duration_minutes) VALUES
(@establishment_id, 'General Consultation', 30),
(@establishment_id, 'Medical Exam', 15);

SET @service_consultation_id = LAST_INSERT_ID();
SET @service_exam_id = @service_consultation_id + 1;

-- ============================================================================
-- PROFESSIONALS
-- ============================================================================
-- Create 2 professionals who work at the establishment

INSERT INTO professionals (establishment_id, name) VALUES
(@establishment_id, 'Dr. Maria Silva'),
(@establishment_id, 'Dr. João Santos');

SET @professional_maria_id = LAST_INSERT_ID();
SET @professional_joao_id = @professional_maria_id + 1;

-- ============================================================================
-- QUEUES
-- ============================================================================
-- Create 1 open queue for walk-in patients

INSERT INTO queues (establishment_id, service_id, name, status, created_at) VALUES
(@establishment_id, @service_consultation_id, 'Walk-in Consultation Queue', 'open', NOW());

SET @queue_id = LAST_INSERT_ID();

-- ============================================================================
-- QUEUE ENTRIES
-- ============================================================================
-- Add sample entries to the queue (simulating walk-in patients)
-- Note: Using client user's ID for demonstration

SET @client_user_id = (SELECT id FROM users WHERE email = 'client@example.com' LIMIT 1);

INSERT INTO queue_entries (queue_id, user_id, position, status, priority, created_at) VALUES
(@queue_id, @client_user_id, 1, 'waiting', 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(@queue_id, NULL, 2, 'waiting', 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(@queue_id, NULL, 3, 'waiting', 5, NOW());

-- ============================================================================
-- APPOINTMENTS
-- ============================================================================
-- Create 3 sample appointments:
-- 1. Booked appointment for today (2 hours from now)
-- 2. Checked-in appointment for today (should be called soon)
-- 3. Booked appointment for tomorrow

SET @today = CURDATE();
SET @tomorrow = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
SET @appointment_time_today = DATE_ADD(NOW(), INTERVAL 2 HOUR);
SET @appointment_time_checkedin = DATE_ADD(NOW(), INTERVAL 5 MINUTE);
SET @appointment_time_tomorrow = CONCAT(@tomorrow, ' 10:00:00');

-- Appointment 1: Booked for today (2 hours from now)
INSERT INTO appointments (
    establishment_id, 
    professional_id, 
    service_id, 
    user_id, 
    start_at, 
    end_at, 
    status, 
    created_at
) VALUES (
    @establishment_id,
    @professional_maria_id,
    @service_consultation_id,
    @client_user_id,
    DATE_FORMAT(@appointment_time_today, '%Y-%m-%d %H:%i:00'),
    DATE_FORMAT(DATE_ADD(@appointment_time_today, INTERVAL 30 MINUTE), '%Y-%m-%d %H:%i:00'),
    'booked',
    NOW()
);

-- Appointment 2: Checked-in (ready to be called)
INSERT INTO appointments (
    establishment_id, 
    professional_id, 
    service_id, 
    user_id, 
    start_at, 
    end_at, 
    status, 
    created_at,
    checkin_at
) VALUES (
    @establishment_id,
    @professional_maria_id,
    @service_consultation_id,
    @client_user_id,
    DATE_FORMAT(@appointment_time_checkedin, '%Y-%m-%d %H:%i:00'),
    DATE_FORMAT(DATE_ADD(@appointment_time_checkedin, INTERVAL 30 MINUTE), '%Y-%m-%d %H:%i:00'),
    'checked_in',
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    NOW()
);

-- Appointment 3: Booked for tomorrow at 10:00 AM
INSERT INTO appointments (
    establishment_id, 
    professional_id, 
    service_id, 
    user_id, 
    start_at, 
    end_at, 
    status, 
    created_at
) VALUES (
    @establishment_id,
    @professional_joao_id,
    @service_consultation_id,
    @client_user_id,
    @appointment_time_tomorrow,
    DATE_FORMAT(DATE_ADD(@appointment_time_tomorrow, INTERVAL 30 MINUTE), '%Y-%m-%d %H:%i:00'),
    'booked',
    NOW()
);

-- ============================================================================
-- SUMMARY
-- ============================================================================
-- Display summary of seeded data

SELECT 'SEEDING COMPLETED SUCCESSFULLY!' as message;

SELECT 
    'Users' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(name, ' (', role, ')') SEPARATOR ', ') as details
FROM users;

SELECT 
    'Establishments' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(name SEPARATOR ', ') as details
FROM establishments;

SELECT 
    'Services' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(name, ' (', duration_minutes, ' min)') SEPARATOR ', ') as details
FROM services;

SELECT 
    'Professionals' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(name SEPARATOR ', ') as details
FROM professionals;

SELECT 
    'Queues' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(name, ' (', status, ')') SEPARATOR ', ') as details
FROM queues;

SELECT 
    'Queue Entries' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT('Position ', position, ' (', status, ')') SEPARATOR ', ') as details
FROM queue_entries;

SELECT 
    'Appointments' as entity,
    COUNT(*) as count,
    GROUP_CONCAT(status SEPARATOR ', ') as details
FROM appointments;

-- ============================================================================
-- NOTES
-- ============================================================================
-- 
-- Login Credentials:
-- - Admin: admin@example.com / password123
-- - Attendant: attendant@example.com / password123
-- - Client: client@example.com / password123
--
-- The database now contains:
-- - 3 users with different roles
-- - 1 establishment (Central Medical Clinic)
-- - 2 services (General Consultation 30min, Medical Exam 15min)
-- - 2 professionals (Dr. Maria Silva, Dr. João Santos)
-- - 1 open queue with 3 entries (1 registered user, 2 anonymous)
-- - 3 appointments (1 booked for today, 1 checked-in, 1 for tomorrow)
--
-- Usage:
-- To seed the database, run:
--   mysql -u root -p < scripts/seed_sample_data.sql
-- Or from MySQL client:
--   source scripts/seed_sample_data.sql;
--
-- ============================================================================
