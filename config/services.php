<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusAgentContracts\Context\AgentContextFactoryInterface;
use Koh\SyliusAgentContracts\Http\AgentJsonResponder;
use Koh\SyliusRobotsTxt\CacheWarmer\ShadowingFileWarner;
use Koh\SyliusRobotsTxt\Command\DumpRobotsTxtCommand;
use Koh\SyliusRobotsTxt\Controller\RobotsTxtController;
use Koh\SyliusRobotsTxt\EventListener\ContentSignalHeaderContributor;
use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Koh\SyliusRobotsTxt\Model\ContentSignal;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ContentSignal::class)
        ->args([
            param('koh_robots_txt.content_signal.search'),
            param('koh_robots_txt.content_signal.ai_input'),
            param('koh_robots_txt.content_signal.ai_train'),
        ]);

    $services->set(RobotsTxtGenerator::class)
        ->args([
            param('koh_robots_txt.ai_crawlers.agents'),
            param('koh_robots_txt.disallow'),
            param('koh_robots_txt.ai_crawlers.disallow'),
            param('koh_robots_txt.ai_crawlers.allowed'),
            service(ContentSignal::class),
            param('koh_robots_txt.sitemaps'),
            param('koh_robots_txt.extra'),
        ])
        ->public();

    $services->set(RobotsTxtController::class)
        ->args([
            service(RobotsTxtGenerator::class),
            service(AgentContextFactoryInterface::class),
            service(AgentJsonResponder::class),
        ])
        ->public()
        ->tag('controller.service_arguments');

    $services->set(ContentSignalHeaderContributor::class)
        ->args([
            service(ContentSignal::class),
            param('koh_robots_txt.content_signal.response_header'),
        ])
        ->tag('koh_agent.response_header');

    $services->set(DumpRobotsTxtCommand::class)
        ->args([
            service(RobotsTxtGenerator::class),
            service(AgentContextFactoryInterface::class),
            service('sylius.repository.channel'),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(ShadowingFileWarner::class)
        ->args([param('kernel.project_dir'), service('logger')->nullOnInvalid()])
        ->tag('kernel.cache_warmer');
};
