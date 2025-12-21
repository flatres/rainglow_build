<?php
namespace Home;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/home', function(RouteCollectorProxy $group){
    $group->post('/state', '\Home\Rainglow:statePost');
    $group->get('/state', '\Home\Rainglow:stateGet');
});

?>
