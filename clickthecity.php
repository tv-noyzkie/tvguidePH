<?php
// Function to fetch ClickTheCity channels with logos
function fetch_clickthecity_channels() {
    $html = file_get_contents("https://www.clickthecity.com/tv/channels/");
    preg_match_all('/netid=(\d+).*?<img src="([^"]+)"[^>]+alt="([^"]+)"/s', $html, $matches, PREG_SET_ORDER);
    $channels = [];
    foreach ($matches as $match) {
        $channels[] = [
            'channel_id' => "ctc_" . $match[1],
            'logo' => $match[2],
            'channel_name' => trim($match[3])
        ];
    }
    return $channels;
}

// Function to fetch ClickTheCity EPG (basic version — hourly placeholder)
function fetch_clickthecity_epg($channel_id) {
    $url = "https://www.clickthecity.com/tv/channels/?netid=" . str_replace("ctc_", "", $channel_id);
    $html = file_get_contents($url);
    $shows = [];

    preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rows);
    foreach ($rows[1] as $row) {
        preg_match('/cTme.*?>(.*?)<\/td>/', $row, $time_match);
        preg_match('/<a.*?>(.*?)<\/a>/', $row, $title_match);
        if ($time_match && $title_match) {
            $start = new DateTime($time_match[1]);
            $end = clone $start;
            $end->modify('+1 hour');
            $shows[] = [
                'start' => $start->format('YmdHis O'),
                'end' => $end->format('YmdHis O'),
                'title' => $title_match[1],
                'channel' => $channel_id
            ];
        }
    }

    return $shows;
}

// Test: Fetch and display ClickTheCity channels
$channels = fetch_clickthecity_channels();
echo "<h2>ClickTheCity Channels</h2>";
echo "<ul>";
foreach ($channels as $ch) {
    echo "<li><strong>" . htmlspecialchars($ch['channel_name']) . "</strong> - <img src='" . $ch['logo'] . "' alt='" . htmlspecialchars($ch['channel_name']) . "' style='width: 50px; height: 50px;' /></li>";
}
echo "</ul>";

// Test: Fetch and display EPG for each ClickTheCity channel
foreach ($channels as $ch) {
    $epg = fetch_clickthecity_epg($ch['channel_id']);
    echo "<h3>EPG for " . htmlspecialchars($ch['channel_name']) . "</h3>";
    if (count($epg) > 0) {
        echo "<ul>";
        foreach ($epg as $show) {
            echo "<li><strong>" . htmlspecialchars($show['title']) . "</strong> - " . $show['start'] . " to " . $show['end'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No program data available.</p>";
    }
}
?>
