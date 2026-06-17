<?php
/**
 * @package     VPJoomla.Plugin
 * @subpackage  System.Vpseo
 *
 * @copyright   Copyright (C) 2024 - 2026 VPJoomla. All rights reserved.
 * @license     GNU GPL v2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use VPJoomla\Plugin\System\Vpseo\Extension\Vpseo;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Vpseo(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'vpseo')
                );

                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
