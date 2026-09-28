<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\EventListener;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusAgentContracts\Provider\ResponseHeaderContributorInterface;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Repeats the Content Signal as a response header on HTML pages.
 *
 * robots.txt states the policy for a crawl; the header states it for the exact
 * resource that was fetched, which is what a retrieval-time agent sees. The two
 * are read by different consumers, so both are emitted from the same
 * configuration.
 *
 * Runs through the shared AgentResponseHeaderListener rather than its own
 * listener — see koh/sylius-agent-contracts for why.
 */
final class ContentSignalHeaderContributor implements ResponseHeaderContributorInterface
{
    public function __construct(
        private readonly ContentSignal $signal,
        private readonly bool $enabled = true,
    ) {
    }

    public function supports(Request $request, Response $response): bool
    {
        return $this->enabled
            && $response->isSuccessful()
            && str_contains($response->headers->get('Content-Type', ''), 'text/html');
    }

    public function contribute(Request $request, Response $response, AgentContext $context): void
    {
        $response->headers->set('Content-Signal', $this->signal->toDirective());
    }
}
