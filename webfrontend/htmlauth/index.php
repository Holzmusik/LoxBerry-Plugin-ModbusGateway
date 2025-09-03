<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "Config/Lite.php";
require_once "function.php";

function zmata_option_set($optval, $pre, $ext, $value) {
    $opt = $pre . $optval . $ext;
    if ($value == $opt) {
        $option = '<option value="' . $optval . '" selected>' . $optval . '</option>';
    } else {
        $option = '<option value="' . $optval . '">' . $optval . '</option>';
    }
    return $option;
}

$L = LBWeb::readlanguage("language.ini");
$template_title = "Modbus Gateway";
$helplink = isset($L['LINKS.WIKI']) ? $L['LINKS.WIKI'] : "";
$helptemplate = "pluginhelp.html";

$navbar = [];
$navbar[1]['Name'] = isset($L['NAVBAR.FIRST']) ? $L['NAVBAR.FIRST'] : "Gateway";
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = isset($L['NAVBAR.SECOND']) ? $L['NAVBAR.SECOND'] : "Log";
$navbar[2]['URL'] = 'log.php';

/* Actions */
if (!empty($_POST['req_start']) && !empty($_POST['device'])) {
    $command = 'sudo ' . $lbpbindir . '/service.sh start mbusd@' . escapeshellarg($_POST['device']) . '.service';
    $cmdstat = shell_exec($command);
    $command = 'sudo ' . $lbpbindir . '/service.sh enable mbusd@' . escapeshellarg($_POST['device']) . '.service';
    $cmdstat = shell_exec($command);
    header("Location: index.php");
    exit;
}

if (!empty($_POST['req_stop']) && !empty($_POST['device'])) {
    $command = 'sudo ' . $lbpbindir . '/service.sh stop mbusd@' . escapeshellarg($_POST['device']) . '.service';
    $cmdstat = shell_exec($command);
    $command = 'sudo ' . $lbpbindir . '/service.sh disable mbusd@' . escapeshellarg($_POST['device']) . '.service';
    $cmdstat = shell_exec($command);
    header("Location: index.php");
    exit;
}

if (!empty($_POST['save_new']) && !empty($_POST['device'])) {
    // Create default config for new gateway
    zmata_conf($lbpconfigdir, $_POST['device'], '9600', '8n1', 'addc', '502', '32', '60', '3', '100', '500');
    zmata_cfg($lbpconfigdir, $_POST['device'], '2');
    header("Location: index.php");
    exit;
}

if (!empty($_POST['change']) && !empty($_POST['device'])) {
    zmata_conf(
        $lbpconfigdir,
        $_POST['device'],
        $_POST['speed'],
        $_POST['mode'],
        $_POST['trx_control'],
        $_POST['port'],
        $_POST['maxconn'],
        $_POST['timeout'],
        $_POST['retries'],
        $_POST['pause'],
        $_POST['wait']
    );
    $loglevel = isset($_POST['loglevel']) ? $_POST['loglevel'] : '2';
    zmata_cfg($lbpconfigdir, $_POST['device'], $loglevel);
    $command = 'sudo ' . $lbpbindir . '/service.sh restart mbusd@' . escapeshellarg($_POST['device']) . '.service';
    $cmdstat = shell_exec($command);
    header("Location: index.php");
    exit;
}

if (!empty($_POST['save_del']) && !empty($_POST['device'])) {
    // Remove conf
    $cfgfile = $lbpconfigdir . '/mbusd-' . $_POST['device'] . '.conf';
    if (file_exists($cfgfile)) {
        @unlink($cfgfile);
    }
    // Remove cfg
    $cfgfile = $lbpconfigdir . '/mbusd-' . $_POST['device'] . '.cfg';
    if (file_exists($cfgfile)) {
        @unlink($cfgfile);
    }
    header("Location: index.php");
    exit;
}

/* Header */
$navbar[1]['active'] = true;
LBWeb::lbheader($template_title, $helplink, $helptemplate);

