<?php
namespace Home;

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Routing\RouteCollectorProxy;

$app->get('/home/almanac', '\Home\Almanac:almanacGet');
$app->group('/home', function(RouteCollectorProxy $group){
  $group->get('/classes', '\Home\Classes:classesGet');
  $group->get('/classes/markbook', '\Home\Markbook:markbookGet');

  $group->get('/classes/metrics/{classId}/{examId}', '\Home\Classes:metricsGet');

  $group->get('/classes/wyaps/{classId}', '\Home\Classes:wyapsGet');
  $group->put('/classes/wyaps/{id}', '\HOD\Wyaps:wyapPut');
  $group->get('/classes/wyaps/results/{id}', '\HOD\Wyaps:wyapsResultsGet');

  $group->get('/classes/mlo/form/{id}', '\Home\Classes:formMLOGet');
  $group->get('/classes/mlo/set/{id}', '\Home\Classes:setMLOGet');
  $group->get('/classes/mlo/{classId}/{examId}', '\Home\Classes:MLOGet');
  $group->post('/classes/mlo', '\Home\Classes:MLOPost');

  $group->get('/absences/all', '\Home\Absences:allAbsencesGet');
  $group->get('/absences/subjects', '\Home\Absences:subjectAbsencesGet');
})->add("Authenticate");
// $app->get('/test', '\Auth\TestClass:testGet')->add(new \Authenticate);
