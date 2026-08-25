<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers
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

echo '<h2>' . ($isNew ? 'Create Event' : 'Edit Event') . '</h2>';

if ($isNew) {
    echo '<p>Your new event will be visible on the site once it has been reviewed by an administrator.</p>';
}
?>

<div class="event-edit front-end-edit">
    <form id="form-event"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=eventform.save'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

        <?php foreach ($this->form->getFieldset('mainFieldset') as $field): ?>
            <?php if ($field->hidden): ?>
                <?php echo $field->input; ?>
            <?php else: ?>
                <?php echo $this->form->renderField($field->fieldname); ?>
            <?php endif; ?>
        <?php endforeach; ?>

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
