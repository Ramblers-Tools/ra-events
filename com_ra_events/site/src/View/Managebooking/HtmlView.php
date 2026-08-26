<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/08/26 RH created - self-service Manage My Booking
 */

namespace Ramblers\Component\Ra_events\Site\View\Managebooking;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for the self-service "Manage My Booking" page.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView {

    public $item;
    public $isMultiGuest;
    public $canEditGuestCount;
    public $existingGuests;
    public $menu_id;
    public $toolsHelper;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        $app = Factory::getApplication();
        $this->toolsHelper = new ToolsHelper;
        $bookingHelper = new BookingHelper;

        $id = $app->input->getInt('id', 0);
        $this->item = $bookingHelper->getOwnedBooking($id);

        $this->isMultiGuest = ($this->item->multi_guest_enabled == 1);
        $this->canEditGuestCount = in_array((int) $this->item->state, array(0, -1));
        $this->existingGuests = $bookingHelper->guestNames($this->item->id);
        $this->menu_id = $app->input->getInt('Itemid');

        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     */
    protected function _prepareDocument() {
        $this->document->setTitle('Manage My Booking');
    }

}
