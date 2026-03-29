<?php

namespace QueueMaster\Tests;

use PHPUnit\Framework\TestCase;
use QueueMaster\Core\Database;
use QueueMaster\Services\AppointmentRequestService;

class AppointmentRequestFlowTest extends TestCase
{
    private Database $db;
    private AppointmentRequestService $service;
    private int $managerUserId;
    private int $professionalUserId;
    private int $secondProfessionalUserId;
    private int $clientUserId;
    private int $establishmentId;
    private int $serviceId;
    private int $professionalId;
    private int $secondProfessionalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::getInstance();
        $this->service = new AppointmentRequestService();

        $this->ensureAppointmentRequestTable();
        $this->db->beginTransaction();
        $this->createTestData();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollback();
        }

        parent::tearDown();
    }

    public function testClientRequestCanBeAcceptedByManagerWithAnotherProfessional(): void
    {
        $startAt = $this->futureDateTime('+1 day 10:00:00');

        $request = $this->service->create([
            'establishment_id' => $this->establishmentId,
            'service_id' => $this->serviceId,
            'start_at' => $startAt,
            'notes' => 'Cliente prefere pela manhã',
        ], [
            'id' => $this->clientUserId,
            'role' => 'client',
        ]);

        $result = $this->service->accept((int)$request['id'], [
            'id' => $this->managerUserId,
            'role' => 'manager',
        ], [
            'professional_id' => $this->secondProfessionalId,
            'decision_note' => 'Alocado para a agenda disponível',
        ]);

        $this->assertSame('accepted', $result['request']['status']);
        $this->assertSame($this->secondProfessionalId, (int)$result['request']['professional_id']);
        $this->assertSame($this->clientUserId, (int)$result['appointment']['user_id']);
        $this->assertSame($this->secondProfessionalId, (int)$result['appointment']['professional_id']);
        $this->assertSame('booked', $result['appointment']['status']);

        $requestRows = $this->db->query(
            'SELECT status, professional_id, responded_by_user_id FROM appointment_requests WHERE id = ?',
            [(int)$request['id']]
        );

        $this->assertCount(1, $requestRows);
        $this->assertSame('accepted', $requestRows[0]['status']);
        $this->assertSame($this->secondProfessionalId, (int)$requestRows[0]['professional_id']);
        $this->assertSame($this->managerUserId, (int)$requestRows[0]['responded_by_user_id']);
    }

    public function testProfessionalCannotAcceptRequestAssigningAnotherProfessional(): void
    {
        $startAt = $this->futureDateTime('+2 day 11:00:00');

        $request = $this->service->create([
            'establishment_id' => $this->establishmentId,
            'service_id' => $this->serviceId,
            'start_at' => $startAt,
        ], [
            'id' => $this->clientUserId,
            'role' => 'client',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('O profissional so pode se alocar a si mesmo');

        $this->service->accept((int)$request['id'], [
            'id' => $this->professionalUserId,
            'role' => 'professional',
        ], [
            'professional_id' => $this->secondProfessionalId,
        ]);
    }

    public function testStaffProposalCanBeAcceptedByClient(): void
    {
        $startAt = $this->futureDateTime('+3 day 14:00:00');

        $request = $this->service->create([
            'establishment_id' => $this->establishmentId,
            'service_id' => $this->serviceId,
            'professional_id' => $this->professionalId,
            'client_user_id' => $this->clientUserId,
            'start_at' => $startAt,
            'notes' => 'Proposta enviada pela equipe',
        ], [
            'id' => $this->managerUserId,
            'role' => 'manager',
        ]);

        $result = $this->service->accept((int)$request['id'], [
            'id' => $this->clientUserId,
            'role' => 'client',
        ]);

        $this->assertSame('staff_to_client', $request['direction']);
        $this->assertSame('accepted', $result['request']['status']);
        $this->assertSame($this->professionalId, (int)$result['appointment']['professional_id']);
        $this->assertSame($this->clientUserId, (int)$result['appointment']['user_id']);
    }

    private function ensureAppointmentRequestTable(): void
    {
        $this->db->getConnection()->exec(
            "
            CREATE TABLE IF NOT EXISTS appointment_requests (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              establishment_id BIGINT UNSIGNED NOT NULL,
              service_id BIGINT UNSIGNED NOT NULL,
              professional_id BIGINT UNSIGNED NULL,
              client_user_id BIGINT UNSIGNED NOT NULL,
              requested_by_user_id BIGINT UNSIGNED NOT NULL,
              responded_by_user_id BIGINT UNSIGNED NULL,
              direction ENUM('client_to_establishment','staff_to_client') NOT NULL,
              requester_role ENUM('client','professional','manager','admin') NOT NULL,
              status ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
              proposed_start_at DATETIME NOT NULL,
              proposed_end_at DATETIME NOT NULL,
              notes TEXT NULL,
              decision_note TEXT NULL,
              responded_at TIMESTAMP NULL DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_appointment_requests_establishment
                FOREIGN KEY (establishment_id) REFERENCES establishments(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_appointment_requests_service
                FOREIGN KEY (service_id) REFERENCES services(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_appointment_requests_professional
                FOREIGN KEY (professional_id) REFERENCES professionals(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT fk_appointment_requests_client
                FOREIGN KEY (client_user_id) REFERENCES users(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_appointment_requests_requested_by
                FOREIGN KEY (requested_by_user_id) REFERENCES users(id)
                ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT fk_appointment_requests_responded_by
                FOREIGN KEY (responded_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL ON UPDATE CASCADE,
              INDEX idx_appointment_requests_status (status, proposed_start_at),
              INDEX idx_appointment_requests_establishment (establishment_id, status, proposed_start_at),
              INDEX idx_appointment_requests_client (client_user_id, status, proposed_start_at),
              INDEX idx_appointment_requests_professional (professional_id, status, proposed_start_at),
              INDEX idx_appointment_requests_requested_by (requested_by_user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        );
    }

    private function createTestData(): void
    {
        $this->managerUserId = $this->createUser('Manager User', 'manager.req@example.com', 'manager');
        $this->professionalUserId = $this->createUser('Professional One', 'professional.one@example.com', 'professional');
        $this->secondProfessionalUserId = $this->createUser('Professional Two', 'professional.two@example.com', 'professional');
        $this->clientUserId = $this->createUser('Client User', 'client.req@example.com', 'client');

        $this->db->execute(
            'INSERT INTO establishments (owner_id, name, address, is_active) VALUES (?, ?, ?, 1)',
            [$this->managerUserId, 'Scheduling Test Clinic', '123 Test Street']
        );
        $this->establishmentId = (int)$this->db->lastInsertId();

        $this->db->execute(
            'INSERT INTO services (establishment_id, name, duration_minutes, is_active) VALUES (?, ?, 30, 1)',
            [$this->establishmentId, 'Consulta']
        );
        $this->serviceId = (int)$this->db->lastInsertId();

        $this->db->execute(
            'INSERT INTO professionals (establishment_id, user_id, name, specialty, is_active) VALUES (?, ?, ?, ?, 1)',
            [$this->establishmentId, $this->professionalUserId, 'Professional One', 'Clínico']
        );
        $this->professionalId = (int)$this->db->lastInsertId();

        $this->db->execute(
            'INSERT INTO professionals (establishment_id, user_id, name, specialty, is_active) VALUES (?, ?, ?, ?, 1)',
            [$this->establishmentId, $this->secondProfessionalUserId, 'Professional Two', 'Clínico']
        );
        $this->secondProfessionalId = (int)$this->db->lastInsertId();
    }

    private function createUser(string $name, string $email, string $role): int
    {
        $this->db->execute(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash('password123', PASSWORD_BCRYPT), $role]
        );

        return (int)$this->db->lastInsertId();
    }

    private function futureDateTime(string $modifier): string
    {
        return date('Y-m-d H:i:s', strtotime($modifier));
    }
}
