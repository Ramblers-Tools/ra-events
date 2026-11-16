<?php

/**
 * 21/03/25 CB display proper message on success
 * 30/06/25 CB extractBookings
 * 12/10/25 CB bookingReports
 * 22/10/25 CB sort by custom fields
 * 03/11/25 CB for custom sort, also sort by name
 * 21/11/25 CB show special requests in CSV
 * 25/02/26 CB showTerms (invoked from link in confirmation email)
 * 02/03/26 CB use preferred_name in reports, not name
 * 17/06/26 CB include booked date in the CSV output
 * 09/11/26 CB Allow callback to booking reports
 * 16/11/26 CB replaced bookingReports with booking_reports.showMenu, change extract to CSV 
 */
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\BaseController;
//use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_events\Site\Helpers\IceHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Event class.
 *
 * @since  1.6.0
 */
class EventController extends BaseController {

    protected $db;
    protected $app;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
// Import CSS
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  void
     *
     * @since   1.0.1
     *
     * @throws  Exception
     */
    public function edit() {
        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.event.id');
        $editId = $this->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.event.id', $editId);

        // Get the model.
        $model = $this->getModel('Event', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit', false));
    }

    public function extractBookings() {
        $event_id = $this->app->input->getInt('id', '0');
        $bookingHelper = new BookingHelper;
        $csvData = $bookingHelper->extractBookings($event_id);

        $this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $this->app->setHeader(
            'Content-Disposition',
            'attachment; filename="bookings_' . date('Y-m-d_H-i-s') . '.csv"',
            true
        );
        $this->app->sendHeaders();
        echo $csvData;
        $this->app->close();
    }

    /**
     * Method to save data
     *
     * @return    void
     *
     * @throws  Exception
     * @since   1.0.1
     */
    public function publish() {
        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.edit', 'com_ra_events') || $user->authorise('core.edit.state', 'com_ra_events')) {
            $model = $this->getModel('Event', 'Site');

            // Get the user data.
            $id = $this->input->getInt('id');
            $state = $this->input->getInt('state');

            // Attempt to save the data.
            $return = $model->publish($id, $state);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::sprintf('Save failed: %s', $model->getError()), 'warning');
            }

            // Clear the booking id from the session.
            $this->app->setUserState('com_ra_events.edit.event.id', null);

            // Flush the data from the session.
            $this->app->setUserState('com_ra_events.edit.event.data', null);

            // Redirect to the list screen.
//			$this->setMessage(Text::_('COM_RA_EVENTS_ITEM_SAVED_SUCCESSFULLY'));
            $this->app->enqueueMessage('Event updated successfully', 'info');
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();

