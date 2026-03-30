-- 0001_admin_showcase_seed.sql
-- Showcase seed for demos linked to admin user id = 1.
-- Creates 3 businesses, varied establishments, fictional professionals,
-- services, queues, access codes, queue history and walk-in guests.
--
-- Run with:
--   php scripts/seed.php up

USE queue_master;

SET @admin_user_id := 1;

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- Guarantee admin id 1 exists for demo ownership.
-- If id 1 already exists, keep the user and ensure admin access is enabled.
-- ---------------------------------------------------------------------------
INSERT INTO users (
    id,
    name,
    email,
    password_hash,
    google_id,
    avatar_url,
    avatar_base64,
    email_verified,
    phone,
    address_line_1,
    address_line_2,
    role,
    manager_access_granted,
    manager_access_granted_at,
    is_active,
    login_blocked_at,
    login_block_reason,
    login_blocked_by_user_id,
    last_login_at,
    created_at,
    updated_at
)
SELECT
    1,
    'Admin Showcase',
    'admin.showcase@queuemaster.local',
    NULL,
    NULL,
    NULL,
    NULL,
    1,
    NULL,
    NULL,
    NULL,
    'admin',
    1,
    NOW(),
    1,
    NULL,
    NULL,
    NULL,
    NOW(),
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE id = @admin_user_id
);

UPDATE users
SET
    role = 'admin',
    manager_access_granted = 1,
    manager_access_granted_at = COALESCE(manager_access_granted_at, NOW()),
    is_active = 1,
    login_blocked_at = NULL,
    login_block_reason = NULL,
    login_blocked_by_user_id = NULL
WHERE id = @admin_user_id;

-- ---------------------------------------------------------------------------
-- Idempotent cleanup of the previous showcase set.
-- ---------------------------------------------------------------------------
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

