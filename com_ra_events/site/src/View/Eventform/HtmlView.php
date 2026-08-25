<?php

/**
 * @version    1.0.0
 * @package    com_ra_events
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/08/26 RH created - front-end event create/edit for organisers
 */

namespace Ramblers\Component\Ra_events\Site\View\Eventform;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;

/**
 * View class for the front-end Event create/edit form.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $item;
    protected $form;
    protected $canSave;
    protected $params;

    /**
     * Display the view.
     *
     * @param   string  $tpl  Template name
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null) {
        $app = Factory::getApplication();

        $this->item = $this->get('Item');
        $this->form = $this->get('Form');
        $this->canSave = $this->get('CanSave');
        $this->params = $app->getParams('com_ra_events');

        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document.
     *
     * @return  void
     */
    protected function _prepareDocument() {
        $app = Factory::getApplication();

        $title = (empty($this->item->id)) ? 'Create Event' : 'Edit Event: ' . $this->item->title;

        $this->document->setTitle($title);
    }

}
