<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests;

use Koh\SyliusRobotsTxt\CacheWarmer\ShadowingFileWarner;
use Koh\SyliusRobotsTxt\Command\DumpRobotsTxtCommand;
use Koh\SyliusRobotsTxt\Controller\RobotsTxtController;
use Koh\SyliusRobotsTxt\EventListener\ContentSignalHeaderContributor;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Koh\SyliusRobotsTxt\KohSyliusRobotsTxtBundle;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * These defaults are a published policy, not internal wiring.
 *
 * Every shop that installs this bundle and configures nothing serves exactly
 * what is declared here: which AI crawler tokens get named, whether they are
 * allowed, and what the shop says about search, retrieval and training. A
 * default that shifts silently changes what every one of those shops declares
 * to the crawlers reading it, and neither the shop nor its operator would see
 * it happen.
 */
final class KohSyliusRobotsTxtBundleTest extends TestCase
{
    public function testTheExtensionIsRegisteredUnderTheDocumentedAlias(): void
    {
        $extension = (new KohSyliusRobotsTxtBundle())->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('koh_robots_txt', $extension->getAlias());
    }

    /** config/ sits at the package root, not under src/, and the imports depend on it. */
    public function testThePathPointsAtThePackageRoot(): void
    {
        self::assertFileExists((new KohSyliusRobotsTxtBundle())->getPath().'/config/services.php');
    }

    /**
     * The default Content Signal says: index this shop and answer from it, but
     * do not train on it. Flipping any of the three changes what every
     * unconfigured installation declares.
     */
    public function testTheDefaultContentSignalIsIndexAndAnswerButDoNotTrain(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getParameter('koh_robots_txt.content_signal.search'));
        self::assertTrue($container->getParameter('koh_robots_txt.content_signal.ai_input'));
        self::assertFalse($container->getParameter('koh_robots_txt.content_signal.ai_train'));
        self::assertTrue($container->getParameter('koh_robots_txt.content_signal.response_header'));
    }

    /** The admin panel and the MCP transport are not crawlable surface. */
    public function testTheDefaultDisallowListCoversTheNonPublicSurface(): void
    {
        self::assertSame(
            ['/admin', '/_mcp', '/mcp/oauth/'],
            $this->load([])->getParameter('koh_robots_txt.disallow'),
        );
    }

    public function testTheRemainingDefaultsAreTheDocumentedOnes(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getParameter('koh_robots_txt.enabled'));
        self::assertSame(['/sitemap.xml'], $container->getParameter('koh_robots_txt.sitemaps'));
        self::assertSame('', $container->getParameter('koh_robots_txt.extra'));
        self::assertTrue($container->getParameter('koh_robots_txt.ai_crawlers.allowed'));
        self::assertSame([], $container->getParameter('koh_robots_txt.ai_crawlers.disallow'));
    }

    /**
     * The token list is a default rather than a hard-coded constant so
     * operators can extend it without waiting for a release — but out of the
     * box it has to be the list the generator publishes.
     */
    public function testTheAiCrawlerTokensDefaultToTheGeneratorsList(): void
    {
        self::assertSame(
            RobotsTxtGenerator::DEFAULT_AI_CRAWLERS,
            $this->load([])->getParameter('koh_robots_txt.ai_crawlers.agents'),
        );
    }

    public function testEveryConfiguredValueReachesTheContainer(): void
    {
        $container = $this->load([
            'enabled' => false,
            'disallow' => ['/checkout'],
            'sitemaps' => ['https://cdn.example.com/sitemap.xml'],
            'extra' => '# hand-written',
            'ai_crawlers' => [
                'allowed' => false,
                'agents' => ['GPTBot'],
                'disallow' => ['/blog'],
            ],
            'content_signal' => [
                'search' => false,
                'ai_input' => false,
                'ai_train' => true,
                'response_header' => false,
            ],
        ]);

        self::assertFalse($container->getParameter('koh_robots_txt.enabled'));
        self::assertSame(['/checkout'], $container->getParameter('koh_robots_txt.disallow'));
        self::assertSame(['https://cdn.example.com/sitemap.xml'], $container->getParameter('koh_robots_txt.sitemaps'));
        self::assertSame('# hand-written', $container->getParameter('koh_robots_txt.extra'));
        self::assertFalse($container->getParameter('koh_robots_txt.ai_crawlers.allowed'));
        self::assertSame(['GPTBot'], $container->getParameter('koh_robots_txt.ai_crawlers.agents'));
        self::assertSame(['/blog'], $container->getParameter('koh_robots_txt.ai_crawlers.disallow'));
        self::assertFalse($container->getParameter('koh_robots_txt.content_signal.search'));
        self::assertFalse($container->getParameter('koh_robots_txt.content_signal.ai_input'));
        self::assertTrue($container->getParameter('koh_robots_txt.content_signal.ai_train'));
        self::assertFalse($container->getParameter('koh_robots_txt.content_signal.response_header'));
    }

    /** An empty list is a decision — "name no crawlers" is not the same as "use the defaults". */
    public function testAnEmptyCrawlerListIsKeptRatherThanReplacedByTheDefault(): void
    {
        self::assertSame([], $this->load(['ai_crawlers' => ['agents' => []]])->getParameter('koh_robots_txt.ai_crawlers.agents'));
    }

    public function testTheServicesTheBundleExistsForAreRegistered(): void
    {
        $container = $this->load([]);

        foreach ([
            ContentSignal::class,
            RobotsTxtGenerator::class,
            RobotsTxtController::class,
            ContentSignalHeaderContributor::class,
            DumpRobotsTxtCommand::class,
            ShadowingFileWarner::class,
        ] as $id) {
            self::assertTrue($container->hasDefinition($id), $id.' must be registered');
        }
    }

    /** The header contributor only runs through the shared listener's tag. */
    public function testTheHeaderContributorIsTagged(): void
    {
        $definition = $this->load([])->getDefinition(ContentSignalHeaderContributor::class);

        self::assertArrayHasKey('koh_agent.response_header', $definition->getTags());
    }

    /** The shadowing check is worthless unless the kernel actually runs it. */
    public function testTheShadowingWarnerIsRegisteredAsACacheWarmer(): void
    {
        $definition = $this->load([])->getDefinition(ShadowingFileWarner::class);

        self::assertArrayHasKey('kernel.cache_warmer', $definition->getTags());
    }

    /** A typo in a configuration key must fail the build, not be ignored. */
    public function testAnUnknownConfigurationKeyIsRejected(): void
    {
        try {
            $this->load(['content_signals' => ['search' => false]]);
        } catch (InvalidConfigurationException $exception) {
            self::assertStringContainsString('content_signals', $exception->getMessage());

            return;
        }

        self::fail('an unrecognised configuration key must be rejected');
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = (new KohSyliusRobotsTxtBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
