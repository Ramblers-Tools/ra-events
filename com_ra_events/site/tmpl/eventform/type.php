<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers, step 1: choose type
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$toolsHelper = new ToolsHelper;

echo '<h2>Create Event</h2>';
echo '<p>Step 1 of 2: choose the type of event you want to create.</p>';

$sql = 'SELECT id, description FROM #__ra_event_types WHERE state=1 ORDER BY ordering';
$types = $toolsHelper->getRows($sql);
?>

<div class="event-edit front-end-edit">
    <form id="form-event-type"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=eventform.selectType'); ?>"
          method="post" class="form-horizontal">

        <div class="control-group">
            <div class="controls" style="display:flex; flex-direction:column;">
                <?php foreach ($types as $type): ?>
                    <label class="radio" style="display:block;">
                        <input type="radio" name="event_type_id" value="<?php echo (int) $type->id; ?>" required />
                        <?php echo htmlspecialchars($type->description); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="control-group">
            <div class="controls">
                <button type="submit" class="btn btn-primary">
                    <span class="fas fa-arrow-right" aria-hidden="true"></span>
                    Next
                </button>
                <a class="btn btn-danger"
                   href="<?php echo Route::_('index.php?option=com_ra_events&task=eventform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_ra_events"/>
        <input type="hidden" name="task" value="eventform.selectType"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
