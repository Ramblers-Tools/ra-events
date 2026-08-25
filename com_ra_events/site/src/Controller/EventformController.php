<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\FormController;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Joomla\CMS\Router\Route;

/**
 * Front-end Event create/edit controller.
 *
 * @since  1.0.0
 */
class EventformController extends FormController {

    /**
     * Method to check out an item for editing (or start a new one) and redirect
     * to the edit form.
     *
     * @return  void
     */
    public function edit($key = NULL, $urlVar = NULL) {
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.event.id');
        $editId = $this->input->getInt('id', 0);

        $this->app->setUserState('com_ra_events.edit.event.id', $editId);

        $model = $this->getModel('Eventform', 'Site');

        if ($editId) {
            $model->checkout($editId);
        }

        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit', false));
    }

    /**
     * Method to start creating a new Event.
     *
     * @return  void
     */
    public function add() {
        $this->app->setUserState('com_ra_events.edit.event.id', 0);
        $this->app->setUserState('com_ra_events.edit.event.data', null);

        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit', false));
    }

    /**
     * Method to abort current operation.
     *
     * @return  void
     */
    public function cancel($key = NULL) {
        $editId = (int) $this->app->getUserState('com_ra_events.edit.event.id');

        if ($editId > 0) {
            $model = $this->getModel('Eventform', 'Site');
            $model->checkin($editId);

            $url = 'index.php?option=com_ra_events&view=event&id=' . $editId;
        } else {
            $url = 'index.php?option=com_ra_events&view=events';
        }

        $this->app->setUserState('com_ra_events.edit.event.id', null);
        $this->app->setUserState('com_ra_events.edit.event.data', null);

        $this->setRedirect(Route::_($url, false));
        $this->redirect();
    }

    /**
     * Method to save data.
     *
     * @return  void
     */
    public function save($key = NULL, $urlVar = NULL) {
        $this->checkToken();

        $model = $this->getModel('Eventform', 'Site');
        $data = $this->input->get('jform', array(), 'array');

        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }

        $objData = (object) $data;
        $this->app->triggerEvent(
                'onContentNormaliseRequestData',
                array($this->option . '.eventform', $objData, $form)
        );
        $data = (array) $objData;

        $data = $model->validate($form, $data);

        if ($data === false) {
            $errors = $model->getErrors();

            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');
            $this->app->setUserState('com_ra_events.edit.event.data', $jform);

            $id = (int) $this->app->getUserState('com_ra_events.edit.event.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit&id=' . $id, false));
            $this->redirect();
            return;
        }

        try {
            $return = $model->save($data);
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->app->setUserState('com_ra_events.edit.event.data', $data);
            $id = (int) $this->app->getUserState('com_ra_events.edit.event.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit&id=' . $id, false));
            $this->redirect();
            return;
        }

        if ($return === false) {
            $this->setMessage(Text::sprintf('Save failed: %s', $model->getError()), 'warning');
            $this->app->setUserState('com_ra_events.edit.event.data', $data);
            $id = (int) $this->app->getUserState('com_ra_events.edit.event.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit&id=' . $id, false));
            $this->redirect();
            return;
        }

        $model->checkin($return);
        $this->app->setUserState('com_ra_events.edit.event.id', null);
        $this->app->setUserState('com_ra_events.edit.event.data', null);

        if (empty($data['id'])) {
            $this->app->enqueueMessage('Event created - it will be visible once reviewed by an administrator', 'success');
        } else {
            $this->app->enqueueMessage('Event updated successfully', 'success');
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event&id=' . $return, false));

        $this->postSaveHook($model, $data);
        $this->redirect();
    }

    /**
     * Function that allows child controller access to model data after save.
     *
     * @param   BaseDatabaseModel  $model      The data model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = array()) {

    }

}
