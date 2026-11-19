<?php

/**
 * @version    1.0.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_events\Site\Service;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Filter\OutputFilter;

/**
 * Class Ra_eventsRouter
 *
 */
class Router extends RouterView {

    private $noIDs;

    /**
     * The category factory
     *
     * @var    CategoryFactoryInterface
     *
     * @since  1.0.1
     */
    private $categoryFactory;

    /**
     * The category cache
     *
     * @var    array
     *
     * @since  1.0.1
     */
    private $categoryCache = [];

    /**
     * Event titles by id, for the address of an event page
     *
     * @var    array|null
     */
    private $titles = null;

    /**
     * @var    DatabaseInterface
     */
    private $db;

    public function __construct(SiteApplication $app, AbstractMenu $menu, CategoryFactoryInterface $categoryFactory, DatabaseInterface $db) {
        $params = Factory::getApplication()->getParams('com_ra_events');
        $this->noIDs = (bool) $params->get('sef_ids');
        $this->categoryFactory = $categoryFactory;
        $this->db = $db;

        $events = new RouterViewConfiguration('events');
        $this->registerView($events);

        $ccEvent = new RouterViewConfiguration('event');
        $ccEvent->setKey('id')->setParent($events);
        $this->registerView($ccEvent);

        $eventform = new RouterViewConfiguration('eventform');
        $eventform->setKey('id');
        $this->registerView($eventform);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Method to get the segment(s) for an event
     *
     * @param   string  $id     ID of the event to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     */
    public function getEventSegment($id, $query) {
        $id = (int) $id;
        if ($this->titles === null) {
            $this->titles = [];
            try {
                $q = $this->db->getQuery(true)
                    ->select($this->db->quoteName(['id', 'title']))
                    ->from($this->db->quoteName('#__ra_events'));
                $this->titles = $this->db->setQuery($q)->loadAssocList('id', 'title');
            } catch (\Throwable $e) {
                $this->titles = [];
            }
        }
        // "35-whitby-weekend": the number finds the event, the words are for people and search engines
        $slug = OutputFilter::stringURLSafe((string) ($this->titles[$id] ?? ''));
        return array($id => $slug === '' ? (string) $id : $id . '-' . $slug);
    }

    /**
     * Keep the list an event was opened from. Joomla otherwise files every event under the first events menu item
     * (e.g. a coach trip under Weekends Away), and its Back link then goes to the wrong list.
     *
     * @param   array  $query  An associative array of URL arguments
     *
     * @return  array  The URL arguments to use to assemble the subsequent URL.
     */
    public function preprocess($query) {
        $keep = 0;
        if (isset($query['Itemid']) && ($query['view'] ?? '') === 'event') {
            $item = $this->menu->getItem($query['Itemid']);
            if ($item && $item->component === 'com_ra_events' && ($item->query['view'] ?? '') === 'events') {
                $keep = (int) $query['Itemid'];
            }
        }
        $query = parent::preprocess($query);
        if ($keep) {
            $query['Itemid'] = $keep;
        }
        return $query;
    }

    /**
     * Leave out query values the menu item already holds (event_type_id), so a menu item's address has no query string
     *
     * @param   array  &$query  The vars that should be converted
     *
     * @return  array  The URL arguments to use to assemble the subsequent URL.
     */
    public function build(&$query) {
        if (isset($query['Itemid'], $query['event_type_id'])) {
            $item = $this->menu->getItem($query['Itemid']);
            if ($item && isset($item->query['event_type_id'])
                && (string) $item->query['event_type_id'] === (string) $query['event_type_id']
                && ($query['view'] ?? ($item->query['view'] ?? '')) === ($item->query['view'] ?? '')) {
                unset($query['event_type_id']);
            }
        }
        return parent::build($query);
    }

    /**
     * Method to get the segment(s) for an eventform
     *
     * @param   string  $id     ID of the eventform to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     */
    public function getEventformSegment($id, $query) {
        return array((int) $id => (string) (int) $id);
    }

    /**
     * Method to get the segment(s) for an event
     *
     * @param   string  $segment  Segment of the event to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     */
    public function getEventId($segment, $query) {
        return (int) $segment;
    }

    /**
     * Method to get the segment(s) for an eventform
     *
     * @param   string  $segment  Segment of the eventform to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     */
    public function getEventformId($segment, $query) {
        return $this->getEventId($segment, $query);
    }

    /**
     * Method to get categories from cache
     *
     * @param   array  $options   The options for retrieving categories
     *
     * @return  CategoryInterface  The object containing categories
     *
     * @since   1.0.1
     */
    private function getCategories(array $options = []): CategoryInterface {
        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }

}
