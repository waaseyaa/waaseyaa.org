<?php

declare(strict_types=1);

namespace WaaseyaaOrg\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    #[Test]
    public function autoload_wires_framework_kernel(): void
    {
        self::assertTrue(class_exists(\Waaseyaa\Foundation\Kernel\HttpKernel::class));
    }
}
