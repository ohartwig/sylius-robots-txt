<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\Controller;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusRobotsTxt\Controller\RobotsTxtController;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * robots.txt is read by clients that are unforgiving about the envelope: a
 * wrong content type, a missing charset or a body served as anything other than
 * 200 and the policy is simply not applied. The rules themselves are the
 * generator's business; what the controller owes is that they arrive intact and
 * scoped to the right channel.
 */
final class RobotsTxtControllerTest extends TestCase
{
    public function testTheGeneratedPolicyIsServedAsPlainText(): void
    {
        $response = $this->controller()(new Request());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('User-agent: *', (string) $response->getContent());
        self::assertStringContainsString('User-agent: GPTBot', (string) $response->getContent());
    }

    /** The Sitemap line is channel-relative in configuration and absolute on the wire. */
    public function testSitemapUrlsAreResolvedAgainstTheRequestingChannel(): void
    {
        self::assertStringContainsString(
            'Sitemap: https://shop.example.com/sitemap.xml',
            (string) $this->controller()(new Request())->getContent(),
        );

        self::assertStringContainsString(
            'Sitemap: https://shop.example.at/sitemap.xml',
            (string) $this->controller(baseUrl: 'https://shop.example.at')(new Request())->getContent(),
        );
    }

    public function testDiscoveryHeadersAreSet(): void
    {
        $response = $this->controller()(new Request());

        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertNotNull($response->getEtag());
    }

    /**
     * Crawlers re-fetch robots.txt constantly and send If-None-Match when they
     * do. A 304 is much cheaper than rebuilding the document.
     */
    public function testAConditionalRequestIsAnsweredWithNotModified(): void
    {
        $etag = (string) $this->controller()(new Request())->getEtag();

        $conditional = new Request();
        $conditional->headers->set('If-None-Match', $etag);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $this->controller()($conditional)->getStatusCode());
    }

    /**
     * The route is not the place a crawler's own identity is resolved — the
     * context comes from the factory, and the controller passes the request
     * through so the responder can answer conditionally.
     */
    public function testTheContextIsResolvedOncePerRequest(): void
    {
        $factory = $this->createMock(AgentContextFactoryInterface::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturn($this->context('https://shop.example.com'));

        $controller = new RobotsTxtController(
            new RobotsTxtGenerator(contentSignal: new ContentSignal()),
            $factory,
            new AgentJsonResponder(),
        );

        $controller(new Request());
    }

    public function testTheContentSignalReachesTheServedDocument(): void
    {
        $controller = new RobotsTxtController(
            new RobotsTxtGenerator(contentSignal: new ContentSignal(search: false, aiInput: false, aiTrain: false)),
            $this->factory('https://shop.example.com'),
            new AgentJsonResponder(),
        );

        self::assertStringContainsString(
            'Content-Signal: ai-train=no, search=no, ai-input=no',
            (string) $controller(new Request())->getContent(),
        );
    }

    private function controller(string $baseUrl = 'https://shop.example.com'): RobotsTxtController
    {
        return new RobotsTxtController(
            new RobotsTxtGenerator(contentSignal: new ContentSignal()),
            $this->factory($baseUrl),
            new AgentJsonResponder(),
        );
    }

    private function factory(string $baseUrl): AgentContextFactoryInterface
    {
        $factory = $this->createMock(AgentContextFactoryInterface::class);
        $factory->method('create')->willReturn($this->context($baseUrl));

        return $factory;
    }

    private function context(string $baseUrl): AgentContext
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB_DE');

        return new AgentContext($channel, $baseUrl, 'de_DE', ['de_DE']);
    }
}
