<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers
 * 25/08/26 RH explicit field list: PHP conditionals for the (fixed) event
 *              type, live JS toggle for the (user-editable) bookable group -
 *              the site theme doesn't reliably run Joomla's core "showon" JS
 * 26/08/26 RH grouped fields into uitab tabs, mirroring the admin edit form
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');

$isNew = empty($this->item->id);
$isCommittee = ($this->item->event_type_id == 1);
$isHolidayWeekend = ($this->item->event_type_id == 4);
$isBookable = ($this->item->bookable == 1);
$isMultiGuest = ($this->item->multi_guest_enabled == 1);

if ($isCommittee) {
    $this->form->setFieldAttribute('details', 'label', 'Agenda');
    $this->form->setFieldAttribute('details', 'description', 'Agenda for the meeting');
}

echo '<h2>' . ($isNew ? 'Create Event' : 'Edit Event') . '</h2>';

if ($isNew) {
    echo '<p>Your new event will be visible on the site once it has been reviewed by an administrator.</p>';
}
?>

<div class="event-edit front-end-edit">
    <form id="form-event"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=eventform.save'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

        <?php echo $this->form->getInput('id'); ?>
        <?php echo $this->form->getInput('state'); ?>
        <?php echo $this->form->getInput('contact_id'); ?>
        <?php echo $this->form->getInput('event_type_id'); ?>
        <?php echo $this->form->getInput('api_site_id'); ?>
        <?php echo $this->form->getInput('original_id'); ?>
        <?php echo $this->form->getInput('created'); ?>
        <?php echo $this->form->getInput('created_by'); ?>
        <?php echo $this->form->getInput('modified'); ?>
        <?php echo $this->form->getInput('modified_by'); ?>
        <?php echo $this->form->getInput('checked_out_time'); ?>
        <?php echo $this->form->getInput('version_note'); ?>
        <?php echo $this->form->getInput('email'); ?>

        <?php echo HTMLHelper::_('uitab.startTabSet', 'eventformTab', array('active' => 'eventform-common')); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-common', 'Common fields'); ?>
        <?php echo $this->form->renderField('event_date'); ?>
        <?php if ($isHolidayWeekend): ?>
            <?php
            $this->form->setFieldAttribute('event_date_end', 'required', 'true');
            echo $this->form->renderField('event_date_end');
            ?>
        <?php endif; ?>
        <?php // When not a Holiday/weekend, event_date_end is simply not posted -
              // EventTable::bind() has no null-handling for this column (unlike
              // most others), so posting '' triggers an "Incorrect date value"
              // DB error. Omitting the key entirely leaves the loaded/NULL value untouched. ?>
        <?php echo $this->form->renderField('event_time'); ?>
        <?php echo $this->form->renderField('title'); ?>
        <?php echo $this->form->renderField('group_code'); ?>
        <?php echo $this->form->renderField('location'); ?>
        <?php echo $this->form->renderField('url'); ?>
        <?php echo $this->form->renderField('url_description'); ?>
        <?php echo $this->form->renderField('attachments'); ?>
        <?php echo $this->form->renderField('attachment_description'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-details', $isCommittee ? 'Agenda' : 'Details'); ?>
        <?php echo $this->form->renderField('details'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if ($isCommittee): ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-reports', 'Reports'); ?>
            <?php echo $this->form->renderField('reports'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-minutes', 'Minutes'); ?>
            <?php echo $this->form->renderField('minutes'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php else: ?>
            <input type="hidden" name="jform[reports]" value="<?php echo htmlspecialchars((string) $this->item->reports); ?>" />
            <input type="hidden" name="jform[minutes]" value="<?php echo htmlspecialchars((string) $this->item->minutes); ?>" />
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-booking', 'Booking'); ?>
        <?php echo $this->form->renderField('bookable'); ?>
        <div class="bookable-dependent" style="<?php echo $isBookable ? '' : 'display:none;'; ?>">
            <?php echo $this->form->renderField('requires_payment'); ?>
            <?php echo $this->form->renderField('max_bookings'); ?>
            <?php echo $this->form->renderField('multi_guest_enabled'); ?>
            <div class="multi-guest-dependent" style="<?php echo $isMultiGuest ? '' : 'display:none;'; ?>">
                <?php echo $this->form->renderField('max_guests'); ?>
            </div>
            <?php echo $this->form->renderField('waiting_list_enabled'); ?>
            <?php echo $this->form->renderField('notify_organiser'); ?>
            <?php echo $this->form->renderField('booking_info'); ?>
            <?php echo $this->form->renderField('booking1'); ?>
            <?php echo $this->form->renderField('booking1_hint'); ?>
            <?php echo $this->form->renderField('booking2'); ?>
            <?php echo $this->form->renderField('booking2_hint'); ?>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'eventformTab', 'eventform-publishing', 'Publishing'); ?>
        <?php echo $this->form->renderField('shareable'); ?>
        <?php echo $this->form->renderField('share_date'); ?>
        <?php echo $this->form->renderField('publication_date'); ?>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <div class="control-group">
            <div class="controls">
                <?php if ($this->canSave): ?>
                    <button type="submit" class="validate btn btn-primary">
                        <span class="fas fa-check" aria-hidden="true"></span>
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <a class="btn btn-danger"
                   href="<?php echo Route::_('index.php?option=com_ra_events&task=eventform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_ra_events"/>
        <input type="hidden" name="task" value="eventform.save"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
    (function () {
        function wireToggle(fieldName, groupSelector) {
            var radios = document.querySelectorAll('input[name="jform[' + fieldName + ']"]');
            var groups = document.querySelectorAll(groupSelector);

            function toggle(showIt) {
                groups.forEach(function (el) {
                    el.style.display = showIt ? '' : 'none';
                });
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    toggle(this.value === '1');
                });
            });
        }

        wireToggle('bookable', '.bookable-dependent');
        wireToggle('multi_guest_enabled', '.multi-guest-dependent');
    })();
</script>
