<?php

namespace App\Exceptions;

use RuntimeException;

class DropshipException extends RuntimeException
{
    public const INVALID_STATUS_TRANSITION = 1001;
    public const ORDER_TERMINAL = 1002;
    public const WAREHOUSE_NOT_ASSIGNED = 1003;
    public const WAREHOUSE_CONFIG_INVALID = 1004;
    public const EMPTY_ITEMS = 1005;
    public const PERMISSION_DENIED = 2001;
    public const WMS_API_ERROR = 3001;
    public const ORDER_NOT_FOUND = 4001;

    protected int $errorCode;

    protected array $context = [];

    public function __construct(
        string $message = '',
        int $code = 0,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $code;
        $this->context = $context;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    public static function invalidStatusTransition(string $from, string $to, array $context = []): self
    {
        return new self(
            sprintf('状态流转不合法：%s -> %s', $from, $to),
            self::INVALID_STATUS_TRANSITION,
            array_merge(['from' => $from, 'to' => $to], $context)
        );
    }

    public static function orderTerminal(string $status, array $context = []): self
    {
        return new self(
            sprintf('当前状态 [%s] 为终态，无法执行该操作', $status),
            self::ORDER_TERMINAL,
            array_merge(['status' => $status], $context)
        );
    }

    public static function warehouseNotAssigned(array $context = []): self
    {
        return new self('未分配海外仓，无法执行该操作', self::WAREHOUSE_NOT_ASSIGNED, $context);
    }

    public static function warehouseConfigInvalid(array $context = []): self
    {
        return new self('海外仓配置不存在或未启用', self::WAREHOUSE_CONFIG_INVALID, $context);
    }

    public static function emptyItems(array $context = []): self
    {
        return new self('代发单至少需要一个商品明细', self::EMPTY_ITEMS, $context);
    }

    public static function permissionDenied(string $action, array $context = []): self
    {
        return new self(
            sprintf('没有权限执行操作：%s', $action),
            self::PERMISSION_DENIED,
            array_merge(['action' => $action], $context)
        );
    }

    public static function wmsApiError(string $provider, string $message, array $context = []): self
    {
        return new self(
            sprintf('WMS[%s]接口调用失败：%s', $provider, $message),
            self::WMS_API_ERROR,
            array_merge(['provider' => $provider, 'message' => $message], $context)
        );
    }

    public static function orderNotFound(string $identifier, array $context = []): self
    {
        return new self(
            sprintf('找不到代发订单：%s', $identifier),
            self::ORDER_NOT_FOUND,
            array_merge(['identifier' => $identifier], $context)
        );
    }
}
