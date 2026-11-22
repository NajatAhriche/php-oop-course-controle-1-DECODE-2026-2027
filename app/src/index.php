<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Lib\Http\Request;
use App\Lib\Http\Router;

$request = new Request();
$response = (new Router())->main($request);

header('Content-Type: application/json; charset=UTF-8');
http_response_code($response->statusCode);
if ($response->statusCode !== 204) {
    echo $response->content;
}
