<?php

namespace Tests\Unit\Services;

use App\Services\DropshipPermissionService;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class DropshipPermissionServiceTest extends TestCase
{
    protected DropshipPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DropshipPermissionService();
    }

    protected function createUser(string $role, int $id = 1, array $warehouseIds = []): User
    {
        $user = new class($role, $id, $warehouseIds) extends User {
            public string $roleName;
            public int $userId;
            public array $warehouseList;
            public bool $isAdminFlag;

            public function __construct(string $role, int $id, array $warehouseIds)
            {
                $this->roleName = $role;
                $this->userId = $id;
                $this->warehouseList = $warehouseIds;
                $this->isAdminFlag = $role === 'admin';
                $this->id = $id;
                $this->warehouse_ids = $warehouseIds;
            }

            public function hasRole(string $role): bool
            {
                return $this->roleName === $role;
            }

            public function managesWarehouse(int $warehouseId): bool
            {
                return in_array($warehouseId, $this->warehouseList, true);
            }
        };
        return $user;
    }

    protected function createOrder(int $createdBy = 1, ?int $warehouseId = null): DropshipOrder
    {
        $order = new DropshipOrder();
        $order->created_by = $createdBy;
        $order->warehouse_id = $warehouseId;
        return $order;
    }

    public function test_admin_can_do_everything(): void
    {
        $admin = $this->createUser('admin', 1);
        $actions = [
            DropshipPermissionService::ACTION_VIEW,
            DropshipPermissionService::ACTION_CREATE,
            DropshipPermissionService::ACTION_EDIT,
            DropshipPermissionService::ACTION_DELETE,
            DropshipPermissionService::ACTION_REVIEW,
            DropshipPermissionService::ACTION_PUSH,
            DropshipPermissionService::ACTION_CANCEL,
            DropshipPermissionService::ACTION_UPDATE_STATUS,
            DropshipPermissionService::ACTION_SYNC_TRACKING,
        ];
        foreach ($actions as $action) {
            $this->assertTrue(
                $this->service->can($admin, $action),
                "Admin should be able to {$action}"
            );
        }
    }

    public function test_auditor_can_only_view_and_review(): void
    {
        $auditor = $this->createUser('auditor', 2);
        $this->assertTrue($this->service->canView($auditor));
        $this->assertTrue($this->service->canReview($auditor));
        $this->assertFalse($this->service->canCreate($auditor));
        $this->assertFalse($this->service->canEdit($auditor));
        $this->assertFalse($this->service->canDelete($auditor));
        $this->assertFalse($this->service->canPush($auditor));
        $this->assertFalse($this->service->canCancel($auditor));
    }

    public function test_viewer_can_only_view(): void
    {
        $viewer = $this->createUser('viewer', 3);
        $this->assertTrue($this->service->canView($viewer));
        $this->assertFalse($this->service->canCreate($viewer));
        $this->assertFalse($this->service->canEdit($viewer));
        $this->assertFalse($this->service->canReview($viewer));
        $this->assertFalse($this->service->canPush($viewer));
    }

    public function test_operator_can_view_create_edit_push_sync(): void
    {
        $operator = $this->createUser('operator', 4);
        $this->assertTrue($this->service->canView($operator));
        $this->assertTrue($this->service->canCreate($operator));
        $this->assertTrue($this->service->canEdit($operator));
        $this->assertTrue($this->service->canPush($operator));
        $this->assertTrue($this->service->canSyncTracking($operator));
        $this->assertFalse($this->service->canReview($operator));
        $this->assertFalse($this->service->canDelete($operator));
    }

    public function test_warehouse_manager_has_full_ops_but_no_delete(): void
    {
        $manager = $this->createUser('warehouse_manager', 5);
        $this->assertTrue($this->service->canView($manager));
        $this->assertTrue($this->service->canCreate($manager));
        $this->assertTrue($this->service->canEdit($manager));
        $this->assertTrue($this->service->canReview($manager));
        $this->assertTrue($this->service->canPush($manager));
        $this->assertTrue($this->service->canCancel($manager));
        $this->assertTrue($this->service->canUpdateStatus($manager));
        $this->assertTrue($this->service->canSyncTracking($manager));
        $this->assertFalse($this->service->canDelete($manager));
    }

    public function test_unknown_role_denied(): void
    {
        $unknown = $this->createUser('unknown_role', 99);
        $this->assertFalse($this->service->can($unknown, DropshipPermissionService::ACTION_VIEW));
        $this->assertFalse($this->service->can($unknown, DropshipPermissionService::ACTION_CREATE));
    }

    public function test_data_scope_creator_can_edit_own_order(): void
    {
        $operator = $this->createUser('operator', 10);
        $order = $this->createOrder(10);
        $this->assertTrue($this->service->canEdit($operator, $order));
    }

    public function test_data_scope_non_creator_cannot_edit_others_order(): void
    {
        $operator = $this->createUser('operator', 10);
        $order = $this->createOrder(999);
        $this->assertFalse($this->service->canEdit($operator, $order));
    }

    public function test_data_scope_warehouse_manager_can_edit_their_warehouse(): void
    {
        $manager = $this->createUser('warehouse_manager', 20, [5, 6]);
        $order = $this->createOrder(999, 5);
        $this->assertTrue($this->service->canEdit($manager, $order));
    }

    public function test_data_scope_warehouse_manager_cannot_edit_other_warehouse(): void
    {
        $manager = $this->createUser('warehouse_manager', 20, [5, 6]);
        $order = $this->createOrder(999, 99);
        $this->assertFalse($this->service->canEdit($manager, $order));
    }

    public function test_admin_bypasses_data_scope(): void
    {
        $admin = $this->createUser('admin', 1);
        $order = $this->createOrder(999, 88);
        $this->assertTrue($this->service->canEdit($admin, $order));
        $this->assertTrue($this->service->canDelete($admin, $order));
    }

    public function test_viewer_can_view_any_order(): void
    {
        $viewer = $this->createUser('viewer', 30);
        $order = $this->createOrder(999, 100);
        $this->assertTrue($this->service->canView($viewer, $order));
    }

    public function test_ensureCan_throws_on_denied(): void
    {
        $this->expectException(DropshipException::class);
        $this->expectExceptionCode(DropshipException::PERMISSION_DENIED);
        $viewer = $this->createUser('viewer', 30);
        $this->service->ensureCan($viewer, DropshipPermissionService::ACTION_CREATE);
    }

    public function test_ensureCan_passes_when_allowed(): void
    {
        $admin = $this->createUser('admin', 1);
        $this->service->ensureCan($admin, DropshipPermissionService::ACTION_DELETE);
        $this->addToAssertionCount(1);
    }

    public function test_can_methods_delegate_to_can(): void
    {
        $admin = $this->createUser('admin', 1);
        $this->assertTrue($this->service->canView($admin));
        $this->assertTrue($this->service->canCreate($admin));
        $this->assertTrue($this->service->canEdit($admin));
        $this->assertTrue($this->service->canDelete($admin));
        $this->assertTrue($this->service->canReview($admin));
        $this->assertTrue($this->service->canPush($admin));
        $this->assertTrue($this->service->canCancel($admin));
        $this->assertTrue($this->service->canUpdateStatus($admin));
        $this->assertTrue($this->service->canSyncTracking($admin));
    }
}
