<?php
namespace Analytics;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/analytics', function(RouteCollectorProxy $group){
    $group->post('/provider/{id}', '\Analytics\Analytics:providerClickPost')->add("Authenticate");;
    $group->post('/course/{id}', '\Analytics\Analytics:courseClickPost')->add("Authenticate");;
    $group->post('/homepage', '\Analytics\Analytics:homepageClickPost');
    $group->post('/page', '\Analytics\Analytics:pagePost');
})

?>
