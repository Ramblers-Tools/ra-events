<?php
/**
 * @version    2.4.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 03/05/25 CB show name larger
 * 08/05/25 CB give error 404 if not logged in
 * 25/07/25 CB regenerated to allow both Add and Update
 * 11/09/25 CB Allow anyone to make a booking if they are logged in
 * 22/09/25 CB show fields custom1 and custom2
 * 28/09/25 CB show Terms
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_events', JPATH_SITE);

$user = Factory::getApplication()->getIdentity();
//$canEdit = Ra_eventsHelper::canUserEdit($this->item, $user);

if ($this->item->state == 1) {
    $state_string = 'Publish';
    $state_value = 1;
} else {
    $state_string = 'Provisional';
    $state_value = 0;
}

$bookingHelper = new BookingHelper;
$toolsHelper = new ToolsHelper;

echo '<h3>' . $this->title . '</h3>';
// Find name of the User
echo $this->intro;

?>

<div class="booking-edit front-end-edit">


    <form id="form-booking"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=bookingform.save'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

        <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

        <?php echo $this->form->getInput('created_by'); ?>
        <?php echo $this->form->getInput('modified_by'); ?>
        <div class="control-group">
            <?php
            $sql = 'SELECT booking1, booking1_hint, booking2, booking2_hint, ';
            $sql .= 'multi_guest_enabled, max_guests ';
            $sql .= 'FROM #__ra_events WHERE id=' . $this->event_id;
            $event = $toolsHelper->getItem($sql);
            $isMultiGuestEvent = ($event->multi_guest_enabled == 1);

            // Find the number of places available
            $sql = 'SELECT max_bookings FROM #__ra_events WHERE id=' . $this->event_id;
            $max_places = $toolsHelper->getValue($sql);
            // Check the number of places booked so far
            $sql = 'SELECT SUM(b.num_places) AS `tot` ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
            $sql .= 'WHERE e.id=' . $this->event_id . ' ';
            $sql .= 'AND e.state=1 ';
            $sql .= 'AND b.state=1 ';
            $confirmed = $this->toolsHelper->getValue($sql);
             $sql = 'SELECT SUM(b.num_places) AS `tot` ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
            $sql .= 'WHERE e.id=' . $this->event_id . ' ';
            $sql .= 'AND e.state=1 ';
            $sql .= 'AND b.state=0 ';
            $provisional = $this->toolsHelper->getValue($sql);

            if ($isMultiGuestEvent) {
                // Guest count picker + N guest-name inputs, revealed by JS. Not part of
                // the formal jform schema (posted as a plain array alongside jform) since
                // the number of visible inputs is dynamic.
                $existingOwnPlaces = ($this->item->id > 0) ? (int) $this->item->num_places : 0;
                $remainingExcludingSelf = $max_places - $confirmed - $provisional + $existingOwnPlaces;
                $guestCap = max(0, min((int) $event->max_guests, $remainingExcludingSelf - 1));
                $existingGuests = ($this->item->id > 0) ? $bookingHelper->guestNames($this->item->id) : array();
                $existingGuestCount = count($existingGuests);
                ?>
                <div class="control-group">
                    <div class="control-label"><label for="guest_count">Number of guests</label></div>
                    <div class="controls">
                        <select name="guest_count" id="guest_count">
                            <?php for ($i = 0; $i <= $guestCap; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $existingGuestCount) ? 'selected="selected"' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <?php for ($i = 1; $i <= max($guestCap, $existingGuestCount); $i++): ?>
                    <div class="control-group guest-name-field" data-guest-index="<?php echo $i; ?>" style="<?php echo ($i <= $existingGuestCount) ? '' : 'display:none;'; ?>">
                        <div class="control-label"><label for="guest_name_<?php echo $i; ?>">Guest <?php echo $i; ?> name</label></div>
                        <div class="controls">
                            <input type="text" name="guests[]" id="guest_name_<?php echo $i; ?>" maxlength="100"
                                   value="<?php echo isset($existingGuests[$i - 1]) ? htmlspecialchars($existingGuests[$i - 1]) : ''; ?>" />
                        </div>
                    </div>
                <?php endfor; ?>
                <input type="hidden" name="jform[num_places]" value="<?php echo max(1, $existingGuestCount + 1); ?>" id="jform_num_places_hidden" />
                <input type="hidden" name="jform[partner]" value="" />
                <?php
            } else {
                // Guest bookings are off for this event - just book a single person.
                // Preserve the old radio/text fields only when editing a booking that
                // already has a second person (e.g. made before multi-guest booking
                // existed), so editing it doesn't silently drop their name.
                $existingNumPlaces = ($this->item->id > 0) ? (int) $this->item->num_places : 1;
                if ($existingNumPlaces > 1) {
                    echo $this->form->renderField('num_places');
                    echo $this->form->renderField('partner');
                } else {
                    echo '<input type="hidden" name="jform[num_places]" value="1" />';
                    echo '<input type="hidden" name="jform[partner]" value="" />';
                }
            }
            ?>
            <?php
            echo $this->form->renderField('special_request');
            if ($event->booking1 !== '') {
                echo $this->form->renderField('custom1');
            }
            if ($event->booking2 !== '') {
                echo $this->form->renderField('custom2');
            }
            echo 'Total available places: <b>' . $max_places . '</b>'   ;
            echo ', Confirmed places: <b>' . $confirmed . '</b>';
            if (isset($this->item->num_places) && $this->item->num_places == 2) {
                // Booking is for two places
                $num_requested = 2  ;
            } else {
                // Booking is for one place or not set
                $num_requested = 1;
            }
             echo ', Provisional places: <b>' . $provisional . '</b>';

            echo '<br>';
            if($confirmed >= $max_places){
                $message1 = 'Fully booked';
                $waiting = $confirmed + $provisional - $max_places;
                $message2 = $waiting .  ' already on the waiting list';
                $this->canState = false;
            } elseif (($confirmed + $provisional) > $max_places) {
                $message1 = 'Over subscribed';
                $message2 = 'Bookings are confirmed on a first-come-first-served basis';
                $this->canState = false;
            }
            echo '<div style="color: red;"><b>' . $message1 . '</b><br>';
            echo $message2 . '</div><br>';
//            if ($confirmed_bookings + $num_requested > $max_bookings) {               
//                echo '<div style="color: red;"><b>Overbooked! WARNING: Only ' . $available . ' places are available for this Event. ';
 //               echo 'You have requested ' . $num_requested . ' places. </b></div><br>';
 //               $this->canState = false;
 //           }
            ?>
            <?php if ($this->canState == true): ?>
                <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                <div class="controls"><?php echo $this->form->getInput('state'); ?></div>
            <?php else: ?>
                <div class="control-label"><?php echo $this->form->getLabel('state') . $state_string; ?></div>
                <input type="hidden" name="jform[state]" value="<?php echo $state_value; ?>" />
            <?php endif; ?>
            <?php
            if ($this->item->id == 0) {
                echo $this->form->renderField('terms');
                $target = 'index.php?option=com_ra_events&view=bookingform&layout=terms';
                echo 'Click to view the full' . $toolsHelper->buildLink($target, 'Terms and Conditions', true) . '.' . '<br>';
                //           } else {
                //               echo 'id:' . $this->id . '<br>';
            }
            ?>
        </div>

        <?php //echo $this->form->renderField('user_id'); ?>
        <?php echo $this->form->renderField('confirmed'); ?>
        <?php echo $this->form->renderField('event_id'); ?>
        <div class="control-group">
            <div class="controls">

                <?php if ($this->canSave): ?>
                    <button type="submit" class="validate btn btn-primary">
                        <span class="fas fa-check" aria-hidden="true"></span>
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <a class="btn btn-danger"
                   href="<?php echo Route::_('index.php?option=com_ra_events&task=bookingform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_ra_events"/>
        <input type="hidden" name="task"
               value="bookingform.save"/>
               <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<?php if ($isMultiGuestEvent): ?>
<script>
    (function () {
        var select = document.getElementById('guest_count');
        var fields = document.querySelectorAll('.guest-name-field');
        var numPlacesHidden = document.getElementById('jform_num_places_hidden');

        function update() {
            var count = parseInt(select.value, 10) || 0;
            fields.forEach(function (field) {
                var index = parseInt(field.getAttribute('data-guest-index'), 10);
                var input = field.querySelector('input');
                if (index <= count) {
                    field.style.display = '';
                    input.required = true;
                } else {
                    field.style.display = 'none';
                    input.required = false;
                    input.value = '';
                }
            });
            if (numPlacesHidden) {
                numPlacesHidden.value = count + 1;
            }
        }

        if (select) {
            select.addEventListener('change', update);
            update();
        }
    })();
</script>
<?php endif; ?>