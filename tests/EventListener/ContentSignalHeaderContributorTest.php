<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\EventListener;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusRobotsTxt\EventListener\ContentSignalHeaderContributor;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContentSignalHeaderContributorTest extends TestCase
{
    public function testHtmlPagesGetTheHeader(): void
    {
        $response = $this->html();

        $contributor = new ContentSignalHeaderContributor(new ContentSignal());
        self::assertTrue($contributor->supports(new Request(), $response));

        $contributor->contribute(new Request(), $response, $this->context());
        self::assertSame('ai-train=no, search=yes, ai-input=yes', $response->headers->get('Content-Signal'));
    }

    /**
     * The signal describes a document, not a payload. Putting it on JSON
     * discovery responses would be noise, and those are cached hard.
     */
    public function testNonHtmlResponsesAreSkipped(): void
    {
        $json = new Response('{}', 200, ['Content-Type' => 'application/json']);

        self::assertFalse((new ContentSignalHeaderContributor(new ContentSignal()))->supports(new Request(), $json));
    }

    public function testErrorResponsesAreSkipped(): void
    {
        $error = new Response('', 500, ['Content-Type' => 'text/html']);

        self::assertFalse((new ContentSignalHeaderContributor(new ContentSignal()))->supports(new Request(), $error));
    }

    public function testTheHeaderCanBeTurnedOffWhileRobotsTxtKeepsTheSignal(): void
    {
        $contributor = new ContentSignalHeaderContributor(new ContentSignal(), enabled: false);

        self::assertFalse($contributor->supports(new Request(), $this->html()));
    }

    public function testHeaderMirrorsTheConfiguredPreferences(): void
    {
        $response = $this->html();

        (new ContentSignalHeaderContributor(new ContentSignal(search: false, aiInput: false, aiTrain: false)))
            ->contribute(new Request(), $response, $this->context());

        self::assertSame('ai-train=no, search=no, ai-input=no', $response->headers->get('Content-Signal'));
    }

    private function html(): Response
    {
        return new Response('<html></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function context(): AgentContext
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB_DE');

        return new AgentContext($channel, 'https://shop.example.com', 'de_DE', ['de_DE']);
    }
}
