<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Fgsystemreport
 * @author     Fero
 * @copyright  2025 Abdulgani; FG rebrand and modifications (c) 2026 Fero
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationServiceTrait;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Tag\TagServiceTrait;
use Psr\Container\ContainerInterface;

/**
 * Component class for Fgsystemreport
 *
 * @since  1.0.0
 */
class FgsystemreportComponent extends MVCComponent implements RouterServiceInterface, BootableExtensionInterface, CategoryServiceInterface
{
    use AssociationServiceTrait;
    use RouterServiceTrait;
    use HTMLRegistryAwareTrait;
    use CategoryServiceTrait, TagServiceTrait {
        CategoryServiceTrait::getTableNameForSection insteadof TagServiceTrait;
        CategoryServiceTrait::getStateColumnForSection insteadof TagServiceTrait;
    }

    /**
     * Booting the extension. No custom HTML service helpers to register -
     * the only one this component ever had (a "toggle published" icon
     * helper for a list view that was removed back in 1.0.56) was never
     * actually called anywhere, so it was removed rather than renamed
     * during the FG rebrand.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function boot(ContainerInterface $container): void  // ADDED: : void
    {
    }

    /**
     * Returns the table for the count items functions for the given section.
     *
     * @param   string|null  $section  The section
     *
     * @return  string|null
     *
     * @since   4.0.0
     */
    protected function getTableNameForSection(?string $section = null): ?string  // ADDED: : ?string
    {
        return null;  // ADDED: return null
    }
    
    /**
     * Adds Count Items for Category Manager.
     *
     * @param   \stdClass[]  $items    The category objects
     * @param   string       $section  The section
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function countItems(array $items, string $section): void  // ADDED: : void
    {
        // Empty implementation
    }
}
