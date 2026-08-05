<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Controller;

use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves /robots.txt.
 *
 * Note for deployment: a `public/robots.txt` on disk is served by the web server
 * before Symfony is ever consulted and shadows this route entirely. The bundle
 * warns about that at cache warmup.
 */
final class RobotsTxtController
{
    public function __construct(
        private readonly RobotsTxtGenerator $generator,
        private readonly AgentContextFactoryInterface $contextFactory,
        private readonly AgentJsonResponder $responder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $context = $this->contextFactory->create();

        return $this->responder->respondWithBody(
            $this->generator->generate($context),
            'text/plain',
            request: $request,
        );
    }
}
