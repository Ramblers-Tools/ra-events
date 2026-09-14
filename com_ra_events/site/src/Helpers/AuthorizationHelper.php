<?php

/**
 * @version    2.5.1
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 14/09/26 CB Created - Event authorization checks
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Helper class for authorization checks on Events
 *
 * @since  2.5.1
 */
class AuthorizationHelper {

    protected $db;
    protected $toolsHelper;
    protected $user;

    public function __construct() {
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->toolsHelper = new ToolsHelper;
        $this->user = Factory::getApplication()->getIdentity();
    }

    /**
     * Check if user is authorized to view an event
     * 
     * Rules:
     * - User must be logged in
     * - Event must be published (state = 1)
     * - Event must not be before today
     * - Either user is a superuser
     * - OR user is member of security group com_ra_events
     * - OR user is the organizer of the event
     *
     * @param   object  $event  Event object with id, state, event_date, contact_id
     * 
     * @return  void throws Exception if not authorized
     * 
     * @throws  Exception
     * @since   2.5.1
     */
    public function checkViewEventAuthorization($event) {
        
        // Check if user is logged in
        if ($this->user->id == 0) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        // Check if event is unpublished (state != 1)
        if ($event->state != 1) {
            throw new \Exception('Event is not published', 403);
        }

        // Check if event is in the past
        $today = new \DateTime('today');
        $eventDate = new \DateTime($event->event_date);
        
        if ($eventDate < $today) {
            throw new \Exception('Event is in the past and cannot be viewed', 403);
        }

        // Check authorization: superuser, group member, or organizer
        if (!$this->isAuthorizedUser($event)) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Check if current user is authorized to access this event
     * 
     * Authorization is granted if ANY of these are true:
     * - User is a superuser
     * - User is member of com_ra_events security group
     * - User is the organizer (via contact_id -> contact_details.user_id)
     *
     * @param   object  $event  Event object with contact_id
     * 
     * @return  boolean True if authorized, false otherwise
     * @since   2.5.1
     */
    public function isAuthorizedUser($event) {
        
        // Check if superuser
        if ($this->toolsHelper->isSuperuser()) {
            return true;
        }

        // Check if member of com_ra_events group
        if ($this->isGroupMember('com_ra_events')) {
            return true;
        }

        // Check if organizer (contact_id -> contact_details.user_id)
        if ($this->isEventOrganizer($event)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is member of specified group
     *
     * @param   string  $groupName  Name of the group to check
     * 
     * @return  boolean True if user is member, false otherwise
     * @since   2.5.1
     */
    protected function isGroupMember($groupName) {
        $query = $this->db->getQuery(true);
        $query->select('COUNT(*)')
            ->from($this->db->quoteName('#__user_usergroup_map'))
            ->innerJoin($this->db->quoteName('#__usergroups') . ' ON ' . 
                $this->db->quoteName('#__usergroups.id') . ' = ' . 
                $this->db->quoteName('#__user_usergroup_map.group_id'))
            ->where($this->db->quoteName('#__user_usergroup_map.user_id') . ' = ' . (int) $this->user->id)
            ->where($this->db->quoteName('#__usergroups.title') . ' = ' . $this->db->quote($groupName));
        
        $this->db->setQuery($query);
        return ($this->db->loadResult() > 0);
    }

    /**
     * Check if current user is the organizer of the event
     * 
     * Organizer is determined by: event.contact_id -> contact_details.user_id
     *
     * @param   object  $event  Event object with contact_id
     * 
     * @return  boolean True if user is organizer, false otherwise
     * @since   2.5.1
     */
    protected function isEventOrganizer($event) {
        
        if (empty($event->contact_id)) {
            return false;
        }

        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__contact_details'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $event->contact_id);
        
        $this->db->setQuery($query);
        $organizerUserId = $this->db->loadResult();

        return ($organizerUserId == $this->user->id);
    }

    /**
     * Check if user can edit an event in the list view
     * 
     * Edit icon shown if:
     * - User has core.create permission for com_ra_events AND
     * - Either user is in com_ra_events group (can edit all events)
     * - OR user is the organizer of the event (can edit own event)
     *
     * @param   object  $event  Event object with contact_id
     * 
     * @return  boolean True if user can edit, false otherwise
     * @since   2.5.1
     */
    public function canEditEvent($event) {
        
        // Must have create permission
        if (!$this->user->authorise('core.create', 'com_ra_events')) {
            return false;
        }

        // If in com_ra_events group, can edit all events
        if ($this->isGroupMember('com_ra_events')) {
            return true;
        }

        // Otherwise, can only edit own events
        return $this->isEventOrganizer($event);
    }

    /**
     * Check if user can see "New" button (create event)
     * 
     * Shows if user has core.create permission for com_ra_events
     *
     * @return  boolean True if user can create, false otherwise
     * @since   2.5.1
     */
    public function canCreateEvent() {
        return $this->user->authorise('core.create', 'com_ra_events');
    }
}
