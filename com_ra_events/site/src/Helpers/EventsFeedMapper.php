<?php

/**
 * Turns rows from #__ra_events into the events feed the Ramblers Tools app reads.
 * The feed format is described in the app's docs/events-feed.md (version 1).
 *
 * Plain PHP with no Joomla calls, so it can be tested from the command line.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

defined('_JEXEC') or die;

class EventsFeedMapper {

    /** Event dates and times on the site are UK local time. */
    const ZONE = 'Europe/London';

    /** #__ra_events.state for a cancelled event. */
    const CANCELLED = -2;

    /**
     * Build the whole feed.
     *
     * @param array  $rows       Rows from the feed query, as arrays (see UpcomingController)
     * @param string $groupCode  e.g. CH02
     * @param string $groupName  e.g. East Cheshire Ramblers
     * @param string $siteRoot   The site's address, e.g. https://example.org.uk/
     * @param \DateTimeImmutable|null $now  For tests; defaults to the current time
     */
    public static function feed(array $rows, string $groupCode, string $groupName, string $siteRoot,
            ?\DateTimeImmutable $now = null): array {
        $now = $now ?? new \DateTimeImmutable('now');
        $events = [];
        foreach ($rows as $row) {
            $event = is_array($row) ? self::event($row, $siteRoot) : null;
            if ($event !== null) {
                $events[] = $event;
            }
        }
        usort($events, fn($a, $b) => (new \DateTimeImmutable($a['start'])) <=> (new \DateTimeImmutable($b['start']))
                    ?: strcmp($a['id'], $b['id']));

        return [
            'version' => 1,
            'group' => ['code' => $groupCode !== '' ? $groupCode : null, 'name' => $groupName],
            'generated_at' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'events' => $events,
        ];
    }

    /** One event, or null if it has no id, title or date. */
    public static function event(array $row, string $siteRoot = ''): ?array {
        $id = trim((string) ($row['id'] ?? ''));
        $title = self::text($row['title'] ?? null);
        $date = self::date($row['event_date'] ?? null);
        if ($id === '' || $title === null || $date === null) {
            return null;
        }

        $zone = new \DateTimeZone(self::ZONE);
        $time = self::time($row['event_time'] ?? null);
        $start = new \DateTimeImmutable($date . ' ' . ($time ?? '00:00'), $zone);
        $endDate = self::date($row['event_date_end'] ?? null);
        $end = $endDate !== null && $endDate > $date ? new \DateTimeImmutable($endDate . ' 00:00', $zone) : null;

        $cancelled = (int) ($row['state'] ?? 1) === self::CANCELLED;
        $event = [
            'id' => $id,
            'title' => $title,
            'type' => self::text($row['event_type'] ?? null),
            'status' => $cancelled ? 'cancelled' : 'confirmed',
            'start' => $start->format(DATE_ATOM),
            'end' => $end ? $end->format(DATE_ATOM) : null,
            'all_day' => $time === null,
            'details' => self::plainText($row['details'] ?? null),
            'location' => self::plainText($row['location'] ?? null),
            'contact_name' => self::text($row['contact_name'] ?? null),
            'url' => $siteRoot !== '' ? rtrim($siteRoot, '/') . '/index.php?option=com_ra_events&view=event&id=' . rawurlencode($id) : null,
            'booking' => $cancelled ? null : self::booking($row),
        ];
        return array_filter($event, fn($v) => $v !== null);
    }

    /** Booking details, or null if the event isn't bookable. */
    private static function booking(array $row): ?array {
        if ((int) ($row['bookable'] ?? 0) !== 1) {
            return null;
        }
        $max = (int) ($row['max_bookings'] ?? 0);
        return array_filter([
            'bookable' => true,
            'max_places' => $max > 0 ? $max : null,
            'places_taken' => max(0, (int) ($row['places_taken'] ?? 0)),
            'requires_payment' => (int) ($row['requires_payment'] ?? 0) === 1,
            'waiting_list' => (int) ($row['waiting_list_enabled'] ?? 0) === 1,
            'info' => self::plainText($row['booking_info'] ?? null),
        ], fn($v) => $v !== null);
    }

    /** A YYYY-MM-DD date, or null. */
    private static function date($value): ?string {
        $value = substr(trim((string) ($value ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || $value === '0000-00-00') {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y) ? $value : null;
    }

    /** An HH:MM time from event_time ("19:00", "9.30"), or null if there isn't one. */
    private static function time($value): ?string {
        if (!preg_match('/^\s*(\d{1,2})[:.](\d{2})\s*$/', (string) ($value ?? ''), $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        return $hour < 24 && $minute < 60 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    /** HTML from the editor as plain text, keeping paragraph and line breaks. */
    public static function plainText($html): ?string {
        $text = (string) ($html ?? '');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#<li[^>]*>#i', '• ', $text);
        // Paragraphs and headings end with a blank line; list items and table rows with a new line.
        $text = preg_replace('#</(p|div|h[1-6]|ul|ol|table)>#i', "\n\n", $text);
        $text = preg_replace('#</(li|tr)>#i', "\n", $text);
        // Scripts and styles have no readable text.
        $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = array_map(fn($line) => trim(preg_replace('/[ \t]+/', ' ', $line)), explode("\n", $text));
        $text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
        return $text === '' ? null : $text;
    }

    private static function text($value): ?string {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $value === '' ? null : $value;
    }
}
