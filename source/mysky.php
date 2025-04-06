<?php
require_once 'utils.php';

date_default_timezone_set('Asia/Manila'); // Set Philippine Timezone

function fetch_mysky_channels() {
    $url = 'https://skyepg.mysky.com.ph/Main/getEventsbyType';
    $content = get_content($url);
    if ($content === false) {
        return [];
    }
    $data = json_decode($content, true);
    if (!$data || !isset($data['location']) || !is_array($data['location'])) {
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

    usort($channels, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $channels;
}

function fetch_mysky_schedule($channel_site_id, $date) {
    $url = 'https://skyepg.mysky.com.ph/Main/getEventsbyType';
    $content = get_content($url);
    if ($content === false) {
        return [];
    }

    $data = json_decode($content, true);
    if (!$data || !isset($data['events']) || !is_array($data['events'])) {
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
    $channels = fetch_mysky_channels();
    if (empty($channels)) {
        return;
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= "<tv generator-info-name=\"tvguidePH - MySky\" generator-info-url=\"\">\n";

    foreach ($channels as $channel) {
        $xml .= "<channel id=\"" . htmlspecialchars($channel['site_id']) . "\">\n";
        $xml .= "<display-name lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($channel['name']) . "</display-name>\n";
        $xml .= "</channel>\n";
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $date = clone $now;

    foreach ($channels as $channel) {
        $schedule = fetch_mysky_schedule($channel['site_id'], $date);
        foreach ($schedule as $program) {
            $xml .= "<programme start=\"" . htmlspecialchars($program['start']) . "\" stop=\"" . htmlspecialchars($program['stop']) . "\" channel=\"" . htmlspecialchars($channel['site_id']) . "\">\n";
            $xml .= "<title lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($program['title']) . "</title>\n";
            if (!empty($program['description'])) {
                $xml .= "<desc lang=\"" . htmlspecialchars($channel['lang']) . "\">" . htmlspecialchars($program['description']) . "</desc>\n";
            }
            $xml .= "</programme>\n";
        }
    }

    $xml .= "</tv>\n";

    $epg_path = __DIR__ . '/../output/mysky.xml';

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($xml);
    $dom->formatOutput = true;
    $dom->save($epg_path);
}

generate_mysky_epg();
