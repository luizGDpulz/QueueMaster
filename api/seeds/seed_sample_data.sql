-- seed_sample_data.sql
-- Sample seed data for QueueMaster testing and development
-- Creates establishments, services, professionals, queues, and sample entries
-- 
-- NOTE: Users are NOT seeded - login with Google OAuth using the email
-- configured in SUPER_ADMIN_EMAIL to become the first admin.
--
-- Run with: php scripts/seed.php sample

USE queue_master;

-- ============================================================================
-- ESTABLISHMENTS
-- ============================================================================
-- Create sample establishments for testing

INSERT INTO establishments (name, slug, description, address, phone, timezone, opens_at, closes_at, created_at) VALUES
('Central Medical Clinic', 'central-medical', 'Full-service medical clinic with walk-in and scheduled appointments', '123 Health Street, Downtown, City 12345', '(11) 3456-7890', 'America/Sao_Paulo', '08:00:00', '18:00:00', NOW()),
('Quick Barber Shop', 'quick-barber', 'Modern barbershop with online queue system', '456 Style Avenue, Shopping Center', '(11) 9876-5432', 'America/Sao_Paulo', '09:00:00', '20:00:00', NOW());

SET @clinic_id = (SELECT id FROM establishments WHERE slug = 'central-medical' LIMIT 1);
SET @barber_id = (SELECT id FROM establishments WHERE slug = 'quick-barber' LIMIT 1);

-- ============================================================================
-- SERVICES
-- ============================================================================
-- Services for the medical clinic
INSERT INTO services (establishment_id, name, description, duration_minutes, price, sort_order) VALUES
(@clinic_id, 'General Consultation', 'Standard medical consultation with a general practitioner', 30, 150.00, 1),
(@clinic_id, 'Medical Exam', 'Quick medical examination and vital signs check', 15, 80.00, 2),
(@clinic_id, 'Vaccination', 'Vaccine administration service', 10, 50.00, 3),
(@clinic_id, 'Lab Results Review', 'Review of laboratory test results with doctor', 20, 100.00, 4);

-- Services for the barber shop
INSERT INTO services (establishment_id, name, description, duration_minutes, price, sort_order) VALUES
(@barber_id, 'Haircut', 'Classic or modern haircut', 30, 45.00, 1),
(@barber_id, 'Beard Trim', 'Beard shaping and trimming', 20, 35.00, 2),
(@barber_id, 'Haircut + Beard', 'Complete haircut and beard service', 45, 70.00, 3),
(@barber_id, 'Hot Towel Shave', 'Traditional hot towel straight razor shave', 30, 55.00, 4);

SET @service_consultation_id = (SELECT id FROM services WHERE name = 'General Consultation' LIMIT 1);
SET @service_exam_id = (SELECT id FROM services WHERE name = 'Medical Exam' LIMIT 1);
SET @service_haircut_id = (SELECT id FROM services WHERE name = 'Haircut' LIMIT 1);
SET @service_beard_id = (SELECT id FROM services WHERE name = 'Beard Trim' LIMIT 1);

-- ============================================================================
-- PROFESSIONALS
-- ============================================================================
-- Professionals for the medical clinic
INSERT INTO professionals (establishment_id, name, email, specialty) VALUES
(@clinic_id, 'Dr. Maria Silva', 'dr.maria@clinic.local', 'General Practitioner'),
(@clinic_id, 'Dr. João Santos', 'dr.joao@clinic.local', 'General Practitioner'),
(@clinic_id, 'Nurse Ana Costa', 'ana.costa@clinic.local', 'Nurse');

-- Professionals for the barber shop
INSERT INTO professionals (establishment_id, name, specialty) VALUES
(@barber_id, 'Carlos Barber', 'Senior Barber'),
(@barber_id, 'Pedro Stylist', 'Hair Stylist'),
(@barber_id, 'Lucas Junior', 'Apprentice Barber');

SET @professional_maria_id = (SELECT id FROM professionals WHERE name = 'Dr. Maria Silva' LIMIT 1);
SET @professional_joao_id = (SELECT id FROM professionals WHERE name = 'Dr. João Santos' LIMIT 1);
SET @professional_carlos_id = (SELECT id FROM professionals WHERE name = 'Carlos Barber' LIMIT 1);

