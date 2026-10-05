<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/10/26 RH created - member's own In Case of Emergency details
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Router\Route;

$item = $this->item;

echo '<h2>My emergency contact</h2>';
if ($this->page_intro !== '') {
    echo '<p>' . $this->page_intro . '</p>';
} else {
    echo '<p>Some events ask for an emergency contact - someone we can call on your behalf ';
    echo 'if something happens while you are out with us. Your details are only shown to the ';
    echo 'organiser of an event you have booked onto that asks for them.</p>';
}

if (is_null($item)) {
    echo '<p><i>You have not given any emergency contact details yet.</i></p>';
} else {
    echo '<p>Last updated: ' . HTMLHelper::_('date', $item->modified, 'd M y H:i') . '</p>';
}
?>

<form id="form-ice"
      action="<?php echo Route::_('index.php?option=com_ra_events&task=ice.save'); ?>"
      method="post" class="form-horizontal">
    <input type="hidden" name="Itemid" value="<?php echo (int) $this->menu_id; ?>" />

    <div class="control-group">
        <div class="control-label"><label for="contact_name">Contact name</label></div>
        <div class="controls">
            <input type="text" name="contact_name" id="contact_name" maxlength="100" size="40"
                   value="<?php echo is_null($item) ? '' : htmlspecialchars($item->contact_name); ?>" />
        </div>
    </div>

    <div class="control-group">
        <div class="control-label"><label for="relationship">Relationship to you</label></div>
        <div class="controls">
            <input type="text" name="relationship" id="relationship" maxlength="50" size="40"
                   placeholder="e.g. Partner, Son, Friend"
                   value="<?php echo is_null($item) ? '' : htmlspecialchars($item->relationship); ?>" />
        </div>
    </div>

    <div class="control-group">
        <div class="control-label"><label for="phone">Telephone number</label></div>
        <div class="controls">
            <input type="text" name="phone" id="phone" maxlength="50" size="40"
                   value="<?php echo is_null($item) ? '' : htmlspecialchars($item->phone); ?>" />
        </div>
    </div>

    <div class="control-group">
        <div class="controls">
            <button type="submit" class="btn btn-primary">
                <span class="fas fa-check" aria-hidden="true"></span> Save
            </button>
        </div>
    </div>
    <p><i>To remove your details, clear all three boxes and save.</i></p>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
