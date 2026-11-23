<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\SecurityPdfHtml as P;

function pdf_row(string $key, string $status, string $label = '', string $note = ''): object
{
    return (object) ['key' => 'COM_FGSYSTEMREPORT_SEC_' . $key, 'label' => $label ?: $key, 'status' => $status, 'value' => 'v', 'note' => $note];
}

return [
    'the PDF table has the themes of the Security page, in the same order' => function () {
        $html = P::render([
            pdf_row('HSTS', 'ok'),
            pdf_row('JOOMLA_CORE', 'warning'),
            pdf_row('MFA', 'ok'),
        ]);
        $u = strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_UPDATES');
        $a = strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_ACCESS');
        $t = strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_TRANSPORT');
        assertTrue($u !== false && $a !== false && $t !== false);
        assertTrue($u < $a && $a < $t);
        // a theme without checks is not printed
        assertSame(false, strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_SERVER'));
    },

    'every check is printed exactly once' => function () {
        $rows = [pdf_row('JOOMLA_CORE', 'ok', 'LBL_ONE'), pdf_row('SOMETHING_NEW', 'ok', 'LBL_TWO'), pdf_row('MFA', 'info', 'LBL_THREE')];
        $html = P::render($rows);
        foreach (['LBL_ONE', 'LBL_TWO', 'LBL_THREE'] as $l) {
            assertSame(1, substr_count($html, '>' . $l . '<'));
        }
        // an unknown check lands in "other" rather than getting lost
        assertTrue(strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_OTHER') !== false);
    },

    'the theme band counts the checks and the problems' => function () {
        $html = P::render([pdf_row('MFA', 'danger'), pdf_row('SUPERUSERS', 'danger'), pdf_row('FIREWALL', 'warning'), pdf_row('FAILED_LOGINS', 'ok')]);
        assertTrue(strpos($html, '(4)') !== false);
        assertTrue(strpos($html, '2 COM_FGSYSTEMREPORT_SEC_STATUS_DANGER') !== false);
        assertTrue(strpos($html, '1 COM_FGSYSTEMREPORT_SEC_STATUS_WARNING') !== false);
    },

    'a theme with no problems shows no problem counts' => function () {
        $html = P::render([pdf_row('HSTS', 'ok'), pdf_row('HTTPS', 'info')]);
        assertSame(false, strpos($html, '1 COM_FGSYSTEMREPORT_SEC_STATUS_'));
    },

    'text from the checks is escaped' => function () {
        $html = P::render([pdf_row('MFA', 'warning', 'A<b>', '<script>x</script> & "q"')]);
        assertSame(false, strpos($html, '<script>'));
        assertTrue(strpos($html, '&lt;script&gt;') !== false);
        assertTrue(strpos($html, 'A&lt;b&gt;') !== false);
    },

    'an accepted risk is marked like on the Security page' => function () {
        $acc = pdf_row('FIREWALL', 'info');
        $acc->accepted = true;
        $exp = pdf_row('MFA', 'warning');
        $exp->accept_expired = true;
        $html = P::render([$acc, $exp, pdf_row('HSTS', 'ok')]);
        assertSame(1, substr_count($html, 'COM_FGSYSTEMREPORT_ACCEPTED_BADGE'));
        assertSame(1, substr_count($html, 'COM_FGSYSTEMREPORT_ACCEPTED_EXPIRED_BADGE'));
    },

    'no checks, no table body' => function () {
        $html = P::render([]);
        assertSame(false, strpos($html, 'COM_FGSYSTEMREPORT_SECGROUP_'));
    },
    'chunks: one piece per theme, the column heads only in the first' => function () {
        $c = P::chunks([pdf_row('JOOMLA_CORE', 'ok'), pdf_row('MFA', 'ok'), pdf_row('HSTS', 'ok')]);
        assertSame(3, count($c));
        assertTrue(strpos($c[0], 'COM_FGSYSTEMREPORT_SEC_COL_CHECK') !== false);
        assertSame(false, strpos($c[1], 'COM_FGSYSTEMREPORT_SEC_COL_CHECK'));
        assertSame(false, strpos($c[2], 'COM_FGSYSTEMREPORT_SEC_COL_CHECK'));
        assertSame([], P::chunks([]));
    },

    'chunks: the theme band is a repeating head and rows are not split over pages' => function () {
        $c = P::chunks([pdf_row('MFA', 'ok', 'ROW_A'), pdf_row('FIREWALL', 'ok', 'ROW_B')]);
        assertTrue(strpos($c[0], '<thead>') !== false && strpos($c[0], '</thead>') !== false);
        // the band is inside the thead, the rows are after it
        assertTrue(strpos($c[0], 'COM_FGSYSTEMREPORT_SECGROUP_ACCESS') < strpos($c[0], '</thead>'));
        assertTrue(strpos($c[0], '</thead>') < strpos($c[0], 'ROW_A'));
        assertSame(2, substr_count($c[0], '<tr nobr="true">'));
        // a whole theme is not forced onto one page (that left half empty pages)
        assertSame(false, strpos($c[0], '<table cellpadding="4" nobr'));
    },
];
