<?php
namespace Provider;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/provider', function(RouteCollectorProxy $group){
    $group->get('/id', '\Provider\Profile:idGet');

    $group->get('/profile', '\Provider\Profile:profileGet');

    $group->post('/category/{id}', '\Provider\Profile:categoryPost');
    $group->post('/newcategory', '\Provider\Profile:newCategoryPost');
    $group->delete('/category/{id}', '\Provider\Profile:categoryDelete');

    $group->post('/country/{id}', '\Provider\Profile:countryPost');
    $group->delete('/country/{id}', '\Provider\Profile:countryDelete');

    $group->put('/profile', '\Provider\Profile:profilePut');

    $group->post('/language/{id}', '\Provider\Profile:languagePost');
    $group->delete('/language/{id}', '\Provider\Profile:languageDelete');
    $group->put('/language', '\Provider\Profile:languagePut');

    $group->post('/logo', '\Provider\Profile:logoPost');
    $group->post('/banner', '\Provider\Profile:bannerPost');

    $group->post('/photo', '\Provider\Profile:photoPost');
    $group->get('/photos', '\Provider\Profile:photosGet');
    $group->get('/video', '\Provider\Profile:videoGet');
    $group->put('/photo/main', '\Provider\Profile:photoMainPut');
    $group->put('/photo', '\Provider\Profile:photoDelete');
    $group->post('/video', '\Provider\Profile:videoPost');


    $group->put('/course', '\Provider\Courses:coursePut');
    $group->get('/course/{id}', '\Provider\Courses:courseGet');
    $group->post('/course', '\Provider\Courses:coursePost');
    $group->post('/course/new', '\Provider\Courses:courseNewPost');
    $group->delete('/course/{id}', '\Provider\Courses:courseDelete');
    $group->post('/course/publish/{id}', '\Provider\Courses:coursePublishPost');
    $group->post('/course/copy/{id}', '\Provider\Courses:courseCopy');
    $group->post('/course/video/{courseId}', '\Provider\Courses:videoPost');

    $group->get('/courses/durations', '\Provider\Courses:durationsGet');
    $group->get('/courses', '\Provider\Courses:coursesGet');
    $group->get('/currencies', '\Provider\Courses:currenciesGet');

    $group->get('/account', '\Provider\Account:accountGet');
    $group->get('/account/transaction', '\Provider\Account:transactionGet');
    $group->post('/account/token', '\Provider\Account:tokenPost');
    $group->post('/account/reset', '\Provider\Account:resetPost');

})->add("Authenticate");

?>