/* NEW */
if (!empty($_POST['req_new'])) {

    echo '<p class="wide">' . (isset($L['GWNEW.HEAD']) ? $L['GWNEW.HEAD'] : 'Neues Gateway hinzufügen') . '</p>';
    echo '<p>' . (isset($L['GWNEW.TEXT']) ? $L['GWNEW.TEXT'] : 'Wähle eine serielle Schnittstelle, um ein neues Gateway zu erstellen.') . '</p>';

    // read cfg global file to find serial path
    $serialcfg = $lbpconfigdir . '/mbusd.cfg';
    $serialpath = '/dev/serial/by-id/';
    if (file_exists($serialcfg)) {
        try {
            $scfg = new Config_Lite($serialcfg);
            $tmp = $scfg->get(null, "SERIAL");
            if (!empty($tmp)) {
                $serialpath = $tmp;
            }
        } catch (Exception $e) {
            // fallback keeps default
        }
    }

    if (is_dir($serialpath) && ($handle = opendir($serialpath))) {
        while (false !== ($device = readdir($handle))) {
            if ($device == "." || $device == "..") {
                continue;
            }
            $file = 'mbusd-' . $device . '.conf';
            echo '<div class="ui-corner-all ui-shadow">';
            echo '<form action="index.php" method="post">';
            echo '<input type="hidden" name="device" value="' . htmlspecialchars($device, ENT_QUOTES) . '">';
            if (file_exists($lbpconfigdir . '/' . $file)) {
                echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="info" type="submit" name="return" value="' . htmlspecialchars($device) . '"> ' . (isset($L['GWNEW.EXIST']) ? $L['GWNEW.EXIST'] : 'existiert bereits');
            } else {
                echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="plus" type="submit" name="save_new" value="' . htmlspecialchars($device) . '">';
            }
            echo '</form>';
            echo '</div>';
        }
        closedir($handle);
    } else {
        echo '<div class="ui-corner-all ui-shadow ui-field-contain">';
        echo '<p>' . htmlspecialchars($serialpath) . ' nicht gefunden.</p>';
        echo '</div>';
    }

/* DEL */
} elseif (!empty($_POST['req_del']) && !empty($_POST['device'])) {

    echo '<p class="wide">' . (isset($L['GWDEL.HEAD']) ? $L['GWDEL.HEAD'] : 'Gateway löschen') . '</p>';
    echo '<div class="ui-corner-all ui-shadow">';
    echo '<form action="index.php" method="post">';
    echo '<input type="hidden" name="device" value="' . htmlspecialchars($_POST['device'], ENT_QUOTES) . '">';
    $status_short = !empty($_POST['status']) ? $_POST['status'] : 'unknown';
    if ($status_short == 'inacti') {
        echo '<p>' . (isset($L['GWDEL.TEXT']) ? $L['GWDEL.TEXT'] : 'Möchtest du löschen:') . ' ' . htmlspecialchars($_POST['device']) . '</p>';
        echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="delete" type="submit" name="save_del" value="' . (isset($L['GWDEL.DELETE']) ? $L['GWDEL.DELETE'] : 'Löschen') . '">';
    } else {
        echo '<p>' . (isset($L['GWDEL.ACTIVE']) ? $L['GWDEL.ACTIVE'] : 'Gateway ist aktiv. Stoppe es zuerst.') . '</p>';
    }
    echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="back" type="submit" name="return" value="' . (isset($L['GWDEL.RETURN']) ? $L['GWDEL.RETURN'] : 'Zurück') . '">';
    echo '</form>';
    echo '</div>';

/* MAIN */
} else {

    echo '<p>' . (isset($L['MAIN.INTRO1']) ? $L['MAIN.INTRO1'] : 'Verwalte deine Modbus-Gateways und deren Konfigurationen.') . '</p>';
    echo '<br>';

    // GATEWAYS
    echo '<p class="wide">' . (isset($L['GATEWAYS.HEAD']) ? $L['GATEWAYS.HEAD'] : 'Gateways') . '</p>';
    echo '<form action="index.php" method="post">';
    echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="plus" type="submit" name="req_new" value="' . (isset($L['GATEWAYS.NEW']) ? $L['GATEWAYS.NEW'] : 'Neues Gateway') . '">';
    echo '</form>';

    $gwdevice = !empty($_POST['show_detail']) ? $_POST['show_detail'] : null;

    $found = false;
    $mask = $lbpconfigdir . "/*.conf";
    foreach (glob($mask) as $file) {
        // read conf file
        try {
            $cfg = new Config_Lite($file);
        } catch (Exception $e) {
            continue;
        }

        $devfile = $cfg->get(null, "device");
        if (empty($devfile)) {
            continue;
        }

        // Extract device name from path, e.g., /dev/serial/by-id/xxx -> xxx
        $devfile_array = explode("/", $devfile);
        $device = end($devfile_array);
        if (empty($device)) {
            continue;
        }

        // Service status
        $command = $lbpbindir . '/service.sh status mbusd@' . escapeshellarg($device) . '.service | grep Active';
        $cmd = shell_exec($command);
        $statusline = is_string($cmd) ? trim($cmd) : '';
        // Keep compatibility with old parsing (inacti / active)
        $status_short = substr($statusline, 11, 6);
        $is_active = (strpos($statusline, 'Active: active') !== false);

        echo '<div class="ui-corner-all ui-shadow ui-field-contain">';
        echo '<form action="index.php" method="post">';
        echo '<input type="hidden" name="device" value="' . htmlspecialchars($device, ENT_QUOTES) . '">';
        echo '<input type="hidden" name="status" value="' . htmlspecialchars($status_short, ENT_QUOTES) . '">';

        echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="delete" type="submit" name="req_del" value="' . (isset($L['GATEWAYS.DEL']) ? $L['GATEWAYS.DEL'] : 'Löschen') . '">';
        echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="info" type="submit" name="show_detail" value="' . htmlspecialchars($device) . '">';

        if (!$is_active && strpos($statusline, 'Active: inactive') !== false) {
            echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="check" type="submit" name="req_start" value="' . (isset($L['GATEWAYS.START']) ? $L['GATEWAYS.START'] : 'Start') . '">';
        } elseif ($is_active) {
            echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="delete" type="submit" name="req_stop" value="' . (isset($L['GATEWAYS.STOP']) ? $L['GATEWAYS.STOP'] : 'Stop') . '">';
        } else {
            echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="check" type="submit" name="req_start" value="' . (isset($L['GATEWAYS.START']) ? $L['GATEWAYS.START'] : 'Start') . '">';
            echo '<pre style="white-space:pre-wrap">' . htmlspecialchars($statusline) . '</pre>';
        }
        echo '</form>';
        echo '</div>';

        if (!$gwdevice) {
            $gwdevice = $device;
        }
        $found = true;

        // Ensure .cfg exists; if not, create and optionally restart if running
        $filecfg = $lbpconfigdir . '/mbusd-' . $device . '.cfg';
        if (!file_exists($filecfg)) {
            zmata_cfg($lbpconfigdir, $device, '2');
            if ($is_active) {
                $command = 'sudo ' . $lbpbindir . '/service.sh restart mbusd@' . escapeshellarg($device) . '.service';
                $cmdstat = shell_exec($command);
            }
        }
    }

    // DETAILS
    if ($found && !empty($gwdevice)) {

        echo '<br><br>';
        echo '<p class="wide">' . (isset($L['GWDETAIL.HEAD']) ? $L['GWDETAIL.HEAD'] : 'Details') . '</p>';

        // read conf file
        $file = $lbpconfigdir . '/mbusd-' . $gwdevice . '.conf';
        try {
            $cfg = new Config_Lite($file);
        } catch (Exception $e) {
            $cfg = null;
        }

        if ($cfg) {
            $devfile = $cfg->get(null, "device");

            echo '<div>';
            echo '<p><b>' . (isset($L['GATEWAYS.DEVICE']) ? $L['GATEWAYS.DEVICE'] : 'Gerät') . ': ' . htmlspecialchars($gwdevice) . '</b></p>';

            $speed       = $cfg->get(null, "speed");
            $mode        = $cfg->get(null, "mode");
            $trx_control = $cfg->get(null, "trx_control");
            $port        = $cfg->get(null, "port");
            $maxconn     = $cfg->get(null, "maxconn");
            $timeout     = $cfg->get(null, "timeout");
            $retries     = $cfg->get(null, "retries");
            $pause       = $cfg->get(null, "pause");
            $wait        = $cfg->get(null, "wait");

            // read loglevel from .cfg (optional)
            $logcfgfile = $lbpconfigdir . '/mbusd-' . $gwdevice . '.cfg';
            $loglevel = '2';
            if (file_exists($logcfgfile)) {
                try {
                    $lcfg = new Config_Lite($logcfgfile);
                    $tmpLL = $lcfg->get(null, "loglevel");
                    if ($tmpLL !== null && $tmpLL !== '') {
                        $loglevel = $tmpLL;
                    }
                } catch (Exception $e) { /* keep default */ }
            }

            // write form
            echo '<form action="index.php" method="post">';
            echo '<label for="speed">' . (isset($L['GWDETAIL.SPEED1']) ? $L['GWDETAIL.SPEED1'] : 'Baudrate') . ' <i>(' . (isset($L['GWDETAIL.SPEED2']) ? $L['GWDETAIL.SPEED2'] : 'z. B. 9600') . ')</i></label>';
            echo '<input type="hidden" name="device" value="' . htmlspecialchars($gwdevice, ENT_QUOTES) . '">';
            echo '<select data-inline="true" data-mini="true" name="speed" id="speed">';
            echo zmata_option_set("1200", "", "", $speed);
            echo zmata_option_set("2400", "", "", $speed);
            echo zmata_option_set("4800", "", "", $speed);
            echo zmata_option_set("9600", "", "", $speed);
            echo zmata_option_set("19200", "", "", $speed);
            echo zmata_option_set("38400", "", "", $speed);
            echo zmata_option_set("57600", "", "", $speed);
            echo zmata_option_set("115200", "", "", $speed);
            echo '</select>';

            echo '<label for="mode">' . (isset($L['GWDETAIL.MODE1']) ? $L['GWDETAIL.MODE1'] : 'Modus') . ' <i>(' . (isset($L['GWDETAIL.MODE2']) ? $L['GWDETAIL.MODE2'] : 'z. B. 8n1') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="mode" id="mode" value="' . htmlspecialchars($mode) . '" type="text">';

            echo '<label for="trx_control">' . (isset($L['GWDETAIL.TRX_CONTROL1']) ? $L['GWDETAIL.TRX_CONTROL1'] : 'TRX Control') . ' <i>(' . (isset($L['GWDETAIL.TRX_CONTROL2']) ? $L['GWDETAIL.TRX_CONTROL2'] : 'z. B. addc') . ')</i></label>';
            echo '<select data-inline="true" data-mini="true" name="trx_control" id="trx_control">';
            echo zmata_option_set("addc", "", "", $trx_control);
            echo zmata_option_set("rts", "", "", $trx_control);
            echo zmata_option_set("sysfs_0", "", "", $trx_control);
            echo zmata_option_set("sysfs_1", "", "", $trx_control);
            echo '</select>';

            echo '<label for="port">' . (isset($L['GWDETAIL.PORT1']) ? $L['GWDETAIL.PORT1'] : 'TCP-Port') . ' <i>(' . (isset($L['GWDETAIL.PORT2']) ? $L['GWDETAIL.PORT2'] : 'z. B. 502') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="port" id="port" value="' . htmlspecialchars($port) . '" type="text">';

            echo '<label for="maxconn">' . (isset($L['GWDETAIL.MAXCONN1']) ? $L['GWDETAIL.MAXCONN1'] : 'Max. Verbindungen') . ' <i>(' . (isset($L['GWDETAIL.MAXCONN2']) ? $L['GWDETAIL.MAXCONN2'] : '') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="maxconn" id="maxconn" value="' . htmlspecialchars($maxconn) . '" type="text">';

            echo '<label for="timeout">' . (isset($L['GWDETAIL.TIMEOUT1']) ? $L['GWDETAIL.TIMEOUT1'] : 'Timeout') . ' <i>(' . (isset($L['GWDETAIL.TIMEOUT2']) ? $L['GWDETAIL.TIMEOUT2'] : 'Sekunden') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="timeout" id="timeout" value="' . htmlspecialchars($timeout) . '" type="text">';

            echo '<label for="retries">' . (isset($L['GWDETAIL.RETRIES1']) ? $L['GWDETAIL.RETRIES1'] : 'Retries') . ' <i>(' . (isset($L['GWDETAIL.RETRIES2']) ? $L['GWDETAIL.RETRIES2'] : '') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="retries" id="retries" value="' . htmlspecialchars($retries) . '" type="text">';

            echo '<label for="pause">' . (isset($L['GWDETAIL.PAUSE1']) ? $L['GWDETAIL.PAUSE1'] : 'Pause') . ' <i>(' . (isset($L['GWDETAIL.PAUSE2']) ? $L['GWDETAIL.PAUSE2'] : 'ms') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="pause" id="pause" value="' . htmlspecialchars($pause) . '" type="text">';

            echo '<label for="wait">' . (isset($L['GWDETAIL.WAIT1']) ? $L['GWDETAIL.WAIT1'] : 'Wartezeit') . ' <i>(' . (isset($L['GWDETAIL.WAIT2']) ? $L['GWDETAIL.WAIT2'] : 'ms') . ')</i></label>';
            echo '<input data-inline="true" data-mini="true" name="wait" id="wait" value="' . htmlspecialchars($wait) . '" type="text">';

            // loglevel
            echo '<label for="loglevel">' . (isset($L['GWDETAIL.LOGLEVEL1']) ? $L['GWDETAIL.LOGLEVEL1'] : 'Log-Level') . '</label>';
            echo '<select data-inline="true" data-mini="true" name="loglevel" id="loglevel">';
            $levels = ['0','1','2','3','4','5','6','7'];
            foreach ($levels as $ll) {
                $sel = ($ll == (string)$loglevel) ? ' selected' : '';
                echo '<option value="' . $ll . '"' . $sel . '>' . $ll . '</option>';
            }
            echo '</select>';

            echo '<br>';
            echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="check" type="submit" name="change" value="' . (isset($L['GWDETAIL.SAVE']) ? $L['GWDETAIL.SAVE'] : 'Speichern und neu starten') . '">';
            echo '</form>';
            echo '</div>';

            // UID Lernmodus Button
            echo '<br><br>';
            echo '<div class="ui-corner-all ui-shadow ui-field-contain">';
            echo '<form action="uid.php" method="get">';
            echo '<input type="hidden" name="device" value="' . htmlspecialchars($gwdevice, ENT_QUOTES) . '">';
            echo '<input data-role="button" data-inline="true" data-mini="true" data-icon="search" type="submit" value="UID Lernmodus starten">';
            echo '</form>';
            echo '</div>';
        } else {
            echo '<div class="ui-corner-all ui-shadow ui-field-contain"><p>Konfiguration für ' . htmlspecialchars($gwdevice) . ' nicht lesbar.</p></div>';
        }
    } else {
        echo '<div class="ui-corner-all ui-shadow ui-field-contain"><p>Keine Gateways gefunden.</p></div>';
    }
}

/* Footer */
LBWeb::lbfooter();
?>
```
