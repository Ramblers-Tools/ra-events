<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/08/26 RH created - self-service Manage My Booking
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;

HTMLHelper::_('bootstrap.tooltip');

$item = $this->item;
$isMultiGuest = $this->isMultiGuest;
$canEditGuestCount = $this->canEditGuestCount;
$existingGuests = $this->existingGuests;
$existingGuestCount = count($existingGuests);

echo '<h3>Manage My Booking</h3>';
echo '<h4>' . $item->title . '</h4>';
echo '<p>' . HTMLHelper::_('date', $item->event_date, 'l d M y') . '</p>';
echo '<p>Status: ' . BookingHelper::showState($item->state) . '</p>';
?>

<form id="form-managebooking"
      action="<?php echo Route::_('index.php?option=com_ra_events&task=managebooking.save'); ?>"
      method="post" class="form-horizontal">
    <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
    <input type="hidden" name="Itemid" value="<?php echo (int) $this->menu_id; ?>" />

    <?php if ($isMultiGuest): ?>
        <?php
        if ($canEditGuestCount) {
            // Same capacity math as the booking form: how many guests this booking
            // could have, given the event's cap and the event's remaining capacity.
            $sql = 'SELECT SUM(num_places) AS tot FROM #__ra_bookings ';
            $sql .= 'WHERE event_id=' . (int) $item->event_id . ' AND state IN (0,1) ';
            $sql .= 'AND id != ' . (int) $item->id;
            $activePlaces = (int) $this->toolsHelper->getValue($sql);
            $remaining = $item->max_bookings - $activePlaces;
            $guestCap = max($existingGuestCount, min((int) $item->max_guests, $remaining - 1));
        } else {
            $guestCap = $existingGuestCount;
        }
        ?>
        <div class="control-group">
            <div class="control-label"><label for="guest_count">Number of guests</label></div>
            <div class="controls">
                <?php if ($canEditGuestCount): ?>
                    <select name="guest_count" id="guest_count">
                        <?php for ($i = 0; $i <= $guestCap; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $existingGuestCount) ? 'selected="selected"' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                <?php else: ?>
                    <?php echo $existingGuestCount; ?>
                    <i>(your booking is confirmed - contact the organiser to change the number of guests)</i>
                <?php endif; ?>
            </div>
        </div>
        <?php for ($i = 1; $i <= $guestCap; $i++): ?>
            <div class="control-group guest-name-field" data-guest-index="<?php echo $i; ?>"
                 style="<?php echo ($i <= $existingGuestCount || !$canEditGuestCount) ? '' : 'display:none;'; ?>">
                <div class="control-label"><label for="guest_name_<?php echo $i; ?>">Guest <?php echo $i; ?> name</label></div>
                <div class="controls">
                    <input type="text" name="guests[]" id="guest_name_<?php echo $i; ?>" maxlength="100"
                           value="<?php echo isset($existingGuests[$i - 1]) ? htmlspecialchars($existingGuests[$i - 1]) : ''; ?>" />
                </div>
            </div>
        <?php endfor; ?>
    <?php elseif ($item->num_places == 2): ?>
        <div class="control-group">
            <div class="control-label"><label for="partner">Other person's name</label></div>
            <div class="controls">
                <input type="text" name="partner" id="partner" maxlength="50"
                       value="<?php echo htmlspecialchars($item->partner); ?>" />
            </div>
        </div>
    <?php else: ?>
        <p>This booking is for a single person.</p>
    <?php endif; ?>

    <div class="control-group">
        <div class="controls">
            <button type="submit" class="btn btn-primary">
                <span class="fas fa-check" aria-hidden="true"></span> Save changes
            </button>
        </div>
    </div>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php if (in_array((int) $item->state, array(0, 1, -1))): ?>
    <form id="form-cancelbooking" style="display:inline;"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=managebooking.cancel'); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>" />
        <input type="hidden" name="Itemid" value="<?php echo (int) $this->menu_id; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
        <button type="submit" class="btn btn-danger"
                onclick="return confirm('Are you sure you want to cancel this booking?');">
            <span class="fas fa-times" aria-hidden="true"></span> Cancel my booking
        </button>
    </form>
<?php endif; ?>

<a class="btn btn-secondary"
   href="<?php echo Route::_('index.php?option=com_ra_events&view=event&id=' . $item->event_id . '&Itemid=' . $this->menu_id); ?>">
    Back to event
</a>

<?php if ($isMultiGuest && $canEditGuestCount): ?>
    <script>
        (function () {
            var select = document.getElementById('guest_count');
            var fields = document.querySelectorAll('.guest-name-field');

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
            }

            if (select) {
                select.addEventListener('change', update);
                update();
            }
        })();
    </script>
<?php endif; ?>
