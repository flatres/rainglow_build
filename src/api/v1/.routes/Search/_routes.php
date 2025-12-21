<?php
namespace Search;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteCollectorProxy;

$app->group('/search', function(RouteCollectorProxy $group){
    $group->get('/profile/{id}', '\Search\Providers:profileGet');
    $group->post('/providers', '\Search\Providers:providerSearchPost');
    $group->post('/providers/{limit}/{page}', '\Search\Providers:providerSearchPagePost');
    $group->post('/provider/contact', '\Search\Providers:providerContactPost')->add("Authenticate");
    $group->post('/favourites/folders', '\Search\Favourites:foldersGet')->add("Authenticate");
    $group->post('/favourite', '\Search\Favourites:favouritePost')->add("Authenticate");
    $group->post('/favourites/folder', '\Search\Favourites:folderPost')->add("Authenticate");
    $group->get('/favourites/courses', '\Search\Favourites:favouritesCoursesGet')->add("Authenticate");
    $group->get('/favourites/providers', '\Search\Favourites:favouritesProvidersGet')->add("Authenticate");

    $group->get('/course/{id}', '\Search\Courses:courseGet');
    $group->get('/course/description/{id}', '\Search\Courses:courseDescriptionGet');
    $group->post('/courses', '\Search\Courses:coursesSearchPost');
    $group->post('/courses/{limit}/{page}', '\Search\Courses:coursesSearchPagePost');
    $group->get('/courses/durations', '\Search\Courses:durationsGet');
    $group->get('/categories', '\Search\Courses:categoriesGet');
    $group->get('/categories/courses/{countryId}', '\Search\Courses:categoriesCoursesCountryGet');
    $group->get('/bookmarks/folders', '\Search\Bookmarks:foldersGet');
    $group->delete('/bookmarks/{id}', '\Search\Bookmarks:bookmarkDelete');
    $group->delete('/bookmarks/folder/{id}', '\Search\Bookmarks:bookmarkFolderDelete');
    $group->put('/bookmarks/folder', '\Search\Bookmarks:bookmarkFolderPut');
    $group->post('/bookmarks/folder', '\Search\Bookmarks:bookmarkFolderPost');
    $group->get('/bookmarks/folder/{id}', '\Search\Bookmarks:folderGet')->add("Authenticate");
})->add("Authenticate");




?>