            if (!$item) {
                // If there isn't any menu item active, redirect to list view
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=events', false));
            } else {
                $this->setRedirect(Route::_('index.php?Itemid=' . $item->id, false));
            }
        } else {
            throw new \Exception(500);
        }
    }

    /**
     * Check in record
     *
     * @return  boolean  True on success
     *
     * @since   1.0.1
     */
    public function checkin() {
        // Check for request forgeries.
        $this->checkToken('GET');

        $id = $this->input->getInt('id', 0);
        $model = $this->getModel();
        $item = $model->getItem($id);

        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.manage', 'com_ra_events') || $item->checked_out == $user->id) {

            $return = $model->checkin($id);

            if ($return === false) {
                // Checkin failed.
                $message = Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError());
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event' . '&id=' . $id, false), $message, 'error');
                return false;
            } else {
                // Checkin succeeded.
                $message = Text::_('COM_RA_EVENTS_CHECKEDIN_SUCCESSFULLY');
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event' . '&id=' . $id, false), $message);
                return true;
            }
        } else {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public function registerEmails() {
        $event_id = $this->app->input->getInt('id', '0');
        $mailshot_id = $this->app->input->getInt('mailshot_id', '0');
        $eventsHelper = new EventsHelper;
        $eventsHelper->sendEmails($mailshot_id);
        Factory::getApplication()->enqueueMessage('Message queued for sending', 'info');
        // Redirect to the display screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event&id=' . $event_id, false));
    }

    public function cancelSending() {
        $event_id = $this->app->input->getInt('id', '0');
        $eventsHelper = new EventsHelper;
        $eventsHelper->cancelSending($event_id);
        Factory::getApplication()->enqueueMessage('Sending cancelled', 'info');
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event&id=' . $event_id, false));
    }

    /**
     * Remove data
     *
     * @return void
     *
     * @throws Exception
     */
    public function remove() {
        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.delete', 'com_ra_events')) {
            $model = $this->getModel('Event', 'Site');

            // Get the user data.
            $id = $this->input->getInt('id', 0);

            // Attempt to save the data.
            $return = $model->delete($id);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::sprintf('Delete failed', $model->getError()), 'warning');
            } else {
                // Check in the booking.
                if ($return) {
                    $model->checkin($return);
                }

                $this->app->setUserState('com_ra_events.edit.event.id', null);
                $this->app->setUserState('com_ra_events.edit.event.data', null);

                $this->app->enqueueMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_SUCCESSFULLY'), 'success');
                $this->app->redirect(Route::_('index.php?option=com_ra_events&view=events', false));
            }

            // Redirect to the list screen.
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();
            $this->setRedirect(Route::_($item->link, false));
        } else {
            throw new \Exception(500);
        }
    }

    public function showEmail() {
        /*
         * This can be invoked both view Myemails, view profileform/ layout=bookings
         * and from the task event.showEmails
         * In each case it must return to the same place, passing the appropriate parameters
         */
        $id = $this->app->input->getInt('id', '0');
        $ref_id = $this->app->input->getInt('ref_id', '0');
        $callback = $this->app->input->getAlnum('callback', '');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        echo '<h2>Email ' . $id . '</h2>';
        $this->toolsHelper->showEmail($id);

        $back = 'index.php?option=com_ra_events&Itemid=' . $menu_id;
        if ($callback == 'bookings') {
            $back .= '&task=booking_reports.emails&id=' . $ref_id;
        } elseif ($callback == 'myemails') {
            $back .= '&view=myemails&user_id=' . $ref_id;
        } elseif ($callback == 'profileform') {
            $back .= '&task=event.showEmails&id=' . $ref_id;
            $back .= '&callback=profileform';
        } else {
            $back .= '&task=event.showEmails&id=' . $ref_id;
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function showEmails() {
        /*
         * Invoked from BookingHelper->showBookings, which in turn can be invoked from:
         *     administrator/tmpl/event/default.php
         *     administrator/tmpl/event/edit.php
         *     tmpl/event/default.php
         * However, for the first two, buttons are not shown, so this ALWAYS returns to event/default
         */

        $event_id = $this->app->input->getInt('id', '0');
        $callback = $this->app->input->getAlnum('callback', '0');

        $sql = 'SELECT title ';
        $sql .= 'FROM #__ra_events WHERE id=' . $event_id;
        $title = $this->toolsHelper->getValue($sql);
        echo '<h2>Emails for ' . $title . '</h2>';

        $target = 'index.php?option=com_ra_events&task=event.showEmail';
        $target .= '&ref_id=' . $event_id;
        $target .= '&callback=' . $callback;
        $target .= '&Itemid=' . $this->menu_id;
        $target .= '&id=';

        $toolsTable = new ToolsTable();
        $toolsTable->add_header("Type,Date,Title,Body,From,To,");
        $sql = 'SELECT id, record_type, date_sent, title, body, ';
        $sql .= 'sender_name, addressee_name ';
        $sql .= 'FROM #__ra_emails ';
        $sql .= 'WHERE sub_system="RA Events" ';
        $sql .= 'AND ref=' . $event_id . ' ';
        $sql .= 'ORDER BY record_type ';
        $rows = $this->toolsHelper->getRows($sql);
        $total = count($rows);
        foreach ($rows as $row) {
            $type = EventsHelper::emailType($row->record_type);
            $toolsTable->add_item($type);
            $toolsTable->add_item(HTMLHelper::_('date', $row->date_sent, 'H:i d/m/y'));
            $toolsTable->add_item($row->title);
            if (strlen($row->body) > 516) {
                $body = strip_tags(substr($row->body, 0, 516)) . ' ....';
//        $link = '';
//        echo $this->toolsHelper->buildLink($link, 'Read more', true, 'readmore') . PHP_EOL;
            } else {
                $body = strip_tags(rtrim($row->body));
            }
            $toolsTable->add_item($body);
            $toolsTable->add_item($row->sender_name);
            $toolsTable->add_item($row->addressee_name);

            $info = $this->toolsHelper->imageButton('I', $target . $row->id);
            $toolsTable->add_item($info);
            $toolsTable->generate_line();
        }

        $toolsTable->generate_table();
        echo 'Total number of emails ' . $total . '<br>';
//      Always return to view event, bur ensure it in turn can return to
//      its calling program
        $back = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        if (is_numeric($callback)) {
            $back .= '&record_type=' . $callback;
        } elseif ($callback == 'profileform') {
            $back .= '&view=profileform&layout=bookings';
        } else {
            $back .= '&layout=' . $callback;
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function showTerms() {
        $title = 'Bookings: Terms and Conditions';
        $sql = 'SELECT `introtext` from #__content WHERE title="' . $title . '"';
//        echo $sql;
        $introtext = $this->toolsHelper->getValue($sql);
        if (is_null($introtext) OR ($introtext == '')) {
            echo '<h2>Terms of Use</h2>';

            echo 'We store details of your real name and email address for the purposes of communicating to you by email and for managing bookings that you make for Events.' . '<br><br>';

            echo 'In addition, we hold a "preferred name" of your choice, and this is shown above. '
            . 'The organiser of the Event will only be able to see this "preferred name" when using reports from the system.' . '<br><br>';

            echo 'We will never share your personal data with any other organisation. ' . '<br>';
        } else {
            echo $introtext . '<br>';
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function test() {
        $bookingHelper = new BookingHelper;
        $bookingHelper->sendAcknowledgement(106, 1);
    }

}
