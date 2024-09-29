<?php

require_once __DIR__.'/config.php';

// function mkdirs($dir, $mode = 0777)
// {
//     if (is_dir($dir) || @mkdir($dir, $mode))
//         return TRUE;
//     if (! mkdirs(dirname($dir), $mode))
//         return FALSE;
    
//     return @mkdir($dir, $mode);
// }
// 返回当前的毫秒时间戳
function msectime()
{
    list ($msec, $sec) = explode(' ', microtime());
    return (int) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
}

function writeFile($file, $str)
{
    $res = fopen($file, 'w+');
    if ($res) {
        fwrite($res, $str);
        fclose($res);
    }
}

function postDestination($url, $content)
{
    $verify_options['http'] = array(
        'method' => 'POST',
        'header' => 'Content-type: application/json',
        'content' => json_encode($content)
        );
    $stream = stream_context_create($verify_options);
    $res = file_get_contents($url, false, $stream);
    return $res;
}

function getServerFromList($id)
{
    if ($id == 0){return false;}
    $vfurl = false;
    $list = postDestination('https://maicadev.monika.love/api/servers', '');
    $jlist = json_decode($list, true);
    foreach ($jlist as $server) {
        if ($server['id'] == $id) {
            $vfurl = $server['httpInterface'];
            break;
        }
    }
    if (!$vfurl){return false;}
    return $vfurl;
}
header('Content-Type: application/json');
//$project = $_POST['project'];
//$model = $_POST['model'];
//$user = $_POST['user'];
if (array_key_exists('server_id', $_POST)) {
    $server_id = $_POST['server_id'];
    $vfurl = getServerFromList($server_id);
    if (!$vfurl) {
        $data = [
            'success' => false,
            'exception' => 'Server not found',
            'files' => null
        ];
        echo json_encode($data);
        return;
    }
} else {
    $server_id = 1;
    $vfurl = 'https://maicadev.monika.love/api';
}
$access_token = $_POST['access_token'];
$jres = json_decode(postDestination($vfurl.'/legality', array('access_token' => $access_token)), true);
if (!$jres['success']) {echo 'failed'; return;}
$uid = $jres['id'];

$configDir = "$server_id/$uid";
$filesDir = "$server_id/$uid";
if (! is_dir(CONFIG_DIR . $configDir)) {
    mkdir(CONFIG_DIR . $configDir, 0755, true);
}
if (! is_dir(FILES_DIR . $filesDir)) {
    mkdir(FILES_DIR . $filesDir, 0755, true);
}

$ls1 = scandir(CONFIG_DIR . $configDir);
$ls2 = scandir(FILES_DIR . $filesDir);
$num_files = count($ls1);
if ($num_files > 6) {
    for ($i=5; $i<$num_files; $i++) {
        $s1 = unlink(CONFIG_DIR . $configDir . '/'.$ls1[$i]);
        $s2 = unlink(FILES_DIR . $filesDir . '/'.$ls2[$i]);
        if (!$s1 || !$s2) {
            $data = [
                'success' => false,
                'exception' => 'Delete failed',
                'files' => null
            ];
            echo json_encode($data);
            return;
        }
    }
    
}


if (count($_FILES) > 0) {
    $result = array();
    foreach ($_FILES as $file) {
        $error = $file['error'];
        if ($error > 0) {
            $data = [
                'success' => false,
                'exception' => $error,
                'files' => null
            ];
            echo json_encode($data);
            return;
        }
        $type = strtolower($file['type']);
        $name = $file['name'];
        $size = $file['size'];
        if ($size >= 50*1024*1024 or (strpos($type, 'image') === null && strpos($type, 'audio') === null)) {
            $data = [
                'success' => false,
                'exception' => 'File inacceptable',
                'files' => null
            ];
            echo json_encode($data);
            return;
        }
        $time = msectime();
        $filePath = $filesDir . '/' . $time;
        $config = [
            'type' => $type,
            'name' => $name,
            'time' => $time,
            'path' => $filePath
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $configPath = $configDir . '/' . $time;
        //mkdir(CONFIG_DIR.$configDir);
        writeFile(CONFIG_DIR . $configPath, $json);
        
        $tmp_name = $file['tmp_name'];
        move_uploaded_file($tmp_name, FILES_DIR . $filePath);
        
        array_push($result, $configPath);
        
        $data = [
            'success' => true,
            'exception' => null,
            'files' => $result
        ];
        echo json_encode($data);
    }
}


?>