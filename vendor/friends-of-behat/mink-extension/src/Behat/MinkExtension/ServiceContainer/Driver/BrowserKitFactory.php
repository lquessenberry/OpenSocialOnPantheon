<?php

/*
 * This file is part of the Behat MinkExtension.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\MinkExtension\ServiceContainer\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;


class BrowserKitFactory implements DriverFactory
{
    /**
     * {@inheritdoc}
     */
    public function getDriverName()
    {
        return 'browserkit_http';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsJavascript()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildDriver(array $config)
    {
        if (!class_exists(BrowserKitDriver::class)) {
            throw new \RuntimeException('Install behat/mink-browserkit-driver in order to use the browserkit_http driver.');
        }
        if (!class_exists(HttpClient::class)) {
            throw new \RuntimeException(sprintf('Class %s not found, did you install symfony/http-client?', HttpClient::class));
        }
        if (!class_exists(HttpBrowser::class)) {
            throw new \RuntimeException(sprintf('Class %s not found, did you install symfony/browser-kit 4.4+?', HttpBrowser::class));
        }

        return new Definition(BrowserKitDriver::class, [
            new Definition(HttpBrowser::class),
            '%mink.base_url%',
        ]);
    }

}
