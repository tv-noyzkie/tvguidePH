<?php
require_once 'utils.php';

function get_cplay_schedule_data() {
    $manila_timezone = new DateTimeZone('Asia/Manila');
    $now_manila = new DateTime('now', $manila_timezone);
    $today_start = $now_manila->format('Y-m-d') . 'T00:00:00Z';
    $today_end = $now_manila->format('Y-m-d') . 'T23:59:59Z';

    $epg_url = 'https://live-data-store-cdn.api.pldt.firstlight.ai/content/epg?start=' . $today_start . '&end=' . $today_end . '&dt=all&client=pldt-cignal-web&reg=ph';
    $channel_info_url = 'https://live-data-store-cdn.api.pldt.firstlight.ai/content?ids=%s&info=detail&mode=detail&st=published&reg=ph&dt=web&client=pldt-cignal-web&pageNumber=1&pageSize=100';

    echo "Fetching Cignal Play EPG data from: " . $epg_url . "\n";
    $epg_content = get_content($epg_url);
    if ($epg_content === false) {
        echo "Failed to fetch Cignal Play EPG data.\n";
        return false;
    }
    echo "Cignal Play EPG data fetched successfully. Content length: " . strlen($epg_content) . " bytes.\n";
    $epg_raw = json_decode($epg_content, true);
    echo "Cignal Play EPG JSON decode result: ";
    print_r($epg_raw);

    if (!isset($epg_raw['data']) || !is_array($epg_raw['data'])) {
        echo "Failed to parse Cignal Play EPG data.\n";
        return false;
    }

    $channel_ids = implode(',', array_column($epg_raw['data'], 'cid'));
    $full_channel_info_url = sprintf($channel_info_url, $channel_ids);
    echo "Fetching Cignal Play channel info from: " . $full_channel_info_url . "\n";
    $channel_info_content = get_content($full_channel_info_url);
    if ($channel_info_content === false) {
        echo "Failed to fetch Cignal Play channel info.\n";
        return false;
    }
    echo "Cignal Play channel info fetched successfully. Content length: " . strlen($channel_info_content) . " bytes.\n";
    $channel_info_raw = json_decode($channel_info_content, true);
    echo "Cignal Play channel info JSON decode result: ";
    print_r($channel_info_raw);

    if (!isset($channel_info_raw['data']) || !is_array($channel_info_raw['data'])) {
        echo "Failed to parse Cignal Play channel info.\n";
        return false;
    }

    $channel_info = [];
    foreach ($channel_info_raw['data'] as $channel) {
        $channel_info[$channel['id']] = $channel;
    }

    return ['epg' => $epg_raw['data'], 'info' => $channel_info];
}

function generate_cplay_epg() {
    echo "Generating CPlay EPG...\n";
    $schedule_data = get_cplay_schedule_data();

    if (!$schedule_data) {
        echo "Failed to retrieve Cignal Play schedule data. Exiting.\n";
        return;
    }

    $epg_data = $schedule_data['epg'];
    $channel_info = $schedule_data['info'];

    // Prepare channel data with display names for sorting
    $channels_with_names = [];
    foreach ($epg_data as $channel_data) {
        if (isset($channel_info[$channel_data['cid']])) {
            $channels_with_names[] = [
                'cid' => $channel_data['cid'],
                'cs' => $channel_data['cs'],
                'display_name' => $channel_info[$channel_data['cid']]['lon'][0]['n'] ?? '',
                'data' => $channel_data,
                'info' => $channel_info[$channel_data['cid']]
            ];
        }
    }

    // Sort channels alphabetically by display name
    usort($channels_with_names, function ($a, $b) {
        return strcmp(strtolower($a['display_name']), strtolower($b['display_name']));
    });

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $manila_timezone = new DateTimeZone('Asia/Manila');
    $now_manila = new DateTime('now', $manila_timezone);
    $xml .= "<tv date=\"" . $now_manila->format('Ymd') . "\" generator-info-name=\"tvguidePH\">\n";

    // Output channels
    foreach ($channels_with_names as $channel_item) {
        $xml .= "<channel id=\"" . htmlspecialchars($channel_item['cs']) . "\">\n";
        $xml .= "<display-name>" . htmlspecialchars($channel_item['display_name']) . "</display-name>\n";
        if (isset($channel_item['info']['ia'][0])) {
            $xml .= "<icon src=\"https://qp-pldt-image-resizer-cloud-prod.akamaized.net/image/" . htmlspecialchars($channel_item['info']['id']) . "/" . htmlspecialchars($channel_item['info']['ia'][0]) . ".jpg?height=150\" />\n";
        }
        $xml .= "<url>https://cignalplay.com</url>\n";
        $xml .= "</channel>\n";
    }

    // Output programmes following the sorted channel order
    foreach ($channels_with_names as $channel_item) {
        foreach ($channel_item['data']['airing'] as $programme) {
            $xml .= "<programme start=\"" . convert_date_time_format($programme['sc_st_dt']) . "\" stop=\"" . convert_date_time_format($programme['sc_ed_dt']) . "\" channel=\"" . htmlspecialchars($channel_item['cs']) . "\">\n";
            $xml .= "<title lang=\"en\">" . htmlspecialchars($programme['pgm']['lon'][0]['n'] ?? '') . "</title>\n";
            $xml .= "<desc lang=\"en\">" . htmlspecialchars($programme['pgm']['lod'][0]['n'] ?? '') . "</desc>\n";
            if (isset($channel_item['info']['log'][0]['n'][0])) {
                $xml .= "<category lang=\"en\">" . htmlspecialchars($channel_item['info']['log'][0]['n'][0]) . "</category>\n";
            }
            $xml .= "</programme>\n";
        }
    }

    $xml .= "</tv>\n";

    $epg_path = __DIR__ . '/../output/individual/cplay.xml'; // Corrected output path
    $result = file_put_contents($epg_path, $xml);
    if ($result === false) {
        echo "Error: Failed to write CPlay EPG data to " . htmlspecialchars($epg_path) . "\n";
        // Optionally, log more details about the error
    } else {
        echo "CPlay EPG generated and saved to " . htmlspecialchars($epg_path) . " (" . $result . " bytes)\n";
    }
}

function convert_date_time_format($date_time) {
    $date = new DateTime($date_time);
    return $date->format('YmdHis O');
}

// Run the EPG generation
generate_cplay_epg();
?>
