<?php
namespace User;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;



$app->group('/user', function(RouteCollectorProxy $group){
    $group->post('/login', '\User\Login:login')->setName('Auth');
    $group->post('/login/google', '\User\Login:googleLogin');
    $group->post('/login/linkedin', '\User\Login:linkedinLogin');
    $group->post('/signup', '\User\Signup:signupPost');
    $group->post('/signup/guest', '\User\Signup:signupGuestPost');
    $group->post('/verify', '\User\Signup:verifyPost');
    $group->post('/reset', '\User\Reset:resetPost');
    $group->post('/reset/password', '\User\Reset:passwordPost');

});
$app->get('/user/notifications', '\User\Notifications:notificationsGet')->add("Authenticate");
$app->put('/user/notifications/read/{id}', '\User\Notifications:notificationAsReadPut')->add("Authenticate");
$app->put('/user/profile', '\User\Login:profilePut')->add("Authenticate");
$app->get('/user/languages', '\User\Selects:languagesGet');
$app->get('/user/currencies', '\User\Selects:currenciesGet');

$app->get('/user/geo', '\User\Geo:geoGet');

$app->get('/user/categories/{locale}', '\User\Selects:categoriesGet');
$app->get('/user/categories/available/providers/{locale}', '\User\Selects:availableCategoriesProvidersGet');
$app->get('/user/categories/available/courses/{locale}', '\User\Selects:availableCategoriesCoursesGet');

$app->get('/user/countries', '\User\Selects:countriesGet');
$app->get('/user/countries/available/providers', '\User\Selects:availableCountriesProvidersGet');
$app->get('/user/countries/available/courses', '\User\Selects:availableCountriesCoursesGet');


$app->post('/user/profile/pic', '\User\Login:profilePicPost')->add("Authenticate");


?>
