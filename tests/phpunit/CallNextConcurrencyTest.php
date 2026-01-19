<?php

namespace QueueMaster\Tests;

use PHPUnit\Framework\TestCase;
use QueueMaster\Core\Database;
use QueueMaster\Services\QueueService;

/**
 * CallNextConcurrencyTest - Test concurrent call-next operations
 * 
 * Tests that when multiple attendants call next simultaneously,
 * only one entry is called and no duplicates occur.
 */
class CallNextConcurrencyTest extends TestCase
{
    private Database $db;
    private QueueService $queueService;
    private int $testQueueId;
    private int $establishmentId;
    private int $professionalId;
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
        $this->establishmentId = (int)$this->db->lastInsertId();

        $serviceSql = "INSERT INTO services (establishment_id, name, duration_minutes) VALUES (?, 'Test Service', 15)";
        $this->db->execute($serviceSql, [$this->establishmentId]);
        $serviceId = (int)$this->db->lastInsertId();

        $professionalSql = "INSERT INTO professionals (establishment_id, name) VALUES (?, 'Test Professional')";
        $this->db->execute($professionalSql, [$this->establishmentId]);
        $this->professionalId = (int)$this->db->lastInsertId();

        $queueSql = "INSERT INTO queues (establishment_id, service_id, name, status) VALUES (?, ?, 'Test Queue', 'open')";
        $this->db->execute($queueSql, [$this->establishmentId, $serviceId]);
        $this->testQueueId = (int)$this->db->lastInsertId();