-- ---------------------------------------------------------------------------
-- Businesses owned by admin id 1.
-- ---------------------------------------------------------------------------
INSERT INTO businesses (owner_user_id, name, slug, description, is_active, created_at)
VALUES
    (@admin_user_id, 'Pulsz Barber House', 'pulsz-barber-house', 'Rede demo de barbearias com foco em corte, barba e atendimento rapido.', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
    (@admin_user_id, 'Clinica Horizonte Saude', 'clinica-horizonte-saude', 'Operacao demo de clinica com consultas, triagem e exames rapidos.', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
    (@admin_user_id, 'Pet Care Studio', 'pet-care-studio', 'Espaco demo para banho, tosa e servicos de bem-estar pet.', 1, DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO business_users (business_id, user_id, role)
SELECT id, @admin_user_id, 'owner'
FROM businesses
WHERE slug IN (
    'pulsz-barber-house',
    'clinica-horizonte-saude',
    'pet-care-studio'
);

SET @barber_business_id := (SELECT id FROM businesses WHERE slug = 'pulsz-barber-house' LIMIT 1);
SET @clinic_business_id := (SELECT id FROM businesses WHERE slug = 'clinica-horizonte-saude' LIMIT 1);
SET @pet_business_id := (SELECT id FROM businesses WHERE slug = 'pet-care-studio' LIMIT 1);

-- ---------------------------------------------------------------------------
-- Establishments.
-- ---------------------------------------------------------------------------
INSERT INTO establishments (
    owner_id,
    business_id,
    name,
    slug,
    description,
    address,
    phone,
    email,
    timezone,
    is_active,
    opens_at,
    closes_at,
    created_at
) VALUES
    (@admin_user_id, @barber_business_id, 'Pulsz Barber Centro', 'pulsz-barber-centro', 'Unidade de maior movimento para cortes premium e combos completos.', 'Rua das Palmeiras, 120 - Centro, Sao Paulo - SP', '(11) 99811-1101', 'centro@pulszbarber.demo', 'America/Sao_Paulo', 1, '09:00:00', '21:00:00', DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (@admin_user_id, @barber_business_id, 'Pulsz Barber Beira Mar', 'pulsz-barber-beira-mar', 'Ponto compacto com fila expressa para almoco e fim de expediente.', 'Av. Beira Mar, 455 - Santos - SP', '(13) 99777-2020', 'beiramar@pulszbarber.demo', 'America/Sao_Paulo', 1, '10:00:00', '22:00:00', DATE_SUB(NOW(), INTERVAL 26 DAY)),
    (@admin_user_id, @clinic_business_id, 'Horizonte Saude Centro', 'horizonte-saude-centro', 'Clinica geral com triagem, retorno e consultas do dia.', 'Rua dos Medicos, 88 - Bela Vista, Sao Paulo - SP', '(11) 4002-1001', 'centro@horizontesaude.demo', 'America/Sao_Paulo', 1, '07:00:00', '19:00:00', DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (@admin_user_id, @clinic_business_id, 'Horizonte Saude Mulher e Exames', 'horizonte-saude-mulher-exames', 'Unidade focada em saude da mulher, coleta e exames rapidos.', 'Alameda Harmonia, 510 - Vila Mariana, Sao Paulo - SP', '(11) 4002-1002', 'mulher@horizontesaude.demo', 'America/Sao_Paulo', 1, '07:30:00', '18:30:00', DATE_SUB(NOW(), INTERVAL 22 DAY)),
    (@admin_user_id, @pet_business_id, 'Pet Care Spa Moema', 'pet-care-spa-moema', 'Espaco para banho, tosa higienica e cuidados express para pets.', 'Av. Pavoezinho, 320 - Moema, Sao Paulo - SP', '(11) 98800-4500', 'moema@petcare.demo', 'America/Sao_Paulo', 1, '08:00:00', '18:00:00', DATE_SUB(NOW(), INTERVAL 18 DAY));

INSERT INTO establishment_users (establishment_id, user_id, role)
SELECT id, @admin_user_id, 'owner'
FROM establishments
WHERE slug IN (
    'pulsz-barber-centro',
    'pulsz-barber-beira-mar',
    'horizonte-saude-centro',
    'horizonte-saude-mulher-exames',
    'pet-care-spa-moema'
);

SET @barber_centro_id := (SELECT id FROM establishments WHERE slug = 'pulsz-barber-centro' LIMIT 1);
SET @barber_beiramar_id := (SELECT id FROM establishments WHERE slug = 'pulsz-barber-beira-mar' LIMIT 1);
SET @clinic_centro_id := (SELECT id FROM establishments WHERE slug = 'horizonte-saude-centro' LIMIT 1);
SET @clinic_exames_id := (SELECT id FROM establishments WHERE slug = 'horizonte-saude-mulher-exames' LIMIT 1);
SET @pet_moema_id := (SELECT id FROM establishments WHERE slug = 'pet-care-spa-moema' LIMIT 1);

-- ---------------------------------------------------------------------------
-- Services.
-- ---------------------------------------------------------------------------
INSERT INTO services (
    establishment_id,
    name,
    description,
    icon,
    duration_minutes,
    price,
    is_active,
    sort_order,
    created_at
) VALUES
    (@barber_centro_id, 'Corte premium', 'Corte masculino com acabamento em navalha e finalizacao premium.', 'content_cut', 45, 65.00, 1, 1, DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (@barber_centro_id, 'Barba desenhada', 'Barba modelada com toalha quente e hidratacao.', 'face', 30, 45.00, 1, 2, DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (@barber_centro_id, 'Combo corte + barba', 'Pacote completo para quem quer resolver tudo em uma visita.', 'style', 60, 99.00, 1, 3, DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (@barber_centro_id, 'Sobrancelha', 'Alinhamento rapido de sobrancelha masculina.', 'remove_red_eye', 15, 20.00, 1, 4, DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (@barber_beiramar_id, 'Corte express', 'Corte rapido pensado para fila curta no horario comercial.', 'bolt', 30, 50.00, 1, 1, DATE_SUB(NOW(), INTERVAL 26 DAY)),
    (@barber_beiramar_id, 'Pigmentacao de barba', 'Realce de barba com acabamento discreto.', 'brush', 35, 55.00, 1, 2, DATE_SUB(NOW(), INTERVAL 26 DAY)),
    (@barber_beiramar_id, 'Combo pai e filho', 'Atendimento em sequencia para dois cortes no mesmo horario.', 'groups', 60, 105.00, 1, 3, DATE_SUB(NOW(), INTERVAL 26 DAY)),
    (@clinic_centro_id, 'Consulta clinica geral', 'Consulta medica de rotina para adultos.', 'medical_services', 30, 180.00, 1, 1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (@clinic_centro_id, 'Retorno medico', 'Retorno rapido para revisao de conduta e exames.', 'assignment_return', 20, 110.00, 1, 2, DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (@clinic_centro_id, 'Triagem e sinais vitais', 'Afericao, anamnese inicial e orientacao de fluxo.', 'monitor_heart', 15, 60.00, 1, 3, DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (@clinic_exames_id, 'Consulta ginecologica', 'Consulta focada em prevencao e acompanhamento.', 'female', 40, 220.00, 1, 1, DATE_SUB(NOW(), INTERVAL 22 DAY)),
    (@clinic_exames_id, 'Coleta laboratorial', 'Coleta rapida para exames de sangue e rotina.', 'science', 15, 75.00, 1, 2, DATE_SUB(NOW(), INTERVAL 22 DAY)),
    (@clinic_exames_id, 'Ultrassom obstetrico', 'Exame de imagem com atendimento agendado ou encaixe.', 'pregnant_woman', 35, 260.00, 1, 3, DATE_SUB(NOW(), INTERVAL 22 DAY)),
    (@pet_moema_id, 'Banho completo', 'Banho com secagem, perfume suave e laco.', 'pets', 45, 70.00, 1, 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
    (@pet_moema_id, 'Tosa higienica', 'Tosa de manutencao para regioes sensiveis.', 'content_cut', 30, 55.00, 1, 2, DATE_SUB(NOW(), INTERVAL 18 DAY)),
    (@pet_moema_id, 'Pacote banho + tosa', 'Atendimento completo com banho, secagem e tosa.', 'spa', 75, 120.00, 1, 3, DATE_SUB(NOW(), INTERVAL 18 DAY)),
    (@pet_moema_id, 'Corte de unhas', 'Servico rapido para manutencao entre banhos.', 'back_hand', 10, 25.00, 1, 4, DATE_SUB(NOW(), INTERVAL 18 DAY));

SET @svc_corte_premium := (SELECT id FROM services WHERE establishment_id = @barber_centro_id AND name = 'Corte premium' LIMIT 1);
SET @svc_barba_desenhada := (SELECT id FROM services WHERE establishment_id = @barber_centro_id AND name = 'Barba desenhada' LIMIT 1);
SET @svc_combo_barber := (SELECT id FROM services WHERE establishment_id = @barber_centro_id AND name = 'Combo corte + barba' LIMIT 1);
SET @svc_corte_express := (SELECT id FROM services WHERE establishment_id = @barber_beiramar_id AND name = 'Corte express' LIMIT 1);
SET @svc_pigmentacao := (SELECT id FROM services WHERE establishment_id = @barber_beiramar_id AND name = 'Pigmentacao de barba' LIMIT 1);
SET @svc_consulta_geral := (SELECT id FROM services WHERE establishment_id = @clinic_centro_id AND name = 'Consulta clinica geral' LIMIT 1);
SET @svc_retorno := (SELECT id FROM services WHERE establishment_id = @clinic_centro_id AND name = 'Retorno medico' LIMIT 1);
SET @svc_triagem := (SELECT id FROM services WHERE establishment_id = @clinic_centro_id AND name = 'Triagem e sinais vitais' LIMIT 1);
SET @svc_gineco := (SELECT id FROM services WHERE establishment_id = @clinic_exames_id AND name = 'Consulta ginecologica' LIMIT 1);
SET @svc_coleta := (SELECT id FROM services WHERE establishment_id = @clinic_exames_id AND name = 'Coleta laboratorial' LIMIT 1);
SET @svc_ultrassom := (SELECT id FROM services WHERE establishment_id = @clinic_exames_id AND name = 'Ultrassom obstetrico' LIMIT 1);
SET @svc_banho := (SELECT id FROM services WHERE establishment_id = @pet_moema_id AND name = 'Banho completo' LIMIT 1);
SET @svc_tosa := (SELECT id FROM services WHERE establishment_id = @pet_moema_id AND name = 'Tosa higienica' LIMIT 1);
SET @svc_pacote_pet := (SELECT id FROM services WHERE establishment_id = @pet_moema_id AND name = 'Pacote banho + tosa' LIMIT 1);

-- ---------------------------------------------------------------------------
-- Fictional professionals.
-- ---------------------------------------------------------------------------
INSERT INTO professionals (
    establishment_id,
    user_id,
    name,
    email,
    phone,
    specialty,
    is_active,
    created_at
) VALUES
    (@barber_centro_id, NULL, 'Diego Nunes', 'diego.nunes@pulszbarber.demo', '(11) 99110-1101', 'Barbeiro senior', 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
    (@barber_centro_id, NULL, 'Rafael Braga', 'rafael.braga@pulszbarber.demo', '(11) 99110-1102', 'Especialista em barba', 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
    (@barber_beiramar_id, NULL, 'Bruno Teixeira', 'bruno.teixeira@pulszbarber.demo', '(13) 99120-2201', 'Barbeiro express', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
    (@barber_beiramar_id, NULL, 'Caio Lima', 'caio.lima@pulszbarber.demo', '(13) 99120-2202', 'Coloracao e acabamento', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
    (@clinic_centro_id, NULL, 'Dra Camila Torres', 'camila.torres@horizontesaude.demo', '(11) 99230-3301', 'Clinica geral', 1, DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (@clinic_centro_id, NULL, 'Dr Renato Faria', 'renato.faria@horizontesaude.demo', '(11) 99230-3302', 'Clinico de plantao', 1, DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (@clinic_centro_id, NULL, 'Julia Moura', 'julia.moura@horizontesaude.demo', '(11) 99230-3303', 'Enfermeira de triagem', 1, DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (@clinic_exames_id, NULL, 'Dra Paola Mendes', 'paola.mendes@horizontesaude.demo', '(11) 99240-4401', 'Ginecologista', 1, DATE_SUB(NOW(), INTERVAL 21 DAY)),
    (@clinic_exames_id, NULL, 'Livia Rocha', 'livia.rocha@horizontesaude.demo', '(11) 99240-4402', 'Tecnica de coleta', 1, DATE_SUB(NOW(), INTERVAL 21 DAY)),
    (@pet_moema_id, NULL, 'Fernanda Alves', 'fernanda.alves@petcare.demo', '(11) 99350-5501', 'Groomer', 1, DATE_SUB(NOW(), INTERVAL 17 DAY)),
    (@pet_moema_id, NULL, 'Matheus Prado', 'matheus.prado@petcare.demo', '(11) 99350-5502', 'Banho e tosa', 1, DATE_SUB(NOW(), INTERVAL 17 DAY));

SET @pro_diego := (SELECT id FROM professionals WHERE establishment_id = @barber_centro_id AND email = 'diego.nunes@pulszbarber.demo' LIMIT 1);
SET @pro_rafael := (SELECT id FROM professionals WHERE establishment_id = @barber_centro_id AND email = 'rafael.braga@pulszbarber.demo' LIMIT 1);
SET @pro_bruno := (SELECT id FROM professionals WHERE establishment_id = @barber_beiramar_id AND email = 'bruno.teixeira@pulszbarber.demo' LIMIT 1);
SET @pro_caio := (SELECT id FROM professionals WHERE establishment_id = @barber_beiramar_id AND email = 'caio.lima@pulszbarber.demo' LIMIT 1);
SET @pro_camila := (SELECT id FROM professionals WHERE establishment_id = @clinic_centro_id AND email = 'camila.torres@horizontesaude.demo' LIMIT 1);
SET @pro_renato := (SELECT id FROM professionals WHERE establishment_id = @clinic_centro_id AND email = 'renato.faria@horizontesaude.demo' LIMIT 1);
SET @pro_julia := (SELECT id FROM professionals WHERE establishment_id = @clinic_centro_id AND email = 'julia.moura@horizontesaude.demo' LIMIT 1);
SET @pro_paola := (SELECT id FROM professionals WHERE establishment_id = @clinic_exames_id AND email = 'paola.mendes@horizontesaude.demo' LIMIT 1);
SET @pro_livia := (SELECT id FROM professionals WHERE establishment_id = @clinic_exames_id AND email = 'livia.rocha@horizontesaude.demo' LIMIT 1);
SET @pro_fernanda := (SELECT id FROM professionals WHERE establishment_id = @pet_moema_id AND email = 'fernanda.alves@petcare.demo' LIMIT 1);
SET @pro_matheus := (SELECT id FROM professionals WHERE establishment_id = @pet_moema_id AND email = 'matheus.prado@petcare.demo' LIMIT 1);

INSERT INTO professional_services (professional_id, service_id)
VALUES
    (@pro_diego, @svc_corte_premium),
    (@pro_diego, @svc_combo_barber),
    (@pro_rafael, @svc_barba_desenhada),
    (@pro_rafael, @svc_combo_barber),
    (@pro_bruno, @svc_corte_express),
    (@pro_caio, @svc_pigmentacao),
    (@pro_camila, @svc_consulta_geral),
    (@pro_camila, @svc_retorno),
    (@pro_renato, @svc_consulta_geral),
    (@pro_julia, @svc_triagem),
    (@pro_paola, @svc_gineco),
    (@pro_livia, @svc_coleta),
    (@pro_livia, @svc_ultrassom),
    (@pro_fernanda, @svc_banho),
    (@pro_fernanda, @svc_pacote_pet),
    (@pro_matheus, @svc_tosa),
    (@pro_matheus, @svc_pacote_pet);

-- ---------------------------------------------------------------------------
-- Queues and queue/service relations.
-- ---------------------------------------------------------------------------
INSERT INTO queues (
    establishment_id,
    service_id,
    name,
    description,
    status,
    max_capacity,
    avg_wait_minutes,
    called_highlight_after_minutes,
    created_at
) VALUES
    (@barber_centro_id, @svc_corte_premium, 'Fila Corte Premium', 'Fila principal para cortes e combos da unidade centro.', 'open', 18, 25, 5, DATE_SUB(NOW(), INTERVAL 28 DAY)),
    (@barber_beiramar_id, @svc_corte_express, 'Fila Express', 'Fluxo agil para clientes sem horario marcado.', 'open', 12, 15, 4, DATE_SUB(NOW(), INTERVAL 25 DAY)),
    (@clinic_centro_id, @svc_triagem, 'Triagem do Dia', 'Fila de recepcao e sinais vitais para encaixes.', 'open', 25, 12, 5, DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (@clinic_centro_id, @svc_consulta_geral, 'Consulta Clinica Geral', 'Fila de consultas do dia apos triagem.', 'open', 14, 30, 8, DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (@clinic_exames_id, @svc_coleta, 'Fila Exames Rapidos', 'Atendimento de coleta e encaixe para exames curtos.', 'open', 16, 18, 6, DATE_SUB(NOW(), INTERVAL 21 DAY)),
    (@pet_moema_id, @svc_banho, 'Banho e Tosa Agora', 'Fila de encaixe para pets com permanencia curta.', 'open', 10, 35, 7, DATE_SUB(NOW(), INTERVAL 17 DAY));

SET @queue_barber_centro := (SELECT id FROM queues WHERE establishment_id = @barber_centro_id AND name = 'Fila Corte Premium' LIMIT 1);
SET @queue_barber_beiramar := (SELECT id FROM queues WHERE establishment_id = @barber_beiramar_id AND name = 'Fila Express' LIMIT 1);
SET @queue_clinic_triagem := (SELECT id FROM queues WHERE establishment_id = @clinic_centro_id AND name = 'Triagem do Dia' LIMIT 1);
SET @queue_clinic_consulta := (SELECT id FROM queues WHERE establishment_id = @clinic_centro_id AND name = 'Consulta Clinica Geral' LIMIT 1);
SET @queue_clinic_exames := (SELECT id FROM queues WHERE establishment_id = @clinic_exames_id AND name = 'Fila Exames Rapidos' LIMIT 1);
SET @queue_pet := (SELECT id FROM queues WHERE establishment_id = @pet_moema_id AND name = 'Banho e Tosa Agora' LIMIT 1);

INSERT INTO queue_services (queue_id, service_id)
VALUES
    (@queue_barber_centro, @svc_corte_premium),
    (@queue_barber_centro, @svc_barba_desenhada),
    (@queue_barber_centro, @svc_combo_barber),
    (@queue_barber_beiramar, @svc_corte_express),
    (@queue_barber_beiramar, @svc_pigmentacao),
    (@queue_clinic_triagem, @svc_triagem),
    (@queue_clinic_consulta, @svc_consulta_geral),
    (@queue_clinic_consulta, @svc_retorno),
    (@queue_clinic_exames, @svc_coleta),
    (@queue_clinic_exames, @svc_ultrassom),
    (@queue_pet, @svc_banho),
    (@queue_pet, @svc_tosa),
    (@queue_pet, @svc_pacote_pet);

INSERT INTO queue_access_codes (queue_id, code, expires_at, max_uses, uses, is_active, created_at)
VALUES
    (@queue_barber_centro, 'BARBER-CENTRO', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW()),
    (@queue_barber_beiramar, 'BARBER-EXPRESS', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW()),
    (@queue_clinic_triagem, 'CLINICA-TRIAGEM', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW()),
    (@queue_clinic_consulta, 'CLINICA-CONSULTA', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW()),
    (@queue_clinic_exames, 'CLINICA-EXAMES', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW()),
    (@queue_pet, 'PET-AGORA', DATE_ADD(NOW(), INTERVAL 180 DAY), NULL, 0, 1, NOW());

INSERT INTO queue_professionals (queue_id, user_id, is_active, created_at, updated_at)
VALUES
    (@queue_barber_centro, @admin_user_id, 1, NOW(), NOW()),
    (@queue_barber_beiramar, @admin_user_id, 1, NOW(), NOW()),
    (@queue_clinic_triagem, @admin_user_id, 1, NOW(), NOW()),
    (@queue_clinic_consulta, @admin_user_id, 1, NOW(), NOW()),
    (@queue_clinic_exames, @admin_user_id, 1, NOW(), NOW()),
    (@queue_pet, @admin_user_id, 1, NOW(), NOW());

-- ---------------------------------------------------------------------------
-- Queue entries with a mix of waiting, called, serving, completed and no-show.
-- All staff handling references admin id 1.
-- ---------------------------------------------------------------------------
INSERT INTO queue_entries (
    public_id,
    queue_id,
    user_id,
    guest_name,
    guest_phone,
    position,
    ticket_number,
    status,
    priority,
    notes,
    professional_id,
    created_at,
    called_at,
    served_at,
    completed_at
) VALUES
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_barber_centro, NULL, 'Mateus Andrade', '(11) 99910-0001', 1, 'BC001', 'waiting', 0, 'Prefere acabamento baixo.', NULL, DATE_SUB(NOW(), INTERVAL 18 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_barber_centro, NULL, 'Guilherme Costa', '(11) 99910-0002', 2, 'BC002', 'waiting', 1, 'Veio para combo completo.', NULL, DATE_SUB(NOW(), INTERVAL 11 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_barber_centro, NULL, 'Eduarda Rocha', '(11) 99910-0003', 3, 'BC003', 'serving', 0, 'Cliente recorrente da unidade.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 24 MINUTE), DATE_SUB(NOW(), INTERVAL 7 MINUTE), DATE_SUB(NOW(), INTERVAL 4 MINUTE), NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_barber_beiramar, NULL, 'Henrique Alves', '(13) 99920-0001', 1, 'BB001', 'waiting', 0, 'Corte rapido antes do expediente.', NULL, DATE_SUB(NOW(), INTERVAL 9 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_barber_beiramar, NULL, 'Paulo Cesar', '(13) 99920-0002', 2, 'BB002', 'done', 0, 'Saiu com pacote express finalizado.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 58 MINUTE), DATE_SUB(NOW(), INTERVAL 34 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 6 MINUTE)),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_triagem, NULL, 'Clara Nogueira', '(11) 99930-0001', 1, 'CT001', 'waiting', 2, 'Chegou com prioridade por gestacao.', NULL, DATE_SUB(NOW(), INTERVAL 14 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_triagem, NULL, 'Vanessa Ribeiro', '(11) 99930-0002', 2, 'CT002', 'called', 0, 'Aguardando entrar para sinais vitais.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 26 MINUTE), DATE_SUB(NOW(), INTERVAL 4 MINUTE), NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_triagem, NULL, 'Tiago Melo', '(11) 99930-0003', 3, 'CT003', 'waiting', 0, 'Encaminhado pela recepcao.', NULL, DATE_SUB(NOW(), INTERVAL 8 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_consulta, NULL, 'Debora Martins', '(11) 99940-0001', 1, 'CC001', 'done', 0, 'Retorno finalizado com receita digital.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 90 MINUTE), DATE_SUB(NOW(), INTERVAL 52 MINUTE), DATE_SUB(NOW(), INTERVAL 48 MINUTE), DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_consulta, NULL, 'Roberto Pinheiro', '(11) 99940-0002', 2, 'CC002', 'no_show', 0, 'Nao respondeu ao chamado da recepcao.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 42 MINUTE), DATE_SUB(NOW(), INTERVAL 17 MINUTE), NULL, DATE_SUB(NOW(), INTERVAL 9 MINUTE)),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_exames, NULL, 'Larissa Prado', '(11) 99950-0001', 1, 'CE001', 'waiting', 0, 'Coleta de rotina solicitada pelo medico.', NULL, DATE_SUB(NOW(), INTERVAL 13 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_clinic_exames, NULL, 'Juliana Araujo', '(11) 99950-0002', 2, 'CE002', 'done', 0, 'Coleta concluida e amostra enviada.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 70 MINUTE), DATE_SUB(NOW(), INTERVAL 28 MINUTE), DATE_SUB(NOW(), INTERVAL 24 MINUTE), DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_pet, NULL, 'Thor - tutor Marina', '(11) 99960-0001', 1, 'PT001', 'waiting', 0, 'Bulldog para banho completo.', NULL, DATE_SUB(NOW(), INTERVAL 16 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_pet, NULL, 'Luna - tutor Felipe', '(11) 99960-0002', 2, 'PT002', 'waiting', 0, 'Shih-tzu para banho e tosa.', NULL, DATE_SUB(NOW(), INTERVAL 7 MINUTE), NULL, NULL, NULL),
    (UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)), @queue_pet, NULL, 'Mel - tutora Carla', '(11) 99960-0003', 3, 'PT003', 'done', 1, 'Saiu com laco e hidratacao.', @admin_user_id, DATE_SUB(NOW(), INTERVAL 82 MINUTE), DATE_SUB(NOW(), INTERVAL 47 MINUTE), DATE_SUB(NOW(), INTERVAL 41 MINUTE), DATE_SUB(NOW(), INTERVAL 14 MINUTE));

-- ---------------------------------------------------------------------------
-- Queue history timeline for demoing the new history views.
-- ---------------------------------------------------------------------------
INSERT INTO queue_entry_events (
    queue_entry_id,
    queue_id,
    user_id,
    actor_user_id,
    actor_type,
    event_type,
    payload,
    occurred_at,
    created_at
)
SELECT
    qe.id,
    qe.queue_id,
    qe.user_id,
    NULL,
    'system',
    'joined',
    JSON_OBJECT(
        'ticket_number', qe.ticket_number,
        'position', qe.position,
        'status', qe.status
    ),
    qe.created_at,
    qe.created_at
FROM queue_entries qe
WHERE qe.queue_id IN (
    @queue_barber_centro,
    @queue_barber_beiramar,
    @queue_clinic_triagem,
    @queue_clinic_consulta,
    @queue_clinic_exames,
    @queue_pet
);

INSERT INTO queue_entry_events (
    queue_entry_id,
    queue_id,
    user_id,
    actor_user_id,
    actor_type,
    event_type,
    payload,
    occurred_at,
    created_at
)
SELECT
    qe.id,
    qe.queue_id,
    qe.user_id,
    @admin_user_id,
    'staff',
    'called',
    JSON_OBJECT(
        'ticket_number', qe.ticket_number,
        'professional_user_id', qe.professional_id,
        'status_after', qe.status
    ),
    qe.called_at,
    qe.called_at
FROM queue_entries qe
WHERE qe.queue_id IN (
    @queue_barber_centro,
    @queue_barber_beiramar,
    @queue_clinic_triagem,
    @queue_clinic_consulta,
    @queue_clinic_exames,
    @queue_pet
)
AND qe.called_at IS NOT NULL;

INSERT INTO queue_entry_events (
    queue_entry_id,
    queue_id,
    user_id,
    actor_user_id,
    actor_type,
    event_type,
    payload,
    occurred_at,
    created_at
)
SELECT
    qe.id,
    qe.queue_id,
    qe.user_id,
    @admin_user_id,
    'staff',
    'serving_started',
    JSON_OBJECT(
        'ticket_number', qe.ticket_number,
        'professional_user_id', qe.professional_id
    ),
    qe.served_at,
    qe.served_at
FROM queue_entries qe
WHERE qe.queue_id IN (
    @queue_barber_centro,
    @queue_barber_beiramar,
    @queue_clinic_triagem,
    @queue_clinic_consulta,
    @queue_clinic_exames,
    @queue_pet
)
AND qe.served_at IS NOT NULL;

INSERT INTO queue_entry_events (
    queue_entry_id,
    queue_id,
    user_id,
    actor_user_id,
    actor_type,
    event_type,
    payload,
    occurred_at,
    created_at
)
SELECT
    qe.id,
    qe.queue_id,
    qe.user_id,
    @admin_user_id,
    'staff',
    'completed',
    JSON_OBJECT(
        'ticket_number', qe.ticket_number,
        'final_status', qe.status
    ),
    qe.completed_at,
    qe.completed_at
FROM queue_entries qe
WHERE qe.queue_id IN (
    @queue_barber_centro,
    @queue_barber_beiramar,
    @queue_clinic_triagem,
    @queue_clinic_consulta,
    @queue_clinic_exames,
    @queue_pet
)
AND qe.status = 'done'
AND qe.completed_at IS NOT NULL;

INSERT INTO queue_entry_events (
    queue_entry_id,
    queue_id,
    user_id,
    actor_user_id,
    actor_type,
    event_type,
    payload,
    occurred_at,
    created_at
)
SELECT
    qe.id,
    qe.queue_id,
    qe.user_id,
    @admin_user_id,
    'staff',
    'no_show',
    JSON_OBJECT(
        'ticket_number', qe.ticket_number,
        'final_status', qe.status
    ),
    qe.completed_at,
    qe.completed_at
FROM queue_entries qe
WHERE qe.queue_id IN (
    @queue_barber_centro,
    @queue_barber_beiramar,
    @queue_clinic_triagem,
    @queue_clinic_consulta,
    @queue_clinic_exames,
    @queue_pet
)
AND qe.status = 'no_show'
AND qe.completed_at IS NOT NULL;

COMMIT;

SELECT 'SHOWCASE SEED COMPLETED' AS message;
SELECT CONCAT('Admin owner user id: ', @admin_user_id) AS owner_reference;
SELECT 'Se o usuario 1 nao existia, o seed criou um admin tecnico de showcase para manter os vinculos.' AS bootstrap_note;
SELECT 'Use o deploy.sh > Seeds > production seeds (up) para carregar este conjunto.' AS hint;
SELECT 'Todos os negocios, estabelecimentos e filas criados neste seed pertencem ao admin id 1.' AS scope_note;

SELECT 'businesses' AS entity, COUNT(*) AS total
FROM businesses
WHERE slug IN ('pulsz-barber-house', 'clinica-horizonte-saude', 'pet-care-studio')
UNION ALL
SELECT 'establishments' AS entity, COUNT(*) AS total
FROM establishments
WHERE slug IN (
    'pulsz-barber-centro',
    'pulsz-barber-beira-mar',
    'horizonte-saude-centro',
    'horizonte-saude-mulher-exames',
    'pet-care-spa-moema'
)
UNION ALL
SELECT 'services' AS entity, COUNT(*) AS total
FROM services
WHERE establishment_id IN (@barber_centro_id, @barber_beiramar_id, @clinic_centro_id, @clinic_exames_id, @pet_moema_id)
UNION ALL
SELECT 'professionals' AS entity, COUNT(*) AS total
FROM professionals
WHERE establishment_id IN (@barber_centro_id, @barber_beiramar_id, @clinic_centro_id, @clinic_exames_id, @pet_moema_id)
UNION ALL
SELECT 'queues' AS entity, COUNT(*) AS total
FROM queues
WHERE id IN (@queue_barber_centro, @queue_barber_beiramar, @queue_clinic_triagem, @queue_clinic_consulta, @queue_clinic_exames, @queue_pet)
UNION ALL
SELECT 'queue_entries' AS entity, COUNT(*) AS total
FROM queue_entries
WHERE queue_id IN (@queue_barber_centro, @queue_barber_beiramar, @queue_clinic_triagem, @queue_clinic_consulta, @queue_clinic_exames, @queue_pet)
UNION ALL
SELECT 'queue_entry_events' AS entity, COUNT(*) AS total
FROM queue_entry_events
WHERE queue_id IN (@queue_barber_centro, @queue_barber_beiramar, @queue_clinic_triagem, @queue_clinic_consulta, @queue_clinic_exames, @queue_pet);
