<?php

class NotFoundException extends Exception
{
}


try {
    if (empty($_GET['server'])) {
        throw new Exception('"server" variable absent in request');
    }
    $serverName = $_GET['server'];
    //read config and check if server exists
    $config = parse_ini_file('app.ini', true);
    if (!array_key_exists($serverName, $config)) {
        throw new Exception('Unknown server');
    }
    $serverConfig = $config[$serverName];


    $dsn = 'sqlite:' . realpath('./data/') . '/' . $serverName . '.sqlite';
    $pdo = new PDO($dsn);
    if (!$pdo->query('SELECT count(*) FROM sqlite_master WHERE type="table" AND name="items"', PDO::FETCH_NUM)->fetch()[0]) {
        throw new NotFoundException('Database file is empty');
    }

    $mode = empty($_GET['mode']) ? 'list' : $_GET['mode'];
    $result = [];

    switch ($mode) {
        case 'list':
            $limit = empty($_GET['limit']) ? 100 : (int)$_GET['limit'];
            $offset = empty($_GET['offset']) ? 0 : (int)$_GET['offset'];
            $query = "SELECT * FROM items";
            $tag = false;
            if (array_key_exists('tag', $_GET)) {
                $tag = $_GET['tag'];
                if (strlen($tag)) {
                    $query .= " WHERE tag = " . $pdo->quote($tag);
                } else {
                    $query .= " WHERE tag IS NULL";
                }
            }
            $query .= " ORDER BY id LIMIT " . $limit . " OFFSET " . $offset;
            $result = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as & $row) {
                $row['files'] = json_decode($row['files']);
		unset($row['server_url']);
            }
            break;
        case 'get':
            if (empty($_GET['id'])) {
                throw new Exception('"id" property is required');
            }
            $id = (int)$_GET['id'];
            $query = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $query->execute([$id]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new NotFoundException('Cannot find specified item');
            }
            $url = '/data/' . $serverName . '/' . $id;
            $dir = '.' . $url;
            $result['post'] = $url . '/post.json';
            $result['requestHeaders'] = $url . '/request-headers.json';
            $result['responseHeaders'] = $url . '/response-headers.json';
            $result['body'] = file_get_contents($dir . '/response-body.raw');
            $result['files'] = json_decode($result['files']);
	    unset($result['server_url']);
            break;
        case 'tags':
            $result = $pdo->query("SELECT  tag, count(*) as 'count' FROM items GROUP BY tag ")->fetchAll(PDO::FETCH_ASSOC);
            break;
        default:
            throw new NotFoundException('Unknown mode');

    }
    header('Content-type: application/json');
    print json_encode($result, JSON_PRETTY_PRINT);

} catch (NotFoundException $e) {
    header('HTTP/1.1 403 Internal Server Error');
    header('Status: 403 Internal Server Error');
    echo '<h2>PHP-Storage Not Found</h2>';
    echo $e->getMessage();
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Status: 500 Internal Server Error');
    echo '<h2>PHP-Storage error</h2>';
    echo $e->getMessage();
}
