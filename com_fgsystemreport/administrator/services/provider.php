<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Fg\Component\Fgsystemreport\Administrator\Extension\FgsystemreportComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The Fgsystemreport service provider.
 *
 * @since  1.0.0
 */
return new class () implements ServiceProviderInterface
{
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void  // ADDED: : void
    {
        $container->registerServiceProvider(new CategoryFactory('\\Fg\\Component\\Fgsystemreport'));
        $container->registerServiceProvider(new MVCFactory('\\Fg\\Component\\Fgsystemreport'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Fg\\Component\\Fgsystemreport'));
        $container->registerServiceProvider(new RouterFactory('\\Fg\\Component\\Fgsystemreport'));

        $container->set(
            ComponentInterface::class,
            function (Container $container)
            {
                $component = new FgsystemreportComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
