<?php
/**
 * Created by PhpStorm.
 * User: shagren
 * Date: 30/08/17
 * Time: 11:13
 */

try {
    set_time_limit(170);
    //get server from uri and reassemble new request uri
    $requestURI = $_SERVER['REQUEST_URI'];
    $parts = explode('/', $requestURI);
    if (count($parts) < 2) {
        throw new Exception('Empty server');
    }
    $server = $parts[1];
    array_splice($parts, 1, 1);
    $requestURI = implode('/', $parts);

    //read config and check if server exists
    $config = parse_ini_file('app.ini', true);
    if (!array_key_exists($server, $config)) {
        throw new Exception('Unknown server');
    }
    $serverConfig = $config[$server];


    //prepare headers
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (preg_match('/^HTTP_/', $name)) {
            // convert HTTP_HEADER_NAME to Header-Name
            $name = strtr(substr($name, 5), '_', ' ');
            $name = ucwords(strtolower($name));
            $name = strtr($name, ' ', '-');
            // add to list
            $headers[$name] = $value;
        }
    }
    //remove some headers
    $headers = array_diff_key($headers, ['Host' => 1, 'Origin' => 1, 'Content-Length' => 1]);

    //prepare POST data.
    $post = '';
    $input = fopen('php://input', 'r    ');
    while ($chunk = fread($input, 1024)) {
        $post .= $chunk;
    }
    fclose($input);

    //Empty post? Lets compose manually
    if (!strlen($post) && count($_FILES)) {
        $headers = array_diff_key($headers, ['Content-Type']);
        $post = $_POST;
        foreach ($_FILES as $name => $fileInfo) {
            $post[$name] = curl_file_create($fileInfo['tmp_name'], $fileInfo['type'], $fileInfo['name']);
        }
    }

    //prepare curl
    $ch = curl_init($serverConfig['url'] . $requestURI);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 160);


    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    $info = curl_getinfo($ch);
    curl_close($ch);
    $responseHeaders = substr($response, 0, $info['header_size']);
    $responseBody = substr($response, $info['header_size']);

    $responseHeaders = preg_split('/$\R?^/m', $responseHeaders);
    array_pop($responseHeaders);
    header_remove();
    foreach ($responseHeaders as $header) {
        header($header);
    }
    echo $responseBody;
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Status: 500 Internal Server Error');
    echo $e->getMessage();
}