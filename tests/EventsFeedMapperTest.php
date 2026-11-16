<?php

// Checks the events feed for the Ramblers Tools app. Run with: php tests/EventsFeedMapperTest.php

define('_JEXEC', 1);
require dirname(__DIR__) . '/com_ra_events/site/src/Helpers/EventsFeedMapper.php';

use Ramblers\Component\Ra_events\Site\Helpers\EventsFeedMapper;

function check($condition, string $what): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $what);
    }
}

function row(array $changes = []): array {
    return array_replace([
        'id' => 42,
        'title' => 'Christmas Meal &amp; Quiz',
        'event_type' => 'Social event',
        'event_date' => '2026-12-12',
        'event_date_end' => null,
        'event_time' => '19:30',
        'details' => '<p>Three courses at the Bulls Head.</p><p>Vegetarian&nbsp;option.</p><script>x()</script>',
        'location' => "The Bulls Head,<br>Macclesfield",
        'contact_name' => 'Sam Organiser',
        'contact_can_email' => 1,
        'state' => 1,
        'bookable' => 1,
        'max_bookings' => 40,
        'places_taken' => 12,
        'requires_payment' => 1,
        'waiting_list_enabled' => 0,
        'booking_info' => '<p>£25, pay by 1 December.</p>',
    ], $changes);
}

$site = 'https://example.org.uk/';

// One event, all the fields.
$e = EventsFeedMapper::event(row(), $site);
check($e['id'] === '42', 'id is a string');
check($e['title'] === 'Christmas Meal & Quiz', 'title entities decoded');
check($e['type'] === 'Social event', 'type');
check($e['status'] === 'confirmed', 'status');
check($e['start'] === '2026-12-12T19:30:00+00:00', 'winter start: ' . $e['start']);
check(!isset($e['end']), 'no end for a one-day event');
check($e['all_day'] === false, 'has a time');
check($e['details'] === "Three courses at the Bulls Head.\n\nVegetarian option.", 'details: ' . json_encode($e['details']));
check($e['location'] === "The Bulls Head,\nMacclesfield", 'location: ' . json_encode($e['location']));
check($e['contact_name'] === 'Sam Organiser', 'contact');
check($e['contact_url'] === 'https://example.org.uk/index.php?option=com_ra_tools&task=system.eventOrganiser&id=42', 'contact_url: ' . $e['contact_url']);
check(!isset(EventsFeedMapper::event(row(['contact_can_email' => 0]), $site)['contact_url']), 'no contact_url without an email');
check(!isset(EventsFeedMapper::event(row(['contact_can_email' => null]), $site)['contact_url']), 'no contact_url without a contact');
check(!isset(EventsFeedMapper::event(row(), '')['contact_url']), 'no contact_url without a site address');
check($e['url'] === 'https://example.org.uk/index.php?option=com_ra_events&view=event&id=42', 'url: ' . $e['url']);
check($e['booking'] === [
    'bookable' => true, 'max_places' => 40, 'places_taken' => 12,
    'requires_payment' => true, 'waiting_list' => false,
], 'booking: ' . json_encode($e['booking']));

// Summer time, and times written with a dot.
$e = EventsFeedMapper::event(row(['event_date' => '2026-06-14', 'event_time' => '9.30']), $site);
check($e['start'] === '2026-06-14T09:30:00+01:00', 'summer start: ' . $e['start']);

// No time: all day.
$e = EventsFeedMapper::event(row(['event_time' => '']), $site);
check($e['all_day'] === true && $e['start'] === '2026-12-12T00:00:00+00:00', 'all day');
$e = EventsFeedMapper::event(row(['event_time' => 'TBA']), $site);
check($e['all_day'] === true, 'unreadable time is all day');

// Several days.
$e = EventsFeedMapper::event(row(['event_date_end' => '2026-12-14']), $site);
check($e['end'] === '2026-12-14T00:00:00+00:00', 'end date');
$e = EventsFeedMapper::event(row(['event_date_end' => '2026-12-12']), $site);
check(!isset($e['end']), 'same-day end left out');

// Cancelled: marked, and no booking.
$e = EventsFeedMapper::event(row(['state' => -2]), $site);
check($e['status'] === 'cancelled' && !isset($e['booking']), 'cancelled');

// Not bookable (committee meetings): no booking block. No limit: no max_places.
$e = EventsFeedMapper::event(row(['bookable' => 0]), $site);
check(!isset($e['booking']), 'not bookable');
$e = EventsFeedMapper::event(row(['max_bookings' => 0, 'places_taken' => null]), $site);
check(!isset($e['booking']['max_places']) && $e['booking']['places_taken'] === 0, 'no limit');

// Missing essentials: no event.
check(EventsFeedMapper::event(row(['title' => ' ']), $site) === null, 'no title');
check(EventsFeedMapper::event(row(['event_date' => '0000-00-00']), $site) === null, 'no date');
check(EventsFeedMapper::event(row(['event_date' => '2026-02-30']), $site) === null, 'impossible date');

// The whole feed, sorted by start.
$now = new DateTimeImmutable('2026-11-14T10:00:00+00:00');
$feed = EventsFeedMapper::feed([
    row(['id' => 2, 'event_date' => '2026-12-20']),
    row(['id' => 1, 'event_date' => '2026-11-20']),
    row(['id' => 3, 'title' => '']),
], 'XX01', 'Test Ramblers', $site, $now);
check($feed['version'] === 1, 'version');
check($feed['group'] === ['code' => 'XX01', 'name' => 'Test Ramblers'], 'group');
check($feed['generated_at'] === '2026-11-14T10:00:00Z', 'generated_at');
check(array_column($feed['events'], 'id') === ['1', '2'], 'sorted, bad row dropped');

echo "EventsFeedMapperTest passed\n";
