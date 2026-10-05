<?php

/**
 * In Case of Emergency (ICE) contact details, held once per member and shown
 * to the organiser of events flagged with requires_ice.
 *
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Ramblers Tools
 * @copyright  2026 Ramblers Tools
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/10/26 RH created
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class IceHelper {

    protected $toolsHelper;

    function __construct() {
        $this->toolsHelper = new ToolsHelper;
    }

    /**
     * The ICE details held for one member, or null if they haven't given any.
     *
     * @param   int  $user_id  Joomla user id
     * @return  object|null
     */
    public function getForUser($user_id) {
        if ((int) $user_id == 0) {
            return null;
        }
        $sql = 'SELECT contact_name, relationship, phone, modified ';
        $sql .= 'FROM #__ra_ice_contacts WHERE user_id=' . (int) $user_id;
        return $this->toolsHelper->getItem($sql);
    }

    /**
     * ICE details for everyone booked onto an event, keyed by user id - avoids
     * an N+1 query when listing a whole event's attendees.
     *
     * @param   int  $event_id
     * @return  array  [user_id => object]
     */
    public function getForEvent($event_id) {
        $sql = 'SELECT i.user_id, i.contact_name, i.relationship, i.phone ';
        $sql .= 'FROM #__ra_ice_contacts AS i ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id = i.user_id ';
        $sql .= 'WHERE b.event_id=' . (int) $event_id . ' ';
        $sql .= 'GROUP BY i.user_id, i.contact_name, i.relationship, i.phone';
        $rows = $this->toolsHelper->getRows($sql);
        $result = array();
        if ($rows === false) {
            return $result;
        }
        foreach ($rows as $row) {
            $result[$row->user_id] = $row;
        }
        return $result;
    }

    /**
     * Replace a member's ICE details. Passing all three fields blank removes
     * the record entirely, which is how a member withdraws them.
     *
     * @param   int     $user_id       Joomla user id
     * @param   string  $name
     * @param   string  $relationship
     * @param   string  $phone
     * @return  bool
     */
    public function saveForUser($user_id, $name, $relationship, $phone) {
        if ((int) $user_id == 0) {
            return false;
        }
        $db = Factory::getDbo();
        $name = trim($name);
        $relationship = trim($relationship);
        $phone = trim($phone);

        $sql = 'DELETE FROM #__ra_ice_contacts WHERE user_id=' . (int) $user_id;
        $this->toolsHelper->executeCommand($sql);

        if ($name === '' && $relationship === '' && $phone === '') {
            return true;
        }

        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $sql = 'INSERT INTO #__ra_ice_contacts ';
        $sql .= '(user_id, contact_name, relationship, phone, created, modified) VALUES (';
        $sql .= (int) $user_id . ', ';
        $sql .= $db->quote($name) . ', ';
        $sql .= $db->quote($relationship) . ', ';
        $sql .= $db->quote($phone) . ', ';
        $sql .= $db->quote($date) . ', ' . $db->quote($date) . ')';
        $this->toolsHelper->executeCommand($sql);
        return true;
    }

    /**
     * One-line rendering of a member's ICE details for a table cell or email,
     * or a "not supplied" marker when there are none.
     *
     * @param   object|null  $ice  A record from getForUser()/getForEvent()
     * @return  string
     */
    public static function format($ice) {
        if (is_null($ice) || empty($ice->contact_name)) {
            return '<i>Not supplied</i>';
        }
        $out = htmlspecialchars($ice->contact_name);
        if (!empty($ice->relationship)) {
            $out .= ' (' . htmlspecialchars($ice->relationship) . ')';
        }
        if (!empty($ice->phone)) {
            $out .= ' - ' . htmlspecialchars($ice->phone);
        }
        return $out;
    }

}
