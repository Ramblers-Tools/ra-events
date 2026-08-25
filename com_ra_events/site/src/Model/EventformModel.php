<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers
 */

namespace Ramblers\Component\Ra_events\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;

/**
 * Front-end Event create/edit model.
 *
 * Organisers may create a new event, or edit an event they own (identified via
 * #__ra_events.contact_id -> #__contact_details.user_id). Users with the
 * core.edit ACL action may edit any event.
 *
 * @since  1.0.0
 */
class EventformModel extends FormModel implements CurrentUserInterface {

    private $item = null;

    /**
     * Returns the contact_id belonging to the current user, or 0 if they have none.
     *
     * @return  int
     */
    private function currentContactId() {
        $eventsHelper = new EventsHelper;
        $contact_id = $eventsHelper->lookupContactid();
        return is_null($contact_id) ? 0 : (int) $contact_id;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_events');

        if ($app->input->get('layout') == 'edit') {
            $id = $app->getUserState('com_ra_events.edit.event.id');
        } else {
            $id = $app->input->get('id');
            $app->setUserState('com_ra_events.edit.event.id', $id);
        }

        $this->setState('event.id', $id);
        $this->setState('params', $app->getParams());
    }

    /**
     * Method to get an item.
     *
     * @param   integer  $id  The id of the item to get.
     *
     * @return  object
     *
     * @throws  \Exception
     */
    public function getItem($id = null) {
        if ($this->item !== null) {
            return $this->item;
        }

        if (empty($id)) {
            $id = $this->getState('event.id');
        }

        $table = $this->getTable();
        $properties = $table->getProperties();
        $this->item = ArrayHelper::toObject($properties, CMSObject::class);

        if ($table->load($id) && !empty($table->id)) {
            $user = $this->getCurrentUser();
            $ownContact = $this->currentContactId();

            $canEdit = $user->authorise('core.edit', 'com_ra_events')
                    || ($ownContact > 0 && $ownContact == $table->contact_id);

            if (!$canEdit) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $properties = $table->getProperties(1);
            $this->item = ArrayHelper::toObject($properties, CMSObject::class);
        } else {
            // New event - step 1 (choose type) already recorded the type in userstate
            $this->item->event_type_id = (int) Factory::getApplication()->getUserState('com_ra_events.edit.event.type_id', 0);
        }

        return $this->item;
    }

    /**
     * Method to get the table.
     *
     * Reuses the administrator Event table - front-end saves go through the
     * same persistence/validation logic as admin-created events.
     *
     * @param   string  $type    Name of the Table class
     * @param   string  $prefix  Optional prefix for the table class name
     * @param   array   $config  Optional configuration array for Table object
     *
     * @return  Table|boolean
     */
    public function getTable($type = 'Event', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to check in an item.
     *
     * @param   integer  $id  The id of the row to check in.
     *
     * @return  boolean
     */
    public function checkin($id = null) {
        $id = (!empty($id)) ? $id : (int) $this->getState('event.id');

        if ($id) {
            $table = $this->getTable();

            if (method_exists($table, 'checkin')) {
                if (!$table->checkin($id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to check out an item for editing.
     *
     * @param   integer  $id  The id of the row to check out.
     *
     * @return  boolean
     */
    public function checkout($id = null) {
        $id = (!empty($id)) ? $id : (int) $this->getState('event.id');

        if ($id) {
            $table = $this->getTable();
            $user = $this->getCurrentUser();

            if (method_exists($table, 'checkout')) {
                if (!$table->checkout($user->get('id'), $id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to get the event form.
     *
     * @param   array    $data      An optional array of data for the form to interrogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true) {
        $form = $this->loadForm('com_ra_events.eventform', 'eventform', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_events.edit.event.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data ? $data : array();
    }

    /**
     * Method to save the form data.
     *
     * New events are always created unpublished (state=0) pending admin review,
     * always bookable, and are always owned by the current user's own contact
     * record - contact_id is never taken from posted data.
     *
     * @param   array  $data  The form data
     *
     * @return  int|boolean  The event id on success, false on failure
     *
     * @throws  \Exception
     */
    public function save($data) {
        $id = (!empty($data['id'])) ? (int) $data['id'] : 0;
        $user = $this->getCurrentUser();
        $ownContact = $this->currentContactId();

        $table = $this->getTable();

        if ($id) {
            $table->load($id);

            $canEdit = $user->authorise('core.edit', 'com_ra_events')
                    || ($ownContact > 0 && $ownContact == $table->contact_id);

            if (!$canEdit) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            // Never let a posted contact_id/state/event_type_id hijack ownership,
            // bypass moderation, or change the type after step 1 has set it.
            $data['contact_id'] = $table->contact_id;
            $data['state'] = $table->state;
            $data['event_type_id'] = $table->event_type_id;
        } else {
            if ($ownContact == 0) {
                throw new \Exception('You are not registered as a Contact, so cannot create an Event. Please contact the site administrator.', 403);
            }

            $type_id = (int) Factory::getApplication()->getUserState('com_ra_events.edit.event.type_id', 0);
            if ($type_id <= 0) {
                throw new \Exception('Please choose an event type first.', 400);
            }

            $data['contact_id'] = $ownContact;
            $data['state'] = 0;
            $data['bookable'] = 1;
            $data['event_type_id'] = $type_id;
        }

        if (!$table->bind($data)) {
            Factory::getApplication()->enqueueMessage($table->getError(), 'error');
            return false;
        }

        if (!$table->check()) {
            Factory::getApplication()->enqueueMessage($table->getError(), 'error');
            return false;
        }

        try {
            if ($table->store() === true) {
                return $table->id;
            } else {
                Factory::getApplication()->enqueueMessage($table->getError(), 'error');
                return false;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if data can be saved.
     *
     * @return  bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }

}
