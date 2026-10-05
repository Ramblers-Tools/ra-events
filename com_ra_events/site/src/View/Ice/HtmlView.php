<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/10/26 RH created - member's own In Case of Emergency details
 */

namespace Ramblers\Component\Ra_events\Site\View\Ice;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use Ramblers\Component\Ra_events\Site\Helpers\IceHelper;

/**
 * View class for a member's own emergency contact details.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView {

    public $item;
    public $menu_id;
    public $page_intro;

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
        $user_id = $app->getIdentity()->id;
        if ($user_id == 0) {
            throw new \Exception('You must be logged in to manage your emergency contact details', 403);
        }

        $iceHelper = new IceHelper;
        $this->item = $iceHelper->getForUser($user_id);
        $this->menu_id = $app->input->getInt('Itemid');
        $this->page_intro = $app->getParams('com_ra_events')->get('page_intro', '');

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
        $this->document->setTitle('My emergency contact');
    }

}
