<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt\Command;

use Koh\SyliusAgentContracts\Context\AgentContextFactory;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints robots.txt for a channel, or writes it to public/robots.txt.
 *
 * Useful for two things: seeing what the route will serve without booting a
 * request, and for deployments that prefer a static file (a single-channel shop
 * behind a CDN, say). Writing the file makes the dynamic route unreachable —
 * that is the point, and the command says so.
 */
#[AsCommand(
    name: 'koh:agent:robots:dump',
    description: 'Print or write robots.txt for a channel.',
)]
final class DumpRobotsTxtCommand extends Command
{
    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly RobotsTxtGenerator $generator,
        private readonly AgentContextFactory $contextFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', 'c', InputOption::VALUE_REQUIRED, 'Channel code (default: first enabled channel)')
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Write to public/robots.txt instead of printing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channel = null;
        $code = $input->getOption('channel');
        // VALUE_NONE, so getOption() yields exactly true or false.
        $write = true === $input->getOption('write');
        if (\is_string($code) && '' !== $code) {
            $channel = $this->channelRepository->findOneByCode($code);
            if (!$channel instanceof ChannelInterface) {
                $io->error(\sprintf('No channel with code "%s".', $code));

                return Command::FAILURE;
            }
        }

        $body = $this->generator->generate($this->contextFactory->create($channel));

        if (!$write) {
            $output->write($body);

            return Command::SUCCESS;
        }

        $path = $this->projectDir.'/public/robots.txt';
        if (false === file_put_contents($path, $body)) {
            $io->error(\sprintf('Could not write %s.', $path));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Wrote %s.', $path));
        $io->warning('The web server now answers /robots.txt from disk; the dynamic route is no longer reached. Re-run this command whenever the configuration changes.');

        return Command::SUCCESS;
    }
}
