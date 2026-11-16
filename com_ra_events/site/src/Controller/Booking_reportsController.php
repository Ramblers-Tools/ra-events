<?php

/**
 * 05/11/26 CB created
 * 09/11/26 CB byBookingDate, histogram reports
 * 16/11/26 CB change extract to CSV download
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_events\Site\Helpers\IceHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHtml;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class Booking_reportsController extends FormController {

    protected $app;
    protected $back;
    protected $booking1;
    protected $booking2;
    protected $breadcrumbs;
    protected $bookingHelper;
    protected $db;
    protected $iceByEvent;   // populated in lookup_event() if event requires ICE details
    protected $iceHelper;
    protected $requires_ice;
    protected $requires_payment;
    protected $toolsHelper;
    protected $user_id;
    protected $user;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
        $this->db = Factory::getDbo();
        $this->bookingHelper = new BookingHelper;
        $this->iceHelper = new IceHelper;
        $this->toolsHelper = new ToolsHelper;
        $this->back = 'index.php?option=com_ra_events&task=booking_reports.showMenu&id=';
        $this->breadcrumbs = $this->toolsHelper->buildLink('index.php?option=com_ra_events&view=events', 'List Events');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('index.php?option=com_ra_events&view=events', 'Show event');
//        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_events&task=reports', 'Event Reports');
        $this->user = $this->app->getIdentity();
        $this->user_id = $this->user->id;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/dashboard.css');
    }

    public function attendees() {
        $event_id = $this->app->input->getInt('id', '0');
        $csv = substr($this->app->input->getCmd('csv', ''), 0, 1);
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>All confirmed attendees</h4>';

        // Can't use bookingHelper, it includes cancelled bookings
        // $names = $this->bookingHelper->guestNamesForEvent($row->id);
        // Find guest names for Confirmed bookings
        $sql = 'SELECT g.booking_id, g.name FROM #__ra_booking_guests AS g ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.id = g.booking_id ';
        $sql .= 'WHERE b.event_id=' . (int) $event_id . ' ';
        $sql .= 'AND b.state=1';
        $rows = $this->toolsHelper->getRows($sql);
        $names = array();
        if ($rows) {
            foreach ($rows as $row) {
                $names[] = $row->name;
            }
        }

        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            if ($row->state == 1) {
                $names[] = $row->preferred_name;
            }
        }
        sort($names);
        $table = new ToolsTable();
        $table->set_csv($csv);

        $table->add_header("Attendees");
        foreach ($names as $name) {
            $table->add_item($name);
            $table->generate_line();
        }

        echo $table->generate_table('Attendees');
        echo $this->toolsHelper->backButton($this->back . $event_id);
        if ($csv == '') {
            $target = "index.php?option=com_ra_events&task=booking_reports.attendees&id=" . $event_id . "&csv=attendees";
            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False, "btn btn-small button-new");
        }
    }

    private function buildSql($event_id) {
        // Build the query sql, except the sort clause
        $sql = 'SELECT b.*, ';
        $sql .= 'g.name AS `group_name`, p.preferred_name, u.name, u.email, ';
        $sql .= 'i.id as ice_id, i.contact_name AS ice_contact, ';
        $sql .= 'i.relationship AS ice_relationship, i.phone AS ice_phone ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'LEFT JOIN #__ra_groups AS g ON g.code = p.home_group  ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id  ';
        $sql .= 'LEFT JOIN #__ra_ice_contacts AS i ON i.user_id = b.user_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND b.state in (0,1,-1)';
//        echo $sql;
        return $sql;
    }

    public function byBookingDate() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>List by Booking date</h4>';
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY created, p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        $column_headings = 'Status,Booked,Name,Group,Places,ICE,Guests,Paid';
        if ($this->booking1) {
            $column_headings .= ',' . $event->booking1;
        }
        if ($this->booking2) {
            $column_headings .= ',' . $event->booking2;
        }
        $toolsTable->add_header($column_headings . ",Special requests");
        foreach ($rows as $row) {
            $toolsTable->add_item($this->status($row));
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item((is_null($row->ice_id)) ? 'No' : 'Yes');
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking));
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->amount_paid);
            if ($this->booking1) {
                $toolsTable->add_item($row->custom1);
            }
            if ($this->booking2) {
                $toolsTable->add_item($row->custom2);
            }
//            if ($requiresIce) {
//                $ice = isset($iceByUser[$row->user_id]) ? $iceByUser[$row->user_id] : null;
//                $toolsTable->add_item(is_null($ice) ? '<i>Not supplied</i>' : htmlspecialchars($ice->contact_name));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->relationship));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->phone));
//            }
            $toolsTable->add_item($row->special_request);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table('Bookings');

        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    public function byName() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>List by name</h4>';
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        $column_headings = 'Name,Group,Status,Places,ICE,Guests,Booked,Paid';
        if ($this->booking1) {
            $column_headings .= ',' . $event->booking1;
        }
        if ($this->booking2) {
            $column_headings .= ',' . $event->booking2;
        }
        $toolsTable->add_header($column_headings . ",Special requests");
        foreach ($rows as $row) {
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($this->status($row));
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item((is_null($row->ice_id)) ? 'No' : 'Yes');
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking));
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->amount_paid);
            if ($this->booking1) {
                $toolsTable->add_item($row->custom1);
            }
            if ($this->booking2) {
                $toolsTable->add_item($row->custom2);
            }
//            if ($requiresIce) {
//                $ice = isset($iceByUser[$row->user_id]) ? $iceByUser[$row->user_id] : null;
//                $toolsTable->add_item(is_null($ice) ? '<i>Not supplied</i>' : htmlspecialchars($ice->contact_name));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->relationship));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->phone));
//            }
            $toolsTable->add_item($row->special_request);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table('Bookings');

        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    public function byStatus() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>List by name</h4>';
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY b.state DESC, p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        $column_headings = 'Status,Name,Group,Places,ICE,Guests,Booked,Paid';
        if ($this->booking1) {
            $column_headings .= ',' . $event->booking1;
        }
        if ($this->booking2) {
            $column_headings .= ',' . $event->booking2;
        }
        $toolsTable->add_header($column_headings . ",Special requests");
        foreach ($rows as $row) {
            $toolsTable->add_item($this->status($row));
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item((is_null($row->ice_id)) ? 'No' : 'Yes');
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking));
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->amount_paid);
            if ($this->booking1) {
                $toolsTable->add_item($row->custom1);
            }
            if ($this->booking2) {
                $toolsTable->add_item($row->custom2);
            }
//            if ($requiresIce) {
//                $ice = isset($iceByUser[$row->user_id]) ? $iceByUser[$row->user_id] : null;
//                $toolsTable->add_item(is_null($ice) ? '<i>Not supplied</i>' : htmlspecialchars($ice->contact_name));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->relationship));
//                $toolsTable->add_item(is_null($ice) ? '' : htmlspecialchars($ice->phone));
//            }
            $toolsTable->add_item($row->special_request);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table('Bookings');

        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    private function checkAccess() {
        // Ensure the current user has access permission to view this data
        /*
          Either they should be a superuser
          Or member of security group com_ra_events
          Or they must be the organiser of the Event
         */
        if (1) {  //<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
            return true;
        } else {
            $this->app->enqueueMessage('You are not authorised to view this data');
            return false;
        }
    }

    private function checkCustom($event) {
        if (($event->booking1 !== '') AND (!is_null($event->booking1))) {
            $this->booking1 = true;
        } else {
            $this->booking1 = false;
        }
        if (($event->booking2 !== '') AND (!is_null($event->booking2))) {
            $this->booking2 = true;
        } else {
            $this->booking2 = false;
        }
    }

    public function custom() {
        $event_id = $this->app->input->getInt('id', '0');
        $mode = $this->app->input->getInt('mode', '1');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);

        $this->showEventDetails($event);
        echo '<h4>Custom Booking Fields</h4>';
        $toolsTable = new ToolsTable();
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY b.custom' . $mode . ', g.name, p.preferred_name';
//        echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        if ($mode == 1) {
            $header = $event->booking1;
            if ($this->booking2) {
                $header .= ',' . $event->booking2;
            }
        } else {
            $header = $event->booking2 . ',' . $event->booking1;
        }
        $toolsTable->add_header($header . ',Name,Group,Status,Places,Guests,ICE,Booked,Paid,Special requests');
        foreach ($rows as $row) {
            if ($mode == 1) {
                $toolsTable->add_item($row->custom1);
                if ($this->booking2) {
                    $toolsTable->add_item($row->custom2);
                }
            } else {
                $toolsTable->add_item($row->custom2);
                $toolsTable->add_item($row->custom1);
            }
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($this->status($row));
            $toolsTable->add_item($row->num_places);

            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking));
            $toolsTable->add_item((is_null($row->ice_id)) ? 'No' : 'Yes');
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->amount_paid);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table('Bookings');
        // Generate a histogram of bookings by custom field
        echo '<h4>Booking Histogram</h4>';
        $sql = 'SELECT b.custom' . $mode . ' AS `custom`, COUNT(b.id) as `count`, ';
        $sql .= 'SUM(b.num_places) as `places`, SUM(b.amount_paid) as `amount_paid` ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'WHERE b.event_id=' . $event_id . '  ';
        $sql .= 'GROUP BY b.custom' . $mode;
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        if ($mode == 1) {
            $header = $event->booking1;
        } else {
            $header = $event->booking2;
        }
        $toolsTable->add_header($header . ',Num bookings,Tot places,Amount rec\'d');
        foreach ($rows as $row) {

            $toolsTable->add_item($row->custom);
            $toolsTable->add_item($row->count);
            $toolsTable->add_item($row->places);
            $toolsTable->add_item($row->amount_paid);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table();
        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    public function emails() {
        /*
         * Changes need to event.showBookings to ensure the back link works correctly
          if ($callback == 'bookings') {
          $back .= '&task=booking_reports.emails&id=' . $ref_id;
          } elseif ($callback == 'myemails') {
         */
        $event_id = $this->app->input->getInt('id', '0');
        $callback = $this->app->input->getAlnum('callback', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);

        $this->showEventDetails($event);
        echo '<h4>Emails for ' . $event->title . '</h4>';
        $sql = 'SELECT title ';
        $sql .= 'FROM #__ra_events WHERE id=' . $event_id;
        $title = $this->toolsHelper->getValue($sql);

        $target = 'index.php?option=com_ra_events&task=event.showEmail';
        $target .= '&ref_id=' . $event_id;
        $target .= '&callback=bookings';
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

        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    public function extract() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $csvData = $this->bookingHelper->extractBookings($event_id, $event->requires_ice == 1);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d_H-i-s') . '.csv"');
        echo $csvData;
        exit;
    }

    public function ice() {
        $event_id = $this->app->input->getInt('id', '0');
        $mode = $this->app->input->getInt('mode', '1');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);

        $this->showEventDetails($event);
        $toolsTable = new ToolsTable();
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);
        $toolsTable = new ToolsTable();
        $count = 0;
        $toolsTable->add_header('Name,Group,Places,ICE contact,Relationship,Phone');
        foreach ($rows as $row) {
            if (!is_null($row->ice_id)) {
                $count++;
            }
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item($row->ice_contact);
            $toolsTable->add_item($row->ice_relationship);
            $toolsTable->add_item($row->ice_phone);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table();
        echo $count . ' of ' . count($rows) . ' bookings have ICE details<br>';
        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    private function lookupEvent($event_id) {
        $sql = 'SELECT e.id, e.event_type_id, e.event_date, e.event_date_end, e.title, ';
        $sql .= 'e.api_site_id, e.booking1, e.booking2, e.max_bookings, e.requires_ice,';
        $sql .= 't.description AS `event_type` ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id ';
        $sql .= 'WHERE e.id=' . $event_id;
        return $this->toolsHelper->getItem($sql);
    }

    private function showEventDetails($event) {
//        if ($this->requiresIce) {
//            $this->iceByEvent = $iceHelper->getForEvent($event->id);
//        }
        // setup the list of Guests
        $bookingHelper = new BookingHelper;
        $this->guestsByBooking = $bookingHelper->guestNamesForEvent($event->id);

        if ($event->event_type_id == 4) { // Holiday
            echo '<h4>' . HTMLHelper::_('date', $event->event_date, 'd-M-y') . ' to ';
            echo HTMLHelper::_('date', $event->event_date_end, 'd-M-y');
        } else {
            echo '<h4>' . HTMLHelper::_('date', $event->event_date, 'l d M y');
        }
        echo ' <b>' . $event->title . '</b></h4>';
        echo 'Number of places: ' . $event->max_bookings . '<br>';
        echo 'Requires ICE details: ' . ($event->requires_ice == 1 ? 'Yes' : 'No') . '<br>';
        $this->requires_payment = $item->requires_payment;
        // See if custom booking fields have been defined
        $this->checkCustom($event);
    }

    public function showMenu() {    // task=booking_reports.showMenu&id=
        if (!$this->user || (int) $this->user->id <= 0) {
            throw new \Exception('You must be logged on to access this function');
        }
//        echo $this->breadcrumbs . '<br>';
        $event_id = $this->app->input->getInt('id', '0');
        $event = $this->lookupEvent($event_id);
        // See if custom fields have been defined
        $this->checkCustom($event);
        if (!is_null($event->api_site_id)) {
            $this->app->enqueueMessage();
            return;
        }

        $target = 'index.php?option=com_ra_events&id=' . $event_id . '&task=booking_reports.';
//        echo $target . '<br>';
        $reports = [
            'List bookings by date' => $target . 'byBookingDate',
            'List bookings by name' => $target . 'byName',
            'List bookings by status' => $target . 'byStatus',
        ];
        if ($this->booking1) {
            $reports['List bookings by ' . $event->booking1] = $target . 'custom&mode=1';
        }
        if ($this->booking2) {
            $reports['List bookings by ' . $event->booking2] = $target . 'custom&mode=2';
        }
        $reports['List all confirmed attendees'] = $target . 'attendees';
        $reports['Special requests'] = $target . 'special';
        $reports['ICE report'] = $target . 'ice';
        $reports['Waiting List'] = $target . 'waitinglist';
        $reports['List emails'] = $target . 'emails';
        $reports['Extract as CSV file'] = $target . 'extract';
        echo '<h2>Booking reports</h2>';
        $this->showEventDetails($event);
        echo '<div style="padding-left: 12px 0;">';
        foreach ($reports as $caption => $task) {
            echo '<li>' . $this->toolsHelper->buildLink($task, $caption) . '</li>';
        }
        echo '</div>';
        echo '<p></p>';
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        echo $this->toolsHelper->backButton($target);
    }

    public function special() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>Special requests</h4>';
        $bookingHelper = new BookingHelper;
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY b.state DESC, p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);

        $toolsTable = new ToolsTable();
        $count = 0;
        $toolsTable->add_header('Status,Name,Group,Special request');
        foreach ($rows as $row) {
            if (($row->special_request == '') OR (is_null($row->special_request))) {
                continue;
            }
            $toolsTable->add_item($this->status($row));
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->special_request);
            $toolsTable->generate_line();
        }
        $toolsTable->generate_table('Special requests');

        foreach ($rows as $row) {
            $names[] = $row->preferred_name;
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            if (!empty($guestsByBooking[$row->id])) {
                $names = array_merge($names, $guestsByBooking[$row->id]);
            }
        }


        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

    private function status($event) {
        // Removes the string 'Status:'

        $status = BookingHelper::showState($event->state, $event->is_paid, $this->requires_payment);
        return str_replace('Status:', '', $status);
    }

    public function waitingList() {
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);

        $this->showEventDetails($event);