        for ($i = 1; $i <= 3; $i++) {
            $userSql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'client')";
            $this->db->execute($userSql, [
                "Test User $i",
                "callnext_user$i@example.com",
                password_hash('password123', PASSWORD_BCRYPT)
            ]);
            $this->testUserIds[] = (int)$this->db->lastInsertId();
        }
    }

    /**
     * Test concurrent call-next operations
     * Simulates two attendants calling next simultaneously
     */
    public function testConcurrentCallNext(): void
    {
        $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $this->queueService->join($this->testQueueId, $this->testUserIds[1], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $result1 = $this->queueService->callNext($this->testQueueId);
        
        $this->assertNotNull($result1, "First call should return an entry");
        $this->assertEquals('queue_entry', $result1['type'], "Should return a queue entry");
        $this->assertArrayHasKey('data', $result1);
        
        $calledId1 = $result1['data']['id'];

        $sql = "SELECT COUNT(*) as count FROM queue_entries WHERE queue_id = ? AND status = 'called'";
        $result = $this->db->query($sql, [$this->testQueueId]);
        $calledCount = (int)$result[0]['count'];
        
        $this->assertEquals(1, $calledCount, "Only one entry should be in 'called' status after first call");

        $result2 = $this->queueService->callNext($this->testQueueId);
        
        $this->assertNotNull($result2, "Second call should return the next entry");
        $calledId2 = $result2['data']['id'];

        $this->assertNotEquals($calledId1, $calledId2, "Different entries should be called");

        $result = $this->db->query($sql, [$this->testQueueId]);
        $calledCount = (int)$result[0]['count'];
        
        $this->assertEquals(2, $calledCount, "Two entries should be in 'called' status after second call");
    }

    /**
     * Test only one wins in concurrent scenario
     * Ensures no duplicate calls occur
     */
    public function testOnlyOneWins(): void
    {
        $entry1 = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $entry2 = $this->queueService->join($this->testQueueId, $this->testUserIds[1], 0);
        $entry3 = $this->queueService->join($this->testQueueId, $this->testUserIds[2], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $firstCall = $this->queueService->callNext($this->testQueueId);
        
        $this->assertNotNull($firstCall, "First call should succeed");
        
        $sql = "SELECT id, status FROM queue_entries WHERE queue_id = ? AND status = 'called'";
        $calledEntries = $this->db->query($sql, [$this->testQueueId]);
        
        $this->assertCount(1, $calledEntries, "Exactly one entry should be called");
        
        $calledId = (int)$calledEntries[0]['id'];
        $this->assertContains($calledId, [$entry1['id'], $entry2['id'], $entry3['id']], 
            "Called entry should be one of the waiting entries");

        $waitingSql = "SELECT COUNT(*) as count FROM queue_entries WHERE queue_id = ? AND status = 'waiting'";
        $waitingResult = $this->db->query($waitingSql, [$this->testQueueId]);
        $waitingCount = (int)$waitingResult[0]['count'];
        
        $this->assertEquals(2, $waitingCount, "Two entries should still be waiting");
    }

    /**
     * Test transaction rollback protection
     * Ensures that failed transactions don't leave inconsistent state
     */
    public function testTransactionProtection(): void
    {
        $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $result = $this->queueService->callNext($this->testQueueId);
        $this->assertNotNull($result, "Should successfully call an entry");

        $this->db->rollback();
        $this->db->beginTransaction();

        $sql = "SELECT COUNT(*) as count FROM queue_entries WHERE queue_id = ? AND status = 'called'";
        $result = $this->db->query($sql, [$this->testQueueId]);
        $calledCount = (int)$result[0]['count'];
        
        $this->assertEquals(0, $calledCount, "Rollback should revert the call status");
    }

    /**
     * Test empty queue behavior
     * Ensures callNext returns null when queue is empty
     */
    public function testEmptyQueueCallNext(): void
    {
        $result = $this->queueService->callNext($this->testQueueId);
        
        $this->assertNull($result, "Should return null when queue is empty");
    }

    /**
     * Test priority ordering in callNext
     * Higher priority entries should be called first
     */
    public function testCallNextWithPriority(): void
    {
        $normalEntry = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $priorityEntry = $this->queueService->join($this->testQueueId, $this->testUserIds[1], 10);
        $anotherNormalEntry = $this->queueService->join($this->testQueueId, $this->testUserIds[2], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $result = $this->queueService->callNext($this->testQueueId);
        
        $this->assertNotNull($result);
        $this->assertEquals($priorityEntry['id'], $result['data']['id'], 
            "Priority entry should be called first");
        $this->assertEquals(10, $result['data']['priority'], "Priority value should be preserved");
    }

    /**
     * Test sequential callNext operations
     * Ensures proper ordering when calling multiple times
     */
    public function testSequentialCallNext(): void
    {
        $entry1 = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);
        $entry2 = $this->queueService->join($this->testQueueId, $this->testUserIds[1], 0);
        $entry3 = $this->queueService->join($this->testQueueId, $this->testUserIds[2], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $call1 = $this->queueService->callNext($this->testQueueId);
        $call2 = $this->queueService->callNext($this->testQueueId);
        $call3 = $this->queueService->callNext($this->testQueueId);

        $calledIds = [
            $call1['data']['id'],
            $call2['data']['id'],
            $call3['data']['id']
        ];

        $this->assertCount(3, array_unique($calledIds), "All three calls should return different entries");
        
        $call4 = $this->queueService->callNext($this->testQueueId);
        $this->assertNull($call4, "Fourth call should return null as queue is now empty");
    }

    /**
     * Test FOR UPDATE locking mechanism
     * Ensures database properly locks rows during callNext
     */
    public function testForUpdateLocking(): void
    {
        $entry = $this->queueService->join($this->testQueueId, $this->testUserIds[0], 0);

        $this->db->commit();
        $this->db->beginTransaction();

        $sql = "SELECT * FROM queue_entries WHERE queue_id = ? AND status = 'waiting' FOR UPDATE";
        $lockedEntries = $this->db->query($sql, [$this->testQueueId]);
        
        $this->assertCount(1, $lockedEntries, "Should lock exactly one waiting entry");
        $this->assertEquals($entry['id'], $lockedEntries[0]['id'], "Locked entry should match the created entry");
    }
}
