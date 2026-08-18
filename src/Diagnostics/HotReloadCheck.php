<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Diagnostics;

use function in_array;
use function is_string;

/**
 * One environment / configuration check for FrankenPHP Hot Reload.
 *
 * @phpstan-type CheckArray array{id: string, label: string, status: string, detail: string, fix: string|null}
 */
final class HotReloadCheck
{
    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_WARN = 'warn';

    public const STATUS_INFO = 'info';

    public const STATUS_SKIP = 'skip';

    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $status,
        private readonly string $detail,
        private readonly ?string $fix = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getFix(): ?string
    {
        return $this->fix;
    }

    public function isPass(): bool
    {
        return $this->status === self::STATUS_PASS;
    }

    public function isFail(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }

    public function isWarn(): bool
    {
        return $this->status === self::STATUS_WARN;
    }

    public function isBlocking(): bool
    {
        return $this->isFail();
    }

    /**
     * @return CheckArray
     */
    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'label'  => $this->label,
            'status' => $this->status,
            'detail' => $this->detail,
            'fix'    => $this->fix,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = (string) ($data['status'] ?? self::STATUS_INFO);
        if (!in_array($status, [self::STATUS_PASS, self::STATUS_FAIL, self::STATUS_WARN, self::STATUS_INFO, self::STATUS_SKIP], true)) {
            $status = self::STATUS_INFO;
        }

        $fix = $data['fix'] ?? null;

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['label'] ?? ''),
            $status,
            (string) ($data['detail'] ?? ''),
            is_string($fix) && $fix !== '' ? $fix : null,
        );
    }
}
