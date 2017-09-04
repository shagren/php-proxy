<?php
/**
 * Created by PhpStorm.
 * User: shagren
 * Date: 30/08/17
 * Time: 11:13
 */

$save = false;
try {
    set_time_limit(170);
    //get server from uri and reassemble new request uri
    $requestURI = $_SERVER['REQUEST_URI'];
    $parts = explode('/', $requestURI);
    if (count($parts) < 2) {
        throw new Exception('Empty server');
    }
    $serverName = $parts[1];
    array_splice($parts, 1, 1);
    $requestURI = implode('/', $parts);

    //read config and check if server exists
    $config = parse_ini_file('app.ini', true);
    if (!array_key_exists($serverName, $config)) {
        throw new Exception('Unknown server');
    }
    $serverConfig = $config[$serverName];


    //prepare headers
    $requestHeaders = [];
    foreach ($_SERVER as $name => $value) {
        if (preg_match('/^HTTP_/', $name)) {
            // convert HTTP_HEADER_NAME to Header-Name
            $name = strtr(substr($name, 5), '_', ' ');
            $name = ucwords(strtolower($name));
            $name = strtr($name, ' ', '-');
            // add to list
            $requestHeaders[$name] = $value;
        }
    }
    //remove some headers
    $requestHeaders = array_diff_key($requestHeaders, ['Host' => 1, 'Origin' => 1, 'Content-Length' => 1]);

    //prepare POST data.
    $post = '';
    $input = fopen('php://input', 'r    ');
    while ($chunk = fread($input, 1024)) {
        $post .= $chunk;
    }
    fclose($input);

    //Empty post? Lets compose manually
    if (!strlen($post) && count($_FILES)) {
        $requestHeaders = array_diff_key($requestHeaders, ['Content-Type']);
        $post = $_POST;
        foreach ($_FILES as $name => $fileInfo) {
            $post[$name] = curl_file_create($fileInfo['tmp_name'], $fileInfo['type'], $fileInfo['name']);
        }
    }

    //prepare curl
    $ch = curl_init($serverConfig['url'] . $requestURI);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 160);

    $curlStart = microtime(true);
    $response = curl_exec($ch);
    $curlDuration = microtime(true) - $curlStart;
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    $responseHeaders = substr($response, 0, $curlInfo['header_size']);
    $responseBody = substr($response, $curlInfo['header_size']);

    $responseHeaders = preg_split('/$\R?^/m', $responseHeaders);
    array_pop($responseHeaders);
    header_remove();
    foreach ($responseHeaders as $header) {
        header($header);
    }
    echo $responseBody;
    
    //lets store if success
    if ($curlInfo['http_code'] == 200) {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ob_flush();
        try {
            $dsn = 'sqlite:' . realpath('./data/') . '/' . $serverName . '.sqlite';
            $pdo = new PDO($dsn);

            //check if table exists
            if (!$pdo->query('SELECT count(*) FROM sqlite_master WHERE type="table" AND name="items"', PDO::FETCH_NUM)->fetch()[0]) {
                $pdo->exec('
                    CREATE TABLE items(
                        id INTEGER NOT NULL PRIMARY KEY,
                        tag STRING,
                        server STRING NOT NULL,
                        server_url STRING NOT NULL,
                        request_date DATETIME NOT NULL,
                        request_method STRING NOT NULL,
                        request_url STRING NOT NULL,
                        response_status INTEGER NOT NULL,
                        duration FLOAT NOT NULL,
                        files STRING
                    )
                ');
            }

            $stmt = $pdo->prepare("INSERT INTO 
                items(tag, server, server_url, request_date, request_method, request_url, response_status, duration) 
                VALUES(?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                isset($_GET['tag']) ? $_GET['tag'] : null,
                $serverName,
                $serverConfig['url'],
                date('Y-m-d H:i:s', $_SERVER["REQUEST_TIME"]),
                $_SERVER['REQUEST_METHOD'],
                $requestURI,
                $curlInfo['http_code'],
                $curlDuration
            ]);

            $id = $pdo->lastInsertId();
            $dir = './data/' . $serverName . '/' . $id;
            $url = '/data/' . $serverName . '/' . $id;
            mkdir($dir, 0777, true);
            $files = [];
            if ($_FILES) {
                $counter = 0;
                foreach ($_FILES as $fileInfo) {
                    $counter++;



                    $cleanName = preg_replace('/[^-_a-z0-9\.]/i', '', $fileInfo['name']);
                    $dstFile = $dir . '/' . $counter . '-' . $cleanName;
                    move_uploaded_file($fileInfo['tmp_name'], $dstFile);

                    $file = [
                        'originalName' => $fileInfo['name'],
                        'size' => $fileInfo['size'],
                        'type' => $fileInfo['type'],
                        'uri' => $url . '/' . $counter . '-' . $cleanName
                    ];



                    //let`s try to resize
                    require_once 'includes/Image.php';
                    $thumbName = preg_replace('/\.(.+)$/', '.thumb.$1', $cleanName);
                    $thumbFile = $dir . '/' . $counter . '-' . $thumbName;
                    try {
                        if (Volcano_Tools_Image::resizeImage($dstFile, $thumbFile, 200, 200)) {
                            list($thumbWidth, $thumbHeight) = getimagesize($thumbFile);
                            $file['thumbUri'] = $url . '/' . $counter . '-' . $thumbName;
                            $file['thumbWidth'] = $thumbWidth;
                            $file['thumbHeight'] = $thumbHeight;
                        }
                    } catch (Exception $e) {
                        //silently ignore
                    }

                    $files[] = $file;
                }
            }
            $update = $pdo->prepare("UPDATE items SET files = ? WHERE id = ?");
            $update->execute([json_encode($files), $id]);
            file_put_contents($dir . '/request-headers.json', json_encode($requestHeaders));
            if (is_array($post)) {
                file_put_contents($dir . '/post.json', json_encode($post));
            } elseif (strlen($post)) {
                file_put_contents($dir . '/post.json', json_encode(['_allpost' => $post]));
            }
            file_put_contents($dir . '/response-headers.json', json_encode($responseHeaders));
            file_put_contents($dir . '/response-body.raw', $responseBody);


        } catch (Exception $e) {
            //silent
        }
    }

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Status: 500 Internal Server Error');
    echo '<h2>PHP-Proxy error</h2>';
    echo $e->getMessage();
}

