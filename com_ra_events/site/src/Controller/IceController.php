<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/10/26 RH created - member's own In Case of Emergency details
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\IceHelper;

/**
 * Lets a logged-in member record their own emergency contact details.
 *
 * The member is always taken from the session - there is no request parameter
 * that can address somebody else's record.
 *
 * @since  1.0.0
 */
class IceController extends BaseController {

    protected $app;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
    }

    public function save() {
        $this->checkToken();

        $user_id = Factory::getApplication()->getIdentity()->id;
        if ($user_id == 0) {
            throw new \Exception('You must be logged in to save emergency contact details', 403);
        }

        $name = trim($this->app->input->getString('contact_name', ''));
        $relationship = trim($this->app->input->getString('relationship', ''));
        $phone = trim($this->app->input->getString('phone', ''));

        $allBlank = ($name === '' && $relationship === '' && $phone === '');
        if (!$allBlank && ($name === '' || $relationship === '' || $phone === '')) {
            $this->app->enqueueMessage('Please give the contact name, their relationship to you, and a telephone number', 'error');
            $this->redirectToIce();
            return;
        }

        $iceHelper = new IceHelper;
        $iceHelper->saveForUser($user_id, $name, $relationship, $phone);

        if ($allBlank) {
            $this->app->enqueueMessage('Your emergency contact details have been removed', 'info');
        } else {
            $this->app->enqueueMessage('Your emergency contact details have been saved', 'info');
        }
        $this->redirectToIce();
    }

    private function redirectToIce() {
        $menu_id = $this->app->input->getInt('Itemid', 0);
        $target = 'index.php?option=com_ra_events&view=ice';
        if ($menu_id > 0) {
            $target .= '&Itemid=' . $menu_id;
        }
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

}
