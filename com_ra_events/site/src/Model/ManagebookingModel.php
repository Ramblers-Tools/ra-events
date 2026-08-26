<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/08/26 RH created - self-service Manage My Booking
 */

namespace Ramblers\Component\Ra_events\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Model for the self-service "Manage My Booking" page.
 *
 * @since  1.0.0
 */
class ManagebookingModel extends BaseDatabaseModel implements CurrentUserInterface {

    private $item = null;

    /**
     * Load the booking (joined with its event), checking that the current user
     * owns it. This is the single place ownership is enforced for this feature -
     * every entry point (the GET view and both POST tasks) must call this rather
     * than trusting a posted event_id/user_id.
     *
     * @param   int  $id  Booking id. Falls back to the request 'id' if omitted.
     *
     * @return  object
     *
     * @throws  \Exception
     */
    public function getItem($id = null) {
        if ($this->item === null) {
            if (empty($id)) {
                $id = Factory::getApplication()->input->getInt('id', 0);
            }
            $toolsHelper = new ToolsHelper;
            $sql = 'SELECT b.id, b.event_id, b.user_id, b.num_places, b.partner, b.state, ';
            $sql .= 'e.title, e.event_date, e.multi_guest_enabled, e.max_guests, e.max_bookings ';
            $sql .= 'FROM #__ra_bookings AS b ';
            $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
            $sql .= 'WHERE b.id=' . (int) $id;
            $item = $toolsHelper->getItem($sql);

            if (is_null($item)) {
                throw new \Exception('Booking not found', 404);
            }

            $currentUserId = $this->getCurrentUser()->id;
            if ($currentUserId == 0) {
                throw new \Exception('You must be logged in to manage a booking', 403);
            }
            if ((int) $item->user_id !== (int) $currentUserId) {
                throw new \Exception('You are not authorised to manage this booking', 403);
            }

            $this->item = $item;
        }

        return $this->item;
    }

}
