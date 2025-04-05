<?php
require_once 'utils.php';

function get_cplay_schedule() {
    $url = 'https://www.cignalplay.com/epg';
    echo "Fetching Cignal Play schedule from: " . $url . "\n"; // Added log
    $content = get_content($url);
    if ($content === false) {
        echo "Failed to fetch Cignal Play schedule.\n";
        return false;
    }
    echo "Cignal Play schedule fetched successfully. Content length: " . strlen($content) . " bytes.\n"; // Added log
    $decoded_content = json_decode($content, true);
    echo "JSON decode result: ";
    print_r($decoded_content); // Added log
    return $decoded_content;
}

function generate_cplay_epg() {
    echo "Generating CPlay EPG...\n";
    $schedule_data = get_cplay_schedule();

    if (!$schedule_data || !isset($schedule_data['channels'])) {
        echo "Failed to parse Cignal Play schedule data.\n";
        var_dump($schedule_data); // Added log
        return;
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= "<tv>\n";

    foreach ($schedule_data['channels'] as $channel) {
        $channel_id = 'cplay_' . $channel['channel_id'];
        $xml .= "<channel id=\"" . htmlspecialchars($channel_id) . "\">\n";
        $xml .= "<display-name>" . htmlspecialchars($channel['channel_name']) . "</display-name>\n";
        $xml .= "</channel>\n";

        if (isset($channel['programs']) && is_array($channel['programs'])) {
            foreach ($channel['programs'] as $program) {
                $start_time = DateTime::createFromFormat('Y-m-d H:i:s', $program['start_time']);
                $end_time = DateTime::createFromFormat('Y-m-d H:i:s', $program['end_time']);

                if ($start_time && $end_time) {
                    $xml .= "<programme start=\"" . htmlspecialchars($start_time->format('YmdHis O')) . "\" stop=\"" . htmlspecialchars($end_time->format('YmdHis O')) . "\" channel=\"" . htmlspecialchars($channel_id) . "\">\n";
                    $xml .= "<title>" . htmlspecialchars($program['title']) . "</title>\n";
                    if (isset($program['description'])) {
                        $xml .= "<desc>" . htmlspecialchars($program['description']) . "</desc>\n";
                    }
                    $xml .= "</programme>\n";
                }
            }
        }
    }

    $xml .= "</tv>\n";

    $epg_path = __DIR__ . '/../output/individual/cplay.xml'; // Modified path
    file_put_contents($epg_path, $xml);
    echo "CPlay EPG generated and saved to " . htmlspecialchars($epg_path) . "\n";
}

// Run the EPG generation
generate_cplay_epg();

?>
