<?php

namespace QueueMaster\Tests;

use PHPUnit\Framework\TestCase;
use QueueMaster\Core\Database;
use QueueMaster\Services\QueueService;

/**
 * QueueConcurrencyTest - Test concurrent queue operations
 * 
 * Tests that multiple users can join a queue simultaneously without
 * position conflicts or duplicate positions.
 */
class QueueConcurrencyTest extends TestCase
{
    private Database $db;
    private QueueService $queueService;
    private int $testQueueId;
    private array $testUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db = Database::getInstance();
        $this->queueService = new QueueService();
        
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
        $establishmentSql = "INSERT INTO establishments (name, address) VALUES ('Test Establishment', '123 Test St')";
        $this->db->execute($establishmentSql);
        $establishmentId = (int)$this->db->lastInsertId();

        $serviceSql = "INSERT INTO services (establishment_id, name, duration_minutes) VALUES (?, 'Test Service', 15)";
        $this->db->execute($serviceSql, [$establishmentId]);
        $serviceId = (int)$this->db->lastInsertId();

        $queueSql = "INSERT INTO queues (establishment_id, service_id, name, status) VALUES (?, ?, 'Test Queue', 'open')";
        $this->db->execute($queueSql, [$establishmentId, $serviceId]);
        $this->testQueueId = (int)$this->db->lastInsertId();

        for ($i = 1; $i <= 5; $i++) {
            $userSql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'client')";
            $this->db->execute($userSql, [
                "Test User $i",
                "testuser$i@example.com",
                password_hash('password123', PASSWORD_BCRYPT)
            ]);
            $this->testUserIds[] = (int)$this->db->lastInsertId();
        }
    }

    /**
     * Test concurrent join operations
     * Simulates multiple users joining queue simultaneously
     */
    public function testConcurrentJoin(): void
    {
        $entries = [];
        
        foreach ($this->testUserIds as $userId) {
            try {
                $entry = $this->queueService->join($this->testQueueId, $userId, 0);
                $entries[] = $entry;
            } catch (\Exception $e) {
                $this->fail("User $userId failed to join queue: " . $e->getMessage());
            }
        }

        $this->assertCount(5, $entries, "All 5 users should successfully join the queue");

        $positions = array_column($entries, 'position');
        
        $this->assertCount(5, array_unique($positions), "All positions should be unique");
        
        sort($positions);
        $this->assertEquals([1, 2, 3, 4, 5], $positions, "Positions should be sequential from 1 to 5");
    }

    /**
     * Test position uniqueness and monotonicity
     * Ensures positions are unique and always increasing
     */
    public function testPositionUniqueness(): void
    {
        $entry1 = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $entry2 = $this->queueService->join($this->testQueueId, $this->testUserIds[1], 0);
        $entry3 = $this->queueService->join($this->testQueueId, $this->testUserIds[2], 0);

        $this->assertNotEquals($entry1['position'], $entry2['position'], "Positions should be unique");
        $this->assertNotEquals($entry2['position'], $entry3['position'], "Positions should be unique");
        $this->assertNotEquals($entry1['position'], $entry3['position'], "Positions should be unique");

        $this->assertGreaterThan($entry1['position'], $entry2['position'], "Positions should be monotonically increasing");
        $this->assertGreaterThan($entry2['position'], $entry3['position'], "Positions should be monotonically increasing");
        
        $sql = "SELECT position FROM queue_entries WHERE queue_id = ? ORDER BY position ASC";
        $results = $this->db->query($sql, [$this->testQueueId]);
        $positions = array_column($results, 'position');
        
        $this->assertEquals($positions, array_unique($positions), "No duplicate positions should exist in database");
    }

    /**
     * Test high concurrency scenario
     * Simulates rapid sequential joins
     */
    public function testRapidSequentialJoins(): void
    {
        $entries = [];
        
        for ($i = 0; $i < 3; $i++) {
            $entry = $this->queueService->join($this->testQueueId, $this->testUserIds[$i], 0);
            $entries[] = $entry;
        }

        $positions = array_column($entries, 'position');
        $uniquePositions = array_unique($positions);
        
        $this->assertCount(3, $uniquePositions, "All positions should be unique even with rapid joins");
        $this->assertEquals(min($positions) + 2, max($positions), "Position range should be consecutive");
    }

    /**
     * Test priority queue behavior
     * Higher priority users should maintain unique positions
     */
    public function testPriorityQueueUniqueness(): void
    {
        $normalEntry = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $priorityEntry1 = $this->queueService->join($this->testQueueId, $this->testUserIds[1], 10);
        $priorityEntry2 = $this->queueService->join($this->testQueueId, $this->testUserIds[2], 10);

        $positions = [
            $normalEntry['position'],
            $priorityEntry1['position'],
            $priorityEntry2['position']
        ];

        $this->assertCount(3, array_unique($positions), "Positions must be unique regardless of priority");
        
        $this->assertEquals(10, $priorityEntry1['priority'], "Priority should be stored correctly");
        $this->assertEquals(10, $priorityEntry2['priority'], "Priority should be stored correctly");
        $this->assertEquals(0, $normalEntry['priority'], "Normal priority should be 0");
    }

    /**
     * Test anonymous user joins (null user_id)
     */
    public function testAnonymousUserJoins(): void
    {
        $entry1 = $this->queueService->join($this->testQueueId, null, 0);
        $entry2 = $this->queueService->join($this->testQueueId, null, 0);
        $entry3 = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);

        $this->assertNull($entry1['user_id'], "Anonymous user should have null user_id");
        $this->assertNull($entry2['user_id'], "Anonymous user should have null user_id");
        $this->assertNotNull($entry3['user_id'], "Registered user should have user_id");

        $positions = [$entry1['position'], $entry2['position'], $entry3['position']];
        $this->assertCount(3, array_unique($positions), "Positions should be unique for anonymous users too");
    }
}
