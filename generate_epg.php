<?php

echo "<h2>Generating EPG Data</h2>";

echo "<h3>Running mysky.php...</h3>";
include 'source/mysky.php';
echo "<hr>";

echo "<h3>Running cplay.php...</h3>";
include 'source/cplay.php';
echo "<hr>";

echo "<h3>Running blast.php...</h3>";
include 'source/blast.php';
echo "<hr>";

echo "<h3>Running clickthecity.py...</h3>";
$output = shell_exec('python source/clickthecity.py 2>&1');
echo "<h4>ClickTheCity Output:</h4><pre>" . htmlspecialchars($output) . "</pre><hr>";

echo "<h3>EPG Generation Tasks Completed!</h3>";
echo "<p>Check the <code>output/individual/</code> directory for the generated XML files.</p>";

?>
