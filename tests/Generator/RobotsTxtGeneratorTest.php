<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\Generator;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;

final class RobotsTxtGeneratorTest extends TestCase
{
    /**
     * Every one of these tokens has to appear as its own User-agent line.
     * A wildcard group is not read as a policy for them.
     */
    #[DataProvider('requiredAiTokens')]
    public function testAiCrawlersAreNamedExplicitly(string $token): void
    {
        $body = (new RobotsTxtGenerator())->generate($this->context());

        self::assertStringContainsString('User-agent: '.$token, $body);
    }

    /** @return iterable<string, array{string}> */
    public static function requiredAiTokens(): iterable
    {
        foreach (['GPTBot', 'OAI-SearchBot', 'Claude-Web', 'Google-Extended', 'Amazonbot', 'anthropic-ai', 'Bytespider', 'CCBot', 'Applebot-Extended'] as $t) {
            yield $t => [$t];
        }
    }

    public function testWildcardGroupCarriesAllowAndContentSignal(): void
    {
        $body = (new RobotsTxtGenerator())->generate($this->context());

        self::assertStringContainsString("User-agent: *\nContent-Signal: ai-train=no, search=yes, ai-input=yes\nAllow: /", $body);
    }

    /** Sitemap is a non-group directive and must be an absolute URL. */
    public function testSitemapIsAbsoluteAndChannelScoped(): void
    {
        $body = (new RobotsTxtGenerator(sitemaps: ['/sitemap.xml']))->generate($this->context());

        self::assertStringContainsString('Sitemap: https://shop.example.com/sitemap.xml', $body);
    }

    public function testAbsoluteSitemapUrlsPassThroughUnchanged(): void
    {
        $body = (new RobotsTxtGenerator(sitemaps: ['https://cdn.example.com/sitemap.xml']))->generate($this->context());

        self::assertStringContainsString('Sitemap: https://cdn.example.com/sitemap.xml', $body);
        self::assertStringNotContainsString('shop.example.com/https', $body);
    }

    public function testDisallowingAiCrawlersEmitsDisallowAll(): void
    {
        $body = (new RobotsTxtGenerator(aiCrawlers: ['GPTBot'], aiCrawlersAllowed: false))->generate($this->context());

        self::assertMatchesRegularExpression('/User-agent: GPTBot\n.*\nDisallow: \/$/m', $body);
        self::assertStringNotContainsString("User-agent: GPTBot\nAllow: /", $body);
    }

    public function testContentSignalReflectsConfiguration(): void
    {
        $body = (new RobotsTxtGenerator(contentSignal: new ContentSignal(search: true, aiInput: false, aiTrain: false)))
            ->generate($this->context());

        self::assertStringContainsString('Content-Signal: ai-train=no, search=yes, ai-input=no', $body);
    }

    /**
     * A group with no rule at all is not a valid RFC 9309 record — "allow
     * everything" has to be spelled out.
     */
    public function testGroupsAlwaysCarryAtLeastOneRule(): void
    {
        foreach ((new RobotsTxtGenerator())->groups() as $group) {
            self::assertMatchesRegularExpression('/^(Allow|Disallow): /m', $group->toRecord());
        }
    }

    public function testSharedPathsAreHiddenFromEveryCrawler(): void
    {
        $body = (new RobotsTxtGenerator(defaultDisallow: ['/admin', '/_mcp']))->generate($this->context());

        self::assertStringContainsString('Disallow: /admin', $body);
        self::assertStringContainsString('Disallow: /_mcp', $body);
    }

    public function testFileEndsWithASingleNewline(): void
    {
        $body = (new RobotsTxtGenerator())->generate($this->context());

        self::assertSame("\n", substr($body, -1));
        self::assertNotSame("\n\n", substr($body, -2));
    }

    private function context(): AgentContext
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB_DE');

        return new AgentContext($channel, 'https://shop.example.com', 'de_DE', ['de_DE']);
    }
}
