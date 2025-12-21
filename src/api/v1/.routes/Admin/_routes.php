<?php
namespace Admin;

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/admin', function(RouteCollectorProxy $group){
    $group->get('/overview', '\Admin\Overview:overviewGet');
    $group->get('/dummies', '\Admin\Dummies:dummiesGet');
    $group->post('/dummies/{count}', '\Admin\Dummies:dummiesPost');
    $group->delete('/dummies', '\Admin\Dummies:dummiesDelete');

    $group->post('/domains', '\Admin\Domains:domainsPost');
    $group->get('/domains', '\Admin\Domains:domainsGet');

    $group->post('/currencies', '\Admin\Currencies:currenciesPost');
    $group->get('/currencies', '\Admin\Currencies:currenciesGet');

    $group->get('/accounts/users', '\Admin\Accounts:usersGet');
    $group->get('/accounts/providers', '\Admin\Accounts:providersGet');

    $group->get('/categories', '\Admin\Categories:categoriesGet');
    $group->post('/category/{name}', '\Admin\Categories:categoryPost');
    $group->delete('/category/{id}', '\Admin\Categories:categoryDelete');
    $group->put('/category', '\Admin\Categories:categoryPut');

    $group->get('/searches/providers', '\Admin\Searches:searchesProvidersGet');
    $group->get('/searches/courses', '\Admin\Searches:searchesCoursesGet');

    $group->get('/clicks/providers', '\Admin\Clicks:clicksProvidersGet');
    $group->get('/clicks/courses', '\Admin\Clicks:clicksCoursesGet');

    $group->get('/emails/all', '\Admin\Emails:allEmailsGet');

    $group->get('/visitors/all', '\Admin\Visitors:allVisitorsGet');
    $group->get('/visitors/map', '\Admin\Visitors:mapVisitorsGet');

    $group->get('/countries', '\Admin\Countries:countriesGet');
    $group->put('/countries', '\Admin\Countries:countryPut');


});
// ->add("Authenticate");

