<?php
require_once 'utils.php';

define('BASE_URL', 'https://epg.tapdmv.com/calendar');

// Fetch channel list
function get_channels() {
    $response = get_content(BASE_URL . '?$limit=10000&$sort[createdAt]=-1');
    $data = json_decode($response, true);
    if (isset($data['data']) && is_array($data['data'])) {
        $channels = [];
        foreach ($data['data'] as $item) {
            $channels[] = [
                'id' => $item['id'],
                'name' => str_replace(['epg-tapgo-', '.json'], '', $item['name']),
                'display_name' => $item['name']
            ];
        }
        usort($channels, function ($a, $b) {
            return strcmp(strtolower($a['display_name']), strtolower($b['display_name']));
        });
        return $channels;
    }
    return [];
}

// Fetch EPG data for a channel
function get_epg($channel_id, $date) {
    $start_date = $date->format('Y-m-d');
    $end_date = $date->modify('+1 day')->format('Y-m-d');
    $date->modify('-1 day'); // Reset the date object
    $url = BASE_URL . '/' . $channel_id . '?$limit=10000&$sort[createdAt]=-1&start=' . $start_date . '&end=' . $end_date;
    $response = get_content($url);
    return json_decode($response, true);
}

// Convert EPG data to XMLTV format
function generate_xmltv($channels_data, $all_epg_data) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= "<tv>\n";

    foreach ($channels_data as $channel) {
        $xml .= "<channel id=\"" . htmlspecialchars($channel['id']) . "\">\n";
        $xml .= "<display-name>" . htmlspecialchars($channel['display_name']) . "</display-name>\n";
        $xml .= "</channel>\n";
    }

    foreach ($channels_data as $channel) {
        $channel_id = $channel['id'];
        $programs_for_channel = array_filter($all_epg_data, function ($program) use ($channel_id) {
            return $program['channel'] == $channel_id;
        });

        usort($programs_for_channel, function ($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        foreach ($programs_for_channel as $program) {
            $start_time = str_replace([':', '-'], '', $program['start']);
            $stop_time = str_replace([':', '-'], '', $program['stop']);
            $xml .= "<programme start=\"" . htmlspecialchars(rtrim($start_time, '+0000')) . " UTC\" stop=\"" . htmlspecialchars(rtrim($stop_time, '+0000')) . " UTC\" channel=\"" . htmlspecialchars($program['channel']) . "\">\n";
            $xml .= "<title>" . htmlspecialchars(trim($program['title'])) . "</title>\n";
            $xml .= "<desc>" . htmlspecialchars($program['description'] ?: 'No description') . "</desc>\n";
            $xml .= "<category>" . htmlspecialchars($program['category'] ?: 'Uncategorized') . "</category>\n";
            $xml .= "</programme>\n";
        }
    }

    $xml .= "</tv>\n";

    file_put_contents('output/individual/blast.xml', $xml);
    echo "Blast EPG XML generated successfully as output/individual/blast.xml!\n";
    echo "Blast.xml Preview (First 500 chars):\n";
    echo htmlspecialchars(substr($xml, 0, 500)) . "\n";
}

if (isset($_SERVER['REQUEST_TIME'])) {
    $current_date_utc = new DateTime('now', new DateTimeZone('UTC'));
} else {
    // Fallback for CLI execution (assuming UTC)
    $current_date_utc = new DateTime('now', new DateTimeZone('UTC'));
}

$channels = get_channels();

if (!empty($channels)) {
    echo "First Channel (Sorted): tvg-id=" . htmlspecialchars($channels[0]['id']) . ", tvg-name=" . htmlspecialchars($channels[0]['name']) . ", tvg-display-name=" . htmlspecialchars($channels[0]['display_name']) . "\n";
}

$all_epg = [];
foreach ($channels as $channel) {
    $epg_data = get_epg($channel['id'], clone $current_date_utc); // Clone to avoid modifying the original date object
    if (isset($epg_data) && is_array($epg_data)) {
        echo "EPG fetched for channel: " . htmlspecialchars($channel['name']) . "\n";
        foreach ($epg_data as $item) {
            $all_epg[] = [
                'channel' => $channel['id'],
                'title' => $item['program'],
                'description' => isset($item['description']) ? $item['description'] : 'No description',
                'category' => isset($item['genre']) ? $item['genre'] : 'Uncategorized',
                'start' => $item['startTime'],
                'stop' => $item['endTime']
            ];
        }
    } else {
        echo "No EPG data for channel: " . htmlspecialchars($channel['name']) . "\n";
    }
}

echo "Fetched Programs (First 5):\n";
print_r(array_slice($all_epg, 0, 5));
echo "\n";

generate_xmltv($channels, $all_epg);

?>