-- ============================================================================
-- PROFESSIONAL_SERVICES (link professionals to their services)
-- ============================================================================
INSERT INTO professional_services (professional_id, service_id) VALUES
(@professional_maria_id, @service_consultation_id),
(@professional_maria_id, @service_exam_id),
(@professional_joao_id, @service_consultation_id),
(@professional_joao_id, @service_exam_id),
(@professional_carlos_id, @service_haircut_id),
(@professional_carlos_id, @service_beard_id);

-- ============================================================================
-- QUEUES
-- ============================================================================
-- Create sample queues

INSERT INTO queues (establishment_id, service_id, name, description, status, max_capacity, avg_wait_minutes, created_at) VALUES
(@clinic_id, @service_consultation_id, 'Walk-in Consultation', 'Queue for patients without appointments', 'open', 20, 25, NOW()),
(@clinic_id, @service_exam_id, 'Quick Exam Queue', 'Fast track for simple examinations', 'open', 10, 10, NOW()),
(@barber_id, @service_haircut_id, 'Haircut Queue', 'Walk-in haircut queue', 'open', 15, 20, NOW());

SET @queue_consultation_id = (SELECT id FROM queues WHERE name = 'Walk-in Consultation' LIMIT 1);
SET @queue_barber_id = (SELECT id FROM queues WHERE name = 'Haircut Queue' LIMIT 1);

-- ============================================================================
-- QUEUE ENTRIES (anonymous walk-ins for demo)
-- ============================================================================
-- Create some anonymous queue entries to simulate walk-in customers
-- Note: These don't have user_id since we're not seeding users

INSERT INTO queue_entries (queue_id, guest_name, guest_phone, position, ticket_number, status, priority, created_at) VALUES
(@queue_consultation_id, 'João Waiting', '(11) 91111-1111', 1, 'C001', 'waiting', 0, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(@queue_consultation_id, 'Maria Priority', '(11) 92222-2222', 2, 'C002', 'waiting', 5, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(@queue_consultation_id, 'Pedro Normal', '(11) 93333-3333', 3, 'C003', 'waiting', 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(@queue_barber_id, 'Carlos Client', '(11) 94444-4444', 1, 'B001', 'waiting', 0, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(@queue_barber_id, 'Fernando Client', '(11) 95555-5555', 2, 'B002', 'waiting', 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- ============================================================================
-- SUMMARY
-- ============================================================================

SELECT 'SEEDING COMPLETED SUCCESSFULLY!' as message;
SELECT '' as '';

SELECT '=== DATA SUMMARY ===' as '';

SELECT 'Establishments' as entity, COUNT(*) as count FROM establishments;
SELECT 'Services' as entity, COUNT(*) as count FROM services;
SELECT 'Professionals' as entity, COUNT(*) as count FROM professionals;
SELECT 'Queues' as entity, COUNT(*) as count FROM queues;
SELECT 'Queue Entries' as entity, COUNT(*) as count FROM queue_entries;

SELECT '' as '';
SELECT '=== NEXT STEPS ===' as '';
SELECT '1. Set SUPER_ADMIN_EMAIL in .env to your Google email' as step;
SELECT '2. Login with Google OAuth to become admin' as step;
SELECT '3. Assign yourself as owner of establishments via SQL or API' as step;

-- ============================================================================
-- HOW TO BECOME ADMIN
-- ============================================================================
-- 
-- Since users are created via Google OAuth login, to become admin:
-- 
-- 1. Set SUPER_ADMIN_EMAIL=your.email@gmail.com in api/.env BEFORE first login
-- 2. Login with that Google account - you'll automatically be admin
-- 
-- OR, to promote an existing user to admin:
-- UPDATE users SET role = 'admin' WHERE email = 'your.email@gmail.com';
-- 
-- To link yourself as establishment owner:
-- UPDATE establishments SET owner_id = (SELECT id FROM users WHERE email = 'your.email@gmail.com') WHERE slug = 'central-medical';
--
-- ============================================================================
