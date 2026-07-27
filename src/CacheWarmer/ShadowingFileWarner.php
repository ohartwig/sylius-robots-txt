<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\CacheWarmer;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Warns when a static public/robots.txt shadows this bundle's route.
 *
 * The web server answers from disk before Symfony is consulted, so the bundle
 * appears to do nothing at all — the file is simply never reached. That failure
 * is invisible in the logs and survives every deploy, which makes it worth an
 * explicit warning at warmup rather than a line in the README.
 *
 * Not a hard failure: some deployments intentionally dump a static file (see
 * koh:agent:robots:dump).
 */
final class ShadowingFileWarner implements CacheWarmerInterface
{
    public function __construct(
        private readonly string $projectDir,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    /** @return array<int, string> */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $path = $this->projectDir.'/public/robots.txt';

        if (is_file($path)) {
            $this->logger?->warning(
                'public/robots.txt exists and is served by the web server before Symfony runs. '
                .'The dynamic /robots.txt route from koh/sylius-robots-txt is unreachable while it is there. '
                .'Delete the file, or keep it in sync with `bin/console koh:agent:robots:dump`.',
                ['path' => $path],
            );
        }

        return [];
    }
}
