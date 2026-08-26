<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/08/26 RH created - self-service Manage My Booking
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Self-service "Manage My Booking" controller.
 *
 * Every task here reloads the booking fresh via BookingHelper::getOwnedBooking(),
 * which throws if the current user does not own it - never trust posted
 * event_id/user_id/state.
 *
 * @since  1.0.0
 */
class ManagebookingController extends BaseController {

    protected $app;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
    }

    /**
     * Update guest names (and, while still Provisional/Waitlisted, the guest
     * count / partner) for the current user's own booking.
     *
     * @return void
     */
    public function save() {
        $this->checkToken();

        $id = $this->app->input->getInt('id', 0);
        $toolsHelper = new ToolsHelper;
        $bookingHelper = new BookingHelper;
        $item = $bookingHelper->getOwnedBooking($id);
        $canEditGuestCount = in_array((int) $item->state, array(0, -1));

        if ($item->multi_guest_enabled == 1) {
            $postedGuests = array_map('trim', $this->app->input->get('guests', array(), 'array'));

            if ($canEditGuestCount) {
                $guestCount = max(0, $this->app->input->getInt('guest_count', 0));
                $postedGuests = array_slice($postedGuests, 0, $guestCount);

                if ($guestCount > (int) $item->max_guests) {
                    $this->app->enqueueMessage('Too many guests selected for this event', 'error');
                    $this->redirectToManage($id);
                    return;
                }
                if (count(array_filter($postedGuests, function ($n) {
                            return $n !== '';
                        })) < $guestCount) {
                    $this->app->enqueueMessage('All guest names must be given', 'error');
                    $this->redirectToManage($id);
                    return;
                }
                // Capacity re-check, excluding this booking's own existing places
                $sql = 'SELECT SUM(num_places) AS tot FROM #__ra_bookings ';
                $sql .= 'WHERE event_id=' . (int) $item->event_id . ' AND state IN (0,1) ';
                $sql .= 'AND id != ' . (int) $item->id;
                $activePlaces = (int) $toolsHelper->getValue($sql);
                $requested = $guestCount + 1;
                if (($activePlaces + $requested) > $item->max_bookings) {
                    $this->app->enqueueMessage('Not enough places left for that many guests - please reduce the number of guests or contact the organiser', 'error');
                    $this->redirectToManage($id);
                    return;
                }
                $newNumPlaces = $requested;
            } else {
                // Locked - the number of guests cannot change, only their names
                $existingCount = max(0, (int) $item->num_places - 1);
                $postedGuests = array_slice($postedGuests, 0, $existingCount);
                if (count(array_filter($postedGuests, function ($n) {
                            return $n !== '';
                        })) < $existingCount) {
                    $this->app->enqueueMessage('All guest names must be given', 'error');
                    $this->redirectToManage($id);
                    return;
                }
                $newNumPlaces = (int) $item->num_places;
            }
            $bookingHelper->saveGuests($item->id, $postedGuests);
            $sql = 'UPDATE #__ra_bookings SET num_places=' . (int) $newNumPlaces . ' WHERE id=' . (int) $item->id;
            $toolsHelper->executeCommand($sql);
        } else {
            // Legacy single-partner booking - the Single/Two-person choice itself is
            // never editable here (matches the main booking form no longer offering
            // it once guest bookings are off), only an existing partner's name can
            // be corrected.
            if ((int) $item->num_places == 2) {
                $partner = trim($this->app->input->getString('partner', ''));
                if ($partner == '') {
                    $this->app->enqueueMessage("The other person's name must be given", 'error');
                    $this->redirectToManage($id);
                    return;
                }
                $db = Factory::getDbo();
                $sql = 'UPDATE #__ra_bookings SET partner=' . $db->quote($partner) . ' WHERE id=' . (int) $item->id;
                $toolsHelper->executeCommand($sql);
            }
        }

        $this->app->enqueueMessage('Your booking has been updated', 'info');
        $this->redirectToManage($id);
    }

    /**
     * Cancel the current user's own booking - or, if it's already Confirmed,
     * send a cancellation request to the organiser instead of cancelling it.
     *
     * @return void
     */
    public function cancel() {
        $this->checkToken();

        $id = $this->app->input->getInt('id', 0);
        $bookingHelper = new BookingHelper;
        $item = $bookingHelper->getOwnedBooking($id);
        $menu_id = $this->app->input->getInt('Itemid', 0);
        $target = 'index.php?option=com_ra_events&view=event&id=' . $item->event_id . '&Itemid=' . $menu_id;

        if (in_array((int) $item->state, array(0, -1))) {
            $bookingHelper->cancelBooking($item->id, $item->user_id);
            $bookingHelper->notifyOrganiserOfSelfCancellation($item->id);
            $this->app->enqueueMessage('Your booking has been cancelled', 'info');
        } elseif ((int) $item->state == 1) {
            $bookingHelper->notifyOrganiserOfCancelRequest($item->id);
            $target .= '&cancelRequested=1';
        } else {
            $this->app->enqueueMessage('This booking is already cancelled', 'info');
        }

        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    private function redirectToManage($id) {
        $menu_id = $this->app->input->getInt('Itemid', 0);
        $target = 'index.php?option=com_ra_events&view=managebooking&id=' . (int) $id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

}
