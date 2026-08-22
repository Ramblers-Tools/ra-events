<?php

/**
 * @version    1.0.0
 * @package    plg_ra_eventscli
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Called by cron to process the outstanding-emails queue for com_ra_events,
 * mirroring plg_ra_mailman's SendemailsCommand.
 */

namespace Ramblers\Plugin\Console\Ra_eventscli\Command;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Console\Command\AbstractCommand;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SendemailsCommand extends AbstractCommand {

    protected static $defaultName = 'ra_events:sendemails';

    private $cliInput;
    private $ioStyle;
    protected $ref;
    private $toolsHelper;

    public function __construct() {
        parent::__construct();
    }

    protected function configure(): void {
        $help = "<info>%command.name%</info> Send outstanding event mailshots
            \nUsage: <info>php %command.full_name%
            \nNo parameters are available</info>";
        $this->setDescription('Called by cron to send outstanding event mailshots.');
        $this->setHelp($help);
//      Set up maximum time of 10 mins (should be parameter in config).
        $max = 10 * 60;
        set_time_limit($max);
    }

    private function configureIO(InputInterface $input, OutputInterface $output) {
        $this->cliInput = $input;
        $this->ioStyle = new SymfonyStyle($input, $output);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $this->configureIO($input, $output);
        $this->ioStyle->comment('Processing started');
        $eventsHelper = new EventsHelper;
        $this->toolsHelper = new ToolsHelper;

        $sql = 'SELECT id, title, emails_outstanding FROM #__ra_events ';
        $sql .= 'WHERE emails_outstanding>0 ORDER BY id';
        $rows = $this->toolsHelper->getRows($sql);
        $this->ref = 0;
        $id = 0;
        foreach ($rows as $row) {
            $id = $row->id;
            $message = 'Sending ' . $row->emails_outstanding . ' emails for event ' . $row->title;
            $this->ioStyle->comment($message);
            $this->logit($message, 1);

            $sql = 'SELECT id FROM #__ra_mail_shots WHERE event_id=' . $id . ' ORDER BY id DESC LIMIT 1';
            $mailshot_id = $this->toolsHelper->getValue($sql);
            if (is_null($mailshot_id)) {
                $this->ioStyle->comment('No mailshot found for event ' . $id . ' - clearing outstanding count');
                continue;
            }
            $this->ref = $mailshot_id;
            $this->ioStyle->comment('Sending emails for Mailshot where id=' . $mailshot_id);
            $eventsHelper->sendEmails($mailshot_id, 'Y');
            foreach ($eventsHelper->messages as $message) {
                $this->ioStyle->comment($message);
            }
        }
        if ($id == 0) {
            $this->ioStyle->comment('No emails outstanding');
            return 1;
        }
        $date = Factory::getDate();
        $message = 'Processing finished ' . HTMLHelper::_('date', $date, 'H:i d/m/y') . ' GMT';
        $this->logit($message, 9);
        $this->ioStyle->comment('Finished');
        return 1;
    }

    public function logit($message, $record_type = '3') {
        $this->toolsHelper->createLog('RA Events', $record_type, $this->ref, $message);
    }

}
