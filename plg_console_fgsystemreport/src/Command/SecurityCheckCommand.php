<?php
/**
 * @package    Plg_console_fgsystemreport
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Console\Fgsystemreport\Command;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * fgsystemreport:check - runs the Security Check and reports on it.
 *
 * Exit codes: 0 = OK, 1 = warnings (only with --fail-on=warning),
 * 2 = critical findings (unless --fail-on=never), 3 = the check could not run.
 *
 * @since  1.0.0
 */
class SecurityCheckCommand extends AbstractCommand
{
    private const HELPER  = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\FgsystemreportHelper';
    private const HISTORY = '\\Fg\\Component\\Fgsystemreport\\Administrator\\Helper\\HistoryHelper';

    /**
     * @var  string
     */
    protected static $defaultName = 'fgsystemreport:check';

    public function __construct()
    {
        parent::__construct('fgsystemreport:check');
    }

    protected function configure(): void
    {
        $this->setDescription('Run the FG System Report Security Check');
        $this->setHelp(
            "Runs the full FG System Report Security Check and prints the result.\n\n"
            . "  php cli/joomla.php fgsystemreport:check\n"
            . "  php cli/joomla.php fgsystemreport:check --format=json --fail-on=warning\n\n"
            . "Exit codes: 0 = OK, 1 = warnings (with --fail-on=warning), 2 = critical findings,\n"
            . "3 = the check could not be run.\n\n"
            . "Checks that probe the site over HTTP(S) (SSL certificate, exposed files and\n"
            . "directories) need to know the site address. In the CLI it is taken from\n"
            . "'live_site' in configuration.php; otherwise pass --url=https://example.com."
        );
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
        $this->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Non-zero exit code on: danger, warning or never', 'danger');
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'Site URL for the HTTP-based checks (e.g. https://example.com)');
        $this->addOption('no-record', null, InputOption::VALUE_NONE, 'Do not store this run in the Security History');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $format = strtolower((string) $input->getOption('format'));
        $failOn = strtolower((string) $input->getOption('fail-on'));
        $url    = trim((string) $input->getOption('url'));
        $json   = $format === 'json';

        if (!in_array($format, ['text', 'json'], true) || !in_array($failOn, ['danger', 'warning', 'never'], true)) {
            $output->writeln('<error>Invalid --format (text|json) or --fail-on (danger|warning|never).</error>');

            return 3;
        }

        if (!class_exists(self::HELPER)) {
            $output->writeln('<error>The FG System Report component (com_fgsystemreport) is not installed.</error>');

            return 3;
        }

        if ($url !== '') {
            $this->setSiteUrl($url);
        }

        // Check labels come from the component's language file
        try {
            Factory::getApplication()->getLanguage()->load('com_fgsystemreport', JPATH_ADMINISTRATOR);
        } catch (\Throwable $e) {
            // Labels fall back to the language keys - still usable
        }

        $helper = self::HELPER;

        try {
            $checks = $helper::getSecurityChecks(null, null, true);
        } catch (\Throwable $e) {
            $output->writeln('<error>The Security Check failed: ' . OutputFormatter::escape($e->getMessage()) . '</error>');

            return 3;
        }

        $score    = $helper::calculateSecurityScore($checks);
        $recorded = false;

        if (!$input->getOption('no-record') && class_exists(self::HISTORY)) {
            $history  = self::HISTORY;
            $recorded = $history::record($checks, 'cli') !== null;
            $history::purge();
        }

        if ($json) {
            $out = [
                'score'    => (int) $score->score,
                'danger'   => (int) $score->dangerCount,
                'warning'  => (int) $score->warningCount,
                'recorded' => $recorded,
                'checks'   => array_map(fn ($c) => [
                    'label'  => $this->plain($c->label),
                    'status' => $c->status,
                    'value'  => $this->plain($c->value),
                    'note'   => $this->plain($c->note),
                ], $checks),
            ];

            $output->writeln(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->renderText($output, $checks, $score, $recorded);
        }

        if ($failOn !== 'never' && $score->dangerCount > 0) {
            return 2;
        }

        if ($failOn === 'warning' && $score->warningCount > 0) {
            return 1;
        }

        return 0;
    }

    private function renderText(OutputInterface $output, array $checks, object $score, bool $recorded): void
    {
        $tags = [
            'ok'      => ['OK  ', 'info'],
            'info'    => ['INFO', 'comment'],
            'warning' => ['WARN', 'comment'],
            'danger'  => ['CRIT', 'error'],
        ];

        $output->writeln('');
        $output->writeln('<info>FG System Report - Security Check</info>');
        $output->writeln('');

        foreach ($checks as $check) {
            [$tag, $style] = $tags[$check->status] ?? ['??? ', 'comment'];
            $line = sprintf('[%s] %s', $tag, $this->plain($check->label));
            $val  = $this->plain($check->value);

            if ($val !== '') {
                $line .= ': ' . $val;
            }

            $output->writeln('<' . $style . '>' . OutputFormatter::escape($line) . '</' . $style . '>');

            $note = $this->plain($check->note);

            if ($note !== '' && in_array($check->status, ['warning', 'danger'], true)) {
                $output->writeln('       ' . OutputFormatter::escape($note));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            'Score: %d/100 - %d critical, %d warning(s)%s',
            $score->score,
            $score->dangerCount,
            $score->warningCount,
            $recorded ? ' (recorded in Security History)' : ''
        ));
        $output->writeln('');
    }

    /**
     * Plain text from a possibly HTML-formatted value.
     */
    private function plain($text): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8')));
    }

    /**
     * Makes Uri::root() resolve to the given site address in the CLI, where
     * there is no request to derive it from.
     */
    private function setSiteUrl(string $url): void
    {
        $parts = parse_url($url);

        if (empty($parts['host']) || empty($parts['scheme'])) {
            return;
        }

        $_SERVER['HTTP_HOST']   = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $_SERVER['HTTPS']       = strtolower($parts['scheme']) === 'https' ? 'on' : 'off';
        $_SERVER['SERVER_PORT'] = (string) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));
        $_SERVER['SCRIPT_NAME'] = rtrim($parts['path'] ?? '', '/') . '/index.php';
        $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];

        if (method_exists(Uri::class, 'reset')) {
            Uri::reset();
        }
    }
}
