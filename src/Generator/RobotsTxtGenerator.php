<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Generator;

use Koh\SyliusAgentContracts\Context\AgentContext;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use Koh\SyliusRobotsTxt\Model\UserAgentGroup;

/**
 * Renders robots.txt for one channel.
 *
 * The AI crawler groups are emitted explicitly rather than folded into the
 * wildcard group: a scanner (and, more to the point, several of the crawlers)
 * only honours a policy that names the product token. A single `User-agent: *`
 * block is not read as a statement about GPTBot.
 */
final class RobotsTxtGenerator
{
    /**
     * The tokens worth naming, as of 2026-07. Deliberately a default rather than
     * a hard-coded list — the set churns and operators need to extend it without
     * waiting for a release.
     *
     * @var list<string>
     */
    public const DEFAULT_AI_CRAWLERS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-Web',
        'anthropic-ai',
        'Google-Extended',
        'Applebot-Extended',
        'Amazonbot',
        'Bytespider',
        'CCBot',
        'PerplexityBot',
        'meta-externalagent',
    ];

    /**
     * @param list<string> $aiCrawlers
     * @param list<string> $defaultDisallow
     * @param list<string> $aiDisallow
     * @param list<string> $sitemaps      absolute URLs or channel-relative paths
     */
    public function __construct(
        private readonly array $aiCrawlers = self::DEFAULT_AI_CRAWLERS,
        private readonly array $defaultDisallow = [],
        private readonly array $aiDisallow = [],
        private readonly bool $aiCrawlersAllowed = true,
        private readonly ?ContentSignal $contentSignal = null,
        private readonly array $sitemaps = ['/sitemap.xml'],
        private readonly string $extra = '',
    ) {
    }

    public function generate(AgentContext $context): string
    {
        $records = [];

        foreach ($this->groups() as $group) {
            $records[] = $group->toRecord();
        }

        $body = implode("\n\n", $records);

        // Sitemap is a non-group directive: it belongs at the end, outside any
        // User-agent record, and must be an absolute URL per the protocol.
        $sitemapLines = [];
        foreach ($this->sitemaps as $sitemap) {
            $sitemapLines[] = 'Sitemap: '.(str_starts_with($sitemap, 'http') ? $sitemap : $context->url($sitemap));
        }

        if ([] !== $sitemapLines) {
            $body .= "\n\n".implode("\n", $sitemapLines);
        }

        if ('' !== trim($this->extra)) {
            $body .= "\n\n".trim($this->extra);
        }

        return $body."\n";
    }

    /** @return list<UserAgentGroup> */
    public function groups(): array
    {
        $signal = $this->contentSignal ?? new ContentSignal();

        $groups = [new UserAgentGroup(
            userAgents: ['*'],
            allow: ['/'],
            disallow: $this->defaultDisallow,
            contentSignal: $signal->toDirective(),
        )];

        if ([] !== $this->aiCrawlers) {
            $groups[] = new UserAgentGroup(
                userAgents: $this->aiCrawlers,
                allow: $this->aiCrawlersAllowed ? ['/'] : [],
                disallow: $this->aiCrawlersAllowed ? $this->aiDisallow : ['/'],
                contentSignal: $signal->toDirective(),
                comment: "AI crawlers, named explicitly.\nA wildcard group is not read as a policy for these agents.",
            );
        }

        return $groups;
    }
}
