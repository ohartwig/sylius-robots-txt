<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\CacheWarmer;

use Koh\SyliusRobotsTxt\CacheWarmer\ShadowingFileWarner;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShadowingFileWarnerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/koh-robots-'.bin2hex(random_bytes(6));
        mkdir($this->projectDir.'/public', 0o777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir.'/public/robots.txt');
        @rmdir($this->projectDir.'/public');
        @rmdir($this->projectDir);
    }

    public function testWarnsWhenAStaticFileShadowsTheRoute(): void
    {
        file_put_contents($this->projectDir.'/public/robots.txt', "User-agent: *\n");

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('unreachable'));

        (new ShadowingFileWarner($this->projectDir, $logger))->warmUp('/tmp');
    }

    public function testStaysQuietWhenTheRouteIsReachable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        (new ShadowingFileWarner($this->projectDir, $logger))->warmUp('/tmp');
    }

    /** Optional: a broken warmer must never be able to fail a deploy. */
    public function testIsOptionalAndSurvivesAMissingLogger(): void
    {
        file_put_contents($this->projectDir.'/public/robots.txt', 'x');

        $warner = new ShadowingFileWarner($this->projectDir);

        self::assertTrue($warner->isOptional());
        self::assertSame([], $warner->warmUp('/tmp'));
    }
}
