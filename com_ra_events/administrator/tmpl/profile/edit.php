<?php
/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_events\Site\Helpers\IceHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_events&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="profile-form" class="form-validate form-horizontal">

    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php echo $this->form->renderField('real_name'); ?>
                <?php echo $this->form->renderField('preferred_name'); ?>
                <?php echo $this->form->renderField('email'); ?>
                <?php echo $this->form->renderField('home_group'); ?>
                <?php echo $this->form->renderField('state'); ?>
                <?php echo $this->form->renderField('id'); ?>
            </fieldset>
            <?php
            // Emergency contact details, read-only - only the member may change them,
            // and they live in #__ra_ice_contacts rather than on the profile record.
            $iceHelper = new IceHelper;
            $ice = $iceHelper->getForUser($this->item->id);
            ?>
            <fieldset class="adminform">
                <legend>Emergency contact</legend>
                <?php if (is_null($ice)) : ?>
                    <p><i>This member has not given any emergency contact details.</i></p>
                <?php else : ?>
                    <p>
                        <b>Contact:</b> <?php echo htmlspecialchars($ice->contact_name); ?><br>
                        <b>Relationship:</b> <?php echo htmlspecialchars($ice->relationship); ?><br>
                        <b>Telephone:</b> <?php echo htmlspecialchars($ice->phone); ?><br>
                        <b>Last updated:</b> <?php echo HTMLHelper::_('date', $ice->modified, 'd M y H:i'); ?>
                    </p>
                <?php endif; ?>
                <p><i>Only the member can change these, from the emergency contact page on the website.</i></p>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php echo $this->form->renderField('created_by'); ?>
    <?php echo $this->form->renderField('modified_by'); ?>



    <input type="hidden" name="task" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
