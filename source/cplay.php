<?php
$epg_raw = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content/epg?start=' . date('Y-m-d') . 'T00:00:00Z&end=' . date('Y-m-d') . 'T23:59:59Z&dt=all&client=pldt-cignal-web&reg=ph'), true);
$channel_info_raw = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content?ids=' . implode(',', array_column($epg_raw['data'], 'cid')) . '&info=detail&mode=detail&st=published&reg=ph&dt=web&client=pldt-cignal-web&pageNumber=1&pageSize=100'), true);
$channel_info = [];
if (isset($channel_info_raw['data'])) {
    foreach ($channel_info_raw['data'] as $channel) {
        $channel_info[$channel['id']] = $channel;
    }
}

// Prepare channel data with display names for sorting
$channels_with_names = [];
foreach ($epg_raw['data'] as $channel_data) {
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
$xml .= "<tv date=\"" . date('Ymd') . "\" generator-info-name=\"AdoboTV\">\n";

// Output channels
foreach ($channels_with_names as $channel_item) {
    $xml .= "<channel id=\"" . htmlspecialchars($channel_item['cs']) . "\">\n";
    $xml .= "<display-name>". htmlspecialchars($channel_item['display_name']) . "</display-name>\n";
    $xml .= "<icon src=\"https://qp-pldt-image-resizer-cloud-prod.akamaized.net/image/" . htmlspecialchars($channel_item['info']['id']) . "/" . htmlspecialchars($channel_item['info']['ia'][0] ?? '') . ".jpg?height=150\" />\n";
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

file_put_contents('output/individual/cplay.xml', $xml);
echo "CPlay EPG generated and saved to output/individual/cplay.xml\n";

function convert_date_time_format($date_time) {
    $date = new DateTime($date_time);
    return $date->format('YmdHis O');
}
//end cplay.php
?>
