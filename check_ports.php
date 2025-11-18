<?php
echo "<h1>Port Checker</h1>";

$ports = [3306, 3307, 80];

foreach ($ports as $port) {
    $connection = @fsockopen('localhost', $port, $errno, $errstr, 2);
    
    if (is_resource($connection)) {
        echo "<p style='color: red;'>✗ Port $port is in use (blocking MySQL)</p>";
        fclose($connection);
    } else {
        echo "<p style='color: green;'>✓ Port $port is available</p>";
    }
}

echo "<h3>Common Solutions:</h3>";
echo "<ol>";
echo "<li>Run XAMPP as Administrator</li>";
echo "<li>Change MySQL port to 3307 in my.ini</li>";
echo "<li>Stop other MySQL services in Windows Services</li>";
echo "<li>Skype uses port 80 - close Skype if running</li>";
echo "</ol>";
?>