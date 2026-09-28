<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Tests\Model;

use Koh\SyliusRobotsTxt\Model\UserAgentGroup;
use PHPUnit\Framework\TestCase;

/**
 * RFC 9309 groups are position-sensitive in a way that reads as cosmetic and is
 * not: a Content-Signal line outside the User-agent lines it belongs to applies
 * to nothing, and a group with no rule at all is not a record a parser accepts.
 * The generator only ever builds groups one particular way, so the rules of the
 * format itself are asserted here rather than through it.
 */
final class UserAgentGroupTest extends TestCase
{
    public function testEveryUserAgentGetsItsOwnLine(): void
    {
        $record = (new UserAgentGroup(['GPTBot', 'ClaudeBot'], allow: ['/']))->toRecord();

        self::assertStringContainsString("User-agent: GPTBot\nUser-agent: ClaudeBot\n", $record);
    }

    /** Content-Signal belongs inside the group, directly after the User-agent lines. */
    public function testTheContentSignalFollowsTheUserAgentLinesAndPrecedesTheRules(): void
    {
        $record = (new UserAgentGroup(
            ['*'],
            allow: ['/'],
            disallow: ['/admin'],
            contentSignal: 'ai-train=no, search=yes, ai-input=yes',
        ))->toRecord();

        self::assertSame(
            "User-agent: *\nContent-Signal: ai-train=no, search=yes, ai-input=yes\nAllow: /\nDisallow: /admin",
            $record,
        );
    }

    public function testAGroupWithoutAContentSignalOmitsTheLine(): void
    {
        $record = (new UserAgentGroup(['*'], allow: ['/']))->toRecord();

        self::assertStringNotContainsString('Content-Signal', $record);
    }

    public function testAnEmptyContentSignalIsNotEmitted(): void
    {
        $record = (new UserAgentGroup(['*'], allow: ['/'], contentSignal: ''))->toRecord();

        self::assertStringNotContainsString('Content-Signal', $record);
    }

    /**
     * A group carrying neither Allow nor Disallow is not a valid RFC 9309
     * record. "Allow everything" has to be spelled out, or the whole group is
     * discarded by the parser and the tokens it names fall back to whatever the
     * wildcard group says — the opposite of naming them explicitly.
     */
    public function testAGroupWithNoRulesSpellsOutAllowEverything(): void
    {
        $record = (new UserAgentGroup(['GPTBot']))->toRecord();

        self::assertSame("User-agent: GPTBot\nAllow: /", $record);
    }

    public function testTheFallbackDoesNotFireWhenThereIsAlreadyARule(): void
    {
        self::assertSame(
            "User-agent: GPTBot\nDisallow: /",
            (new UserAgentGroup(['GPTBot'], disallow: ['/']))->toRecord(),
        );

        self::assertSame(1, substr_count((new UserAgentGroup(['GPTBot'], allow: ['/']))->toRecord(), 'Allow: /'));
    }

    /** A multi-line comment has to be commented on every line, or it becomes a directive. */
    public function testEveryLineOfAMultiLineCommentIsPrefixed(): void
    {
        $record = (new UserAgentGroup(
            ['GPTBot'],
            allow: ['/'],
            comment: "AI crawlers, named explicitly.\nA wildcard group is not read as a policy for these agents.",
        ))->toRecord();

        self::assertStringStartsWith(
            "# AI crawlers, named explicitly.\n# A wildcard group is not read as a policy for these agents.\nUser-agent: GPTBot",
            $record,
        );
    }

    public function testAnEmptyCommentAddsNoLine(): void
    {
        self::assertStringStartsWith('User-agent: GPTBot', (new UserAgentGroup(['GPTBot'], allow: ['/'], comment: ''))->toRecord());
    }

    public function testEveryPathGetsItsOwnLine(): void
    {
        $record = (new UserAgentGroup(['*'], allow: ['/'], disallow: ['/admin', '/_mcp']))->toRecord();

        self::assertStringContainsString("Disallow: /admin\nDisallow: /_mcp", $record);
    }
}
