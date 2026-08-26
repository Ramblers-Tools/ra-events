<?php

/**
 * @version    2.4.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 03/05/25 CB correct cancel
 * 08/05/25 CB set up event_id in userstate
 * 26/07/25 Allow both Add and Update
 * 29/09/25 CB check for cancel when creating a booking from an email
 * 01/11/25 CB correct redirection
 * 08/12/25 CB correct display of year on confirm
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Booking class.
 *
 * @since  2.0
 */
class BookingformController extends FormController {

    /**
     * Method to abort current operation
     *
     * @return void
     *
     * @throws Exception
     */
    public function cancel($key = NULL) {
        // The booking for could have been invoked in one of several ways:
        // from creating a booking via link in an email - return to list of evemts
        // By a user starting to book - return to the event form
        // By the organiser starting to make a change - returm to list of booking
        // By the organiser select a profile, then quitting
        $callback = $this->app->getUserState('com_ra_events.bookingform.callback');
//      event_id cannot be passed to the view directly, so it is stored in the State
        $event_id = $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
        if ($event_id == 0) {
            throw new \Exception("Can't find event number", 403);
        }

        if ($callback == 'profiles') {
//            $event_id = $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
            $url = 'index.php?option=com_ra_events&view=profiles&event_id=' . $event_id;
        } elseif ($callback == 'showBookings') {
            $url = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=' . $event_id;
        } elseif ($callback == 'email') {
            $url = 'index.php';
        } else {
            // Get the current edit id.
            $editId = (int) $this->app->getUserState('com_ra_events.edit.bookingform.id');
//        die("cancel: $editId");

            if ($editId > 0) {
                // Get the model.
                $model = $this->getModel('Bookingform', 'Site');
                // Check in the item
                if ($editId) {
                    $model->checkin($editId);
                }

//                $event_id = $this->lookupEvent($editId);
                $url = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=' . $event_id;
                //                   }
            }
            // Parameters will have been saved in the Event view
            $id = Factory::getApplication()->getUserState('com_ra_events.event.id', '0');
            if ($id == 0) {
                // Cancel from creating a booking via link in an email
                $url = 'index.php';
            }
            $layout = Factory::getApplication()->getUserState('com_ra_events.event.layout');
            $menu_id = Factory::getApplication()->getUserState('com_ra_events.event.menu_id');
            $url = 'index.php?option=com_ra_events&view=event&id=' . $id;
            $url .= '&Itemid=' . $menu_id . '&layout=' . $layout;
        }
        $this->setRedirect(Route::_($url, false));
        $this->redirect();
    }

    public function confirm() {
        $id = $this->app->getUserState('com_ra_events.bookingform.id');
        $toolsHelper = new ToolsHelper;
        if ($id == 0) {
            $this->app->enqueueMessage('Booking id not given ', 'error');
        } else {
            $bookingHelper = new BookingHelper;
            // Display details of the booking
            echo $bookingHelper->getBookingDetails($id);
            echo '<h4>The list of bookings is now:</h4>';
            $event_id = $this->lookupEvent($booking_id);
            $sql = 'SELECT p.preferred_name, s.title, b.state, b.created ';
            $sql .= 'FROM #__ra_profiles AS p ';
            $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id=p.id ';
            $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state ';
            $sql .= 'WHERE b.event_id=' . $event_id;
            $sql .= ' ORDER BY b.created';
            $rows = $toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                echo HTMLHelper::_('date', $row->created, 'd M y H:i') . ',' . $row->preferred_name . ', ' . $row->title . '<br>';
            }
        }
        // Clear the booking id from the session.
        $this->app->setUserState('com_ra_events.edit.booking.id', null);

        $target = 'index.php?option=com_ra_events&view=events';
        echo $toolsHelper->buildButton($target, 'List all events');
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  void
     *
     * @since   2.0
     *
     * @throws  Exception
     */
    public function edit($key = NULL, $urlVar = NULL) {
        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
        $editId = $this->input->getInt('id', 0);
        $event_id = $this->input->getInt('event_id', 0);

        // Set the booking id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.booking.id', $editId);

        // Set the event id for use by the booking form
        $this->app->setUserState('com_ra_events.bookingform.event_id', $event_id);

        // Get the model.
        $model = $this->getModel('Bookingform', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }
        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit', false));
    }

    private function lookupEvent($booking_id) {
        if ($booking_id == 0) {
            $event_id = $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
        } else {
            $sql = 'SELECT event_id FROM #__ra_bookings WHERE id=' . $booking_id;
            $toolsHelper = new ToolsHelper;
            $event_id = $toolsHelper->getValue($sql);
        }
        return $event_id;
    }

