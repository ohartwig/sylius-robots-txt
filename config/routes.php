<?php

// SPDX-FileCopyrightText: 2026 Kai Ole Hartwig <mail@ole-hartwig.eu>
// SPDX-License-Identifier: MIT

declare(strict_types=1);

use Koh\SyliusRobotsTxt\Controller\RobotsTxtController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // No _locale prefix: robots.txt lives at the origin root. Sylius' shop
    // routes are imported under /{_locale} with a locale requirement that no
    // segment containing a dot can satisfy, so there is no collision.
    $routes->add('koh_robots_txt', '/robots.txt')
        ->controller(RobotsTxtController::class)
        ->methods(['GET', 'HEAD']);
};
