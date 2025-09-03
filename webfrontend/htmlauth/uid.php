<?php
echo "<h2>Modbus UID Lernmodus</h2>";
$output = shell_exec("sudo /opt/loxberry/bin/uid_assign.py");
echo "<pre>$output</pre>";
?>
