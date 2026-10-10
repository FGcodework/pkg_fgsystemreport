<?php
/**
 * @version    CVS: 1.0.28
 * @package    Com_Fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Fg\Component\Fgsystemreport\Administrator\Pdf;
// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper;
require_once JPATH_ADMINISTRATOR . '/components/com_fgsystemreport/assets/library/TCPDF/library/tcpdf.php';
/**
 * Custom TCPDF subclass with the report header and footer.
 *
 * @since  1.0.28
 */
class FgSystemPDF extends \TCPDF
{
    protected $showDomain;
    protected $showJoomlaVersion;

    public function setShowJoomlaVersion($val)
    {
        $this->showJoomlaVersion = $val;
    }

    public function setShowDomain($val)
    {
        $this->showDomain = $val;
    }

    public function Header()
    {
        $this->SetFont('dejavusanssub', 'B', 12);
        $this->SetXY(15, 10);
        $title = Text::_('COM_FGSYSTEMREPORT_PDF_DETAILED_REPORT_TITLE');

        // Domain line logic
        if ($this->showDomain) {
            // Uri::root() is Joomla's own authoritative site address (it
            // respects the $live_site override in configuration.php when
            // set), unlike $_SERVER['HTTP_HOST']/['REQUEST_URI'], which
            // reflect whatever the client sent and can be spoofed on
            // servers that don't strictly enforce a matching vhost.
            $cleanDomain = rtrim(preg_replace('#^https?://#', '', Uri::root()), '/');

            $title .= ' ' . Text::sprintf('COM_FGSYSTEMREPORT_PDF_OF_DOMAIN', $cleanDomain);
        }

        $this->Cell(0, 6, $title, 0, 1, 'L');

        $this->SetFont('dejavusanssub', '', 9);
        $this->SetX(15);
        $this->Cell(0, 6, Text::sprintf('COM_FGSYSTEMREPORT_PDF_GENERATED_ON', date('d-M-Y H:i')), 0, 1, 'L');
        if ($this->showJoomlaVersion) {
            $version = new \Joomla\CMS\Version();
            $joomlaVersion = $version->getShortVersion();
            $versionLine = Text::sprintf('COM_FGSYSTEMREPORT_PDF_JOOMLA_VERSION_LINE', $joomlaVersion);

            $ownVersion = FgsystemreportHelper::getOwnVersion();
            if ($ownVersion !== '') {
                $versionLine .= '   ' . Text::sprintf('COM_FGSYSTEMREPORT_PDF_COMPONENT_VERSION_LINE', $ownVersion);
            }

            $this->Cell(0, 6, $versionLine, 0, 1, 'L');
        }
        // Line separator
        $this->Line(15, 28, 195, 28);
        $this->Ln(8);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('dejavusanssub', 'I', 8);
        $this->Cell(0, 10, Text::sprintf('COM_FGSYSTEMREPORT_PDF_PAGE_OF', $this->getAliasNumPage(), $this->getAliasNbPages()), 0, 0, 'C');
    }
}
