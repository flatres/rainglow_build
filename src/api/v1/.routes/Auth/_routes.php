<?php
namespace Auth;

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/auth', function(RouteCollectorProxy $group){
    $group->get('/login/{login}/{password}', '\Auth\Login:loginGET'); //is group necessary?
    $group->post('/login', '\Auth\Login:login')->setName('Auth');;
    $group->get('/test', '\Auth\TestClass:testGet');//->add("Authenticate");
    $group->post('/bug', '\Auth\Bug:report')->add("Authenticate");
    $group->post('/login/dark', '\Auth\Login:darkPost');
});

$app->get('/auth/permissions', '\Auth\Login:permissionsGet')->add("Authenticate");
