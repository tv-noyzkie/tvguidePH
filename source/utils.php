<?php

function get_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add a timeout to prevent indefinite hanging
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_USERAGENT, 'tvguidePH EPG Fetcher'); // Set a user agent

    $output = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        echo "cURL Error fetching URL '" . htmlspecialchars($url) . "': " . htmlspecialchars($error) . "\n";
        return false;
    }

    if ($http_code != 200) {
        echo "HTTP Error " . htmlspecialchars($http_code) . " fetching URL '" . htmlspecialchars($url) . "'.\n";
        return false;
    }

    return $output;
}

?>
