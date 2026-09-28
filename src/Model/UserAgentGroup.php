<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Model;

/**
 * One RFC 9309 group: a set of user agents plus the rules that apply to them.
 *
 * Modelled rather than kept as free text because other bundles contribute rules
 * (a Disallow for the MCP transport, the Sitemap line) and because the Content
 * Signal has to be validated — a typo there is silently ignored by every
 * crawler that reads it.
 */
final readonly class UserAgentGroup
{
    /**
     * @param list<string>  $userAgents    product tokens; ['*'] for the catch-all group
     * @param list<string>  $allow
     * @param list<string>  $disallow
     * @param ?string       $contentSignal e.g. 'ai-train=no, search=yes, ai-input=yes'
     */
    public function __construct(
        public array $userAgents,
        public array $allow = [],
        public array $disallow = [],
        public ?string $contentSignal = null,
        public ?string $comment = null,
    ) {
    }

    public function toRecord(): string
    {
        $lines = [];

        if (null !== $this->comment && '' !== $this->comment) {
            foreach (explode("\n", $this->comment) as $line) {
                $lines[] = '# '.$line;
            }
        }

        foreach ($this->userAgents as $agent) {
            $lines[] = 'User-agent: '.$agent;
        }

        // Content-Signal belongs inside the group it applies to, directly after
        // the User-agent lines.
        if (null !== $this->contentSignal && '' !== $this->contentSignal) {
            $lines[] = 'Content-Signal: '.$this->contentSignal;
        }

        foreach ($this->allow as $path) {
            $lines[] = 'Allow: '.$path;
        }

        foreach ($this->disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        // A group with neither Allow nor Disallow is not a valid RFC 9309 record;
        // "allow everything" has to be spelled out.
        if ([] === $this->allow && [] === $this->disallow) {
            $lines[] = 'Allow: /';
        }

        return implode("\n", $lines);
    }
}
