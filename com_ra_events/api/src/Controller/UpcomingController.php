<?php

/**
 * The events feed for the Ramblers Tools app: GET /api/index.php/v1/ra_events/upcoming
 *
 * Public, no token needed. Lists this site's own upcoming events (not ones shared in from other
 * groups): confirmed events, and cancelled ones so the app can mark them, that have passed their
 * publication date and haven't ended. Provisional events are left out, as on the website.
 * Places taken count confirmed and pending bookings, as the booking page does.
 *
 * The format is described in the app's docs/events-feed.md.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_events\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_events\Site\Helpers\EventsFeedMapper;

class UpcomingController extends BaseController {

    /** Bookings change, so the app shouldn't keep a copy for long. */
    const MAX_AGE_SECONDS = 300;

    public function feed() {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $rows = $this->rows($db);
            $group = strtoupper(trim((string) ComponentHelper::getParams('com_ra_tools')->get('default_group', '')));
            $feed = EventsFeedMapper::feed($rows, $group, $this->groupName($db, $group), $this->siteRoot());
        } catch (\Throwable $e) {
            // Never pass on the message: it could describe the database.
            $this->send(500, ['error' => 'The events could not be loaded. Please try again later.']);
        }
        $this->send(200, $feed);
    }

    private function rows(DatabaseInterface $db): array {
        $placesTaken = $db->getQuery(true)
                ->select('COALESCE(SUM(b.num_places), 0)')
                ->from($db->quoteName('#__ra_bookings', 'b'))
                ->where('b.event_id = a.id')
                ->where('b.state IN (0, 1)');

        $query = $db->getQuery(true)
                ->select([
                    'a.id', 'a.title', 'a.event_date', 'a.event_date_end', 'a.event_time', 'a.details',
                    'a.location', 'a.state', 'a.bookable', 'a.max_bookings', 'a.requires_payment',
                    'a.waiting_list_enabled', 'a.booking_info',
                    't.description AS event_type',
                    'c.name AS contact_name',
                    '(' . $placesTaken . ') AS places_taken',
                ])
                ->from($db->quoteName('#__ra_events', 'a'))
                ->leftJoin($db->quoteName('#__ra_event_types', 't') . ' ON t.id = a.event_type_id')
                ->leftJoin($db->quoteName('#__contact_details', 'c') . ' ON c.id = a.contact_id')
                // Confirmed (1) and cancelled (-2); not provisional (0).
                ->where('a.state IN (1, -2)')
                // This site's own events, not ones shared in from other groups.
                ->where('a.api_site_id IS NULL')
                // Published, as on the website's events list.
                ->where('a.publication_date IS NOT NULL')
                ->where('DATEDIFF(a.publication_date, CURRENT_DATE) <= 0')
                // Not over yet: a weekend away still shows on its second day.
                ->where('COALESCE(a.event_date_end, a.event_date) >= CURRENT_DATE')
                ->order('a.event_date ASC, a.id ASC');

        return $db->setQuery($query)->loadAssocList() ?: [];
    }

    private function groupName(DatabaseInterface $db, string $group): string {
        if ($group !== '') {
            try {
                $query = $db->getQuery(true)
                        ->select($db->quoteName('name'))
                        ->from($db->quoteName('#__ra_groups'))
                        ->where($db->quoteName('code') . ' = :code')
                        ->bind(':code', $group);
                $name = trim((string) $db->setQuery($query)->loadResult());
                if ($name !== '') {
                    return $name;
                }
            } catch (\Throwable $e) {
                // Fall back to the site name.
            }
        }
        return (string) Factory::getApplication()->get('sitename', $group);
    }

    /** The site's own address. Under the API, Uri::root() ends in /api/. */
    private function siteRoot(): string {
        $root = rtrim(Uri::root(), '/');
        if (substr($root, -4) === '/api') {
            $root = substr($root, 0, -4);
        }
        return $root . '/';
    }

    private function send(int $status, array $body) {
        $app = Factory::getApplication();
        $app->setHeader('Status', (string) $status, true);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', $status === 200 ? 'public, max-age=' . self::MAX_AGE_SECONDS : 'no-store', true);
        $app->sendHeaders();
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
