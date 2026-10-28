<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Read-only tail viewer for Joomla log files. Only plain files with a
 * whitelisted extension directly inside the configured log folder can be
 * opened (no sub-folders, no symlinks, no path traversal).
 *
 * @since  1.8.0
 */
final class LogViewHelper
{
	public const MAX_BYTES = 262144; // last 256 kB of a file are read
	public const MAX_FILES = 200;
	private const EXT      = ['log', 'php', 'txt', 'json'];

	public static function directory(): string
	{
		$configured = (string) Factory::getApplication()->get('log_path', '');
		$dir        = ($configured !== '' && is_dir($configured)) ? $configured : JPATH_ADMINISTRATOR . '/logs';

		return (string) realpath($dir);
	}

	/** @return  array  list of ['name','size','modified'] newest first */
	public static function listFiles(string $dir): array
	{
		if ($dir === '' || !is_dir($dir) || !is_readable($dir)) {
			return [];
		}

		$out = [];

		foreach (new \DirectoryIterator($dir) as $f) {
			if ($f->isDot() || $f->isLink() || !$f->isFile() || !$f->isReadable()) {
				continue;
			}

			if (!in_array(strtolower($f->getExtension()), self::EXT, true) || $f->getFilename() === 'index.html') {
				continue;
			}

			$out[] = ['name' => $f->getFilename(), 'size' => (int) $f->getSize(), 'modified' => (int) $f->getMTime()];
		}

		usort($out, static fn ($a, $b) => ($b['modified'] <=> $a['modified']) ?: strcmp($a['name'], $b['name']));

		return array_slice($out, 0, self::MAX_FILES);
	}

	/**
	 * Last $lines lines (after optional case-insensitive filter) of the last
	 * MAX_BYTES of the file.
	 *
	 * @return  array  ['ok'=>bool,'error'=>string,'lines'=>list<string>,'truncated'=>bool,'matched'=>int]
	 */
	public static function read(string $dir, string $name, int $lines, string $filter = ''): array
	{
		$res = ['ok' => false, 'error' => '', 'lines' => [], 'truncated' => false, 'matched' => 0];
		$ok  = array_column(self::listFiles($dir), null, 'name');

		if ($name === '' || basename($name) !== $name || !isset($ok[$name])) {
			$res['error'] = 'invalid';

			return $res;
		}

		$path = $dir . DIRECTORY_SEPARATOR . $name;
		$size = $ok[$name]['size'];
		$fh   = @fopen($path, 'rb');

		if ($fh === false) {
			$res['error'] = 'unreadable';

			return $res;
		}

		$offset = max(0, $size - self::MAX_BYTES);

		if ($offset > 0) {
			fseek($fh, $offset);
			fgets($fh); // drop the partial first line
			$res['truncated'] = true;
		}

		$raw = (string) stream_get_contents($fh, self::MAX_BYTES);
		fclose($fh);

		$all = preg_split('/\r\n|\n|\r/', $raw) ?: [];

		if ($filter !== '') {
			$all = array_values(array_filter($all, static fn ($l) => stripos($l, $filter) !== false));
		} else {
			$all = array_values(array_filter($all, static fn ($l) => $l !== ''));
		}

		$res['matched'] = count($all);
		$res['lines']   = array_slice($all, -max(1, $lines));
		$res['ok']      = true;

		return $res;
	}
}
