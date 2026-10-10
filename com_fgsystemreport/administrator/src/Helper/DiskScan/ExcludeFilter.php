<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper\DiskScan;

\defined('_JEXEC') or die;

use RecursiveFilterIterator;
use RecursiveIterator;

/**
 * Filter that skips excluded folders (e.g. cache, tmp, logs, .git,
 * node_modules) entirely while walking a directory tree. Excluded folders
 * are never entered - their contents don't count toward the parent
 * folder's size and scanning is faster since nothing inside them is
 * visited at all.
 *
 * Adapted from mod_jm_treesize (Maarten Blokdijk, jmodules.com), which
 * Fero has maintained and improved through many iterations - simplified
 * here for a Joomla-6-only context (the original also supported Joomla
 * 3.x-5, which this component doesn't need to).
 *
 * @since  1.2.0
 */
final class ExcludeFilter extends RecursiveFilterIterator
{
    /**
     * Flipped array (folder name => true) - isset() lookup is O(1),
     * instead of in_array() scanning the whole list on every folder in
     * the tree.
     *
     * @var array
     */
    private array $excludeMap;

    public function __construct(RecursiveIterator $iterator, array $excludeMap)
    {
        parent::__construct($iterator);
        $this->excludeMap = $excludeMap;
    }

    public function accept(): bool
    {
        if ($this->isDir()) {
            // Skip symlinked folders entirely - a symlink pointing back at
            // itself (or a cycle through several symlinks) would otherwise
            // cause infinite recursion.
            if ($this->current()->isLink()) {
                return false;
            }

            return !isset($this->excludeMap[$this->current()->getFilename()]);
        }

        return true;
    }

    public function getChildren(): RecursiveFilterIterator
    {
        return new self($this->getInnerIterator()->getChildren(), $this->excludeMap);
    }
}