    /**
     * Method to save data.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   2.0
     */
    public function save($key = NULL, $urlVar = NULL) {
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $model = $this->getModel('Bookingform', 'Site');

        // Get the user data.
        $data = $this->input->get('jform', array(), 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }

        // Send an object which can be modified through the plugin event
        $objData = (object) $data;
        $this->app->triggerEvent(
                'onContentNormaliseRequestData',
                array($this->option . '.' . $this->context, $objData, $form)
        );

        $data = (array) $objData;

        // Validate the posted data.
        $data = $model->validate($form, $data);
        $error = false;

        $event_id = (int) $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT multi_guest_enabled, max_guests, max_bookings FROM #__ra_events WHERE id=' . $event_id;
        $event = $toolsHelper->getItem($sql);
        $isMultiGuestEvent = (!is_null($event) && $event->multi_guest_enabled == 1);

        if ($isMultiGuestEvent) {
            $guestCount = $this->input->getInt('guest_count', 0);
            $postedGuests = $this->input->get('guests', array(), 'array');
            $postedGuests = array_map('trim', array_slice($postedGuests, 0, $guestCount));

            if ($guestCount > (int) $event->max_guests) {
                $this->app->enqueueMessage('Too many guests selected for this event', 'error');
                $error = true;
            } elseif (count(array_filter($postedGuests, function ($n) {
                        return $n !== '';
                    })) < $guestCount) {
                $this->app->enqueueMessage('All guest names must be given', 'error');
                $error = true;
            } elseif ($this->app->getUserState('com_ra_events.bookingform.waitlist', 0)) {
                // This booking is deliberately going onto the waiting list (organiser
                // used "Add to wait list", or the event was already full when the
                // booker started) - it's meant to exceed max_bookings, so the capacity
                // check below doesn't apply here.
            } else {
                // Server-side capacity enforcement - the form only offers options that
                // fit at the time it was rendered, but re-check here too since capacity
                // may have changed between rendering and submitting.
                $editId = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
                $existingOwnPlaces = 0;
                if ($editId) {
                    $sql = 'SELECT num_places FROM #__ra_bookings WHERE id=' . $editId;
                    $existingOwnPlaces = (int) $toolsHelper->getValue($sql);
                }
                $sql = 'SELECT SUM(num_places) AS tot FROM #__ra_bookings ';
                $sql .= 'WHERE event_id=' . $event_id . ' AND state IN (0,1)';
                $activePlaces = (int) $toolsHelper->getValue($sql);
                $requested = $guestCount + 1;
                if (($activePlaces - $existingOwnPlaces + $requested) > $event->max_bookings) {
                    $this->app->enqueueMessage('Not enough places left for that many guests - please reduce the number of guests or use the waiting list', 'error');
                    $error = true;
                }
            }

            if (!$error) {
                $data['num_places'] = $guestCount + 1;
                $data['partner'] = '';
                $data['_guests'] = $postedGuests;
            }
        } elseif (($data["num_places"] == 2) AND ($data["partner"] == '')) {
            $this->app->enqueueMessage('Name of second person must be given', 'error');
            $error = true;
        }
        // Check for errors.
        if (($data === false) OR ($error == true)) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage('Controller: ' . $errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage('Controller: ' . $errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');

            // Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit&id=' . $id, false));

            $this->redirect();
        }
        // Retrieve working variables from the state
        $user_id = Factory::getApplication()->getUserState('com_ra_events.bookingform.user_id', 0);
        $callback = Factory::getApplication()->getUserState('com_ra_events.bookingform.callback', '');
        $event_id = $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
        //       echo "user $user_id<br>";
        $data['user_id'] = (int) $user_id;
        // Attempt to save the data.
        // Extra processing to send emails etc. is done in the save function of the Model
        $return = $model->save($data);
        if ($return === false) {
            $this->app->enqueueMessage('Controller: Save failed: ' . $model->getError(), 'error');
//        } else  {
//            $this->app->enqueueMessage('Controller: Save successful', 'error');
//            $this->app->enqueueMessage('Controller: return=' . $return, 'error');
        }
//       die('Controller after save');
        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', $data);

            // Redirect back to the edit screen.
//            $id = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
            $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit&id=' . $event_id, false));
            $this->redirect();
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }
        // Should say created/updated as appropriate
        if (!empty($return)) {
            $this->setMessage(Text::_('Booking updated'));
        }

        if ($event_id == 0) {
            throw new \Exception("Can't find event number", 403);
        }

        // Redirect as appropriate
        if ($callback == 'showBookings') {
            $target = 'index.php?option=com_ra_events&Itemid=&task=booking.showBookings&event_id=' . $event_id;
            $target .= '&Itemid=' . $menu->id;
//        $this->app->enqueueMessage('Redirecting to ' . $target, 'info');
        } elseif ($callback == 'profiles') {
            $target = 'index.php?option=com_ra_events&view=profiles';
        } elseif ($callback == 'email') {
            $target = 'index.php?option=com_ra_events&task=bookingform.confirm';
        } else {
            // Creating a new booking, parameters will have been saved in the Event view
            $id = Factory::getApplication()->getUserState('com_ra_events.event.id');
            $layout = Factory::getApplication()->getUserState('com_ra_events.event.layout');
            $menu_id = Factory::getApplication()->getUserState('com_ra_events.event.menu_id');
            $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
            $target .= '&Itemid=' . $menu_id . '&layout=' . $layout;
        }
        $this->setRedirect(Route::_($target, false));

        // Flush the data from the session.
        $this->app->setUserState('com_ra_events.edit.booking.data', null);

        // Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $data);
        $this->redirect();
    }

    /**
     * Method to remove data
     *
     * @return  void
     *
     * @throws  Exception
     *
     * @since   2.0
     */
    public function remove() {
        $model = $this->getModel('Bookingform', 'Site');
        $pk = $this->input->getInt('id');

        // Attempt to save the data
        try {
            // Check in before delete
            $return = $model->checkin($return);
            // Clear id from the session.
            $this->app->setUserState('com_ra_events.edit.booking.id', null);

            $menu = $this->app->getMenu();
            $item = $menu->getActive();
            $url = (empty($item->link) ? 'index.php?option=com_ra_events&view=bookings' : $item->link);

            if ($return) {
                $model->delete($pk);
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_SUCCESSFULLY'));
            } else {
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_UNSUCCESSFULLY'), 'warning');
            }


            $this->setRedirect(Route::_($url, false));
            // Flush the data from the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', null);
        } catch (\Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
            $this->setRedirect('index.php?option=com_ra_events&view=bookings');
        }
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     *
     * @param   BaseDatabaseModel  $model      The data model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = array()) {

    }

}
