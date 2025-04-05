<?php
require_once 'utils.php';

date_default_timezone_set('Asia/Manila'); // Set Philippine Timezone

function fetch_mysky_channels() {
    $url = 'https://skyepg.mysky.com.ph/Main/getEventsbyType';
    $content = get_content($url);
    if ($content === false) {
        echo "Failed to fetch MySky channel list.\n";
        return [];
    }

    $data = json_decode($content, true);
    if (!$data || !isset($data['location']) || !is_array($data['location'])) {
        echo "Failed to parse MySky channel list.\n";
        return [];
    }

    $channels = [];
    foreach ($data['location'] as $item) {
        $channels[] = [
            'site_id' => $item['id'],
            'name' => $item['name'],
            'lang' => 'en',
        ];
    }

    // Sort channels by name
    usort($channels, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $channels;
}

function fetch_mysky_schedule($channel_site_id, $date) {
    $url = 'https://skyepg.mysky.com.ph/Main/getEventsbyType';
    $content = get_content($url);
    if ($content === false) {
        echo "Failed to fetch MySky events.\n";
        return [];
    }

    $data = json_decode($content, true);
    if (!$data || !isset($data['events']) || !is_array($data['events'])) {
        echo "Failed to parse MySky events.\n";
        return [];
    }

    $formatted_date = $date->format('Y/m/d');
    $programs = [];
    foreach ($data['events'] as $item) {
        if ($item['location'] == $channel_site_id && strpos($item['start'], $formatted_date) !== false) {
            $start_time = DateTime::createFromFormat('Y/m/d H:i', $item['start'], new DateTimeZone('Asia/Manila'));
            $stop_time = DateTime::createFromFormat('Y/m/d H:i', $item['end'], new DateTimeZone('Asia/Manila'));

            if ($start_time && $stop_time) {
                $programs[] = [
                    'title' => $item['name'],
                    'description' => isset($item['userData']['description']) ? $item['userData']['description'] : '',
                    'start' => $start_time->format('YmdHis O'),
                    'stop' => $stop_time->format('YmdHis O'),
                ];
            }
        }
    }

    return $programs;
}

function generate_mysky_epg() {
    echo "Starting to generate MySky EPG for the next 2 hours...\n";
    $channels = fetch_mysky_channels();
    if (empty($channels)) {
        echo "No MySky channels found. Exiting.\n";
        return;
    }

    echo "Fetched " . count($channels) . " MySky channels successfully!\n";

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= "<tv>\n";

    foreach ($channels as $channel) {
        $xml .= "<channel id=\"" . htmlspecialchars($channel['site_id']) . "\">\n";
        $xml .= "<display-name lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($channel['name']) . "</display-name>\n";
        $xml .= "</channel>\n";
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    // We will only fetch for the current hour and the next
    $dates = [$now];

    foreach ($channels as $channel) {
        echo "Fetching schedule for MySky channel: " . htmlspecialchars($channel['name']) . " (ID: " . htmlspecialchars($channel['site_id']) . ")...\n";
        foreach ($dates as $date) {
            $schedule = fetch_mysky_schedule($channel['site_id'], $date);
            if (!empty($schedule)) {
                $two_hours_later = (clone $now)->modify('+2 hours');
                foreach ($schedule as $program) {
                    $program_start = DateTime::createFromFormat('YmdHis O', $program['start']);
                    if ($program_start >= $now && $program_start < $two_hours_later) {
                        $xml .= "<programme start=\"" . htmlspecialchars($program['start']) . "\" stop=\"" . htmlspecialchars($program['stop']) . "\" channel=\"" . htmlspecialchars($channel['site_id']) . "\">\n";
                        $xml .= "<title lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($program['title']) . "</title>\n";
                        if (!empty($program['description'])) {
                            $xml .= "<desc lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($program['description']) . "</desc>\n";
                        }
                        $xml .= "</programme>\n";
                    }
                }
            } else {
                echo "No schedule found for " . htmlspecialchars($channel['name']) . " on " . $date->format('Y-m-d') . ".\n";
            }
        }
    }

    $xml .= "</tv>\n";

    $epg_path = __DIR__ . '/../output/individual/mysky_' . date('Ymd') . '_2hr.xml'; // Timestamp removed and path corrected
    echo "Writing MySky EPG data to " . htmlspecialchars($epg_path) . "...\n";

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($xml);
    $dom->formatOutput = true;
    if ($dom->save($epg_path)) {
        echo "MySky EPG generation completed and written to " . htmlspecialchars($epg_path) . "!\n";
    } else {
        echo "Error writing MySky EPG data to " . htmlspecialchars($epg_path) . ".\n";
    }
}

// Run the EPG generation
generate_mysky_epg();

?>
