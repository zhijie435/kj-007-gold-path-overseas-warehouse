<?php

namespace App\Services;

use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\User;

class DropshipPermissionService
{
    public const ACTION_VIEW = 'view';
    public const ACTION_CREATE = 'create';
    public const ACTION_EDIT = 'edit';
    public const ACTION_DELETE = 'delete';
    public const ACTION_REVIEW = 'review';
    public const ACTION_PUSH = 'push';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_UPDATE_STATUS = 'update_status';
    public const ACTION_SYNC_TRACKING = 'sync_tracking';

    protected array $rolePermissions = [
        'admin' => [
            self::ACTION_VIEW, self::ACTION_CREATE, self::ACTION_EDIT, self::ACTION_DELETE,
            self::ACTION_REVIEW, self::ACTION_PUSH, self::ACTION_CANCEL,
            self::ACTION_UPDATE_STATUS, self::ACTION_SYNC_TRACKING,
        ],
        'warehouse_manager' => [
            self::ACTION_VIEW, self::ACTION_CREATE, self::ACTION_EDIT,
            self::ACTION_REVIEW, self::ACTION_PUSH, self::ACTION_CANCEL,
            self::ACTION_UPDATE_STATUS, self::ACTION_SYNC_TRACKING,
        ],
        'operator' => [
            self::ACTION_VIEW, self::ACTION_CREATE, self::ACTION_EDIT,
            self::ACTION_PUSH, self::ACTION_SYNC_TRACKING,
        ],
        'auditor' => [
            self::ACTION_VIEW, self::ACTION_REVIEW,
        ],
        'viewer' => [
            self::ACTION_VIEW,
        ],
    ];

    public function can(User $user, string $action, ?DropshipOrder $order = null): bool
    {
        $role = $this->getUserRole($user);

        if (!isset($this->rolePermissions[$role])) {
            return false;
        }

        if (!in_array($action, $this->rolePermissions[$role], true)) {
            return false;
        }

        return $this->checkDataScope($user, $action, $order);
    }

    public function ensureCan(User $user, string $action, ?DropshipOrder $order = null): void
    {
        if (!$this->can($user, $action, $order)) {
            throw DropshipException::permissionDenied($action, [
                'user_id' => $user->id,
                'order_id' => $order?->id,
            ]);
        }
    }

    public function canView(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_VIEW, $order);
    }

    public function canCreate(User $user): bool
    {
        return $this->can($user, self::ACTION_CREATE);
    }

    public function canEdit(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_EDIT, $order);
    }

    public function canDelete(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_DELETE, $order);
    }

    public function canReview(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_REVIEW, $order);
    }

    public function canPush(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_PUSH, $order);
    }

    public function canCancel(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_CANCEL, $order);
    }

    public function canUpdateStatus(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_UPDATE_STATUS, $order);
    }

    public function canSyncTracking(User $user, ?DropshipOrder $order = null): bool
    {
        return $this->can($user, self::ACTION_SYNC_TRACKING, $order);
    }

    protected function getUserRole(User $user): string
    {
        if (method_exists($user, 'hasRole')) {
            foreach (array_keys($this->rolePermissions) as $role) {
                if ($user->hasRole($role)) {
                    return $role;
                }
            }
        }

        if (isset($user->role)) {
            return (string) $user->role;
        }

        if (isset($user->is_admin) && $user->is_admin) {
            return 'admin';
        }

        return 'viewer';
    }

    protected function checkDataScope(User $user, string $action, ?DropshipOrder $order): bool
    {
        if ($order === null) {
            return true;
        }

        $role = $this->getUserRole($user);

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'viewer') {
            return true;
        }

        if ($action === self::ACTION_VIEW) {
            return true;
        }

        if ($order->created_by === $user->id) {
            return true;
        }

        if (isset($order->warehouse_id) && $this->userManagesWarehouse($user, $order->warehouse_id)) {
            return true;
        }

        return false;
    }

    protected function userManagesWarehouse(User $user, int $warehouseId): bool
    {
        if (method_exists($user, 'managesWarehouse')) {
            return $user->managesWarehouse($warehouseId);
        }

        if (isset($user->warehouse_ids) && is_array($user->warehouse_ids)) {
            return in_array($warehouseId, $user->warehouse_ids, true);
        }

        return true;
    }

    public function applyDataScopeQuery($query, User $user): mixed
    {
        $role = $this->getUserRole($user);

        if ($role === 'admin' || $role === 'viewer') {
            return $query;
        }

        if (method_exists($user, 'warehouse_ids') && !empty($user->warehouse_ids)) {
            return $query->whereIn('warehouse_id', $user->warehouse_ids);
        }

        if (method_exists($query, 'where')) {
            return $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id);
                if (isset($user->warehouse_ids) && is_array($user->warehouse_ids)) {
                    $q->orWhereIn('warehouse_id', $user->warehouse_ids);
                }
            });
        }

        return $query;
    }
}
