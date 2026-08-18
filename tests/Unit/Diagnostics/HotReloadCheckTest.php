<?php

declare(strict_types=1);

namespace Nowo\HotReloadBundle\Tests\Unit\Diagnostics;

use Nowo\HotReloadBundle\Diagnostics\HotReloadCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HotReloadCheckTest extends TestCase
{
    #[Test]
    public function itExposesStatusHelpersAndSerializes(): void
    {
        $pass = new HotReloadCheck('id', 'Label', HotReloadCheck::STATUS_PASS, 'ok');
        self::assertTrue($pass->isPass());
        self::assertFalse($pass->isFail());
        self::assertFalse($pass->isWarn());
        self::assertFalse($pass->isBlocking());
        self::assertSame('id', $pass->getId());
        self::assertSame('Label', $pass->getLabel());
        self::assertSame('ok', $pass->getDetail());
        self::assertNull($pass->getFix());

        $fail = new HotReloadCheck('x', 'X', HotReloadCheck::STATUS_FAIL, 'no', 'fix me');
        self::assertTrue($fail->isFail());
        self::assertTrue($fail->isBlocking());
        self::assertSame('fix me', $fail->getFix());

        $warn = new HotReloadCheck('w', 'W', HotReloadCheck::STATUS_WARN, 'maybe');
        self::assertTrue($warn->isWarn());

        $restored = HotReloadCheck::fromArray($fail->toArray());
        self::assertSame($fail->toArray(), $restored->toArray());
    }

    #[Test]
    public function itNormalizesInvalidFromArrayPayloads(): void
    {
        $check = HotReloadCheck::fromArray([
            'status' => 'nope',
            'fix'    => 123,
        ]);

        self::assertSame(HotReloadCheck::STATUS_INFO, $check->getStatus());
        self::assertSame('', $check->getId());
        self::assertSame('', $check->getLabel());
        self::assertSame('', $check->getDetail());
        self::assertNull($check->getFix());
    }
}
