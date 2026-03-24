<?php

namespace QueueMaster\Tests;

use PHPUnit\Framework\TestCase;
use QueueMaster\Models\Business;
use QueueMaster\Models\BusinessUser;
use QueueMaster\Models\QueueAccessCode;
use QueueMaster\Services\QuotaService;
use QueueMaster\Services\PlanService;
use QueueMaster\Models\UserPlanSubscription;
use QueueMaster\Core\Database;

/**
 * BusinessHierarchyTest - Tests for multi-tenant business hierarchy
 * 
 * Tests business creation, user management, quota enforcement,
 * and queue access code validation.
 */
class BusinessHierarchyTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollback();
        }
        parent::tearDown();
    }

    /**
     * Helper to create a test user
     */
    private function createTestUser(string $name, string $email, string $role = 'client'): int
    {
        $sql = "INSERT INTO users (name, email, role) VALUES (?, ?, ?)";
        $this->db->execute($sql, [$name, $email, $role]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Helper to create a test business
     */
    private function createTestBusiness(int $ownerUserId, string $name): int
    {
        $sql = "INSERT INTO businesses (owner_user_id, name) VALUES (?, ?)";
        $this->db->execute($sql, [$ownerUserId, $name]);
        return (int)$this->db->lastInsertId();
    }

    // ========================================================================
    // Business Model Tests
    // ========================================================================

    public function testBusinessCreation(): void
    {
        $userId = $this->createTestUser('Owner', 'owner@test.com', 'manager');
        
        $businessId = Business::create([
            'owner_user_id' => $userId,
            'name' => 'Test Business',
            'slug' => 'test-business',
        ]);

        $this->assertGreaterThan(0, $businessId);

        $business = Business::find($businessId);
        $this->assertNotNull($business);
        $this->assertEquals('Test Business', $business['name']);
        $this->assertEquals('test-business', $business['slug']);
        $this->assertEquals($userId, $business['owner_user_id']);
    }

    public function testBusinessValidationRequiresName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Business::create([
            'owner_user_id' => 1,
            'name' => '', // Empty name
        ]);
    }

    public function testBusinessFindBySlug(): void
    {
        $userId = $this->createTestUser('Owner', 'slug-owner@test.com', 'manager');
        Business::create([
            'owner_user_id' => $userId,
            'name' => 'Slug Business',
            'slug' => 'slug-test',
        ]);

        $found = Business::findBySlug('slug-test');
        $this->assertNotNull($found);
        $this->assertEquals('Slug Business', $found['name']);

        $notFound = Business::findBySlug('nonexistent');
        $this->assertNull($notFound);
    }

    // ========================================================================
    // BusinessUser Model Tests
    // ========================================================================

    public function testBusinessUserAddAndExists(): void
    {
        $ownerId = $this->createTestUser('Owner', 'bu-owner@test.com', 'manager');
        $managerId = $this->createTestUser('Manager', 'bu-manager@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'BU Test Business');

        // Add owner
        $ownerLinkId = BusinessUser::addUser($businessId, $ownerId, 'owner');
        $this->assertGreaterThan(0, $ownerLinkId);
        $this->assertTrue(BusinessUser::exists($businessId, $ownerId));

        // Add manager
        $managerLinkId = BusinessUser::addUser($businessId, $managerId, 'manager');
        $this->assertGreaterThan(0, $managerLinkId);
        $this->assertTrue(BusinessUser::exists($businessId, $managerId));

        // Check roles
        $this->assertEquals('owner', BusinessUser::getRole($businessId, $ownerId));
        $this->assertEquals('manager', BusinessUser::getRole($businessId, $managerId));
    }

    public function testBusinessUserDuplicatePrevented(): void
    {
        $userId = $this->createTestUser('DupUser', 'dup@test.com', 'manager');
        $businessId = $this->createTestBusiness($userId, 'Dup Test Business');

        BusinessUser::addUser($businessId, $userId, 'owner');

        $this->expectException(\InvalidArgumentException::class);
        BusinessUser::addUser($businessId, $userId, 'manager');
    }

    public function testBusinessUserRemove(): void
    {
        $ownerId = $this->createTestUser('RemOwner', 'rem-owner@test.com', 'manager');
        $managerId = $this->createTestUser('RemManager', 'rem-mgr@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'Remove Test');

        BusinessUser::addUser($businessId, $managerId, 'manager');
        $this->assertTrue(BusinessUser::exists($businessId, $managerId));

        BusinessUser::removeUser($businessId, $managerId);
        $this->assertFalse(BusinessUser::exists($businessId, $managerId));
    }

    public function testBusinessUserCountManagers(): void
    {
        $ownerId = $this->createTestUser('CntOwner', 'cnt-owner@test.com', 'manager');
        $mgr1 = $this->createTestUser('Mgr1', 'cnt-mgr1@test.com', 'manager');
        $mgr2 = $this->createTestUser('Mgr2', 'cnt-mgr2@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'Count Test');

        BusinessUser::addUser($businessId, $ownerId, 'owner');
        $this->assertEquals(0, BusinessUser::countManagers($businessId));

        BusinessUser::addUser($businessId, $mgr1, 'manager');
        $this->assertEquals(1, BusinessUser::countManagers($businessId));

        BusinessUser::addUser($businessId, $mgr2, 'manager');
        $this->assertEquals(2, BusinessUser::countManagers($businessId));
    }

    public function testBusinessUserCountManagersIncludesEstablishmentManagers(): void
    {
        $ownerId = $this->createTestUser('EstOwner', 'est-owner@test.com', 'manager');
        $managerId = $this->createTestUser('EstManager', 'est-manager@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'Est Manager Count');

        $this->db->execute(
            "INSERT INTO establishments (name, business_id) VALUES ('Est Count', ?)",
            [$businessId]
        );
        $establishmentId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO establishment_users (establishment_id, user_id, role) VALUES (?, ?, 'manager')",
            [$establishmentId, $managerId]
        );

        $this->assertEquals(1, BusinessUser::countManagers($businessId));
    }

    public function testBusinessUserAccessDenied(): void
    {
        $ownerId = $this->createTestUser('AccessOwner', 'access-owner@test.com', 'manager');
        $outsider = $this->createTestUser('Outsider', 'outsider@test.com', 'client');
        $businessId = $this->createTestBusiness($ownerId, 'Access Test');

        // Outsider should not be in business
        $this->assertFalse(BusinessUser::exists($businessId, $outsider));
        $this->assertNull(BusinessUser::getRole($businessId, $outsider));
    }

    // ========================================================================
    // QueueAccessCode Tests
    // ========================================================================

    public function testQueueAccessCodeGeneration(): void
    {
        $code = QueueAccessCode::generateCode();
        $this->assertEquals(8, strlen($code));
        // Should only contain allowed chars
        $this->assertMatchesRegularExpression('/^[A-Z2-9]+$/', $code);
    }

    public function testQueueAccessCodeCreateAndFind(): void
    {
        $userId = $this->createTestUser('QCode Owner', 'qcode@test.com', 'manager');

        // Create establishment and queue
        $this->db->execute("INSERT INTO establishments (name) VALUES ('QCode Est')");
        $estId = (int)$this->db->lastInsertId();
        
        $this->db->execute("INSERT INTO queues (establishment_id, name, status) VALUES (?, 'QCode Queue', 'open')", [$estId]);
        $queueId = (int)$this->db->lastInsertId();

        $code = QueueAccessCode::generateCode();
        $codeId = QueueAccessCode::create([
            'queue_id' => $queueId,
            'code' => $code,
            'max_uses' => 10,
        ]);

        $this->assertGreaterThan(0, $codeId);

        $found = QueueAccessCode::findByCode($code);
        $this->assertNotNull($found);
        $this->assertEquals($queueId, $found['queue_id']);
        $this->assertEquals(0, $found['uses']);
    }

    public function testQueueAccessCodeValidation(): void
    {
        // Valid code
        $validCode = [
            'is_active' => 1,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'max_uses' => 10,
            'uses' => 5,
        ];
        $this->assertTrue(QueueAccessCode::isValid($validCode));

        // Expired code
        $expiredCode = [
            'is_active' => 1,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'max_uses' => 10,
            'uses' => 5,
        ];
        $this->assertFalse(QueueAccessCode::isValid($expiredCode));

        // Exhausted code
        $exhaustedCode = [
            'is_active' => 1,
            'expires_at' => null,
            'max_uses' => 10,
            'uses' => 10,
        ];
        $this->assertFalse(QueueAccessCode::isValid($exhaustedCode));

        // Inactive code
        $inactiveCode = [
            'is_active' => 0,
            'expires_at' => null,
            'max_uses' => null,
            'uses' => 0,
        ];
        $this->assertFalse(QueueAccessCode::isValid($inactiveCode));

        // Unlimited code (null max_uses, null expires_at)
        $unlimitedCode = [
            'is_active' => 1,
            'expires_at' => null,
            'max_uses' => null,
            'uses' => 999,
        ];
        $this->assertTrue(QueueAccessCode::isValid($unlimitedCode));
    }

    // ========================================================================
    // QuotaService Tests
    // ========================================================================

    public function testQuotaServiceNoSubscriptionAllowed(): void
    {
        // Business with no subscription should be allowed (no limits)
        $userId = $this->createTestUser('QuotaOwner', 'quota@test.com', 'manager');
        $businessId = $this->createTestBusiness($userId, 'Quota Test');

        $result = QuotaService::canCreateEstablishment($businessId);
        $this->assertTrue($result['allowed']);
    }

    public function testQuotaServiceEstablishmentLimit(): void
    {
        $userId = $this->createTestUser('EstLimit', 'estlimit@test.com', 'manager');
        $businessId = $this->createTestBusiness($userId, 'Est Limit Test');
        BusinessUser::addUser($businessId, $userId, 'owner');

        // Create a plan with limit of 1 establishment
        $this->db->execute(
            "INSERT INTO plans (name, max_establishments_per_business) VALUES ('TestPlan', 1)"
        );
        $planId = (int)$this->db->lastInsertId();

        // Create subscription
        $this->db->execute(
            "INSERT INTO user_plan_subscriptions (user_id, plan_id, status, starts_at) VALUES (?, ?, 'active', NOW())",
            [$userId, $planId]
        );

        // First establishment should be allowed
        $result = QuotaService::canCreateEstablishment($businessId);
        $this->assertTrue($result['allowed']);

        // Create one establishment
        $this->db->execute(
            "INSERT INTO establishments (name, business_id) VALUES ('First Est', ?)",
            [$businessId]
        );

        // Second should be blocked
        $result = QuotaService::canCreateEstablishment($businessId);
        $this->assertFalse($result['allowed']);
        $this->assertEquals('quota_exceeded', $result['error']);
    }

    public function testQuotaServiceManagerLimit(): void
    {
        $userId = $this->createTestUser('MgrLimit', 'mgrlimit@test.com', 'manager');
        $businessId = $this->createTestBusiness($userId, 'Mgr Limit Test');
        BusinessUser::addUser($businessId, $userId, 'owner');

        // Create a plan with limit of 1 manager
        $this->db->execute(
            "INSERT INTO plans (name, max_managers) VALUES ('MgrPlan', 1)"
        );
        $planId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO user_plan_subscriptions (user_id, plan_id, status, starts_at) VALUES (?, ?, 'active', NOW())",
            [$userId, $planId]
        );

        // Adding first manager should work
        $result = QuotaService::canAddManager($businessId);
        $this->assertTrue($result['allowed']);

        // Add a manager
        $mgr1 = $this->createTestUser('AddedMgr', 'addedmgr@test.com', 'manager');
        BusinessUser::addUser($businessId, $mgr1, 'manager');

        // Adding second manager should be blocked
        $result = QuotaService::canAddManager($businessId);
        $this->assertFalse($result['allowed']);
        $this->assertEquals('quota_exceeded', $result['error']);
    }

    public function testQuotaServiceUnlimitedPlan(): void
    {
        $userId = $this->createTestUser('Unlimited', 'unlimited@test.com', 'manager');
        $businessId = $this->createTestBusiness($userId, 'Unlimited Test');

        // Premium plan with null limits = unlimited
        $this->db->execute(
            "INSERT INTO plans (name, max_establishments_per_business, max_managers) VALUES ('PremiumTest', NULL, NULL)"
        );
        $planId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO user_plan_subscriptions (user_id, plan_id, status, starts_at) VALUES (?, ?, 'active', NOW())",
            [$userId, $planId]
        );

        $result = QuotaService::canCreateEstablishment($businessId);
        $this->assertTrue($result['allowed']);

        $result = QuotaService::canAddManager($businessId);
        $this->assertTrue($result['allowed']);
    }

    public function testPlanServiceBlocksDowngradeBelowCurrentUsage(): void
    {
        $ownerId = $this->createTestUser('Plan Owner', 'plan-owner@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'Plan Owner Business');
        BusinessUser::addUser($businessId, $ownerId, 'owner');

        $this->db->execute(
            "INSERT INTO establishments (name, business_id) VALUES ('Plan Est', ?)",
            [$businessId]
        );

        $this->db->execute(
            "INSERT INTO plans (name, max_businesses, max_establishments_per_business, max_managers, max_professionals_per_establishment)
             VALUES ('Plan High', 3, 3, 3, 3)"
        );
        $highPlanId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO plans (name, max_businesses, max_establishments_per_business, max_managers, max_professionals_per_establishment)
             VALUES ('Plan Low', 1, 1, 1, 1)"
        );
        $lowPlanId = (int)$this->db->lastInsertId();

        $planService = new PlanService();
        $planService->assignPlanToUser($ownerId, $highPlanId);

        $this->expectException(\RuntimeException::class);
        $planService->assignPlanToUser($ownerId, $lowPlanId);
    }

    public function testPlanServiceBlocksDeletingPlanWithLinkedManagers(): void
    {
        $ownerId = $this->createTestUser('Delete Plan Owner', 'delete-plan-owner@test.com', 'manager');
        $businessId = $this->createTestBusiness($ownerId, 'Delete Plan Business');
        BusinessUser::addUser($businessId, $ownerId, 'owner');

        $this->db->execute(
            "INSERT INTO plans (name, max_businesses) VALUES ('Delete Guard Plan', 1)"
        );
        $planId = (int)$this->db->lastInsertId();

        UserPlanSubscription::create([
            'user_id' => $ownerId,
            'plan_id' => $planId,
            'status' => 'active',
            'starts_at' => date('Y-m-d H:i:s'),
        ]);

        $result = (new PlanService())->canDeletePlan($planId);

        $this->assertFalse($result['allowed']);
        $this->assertSame(1, (int)($result['active_links'] ?? 0));
    }

    public function testPlanServiceAllowsAssigningPlanToApprovedManagerWithoutOwnedBusiness(): void
    {
        $userId = $this->createTestUser('Approved Manager', 'approved-manager@test.com', 'client');
        $this->db->execute(
            "UPDATE users SET manager_access_granted = 1, manager_access_granted_at = NOW(), role = 'manager' WHERE id = ?",
            [$userId]
        );

        $this->db->execute(
            "INSERT INTO plans (name, max_businesses) VALUES ('Approved Manager Plan', 1)"
        );
        $planId = (int)$this->db->lastInsertId();

        $subscription = (new PlanService())->assignPlanToUser($userId, $planId);

        $this->assertNotEmpty($subscription['id'] ?? null);
        $this->assertSame($userId, (int)($subscription['user_id'] ?? 0));
        $this->assertSame($planId, (int)($subscription['plan_id'] ?? 0));
    }

    public function testQuotaServiceBlocksBusinessCreationWithoutActivePlan(): void
    {
        $userId = $this->createTestUser('No Plan Manager', 'no-plan-manager@test.com', 'manager');
        $this->db->execute(
            "UPDATE users SET manager_access_granted = 1, manager_access_granted_at = NOW() WHERE id = ?",
            [$userId]
        );
        $this->db->execute("UPDATE plans SET is_active = 0 WHERE name = 'Free'");

        $result = QuotaService::canCreateBusiness($userId);

        $this->assertFalse($result['allowed']);
        $this->assertSame('plan_required', $result['error']);
    }
}
