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
        $save = true;
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Status: 500 Internal Server Error');
    echo '<h2>PHP-Proxy error</h2>';
    echo $e->getMessage();
}

@fastcgi_finish_request();

try {
    if ($save) {
        $dsn = 'sqlite:' . realpath('./data/') . '/' . $serverName . '.sqlite';
        $pdo = new PDO($dsn);

        //check if table exists
        if (!$pdo->query('SELECT count(*) FROM sqlite_master WHERE type="table" AND name="items"')->fetch()) {
            $pdo->exec('
            CREATE TABLE items(
                id INTEGER NOT NULL PRIMARY KEY,
                server STRING NOT NULL,
                request_date DATETIME NOT NULL,
                request_method STRING NOT NULL,
                request_url STRING NOT NULL,
                response_status INTEGER NOT NULL,
                duration FLOAT NOT NULL
            )
        ');
        }

        $stmt = $pdo->prepare("INSERT INTO 
        items(server, request_date, request_method, request_url, response_status, duration) 
        VALUES(?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $serverName,
            date('Y-m-d H:i:s', $_SERVER["REQUEST_TIME"]),
            $_SERVER['REQUEST_METHOD'],
            $requestURI,
            $curlInfo['http_code'],
            $curlDuration
        ]);

        $id = $pdo->lastInsertId();
        $dir = './data/' . $serverName . '/' . $id;
        mkdir($dir, 0777, true);
        if ($_FILES) {
            $counter = 0;
            foreach ($_FILES as $fileInfo) {
                $counter++;
                move_uploaded_file($fileInfo['tmp_name'], $dir . '/' . $counter . '-' . $fileInfo['name']);
            }
        }
        file_put_contents($dir . '/request-headers.json', json_encode($requestHeaders));
        if (is_array($post)) {
            file_put_contents($dir . '/post.json', json_encode($post));
        } elseif (strlen($post)) {
            file_put_contents($dir . '/post.json', json_encode(['_allpost' => $post]));
        }
        file_put_contents($dir . '/response-headers.json', json_encode($responseHeaders));
        file_put_contents($dir . '/response-body.raw', $responseBody);



    }
} catch (Exception $e) {
    //silent
    echo "";
}