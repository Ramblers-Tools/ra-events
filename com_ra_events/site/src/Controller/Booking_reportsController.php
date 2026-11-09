<?php

/**
 * 05/11/26 CB created
 * 09/11/26 CB byBookingDate, histogram reports
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
    protected $breadcrumbs;
    protected $bookingHelper;
    protected $criteria_sql;
    protected $db;
    protected $iceByEvent;   // populated in lookup_event() if event requires ICE details
    protected $iceHelper;
    protected $requires_ice; 
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
    }

    public function attendees(){   
        $event_id = $this->app->input->getInt('id', '0');
        $csv = substr($this->app->input->getCmd('csv', ''), 0, 1);
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
        $this->showEventDetails($event);
        echo '<h4>All attendees</h4>';
        $bookingHelper = new BookingHelper;
        $sql = $this->buildSql($event_id);
        $sql .= ' ORDER BY p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);

        $names = [];
        foreach ($rows as $row) {
            $names[] = $row->preferred_name;
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);          
            if (!empty($guestsByBooking[$row->id])) {
                $names = array_merge($names, $guestsByBooking[$row->id]);
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
        $sql .= ' AND b.state in (0,1)';
//        echo $sql;
        return $sql;
    }

    public function byBookingDate(){
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
//        $event = $this->toolsHelper->getItem('SELECT title, booking1,
        $toolsTable = new ToolsTable();
        $column_headings = 'Booked,Name,Group,Places,ICE,Guests,Paid';
        if ($event->booking1 !== '') {
            $column_headings .= ',' . $event->booking1;
        }
        if ($event->booking2 !== '') {
            $column_headings .= ',' . $event->booking2;
        }        
        $toolsTable->add_header($column_headings . ",Special requests");
        foreach ($rows as $row) {
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item((is_null($row->ice_id)) ?'No' : 'Yes');
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking ));
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->amount_paid);
            if ($event->booking1 !== '') {
                $toolsTable->add_item($row->custom1);
            }
            if ($event->booking2 !== '') {
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

    public function byName(){      
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
//        $event = $this->toolsHelper->getItem('SELECT title, booking1,
        $toolsTable = new ToolsTable();
        $column_headings = 'Name,Group,Places,ICE,Guests,Booked,Paid';
        if ($event->booking1 !== '') {
            $column_headings .= ',' . $event->booking1;
        }
        if ($event->booking2 !== '') {
            $column_headings .= ',' . $event->booking2;
        }        
        $toolsTable->add_header($column_headings . ",Special requests");
        foreach ($rows as $row) {
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            $toolsTable->add_item((is_null($row->ice_id)) ?'No' : 'Yes');
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking ));
//            $toolsTable->add_item(isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '');
            $toolsTable->add_item($row->created);
            $toolsTable->add_item($row->amount_paid);
            if ($event->booking1 !== '') {
                $toolsTable->add_item($row->custom1);
            }
            if ($event->booking2 !== '') {
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

    private function checkAccess(){
    // Ensure the current user has access permission to view this data
    /*
      Either they should be a superuser
      Or member of security group com_ra_events
      Or they must be the organiser of the Event
    */
        if (1) {                   //<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
            return true;
        } else {
            $this->app->enqueueMessage('You are not authorised to view this data');
            return false;
        }
    }

        /**
     * Quote one value for the hand-built CSV in bookingReports(). Emergency
     * contact fields are free text and routinely contain commas.
     *
     * @param   string  $value
     * @return  string
     */
    private function csvField($value) {
        $value = (string) $value;
        if (strpbrk($value, ",\"\r\n") === false) {
            return $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
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
            $header = $event->booking1 . ',' . $event->booking2;
        } else {
            $header =  $event->booking2 . ',' . $event->booking1;
        }
        $toolsTable->add_header($header . ',Name,Group,Places,Guests,ICE,Booked,Paid,Special requests');
        foreach ($rows as $row) {
            if ($mode == 1) {
                $toolsTable->add_item($row->custom1);
                $toolsTable->add_item($row->custom2);
            } else {
                $toolsTable->add_item($row->custom2);
                $toolsTable->add_item($row->custom1);
            }
            $toolsTable->add_item($row->preferred_name);
            $toolsTable->add_item($row->group_name);
            $toolsTable->add_item($row->num_places);
            
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $toolsTable->add_item(count($guestsByBooking ));
            $toolsTable->add_item((is_null($row->ice_id)) ?'No' : 'Yes');
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
            $header =  $event->booking2;
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
        
;
        echo $this->toolsHelper->backButton($this->back . $event_id);
   }

    public function emails(){
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

    public function extract(){
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event_id = $this->app->input->getInt('id', '0');
        $event = $this->lookupEvent($event_id);
        // Usually ICE details is set up in showEventDetails
        $this->requiresIce = ($event->requires_ice == 1);
        
        $sql = $this->buildSql($event_id);
        $rows = $this->toolsHelper->getRows($sql);

        // Build column headings based on sort order and presence of custom fields
        $column_headings = 'Name,Group';
        // One heading per add_item() below: created, email, guests.
        $column_headings .= ',Booked, Paid, Guests';
        if ($event->booking1 !== '') {
            $column_headings .= ', ' . $event->booking1;
        }
        if ($event->booking2 !== '') {
            $column_headings .= ', ' . $event->booking2;
        }
        if ($this->requiresIce) {
            $column_headings .= ', ICE contact, Relationship, ICE phone';
        }        
        $csvData = $column_headings . ",Special requests\n";
        foreach ($rows as $row) {
            if ($sort == 'name') {
                $csvData .= $row->preferred_name . ', ';
                $csvData .= $row->group_name . ', ';
            } else {
                $csvData .= $row->group_name . ', ';
                $csvData .= $row->preferred_name . ', ';
            }
            $csvData .= $row->created . ', ';
            $csvData .= $row->amount_paid . ', ';
            $guestsByBooking = $this->bookingHelper->guestNamesForEvent($row->id);
            $csvData .= isset($guestsByBooking) ? implode(', ', $guestsByBooking) : '';
            $csvData .= ', ' . $row->custom1;
        
            if ($event->booking2 !== '') {
                $csvData .= ', ' . $row->custom2;
            }
            if ($this->requiresIce) {
                $csvData .= ', ' . $this->csvField($row->ice_contact);
                $csvData .= ', ' . $this->csvField($row->ice_relationship);
                $csvData .= ', ' . $this->csvField($row->ice_phone);
            }
            $csvData .= ', ';

            if (str_contains($row->special_request, ',')) {
                $csvData .= '"' . $row->special_request . '"';
            } else {
                $csvData .= $row->special_request;
            }
            $csvData .= "\n";
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d') . '.csv"');
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
//        echo $sql . '<br>';
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
    
    private function showEventDetails($event){
//        if ($this->requiresIce) {
//            $this->iceByEvent = $iceHelper->getForEvent($event->id);
//        }   
        // setup the list of Guests
        $bookingHelper = new BookingHelper;
        $this->guestsByBooking = $bookingHelper->guestNamesForEvent($event_id);    

        if ($event_type_id == 4) { // Holiday
            echo '<h4>' . HTMLHelper::_('date', $event->event_date, 'd-M-y') . ' to ';
                echo HTMLHelper::_('date', $event->event_date_end, 'd-M-y');
        } else {
            echo '<h4>' . HTMLHelper::_('date', $event->event_date, 'l d M y');
        }
        echo ' <b>' . $event->title . '</b></h4>';
        echo 'Number of places: ' . $event->max_bookings . '<br>';
        echo 'Requires ICE details: ' . ($event->requires_ice == 1 ? 'Yes' : 'No') . '<br>';
    }

    public function showMenu(){    // task=booking_reports.showMenu&id=
        if (!$this->user || (int) $this->user->id <= 0) {
            throw new \Exception('You must be logged on to access this function');
        }
//        echo $this->breadcrumbs . '<br>';
        $event_id = $this->app->input->getInt('id', '0');
        $event = $this->lookupEvent($event_id);

        if (!is_null($event->api_site_id)) {
            $this->app->enqueueMessage();
            return;
        } 

        $target = 'index.php?option=com_ra_events&id=' . $event_id . '&task=booking_reports.';
//        echo $target . '<br>';
        $reports = [
            'List booking by date' => $target .'byBookingDate',
            'List bookings by name' => $target .'byName',
        ];
        if (!is_null($event->booking1)) {
            $reports['List bookings by ' . $event->booking1] = $target .'custom&mode=1';
        }
        if (!is_null($event->booking2)) {
            $reports['List bookings by ' . $event->booking2] = $target .'custom&mode=2';
        }
        $reports['List all attendees'] = $target .'attendees';
        $reports['Special requests'] = $target .'special';
        $reports['ICE report'] = $target .'ice';
        $reports['Waiting List'] = $target .'waitinglist';
        $reports['List emails'] = $target .'emails';
        $reports['Extract as CSV file'] = $target .'extract';
        ?>
        <div class="dashboard-grid">
            <div class="dashboard-block">
                <div class="block-header">
                    <h2>Booking reports</h2>
                </div>
                <?php
                $this->showEventDetails($event);
                ?>
                <div class="block-content">
                    <?php
                    echo '<ul>';
                    foreach ($reports as $caption => $task) {
                        echo '<li>' . $this->toolsHelper->buildLink($task, $caption) . '</li>';
                    }
                    echo '</ul>';
                    ?>
                </div>
            </div>
        </div>
        <?php
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        echo $this->toolsHelper->backButton($target);
    }

        public function special(){   
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
        $sql .= ' ORDER BY p.preferred_name, g.name';
        $rows = $this->toolsHelper->getRows($sql);

        $toolsTable = new ToolsTable();
        $count = 0;
        $toolsTable->add_header('Name,Group,Special request');
        foreach ($rows as $row) {
            if (($row->special_request == '') OR (is_null($row->special_request))) {
                continue;
            }
     
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

    public function waitingList(){
        $event_id = $this->app->input->getInt('id', '0');
        if (!$this->checkAccess()) {
            echo $this->toolsHelper->backButton($this->back . $event_id);
            return;
        }
        $event = $this->lookupEvent($event_id);
     
        $this->showEventDetails($event);
        echo '<h4>Waiting List</h4>';
        echo $this->toolsHelper->backButton($this->back . $event_id);
    }

}
