<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Small read-only health probes used by the Security Check:
 * key folder permissions and orphaned rows in the assets table.
 *
 * @since  1.7.0
 */
final class SiteHealthHelper
{
	/**
	 * Inspect the key Joomla folders.
	 *
	 * Rules (kept deliberately free of false positives on shared hosting,
	 * where PHP usually owns the files and "writable" is normal):
	 *  - world-writable (o+w) on any inspected folder           -> danger
	 *  - a folder Joomla must write to is missing or read-only   -> warning
	 *
	 * @return  array  list of ['name','perm','status','problem']
	 */
	public static function folderPermissions(): array
	{
		$app    = Factory::getApplication();
		$tmp    = (string) $app->get('tmp_path', '') ?: JPATH_ROOT . '/tmp';
		$logs   = (string) $app->get('log_path', '') ?: JPATH_ADMINISTRATOR . '/logs';
		$root   = rtrim(JPATH_ROOT, '/\\');

		// [path, mustBeWritable]
		$targets = [
			[JPATH_ROOT, false],
			[JPATH_ADMINISTRATOR, false],
			[JPATH_ROOT . '/plugins', false],
			[JPATH_CACHE, true],
			[$tmp, true],
			[$logs, true],
			[JPATH_ROOT . '/images', true],
		];

		$isWindows = stripos(PHP_OS, 'WIN') === 0;
		$rows      = [];

		foreach ($targets as [$path, $mustWrite]) {
			$name = strpos($path, $root) === 0 ? (substr($path, strlen($root)) ?: '/') : $path;
			$row  = ['name' => $name, 'perm' => '', 'status' => 'ok', 'problem' => ''];

			if (!is_dir($path)) {
				if ($mustWrite) {
					$row['status']  = 'warning';
					$row['problem'] = 'missing';
				}

				$rows[] = $row;
				continue;
			}

			$mode        = @fileperms($path);
			$row['perm'] = $mode === false ? '' : substr(sprintf('%o', $mode), -4);

			if (!$isWindows && $mode !== false && ($mode & 0002)) {
				$row['status']  = 'danger';
				$row['problem'] = 'world';
			} elseif ($mustWrite && !is_writable($path)) {
				$row['status']  = 'warning';
				$row['problem'] = 'readonly';
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Count rows in #__assets whose parent_id points to a missing row.
	 */
	public static function orphanAssets(): ?int
	{
		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			return (int) $db->setQuery(
				'SELECT COUNT(*) FROM ' . $db->quoteName('#__assets', 'a')
				. ' LEFT JOIN ' . $db->quoteName('#__assets', 'p') . ' ON p.id = a.parent_id'
				. ' WHERE a.parent_id > 0 AND p.id IS NULL'
			)->loadResult();
		} catch (\Throwable $e) {
			return null;
		}
	}
}