// Following code copied from BookingCotroller.showBookings
// Should be a shared function in BookingsHelper
        $sql = 'SELECT b.id, b.user_id, b.created, b.num_places, p.preferred_name ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND b.state=-1';
        $sql .= ' ORDER BY b.created';
        $rows = $this->toolsHelper->getRows($sql);
        echo '<h4>Waiting List</h4>';
        if ($rows) {
            $header = 'Name,Joined,Places';
            if ($canEdit) {
                $header .= ',Action';
            }
            $table = new ToolsTable;
            $table->add_header($header);
            foreach ($rows as $row) {
                $table->add_item($row->preferred_name);
                $table->add_item(HTMLHelper::_('date', $row->created, 'd M y H:i'));
                $table->add_item($row->num_places);
                if ($canEdit) {
                    $confirm_target = 'index.php?option=com_ra_events&task=booking.confirmBooking&event_id=' . $event_id;
                    $confirm_target .= '&Itemid=' . $menu_id . '&id=' . $row->id;
                    if (($confirmed_places + $provisional_places + $row->num_places) > $item->max_bookings) {
                        $class = ToolsHelper::lookupColourCode('darkgreen', 'B');
                        $message = 'Confirming this booking will take the event over its maximum allocated spaces. Continue?';
                        $actions = '<a class="' . $class . '" href="' . $confirm_target . '" ';
                        $actions .= 'onclick="return confirm(\'' . $message . '\');" target="_self">Confirm Booking</a>';
                    } else {
                        $actions = $this->toolsHelper->buildButton($confirm_target, 'Confirm Booking', False, 'darkgreen');
                    }
                    $cancel_target = 'index.php?option=com_ra_events&task=booking.cancelBooking&event_id=' . $event_id;
                    $cancel_target .= '&Itemid=' . $menu_id . '&id=' . $row->id . '&user_id=' . $row->user_id;
                    $actions .= $this->iconButton($cancel_target, 'icon-trash', 'Cancel booking', 'ra-red', 'Are you sure you want to cancel this booking?');
                    $table->add_item($actions);
                }
                $table->generate_line();
            }
            $table->generate_table();
        } else {
            echo 'No one on the waiting list<br>';
        }

        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

}
