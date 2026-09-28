<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Koh\SyliusRobotsTxt;

use Koh\SyliusRobotsTxt\Generator\RobotsTxtGenerator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Channel-aware robots.txt with explicit AI crawler rules and Content Signals.
 */
final class KohSyliusRobotsTxtBundle extends AbstractBundle
{
    protected string $extensionAlias = 'koh_robots_txt';

    /** config/ sits at the package root, not under src/. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->arrayNode('disallow')
                    ->info('Paths hidden from every crawler.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['/admin', '/_mcp', '/mcp/oauth/'])
                ->end()
                ->arrayNode('sitemaps')
                    ->info('Absolute URLs, or channel-relative paths that get expanded.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['/sitemap.xml'])
                ->end()
                ->arrayNode('ai_crawlers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allowed')
                            ->defaultTrue()
                            ->info('false emits Disallow: / for every named AI crawler.')
                        ->end()
                        ->arrayNode('agents')
                            ->info('Product tokens to name explicitly. A wildcard group is not read as a policy for these.')
                            ->scalarPrototype()->end()
                            ->defaultValue(RobotsTxtGenerator::DEFAULT_AI_CRAWLERS)
                        ->end()
                        ->arrayNode('disallow')
                            ->info('Extra paths hidden from AI crawlers only.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('content_signal')
                    ->addDefaultsIfNotSet()
                    ->info('contentsignals.org preferences. Defaults say: index and answer from this shop, but do not train on it.')
                    ->children()
                        ->booleanNode('search')->defaultTrue()->end()
                        ->booleanNode('ai_input')->defaultTrue()->end()
                        ->booleanNode('ai_train')->defaultFalse()->end()
                        ->booleanNode('response_header')
                            ->defaultTrue()
                            ->info('Also emit Content-Signal as a response header on HTML pages.')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('extra')
                    ->defaultValue('')
                    ->info('Verbatim text appended to the end of robots.txt.')
                ->end()
            ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $ai = \is_array($config['ai_crawlers'] ?? null) ? $config['ai_crawlers'] : [];
        $signal = \is_array($config['content_signal'] ?? null) ? $config['content_signal'] : [];

        $builder->setParameter('koh_robots_txt.enabled', (bool) ($config['enabled'] ?? true));
        $builder->setParameter('koh_robots_txt.disallow', $config['disallow'] ?? []);
        $builder->setParameter('koh_robots_txt.sitemaps', $config['sitemaps'] ?? []);
        $builder->setParameter('koh_robots_txt.extra', (string) ($config['extra'] ?? ''));
        $builder->setParameter('koh_robots_txt.ai_crawlers.allowed', (bool) ($ai['allowed'] ?? true));
        $builder->setParameter('koh_robots_txt.ai_crawlers.agents', $ai['agents'] ?? []);
        $builder->setParameter('koh_robots_txt.ai_crawlers.disallow', $ai['disallow'] ?? []);
        $builder->setParameter('koh_robots_txt.content_signal.search', (bool) ($signal['search'] ?? true));
        $builder->setParameter('koh_robots_txt.content_signal.ai_input', (bool) ($signal['ai_input'] ?? true));
        $builder->setParameter('koh_robots_txt.content_signal.ai_train', (bool) ($signal['ai_train'] ?? false));
        $builder->setParameter('koh_robots_txt.content_signal.response_header', (bool) ($signal['response_header'] ?? true));

        $container->import('../config/services.php');
    }
}
