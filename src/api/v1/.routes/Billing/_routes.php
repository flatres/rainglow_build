<?php
namespace Billing;

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/billing', function(RouteCollectorProxy $group){
    $group->post('/session/subscription', '\Billing\Stripe:subscriptionSessionPost');
    $group->post('/session/tokens', '\Billing\Stripe:tokensSessionPost');
})->add("Authenticate");
//

