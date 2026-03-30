-- 0001_admin_showcase_seed_down.sql
-- Removes the admin showcase data created by 0001_admin_showcase_seed.sql.
--
-- Run with:
--   php scripts/seed.php down

USE queue_master;

START TRANSACTION;

DELETE FROM establishments
WHERE slug IN (
    'pulsz-barber-centro',
    'pulsz-barber-beira-mar',
    'horizonte-saude-centro',
    'horizonte-saude-mulher-exames',
    'pet-care-spa-moema'
);

DELETE FROM business_users
WHERE business_id IN (
    SELECT id
    FROM businesses
    WHERE slug IN (
        'pulsz-barber-house',
        'clinica-horizonte-saude',
        'pet-care-studio'
    )
);

DELETE FROM businesses
WHERE slug IN (
    'pulsz-barber-house',
    'clinica-horizonte-saude',
    'pet-care-studio'
);

COMMIT;

SELECT 'SHOWCASE SEED REMOVED' AS message;
