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

		// Confirmation dialog: a form with data-fg-confirm="message" (optional
		// data-fg-confirm-ok="button label") asks here instead of using the
		// browser's native confirm() box.
		$title  = htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_CONFIRM_TITLE'), ENT_QUOTES, 'UTF-8');
		$cancel = htmlspecialchars(Text::_('JCANCEL'), ENT_QUOTES, 'UTF-8');
		$okDef  = htmlspecialchars(Text::_('COM_FGSYSTEMREPORT_CONFIRM_OK'), ENT_QUOTES, 'UTF-8');

		echo <<<HTML
<style>
#fg-confirm-overlay{position:fixed;inset:0;z-index:20001;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.5);backdrop-filter:blur(2px)}
#fg-confirm-overlay.show{display:flex}
#fg-confirm-overlay .fg-confirm-box{background:var(--body-bg,#fff);color:var(--body-color,#212529);width:100%;max-width:440px;padding:26px 28px 22px;border-radius:14px;border:1px solid rgba(128,128,128,.28);box-shadow:0 18px 60px rgba(0,0,0,.4)}
#fg-confirm-overlay .fg-confirm-head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
#fg-confirm-overlay .fg-confirm-icon{flex:0 0 auto;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(220,53,69,.14);color:#dc3545;font-size:20px;font-weight:800}
#fg-confirm-overlay .fg-confirm-title{margin:0;font-size:1.1rem;font-weight:800;letter-spacing:-.01em}
#fg-confirm-overlay .fg-confirm-msg{margin:0 0 22px;font-size:.95rem;line-height:1.5;opacity:.85}
#fg-confirm-overlay .fg-confirm-actions{display:flex;justify-content:flex-end;gap:10px}
@media (prefers-reduced-motion:no-preference){#fg-confirm-overlay.show .fg-confirm-box{animation:fgConfirmIn .14s ease-out}@keyframes fgConfirmIn{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}}
</style>
<div id="fg-confirm-overlay" role="alertdialog" aria-modal="true" aria-labelledby="fg-confirm-title" aria-describedby="fg-confirm-msg">
	<div class="fg-confirm-box">
		<div class="fg-confirm-head">
			<span class="fg-confirm-icon" aria-hidden="true">!</span>
			<h2 class="fg-confirm-title" id="fg-confirm-title">{$title}</h2>
		</div>
		<p class="fg-confirm-msg" id="fg-confirm-msg"></p>
		<div class="fg-confirm-actions">
			<button type="button" class="btn btn-outline-secondary" id="fg-confirm-cancel">{$cancel}</button>
			<button type="button" class="btn btn-danger" id="fg-confirm-ok">{$okDef}</button>
		</div>
	</div>
</div>
<script>
(function () {
	var ov = document.getElementById('fg-confirm-overlay');
	var okBtn = document.getElementById('fg-confirm-ok');
	var cancelBtn = document.getElementById('fg-confirm-cancel');
	var okDefault = okBtn.textContent;
	var pending = null, opener = null;
	function close() {
		ov.classList.remove('show');
		pending = null;
		if (opener && opener.focus) { opener.focus(); }
	}
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (!f || !f.hasAttribute || !f.hasAttribute('data-fg-confirm') || f.__fgConfirmed) { return; }
		e.preventDefault();
		pending = f;
		opener = document.activeElement;
		document.getElementById('fg-confirm-msg').textContent = f.getAttribute('data-fg-confirm');
		okBtn.textContent = f.getAttribute('data-fg-confirm-ok') || okDefault;
		ov.classList.add('show');
		cancelBtn.focus();
	});
	okBtn.addEventListener('click', function () {
		var f = pending;
		if (!f) { return; }
		f.__fgConfirmed = true;
		ov.classList.remove('show');
		if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }
	});
	cancelBtn.addEventListener('click', close);
	ov.addEventListener('click', function (e) { if (e.target === ov) { close(); } });
	document.addEventListener('keydown', function (e) {
		if (!ov.classList.contains('show')) { return; }
		if (e.key === 'Escape') { e.preventDefault(); close(); }
		if (e.key === 'Tab') {
			e.preventDefault();
			(document.activeElement === okBtn ? cancelBtn : okBtn).focus();
		}
	});
})();
</script>
HTML;
	}
}
