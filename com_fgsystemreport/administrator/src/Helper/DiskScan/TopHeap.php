<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper\DiskScan;

\defined('_JEXEC') or die;

use SplHeap;

/**
 * Bounded min-heap holding only the top N largest folders found so far
 * (node = [size, path]; the heap's root is always the smallest of the
 * currently-held N, so it's cheap to evict when a larger one shows up).
 * Avoids sorting every scanned folder (O(folders log folders)) when only
 * a handful are actually shown - O(folders log N) instead.
 *
 * Adapted from mod_jm_treesize (Maarten Blokdijk, jmodules.com).
 *
 * @since  1.2.0
 */
final class TopHeap extends SplHeap
{
    protected function compare($value1, $value2): int
    {
        // Reversed compared to a normal max-heap = min-heap (the smallest
        // size is "highest priority", i.e. at the root, so it's evicted
        // first once capacity is exceeded).
        return $value2[0] <=> $value1[0];
    }
}
