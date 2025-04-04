<?php
  // Fetching EPG data from Cignal
  $epg_raw = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content/epg?start=' . date('Y-m-d') . 'T00:00:00Z&end=' . date('Y-m-d') . 'T23:59:59Z&dt=all&client=pldt-cignal-web&reg=ph'), true);
  $channel_info = json_decode(file_get_contents('https://live-data-store-cdn.api.pldt.firstlight.ai/content?ids=' . implode(',', array_column($epg_raw['data'], 'cid')) . '&info=detail&mode=detail&st=published&reg=ph&dt=web&client=pldt-cignal-web&pageNumber=1&pageSize=100'), true);

  // Initialize XML content
  $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
  $xml .= "<tv date=\"" . date('Ymd') . "\" generator-info-name=\"AdoboTV\">";

  // Fetch and add channel information
  foreach ($epg_raw['data'] as $channel) {
    $key = array_search($channel['cid'], array_column($channel_info['data'], 'id'));

    $xml .= "<channel id=\"" . $channel['cs'] . "\">";
    $xml .= "<display-name>" . htmlspecialchars($channel_info['data'][$key]['lon'][0]['n']) . "</display-name>";
    $xml .= "<icon src=\"https://qp-pldt-image-resizer-cloud-prod.akamaized.net/image/" . $channel_info['data'][$key]['id'] . "/" . $channel_info['data'][$key]['ia'][0] . ".jpg?height=150\" />";
    $xml .= "<url>https://cignalplay.com</url>";
    $xml .= "</channel>";

    // Fetch and add schedule/programmes
    foreach ($channel['airing'] as $programme) {
      $xml .= "<programme start=\"" . convert_date_time_format($programme['sc_st_dt']) . "\" stop=\"" . convert_date_time_format($programme['sc_ed_dt']) . "\" channel=\"" . $channel['cs'] . "\">";
      $xml .= "<title lang=\"en\">" . htmlspecialchars($programme['pgm']['lon'][0]['n']) . "</title>";
      $xml .= "<desc lang=\"en\">" . htmlspecialchars($programme['pgm']['lod'][0]['n']) . "</desc>";
      $xml .= "<category lang=\"en\">" . htmlspecialchars($channel_info['data'][$key]['log'][0]['n'][0]) . "</category>";
      $xml .= "</programme>";
    }
  }

  // Add ClickTheCity data
  function fetch_channels() {
      $url = "https://www.clickthecity.com/tv/channels/";
      $response = file_get_contents($url);
      $channels = [];
      if ($response) {
          preg_match_all('/netid=(\d+)/', $response, $matches);
          $channel_ids = $matches[1];
          
          foreach ($channel_ids as $channel_id) {
              $channels[] = [
                  'channel_id' => $channel_id,
                  'channel_name' => 'Channel ' . $channel_id // Placeholder for real names
              ];
          }
      }
      return $channels;
  }

  function fetch_schedule($channel_id) {
      $url = "https://www.clickthecity.com/tv/channels/?netid=" . $channel_id;
      $response = file_get_contents($url);
      $schedule = [];
      if ($response) {
          preg_match_all('/<tr>(.*?)<\/tr>/', $response, $matches);
          foreach ($matches[1] as $row) {
              preg_match('/cTme.*?>(.*?)<\/', $row, $time_match);
              preg_match('/<a.*?>(.*?)<\/a>/', $row, $title_match);
              if ($time_match && $title_match) {
                  $start_time = $time_match[1];
                  $start_dt = new DateTime($start_time);
                  $end_dt = $start_dt->modify('+1 hour');
                  $schedule[] = [
                      "start" => $start_dt->format("YmdHis"),
                      "end" => $end_dt->format("YmdHis"),
                      "title" => $title_match[1],
                      "channel_name" => "ClickTheCity Channel"
                  ];
              }
          }
      }
      return $schedule;
  }

  // Add ClickTheCity channel data
  $channels = fetch_channels();
  foreach ($channels as $channel) {
      $xml .= "<channel id=\"" . $channel['channel_id'] . "\">";
      $xml .= "<display-name>" . htmlspecialchars($channel['channel_name']) . "</display-name>";
      $xml .= "</channel>";
      
      // Add programmes
      $schedule = fetch_schedule($channel['channel_id']);
      foreach ($schedule as $show) {
          $xml .= "<programme start=\"" . $show['start'] . "\" stop=\"" . $show['end'] . "\" channel=\"" . $channel['channel_id'] . "\">";
          $xml .= "<title lang=\"en\">" . htmlspecialchars($show['title']) . "</title>";
          $xml .= "<desc lang=\"en\">" . htmlspecialchars($show['title']) . "</desc>";
          $xml .= "</programme>";
      }
  }

  // End of the XML
  $xml .= "</tv>";

  // Save XML to a file
  file_put_contents('tvguidePH.xml', $xml);

  // Function to convert date-time format
  function convert_date_time_format($date_time) {
      $date = new DateTime($date_time);
      return $date->format('YmdHis O');
  }

  echo "EPG generation completed and saved as tvguidePH.xml!";
?>
