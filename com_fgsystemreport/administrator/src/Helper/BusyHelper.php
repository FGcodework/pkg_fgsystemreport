<?php
/**
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Fg\Component\Fgsystemreport\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * "Working..." overlay for long-running actions (check updates, rescans).
 *
 * Mark a link or submit button with class "fg-busy" and optionally
 * data-fg-busy-text="Text to show". Call BusyHelper::render() once per page.
 */
final class BusyHelper
{
	private static bool $rendered = false;

	public static function render(): void
	{
		if (self::$rendered) {
			return;
		}

		self::$rendered = true;

		$default = json_encode(Text::_('COM_FGSYSTEMREPORT_BUSY_WORKING'));
		$wait    = htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_BUSY_PLEASE_WAIT'), ENT_QUOTES, 'UTF-8');

		echo <<<HTML
<style>
#fg-busy-overlay{position:fixed;inset:0;z-index:20000;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45)}
#fg-busy-overlay.show{display:flex}
#fg-busy-overlay .fg-busy-box{background:var(--body-bg,#fff);color:var(--body-color,#212529);padding:28px 40px;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.35);text-align:center;min-width:260px}
#fg-busy-overlay .spinner-border{width:2.5rem;height:2.5rem}
#fg-busy-overlay .fg-busy-text{margin-top:14px;font-size:16px;font-weight:600}
#fg-busy-overlay .fg-busy-sub{margin-top:4px;font-size:13px;opacity:.7}
</style>
<div id="fg-busy-overlay" role="alertdialog" aria-live="assertive" aria-busy="true">
	<div class="fg-busy-box">
		<div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
		<div class="fg-busy-text" id="fg-busy-text"></div>
		<div class="fg-busy-sub">{$wait}</div>
	</div>
</div>
<script>
(function () {
	var def = {$default};
	var ov = function () { return document.getElementById('fg-busy-overlay'); };
	function show(text) {
		var o = ov();
		if (!o) { return; }
		document.getElementById('fg-busy-text').textContent = text || def;
		o.classList.add('show');
	}
	window.addEventListener('pageshow', function () { var o = ov(); if (o) { o.classList.remove('show'); } });
	document.addEventListener('click', function (e) {
		var el = e.target.closest ? e.target.closest('.fg-busy') : null;
		if (!el || e.defaultPrevented || e.button || e.ctrlKey || e.metaKey || e.shiftKey) { return; }
		if (el.tagName === 'A' && el.target === '_blank') { return; }
		// let the navigation start first, then paint the overlay
		setTimeout(function () { show(el.getAttribute('data-fg-busy-text')); }, 0);
	});
})();
</script>
HTML;
	}
}
