<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\Command;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusRobotsTxt\Command\DumpRobotsTxtCommand;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Writing public/robots.txt is the one operation in this bundle that changes
 * how the shop behaves outside Symfony: the web server answers from disk before
 * the framework is consulted, so a successful write makes the dynamic route
 * permanently unreachable. That is a legitimate deployment choice, and the
 * command exists to make it, but it has to be announced and it has to actually
 * report failure when the file cannot be written.
 */
final class DumpRobotsTxtCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/koh-robots-dump-'.bin2hex(random_bytes(6));
        mkdir($this->projectDir.'/public', 0o777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir.'/public/robots.txt');
        @chmod($this->projectDir.'/public', 0o777);
        @rmdir($this->projectDir.'/public');
        @rmdir($this->projectDir);
    }

    public function testThePolicyIsPrintedByDefault(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('User-agent: *', $tester->getDisplay());
        self::assertStringContainsString('User-agent: GPTBot', $tester->getDisplay());
    }

    /** Printing must never touch the file — that is what --write is for. */
    public function testPrintingDoesNotWriteTheFile(): void
    {
        $this->tester()->execute([]);

        self::assertFileDoesNotExist($this->projectDir.'/public/robots.txt');
    }

    public function testWriteProducesTheSameDocumentOnDisk(): void
    {
        $printed = $this->tester();
        $printed->execute([]);

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--write' => true]));

        self::assertFileExists($this->projectDir.'/public/robots.txt');
        self::assertSame($printed->getDisplay(), file_get_contents($this->projectDir.'/public/robots.txt'));
    }

    /**
     * The write is what makes the dynamic route unreachable. An operator who is
     * not told that will wonder for a long time why configuration changes stop
     * having any effect.
     */
    public function testWritingWarnsThatTheDynamicRouteIsNowShadowed(): void
    {
        $tester = $this->tester();
        $tester->execute(['--write' => true]);

        self::assertStringContainsString('no longer reached', $tester->getDisplay());
        self::assertStringContainsString('Wrote', $tester->getDisplay());
    }

    /** A deploy step that cannot write the file must not report success. */
    public function testAnUnwritableTargetFails(): void
    {
        $tester = $this->tester(projectDir: $this->projectDir.'/does-not-exist');

        self::assertSame(Command::FAILURE, @$tester->execute(['--write' => true]));
        self::assertStringContainsString('Could not write', $tester->getDisplay());
    }

    public function testOneChannelCanBeSelectedByCode(): void
    {
        $channel = $this->channel('WEB_AT');
        $tester = $this->tester(byCode: ['WEB_AT' => $channel], expectedChannel: $channel);

        self::assertSame(Command::SUCCESS, $tester->execute(['--channel' => 'WEB_AT']));
        self::assertStringContainsString('User-agent: *', $tester->getDisplay());
    }

    /**
     * Without --channel the factory picks the channel itself, which is what
     * lets a single-channel shop run the command with no arguments.
     */
    public function testWithoutAChannelTheFactoryDecides(): void
    {
        $tester = $this->tester(expectedChannel: null);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    /** An empty --channel means "let the factory decide", not "look up the empty code". */
    public function testAnEmptyChannelOptionDoesNotTriggerALookup(): void
    {
        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::never())->method('findOneByCode');

        $tester = new CommandTester($this->command($repository, null));

        self::assertSame(Command::SUCCESS, $tester->execute(['--channel' => '']));
    }

    /**
     * A typo in the channel code must fail loudly. This runs from a deploy
     * hook, where a zero exit means "the shop's robots.txt is current".
     */
    public function testAnUnknownChannelCodeFailsAndWritesNothing(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--channel' => 'NOPE', '--write' => true]));
        self::assertStringContainsString('No channel with code "NOPE"', $tester->getDisplay());
        self::assertFileDoesNotExist($this->projectDir.'/public/robots.txt');
    }

    /**
     * @param array<string, ChannelInterface> $byCode
     */
    private function tester(
        array $byCode = [],
        ?string $projectDir = null,
        ?ChannelInterface $expectedChannel = null,
    ): CommandTester {
        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->method('findOneByCode')->willReturnCallback(
            static fn (string $code): ?ChannelInterface => $byCode[$code] ?? null,
        );

        return new CommandTester($this->command($repository, $expectedChannel, $projectDir));
    }

    /** @param ChannelRepositoryInterface<ChannelInterface> $repository */
    private function command(
        ChannelRepositoryInterface $repository,
        ?ChannelInterface $expectedChannel,
        ?string $projectDir = null,
    ): DumpRobotsTxtCommand {
        $factory = $this->createMock(AgentContextFactoryInterface::class);
        $factory->method('create')->willReturnCallback(
            function (?ChannelInterface $channel) use ($expectedChannel): AgentContext {
                self::assertSame($expectedChannel, $channel, 'the command decides which channel is dumped');

                return $this->context($channel ?? $this->channel('WEB_DE'));
            },
        );

        return new DumpRobotsTxtCommand(
            new RobotsTxtGenerator(contentSignal: new ContentSignal()),
            $factory,
            $repository,
            $projectDir ?? $this->projectDir,
        );
    }

    private function context(ChannelInterface $channel): AgentContext
    {
        return new AgentContext($channel, 'https://shop.example.com', 'de_DE', ['de_DE']);
    }

    private function channel(string $code): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);

        return $channel;
    }
}
