<?php

namespace QueueMaster\Tests;

use PHPUnit\Framework\TestCase;
use QueueMaster\Core\Database;
use QueueMaster\Services\AppointmentService;

/**
 * AppointmentConflictTest - Test appointment conflict detection
 * 
 * Tests that overlapping appointments are properly rejected and
 * non-overlapping appointments succeed.
 */
class AppointmentConflictTest extends TestCase
{
    private Database $db;
    private AppointmentService $appointmentService;
    private int $establishmentId;
    private int $professionalId;
    private int $serviceId;
    private array $testUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db = Database::getInstance();
        $this->appointmentService = new AppointmentService();
        
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

    private function createTestData(): void
    {
        $establishmentSql = "INSERT INTO establishments (name, address) VALUES ('Test Clinic', '456 Medical Ave')";
        $this->db->execute($establishmentSql);
        $this->establishmentId = (int)$this->db->lastInsertId();

        $serviceSql = "INSERT INTO services (establishment_id, name, duration_minutes) VALUES (?, 'Consultation', 30)";
        $this->db->execute($serviceSql, [$this->establishmentId]);
        $this->serviceId = (int)$this->db->lastInsertId();

        $professionalSql = "INSERT INTO professionals (establishment_id, name) VALUES (?, 'Dr. Smith')";
        $this->db->execute($professionalSql, [$this->establishmentId]);
        $this->professionalId = (int)$this->db->lastInsertId();

        for ($i = 1; $i <= 3; $i++) {
            $userSql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'client')";
            $this->db->execute($userSql, [
                "Patient $i",
                "patient$i@example.com",
                password_hash('password123', PASSWORD_BCRYPT)
            ]);
            $this->testUserIds[] = (int)$this->db->lastInsertId();
        }
    }

    /**
     * Test no double-booking occurs
     * Ensures overlapping appointments are rejected
     */
    public function testNoDoubleBooking(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $startTime = "$tomorrow 10:00:00";

        $appointment1 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => $startTime,
        ]);

        $this->assertIsArray($appointment1);
        $this->assertEquals('booked', $appointment1['status']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Time slot conflict');

        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => $startTime,
        ]);
    }

    /**
     * Test valid non-overlapping appointment
     * Ensures appointments at different times succeed
     */
    public function testValidAppointment(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $appointment1 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => "$tomorrow 10:00:00",
        ]);

        $appointment2 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => "$tomorrow 11:00:00",
        ]);

        $this->assertIsArray($appointment1);
        $this->assertIsArray($appointment2);
        $this->assertNotEquals($appointment1['id'], $appointment2['id']);
        
        $this->assertEquals("$tomorrow 10:00:00", $appointment1['start_at']);
        $this->assertEquals("$tomorrow 10:30:00", $appointment1['end_at']);
        
        $this->assertEquals("$tomorrow 11:00:00", $appointment2['start_at']);
        $this->assertEquals("$tomorrow 11:30:00", $appointment2['end_at']);
    }

    /**
     * Test time validation and end time calculation
     * Ensures start/end time logic is correct
     */
    public function testTimeValidation(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $appointment = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => "$tomorrow 14:00:00",
        ]);

        $this->assertEquals("$tomorrow 14:00:00", $appointment['start_at']);
        $this->assertEquals("$tomorrow 14:30:00", $appointment['end_at']);
        
        $startTimestamp = strtotime($appointment['start_at']);
        $endTimestamp = strtotime($appointment['end_at']);
        $diffMinutes = ($endTimestamp - $startTimestamp) / 60;
        
        $this->assertEquals(30, $diffMinutes, "Duration should be 30 minutes");
    }

    /**
     * Test overlapping at start boundary
     * New appointment starts during existing appointment
     */
    public function testOverlapAtStart(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => "$tomorrow 10:00:00",
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Time slot conflict');

        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => "$tomorrow 10:15:00",
        ]);
    }

    /**
     * Test overlapping at end boundary
     * New appointment starts before existing ends
     */
    public function testOverlapAtEnd(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => "$tomorrow 10:00:00",
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Time slot conflict');

        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => "$tomorrow 09:45:00",
        ]);
    }

    /**
     * Test exact boundary appointments (no overlap)
     * Appointment starts exactly when previous ends
     */
    public function testExactBoundaryAppointments(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $appointment1 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => "$tomorrow 10:00:00",
        ]);

        $appointment2 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => "$tomorrow 10:30:00",
        ]);

        $this->assertIsArray($appointment1);
        $this->assertIsArray($appointment2);
        $this->assertEquals($appointment1['end_at'], $appointment2['start_at'], 
            "Second appointment should start exactly when first ends");
    }

    /**
     * Test cancelled appointments don't block slots
     * Cancelled appointments shouldn't cause conflicts
     */
    public function testCancelledAppointmentsDontBlock(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $startTime = "$tomorrow 10:00:00";
        
        $appointment1 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => $startTime,
        ]);

        $this->appointmentService->cancel($appointment1['id'], $this->testUserIds[0]);

        $appointment2 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => $startTime,
        ]);

        $this->assertIsArray($appointment2);
        $this->assertEquals('booked', $appointment2['status']);
    }

    /**
     * Test multiple appointments in a day
     * Ensures proper scheduling throughout the day
     */
    public function testMultipleAppointmentsInDay(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $appointments = [];
        $times = ['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00'];
        
        foreach ($times as $index => $time) {
            $userId = $this->testUserIds[$index % count($this->testUserIds)];
            $appointment = $this->appointmentService->create([
                'establishment_id' => $this->establishmentId,
                'professional_id' => $this->professionalId,
                'service_id' => $this->serviceId,
                'user_id' => $userId,
                'start_at' => "$tomorrow $time",
            ]);
            $appointments[] = $appointment;
        }

        $this->assertCount(5, $appointments, "All 5 appointments should be created successfully");
        
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE professional_id = ? AND DATE(start_at) = ?";
        $result = $this->db->query($sql, [$this->professionalId, $tomorrow]);
        $count = (int)$result[0]['count'];
        
        $this->assertEquals(5, $count, "Database should have 5 appointments for the professional");
    }

    /**
     * Test different professionals don't conflict
     * Same time slot for different professionals should work
     */
    public function testDifferentProfessionalsNoConflict(): void
    {
        $professionalSql = "INSERT INTO professionals (establishment_id, name) VALUES (?, 'Dr. Jones')";
        $this->db->execute($professionalSql, [$this->establishmentId]);
        $professional2Id = (int)$this->db->lastInsertId();

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $startTime = "$tomorrow 10:00:00";
        
        $appointment1 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => $startTime,
        ]);

        $appointment2 = $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $professional2Id,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[1],
            'start_at' => $startTime,
        ]);

        $this->assertIsArray($appointment1);
        $this->assertIsArray($appointment2);
        $this->assertNotEquals($appointment1['professional_id'], $appointment2['professional_id']);
        $this->assertEquals($appointment1['start_at'], $appointment2['start_at'], 
            "Same time slot should work for different professionals");
    }

    /**
     * Test invalid datetime format
     * Should throw exception for invalid date format
     */
    public function testInvalidDatetimeFormat(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid start_at datetime');

        $this->appointmentService->create([
            'establishment_id' => $this->establishmentId,
            'professional_id' => $this->professionalId,
            'service_id' => $this->serviceId,
            'user_id' => $this->testUserIds[0],
            'start_at' => 'invalid-date-format',
        ]);
    }
}
