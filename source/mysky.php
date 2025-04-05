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

    $epg_path = __DIR__ . '/../output/individual/mysky.xml'; // Simplified filename
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
