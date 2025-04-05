<?php
require_once 'utils.php';

define('BASE_URL', 'https://epg.tapdmv.com/calendar');
define('FETCH_DAYS', 2); // Fetch EPG data for this many days

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
    } else {
        echo "Error fetching or parsing channel list.\n";
        return [];
    }
}

// Fetch EPG data for a channel for a specific date
function get_epg_for_date($channel_id, $date) {
    $start_date = $date->format('Y-m-d');
    $end_date = $date->modify('+1 day')->format('Y-m-d');
    $date->modify('-1 day'); // Reset the date object
    $url = BASE_URL . '/' . $channel_id . '?$limit=10000&$sort[createdAt]=-1&start=' . $start_date . '&end=' . $end_date;
    $response = get_content($url);
    if ($response === false) {
        echo "Error fetching EPG data for channel ID: " . htmlspecialchars($channel_id) . " on " . $start_date . "\n";
        return null;
    }
    return json_decode($response, true);
}

// Convert EPG data to XMLTV format
function generate_xmltv($channels_data, $all_epg_data) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= "<tv generator-info-name=\"tvguidePH - Blast\" generator-info-url=\"\">\n";

    foreach ($channels_data as $channel) {
        $xml .= "<channel id=\"" . htmlspecialchars($channel['id']) . "\">\n";
        $xml .= "<display-name>" . htmlspecialchars($channel['display_name']) . "</display-name>\n";
        // Add optional elements like icon and url if available in the API
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
            $xml .= "<title lang=\"en\">" . htmlspecialchars(trim($program['title'])) . "</title>\n";
            $xml .= "<desc lang=\"en\">" . htmlspecialchars($program['description'] ?: 'No description') . "</desc>\n";
            $xml .= "<category lang=\"en\">" . htmlspecialchars($program['category'] ?: 'Uncategorized') . "</category>\n";
            $xml .= "</programme>\n";
        }
    }

    $xml .= "</tv>\n";

    $epg_path = __DIR__ . '/../output/individual/blast.xml'; // Modified path
    $result = file_put_contents($epg_path, $xml);
    if ($result === false) {
        echo "Error: Failed to write Blast EPG data to " . htmlspecialchars($epg_path) . "\n";
        // Optionally, log more details about the error: error_get_last()
    } else {
        echo "Blast EPG XML generated successfully as " . htmlspecialchars($epg_path) . " (" . $result . " bytes)!\n";
        echo "Blast.xml Preview (First 500 chars):\n";
        echo htmlspecialchars(substr($xml, 0, 500)) . "\n";
    }
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
for ($i = 0; $i < FETCH_DAYS; $i++) {
    $fetch_date = clone $current_date_utc;
    $fetch_date->modify("+$i day");
    $formatted_date = $fetch_date->format('Y-m-d');
    echo "Fetching EPG data for: " . $formatted_date . " UTC\n";
    foreach ($channels as $channel) {
        $epg_data = get_epg_for_date($channel['id'], clone $fetch_date);
        if (isset($epg_data) && is_array($epg_data)) {
            echo "EPG fetched for channel: " . htmlspecialchars($channel['name']) . " (" . $formatted_date . ")\n";
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
            echo "No EPG data or fetch error for channel: " . htmlspecialchars($channel['name']) . " (" . $formatted_date . ")\n";
        }
    }
}

echo "Total Fetched Programs: " . count($all_epg) . "\n";
echo "Fetched Programs (First 5):\n";
print_r(array_slice($all_epg, 0, 5));
echo "\n";

generate_xmltv($channels, $all_epg);

?>
