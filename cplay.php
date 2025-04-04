<?php
// Fetch EPG data from Cignal API
$epg_raw = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content/epg?start=' . date('Y-m-d') . 'T00:00:00Z&end=' . date('Y-m-d') . 'T23:59:59Z&dt=all&client=pldt-cignal-web&reg=ph'), true);
$channel_info = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content?ids=' . implode(',', array_column($epg_raw['data'], 'cid')) . '&info=detail&mode=detail&st=published&reg=ph&dt=web&client=pldt-cignal-web&pageNumber=1&pageSize=100'), true);

// XMLTV Header
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<tv date=\"" . date('Ymd') . "\" generator-info-name=\"Cignal EPG Generator\">\n";

// List all channels first
foreach ($epg_raw['data'] as $channel) {
    $key = array_search($channel['cid'], array_column($channel_info['data'], 'id'));
    
    $xml .= "  <channel id=\"" . $channel['cs'] . "\">\n";
    $xml .= "    <display-name>" . htmlspecialchars($channel_info['data'][$key]['lon'][0]['n']) . "</display-name>\n";
    $xml .= "    <icon src=\"https://qp-pldt-image-resizer-cloud-prod.akamaized.net/image/" . $channel_info['data'][$key]['id'] . "/" . $channel_info['data'][$key]['ia'][0] . ".jpg?height=150\" />\n";
    $xml .= "    <url>https://cignalplay.com</url>\n";
    $xml .= "  </channel>\n";
}

// List all programs after channels
foreach ($epg_raw['data'] as $channel) {
    foreach ($channel['airing'] as $programme) {
        $xml .= "  <programme start=\"" . convert_date_time_format($programme['sc_st_dt']) . "\" stop=\"" . convert_date_time_format($programme['sc_ed_dt']) . "\" channel=\"" . $channel['cs'] . "\">\n";
        $xml .= "    <title lang=\"en\">" . htmlspecialchars($programme['pgm']['lon'][0]['n']) . "</title>\n";
        $xml .= "    <desc lang=\"en\">" . htmlspecialchars($programme['pgm']['lod'][0]['n']) . "</desc>\n";
        $xml .= "    <category lang=\"en\">" . htmlspecialchars($channel_info['data'][$key]['log'][0]['n'][0]) . "</category>\n";
        $xml .= "  </programme>\n";
    }
}

$xml .= "</tv>\n";

// Save XML file with pretty print
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml);
file_put_contents("cignal_epg.xml", $dom->saveXML());

echo "XMLTV file generated successfully: cignal_epg.xml";

// Function to format date/time
function convert_date_time_format($date_time) {
    $date = new DateTime($date_time);
    return $date->format('YmdHis O');
}
?>
