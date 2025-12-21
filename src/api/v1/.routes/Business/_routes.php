<?php
namespace User;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/business', function(RouteCollectorProxy $group){
    $group->get('/privacy', '\Business\Privacy:privacyGet');
    $group->get('/cookies', '\Business\Cookies:cookiesGet');
    $group->get('/terms', '\Business\Terms:termsGet');
});
?>
