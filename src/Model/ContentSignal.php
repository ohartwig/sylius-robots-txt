<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Model;

/**
 * A Content Signal declaration (contentsignals.org, draft-romm-aipref-contentsignals).
 *
 * Three independent preferences, each yes or no:
 *   search    — may appear in a search index and be linked
 *   ai-input  — may be retrieved at answer time (RAG)
 *   ai-train  — may be used to train a model
 */
final readonly class ContentSignal
{
    public const SIGNALS = ['search', 'ai-input', 'ai-train'];

    public function __construct(
        public bool $search = true,
        public bool $aiInput = true,
        public bool $aiTrain = false,
    ) {
    }

    /**
     * Order follows the published examples: ai-train, search, ai-input.
     */
    public function toDirective(): string
    {
        return \sprintf(
            'ai-train=%s, search=%s, ai-input=%s',
            $this->aiTrain ? 'yes' : 'no',
            $this->search ? 'yes' : 'no',
            $this->aiInput ? 'yes' : 'no',
        );
    }
}
