<?php
namespace User;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

if (!defined('SITE_URL')) define('SITE_URL', $_ENV["SITE_URL"]);

class Selects
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }

    public function languagesGet($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $langs = $this->sql->select('locales', '*', 'hasLanguage = 1', []);
      return emit($response, $langs);

    }

    public function currenciesGet($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $currencies = $this->sql->select('currencies', '*', 'id>0 ORDER BY code ASC', []);
      return emit($response, $currencies);

    }

    public function categoriesGet($request, $response, $args)
    {
      $data = $request->getParsedBody();
      $cats = $this->sql->select('categories', '*', 'isActive = 1 ORDER BY name ASC', []);
      return emit($response, $cats);

    }

    public function countriesGet($request, $response, $args)
    {

      $flagRoot = SITE_URL . '/api/v1/public/flags/w80/';
      $cats = $this->sql->select('countries', '*', '1=1 ORDER BY name ASC', []);
      foreach ($cats as &$c) {
        $c['flagUrl'] = $flagRoot . strtolower($c['isoCode']) . '.png';
      }
      return emit($response, $cats);

    }

    public function availableCategoriesProvidersGet($request, $response, $args)
    {
      // $cats = $this->sql->query(
      //   'SELECT id, name
      //   FROM categories
      //   WHERE id IN (SELECT categoryId FROM categories_providers);',
      //   []
      // );

      $cats = $this->sql->query('
        SELECT DISTINCT c.id, c.name
        FROM categories c
        JOIN categories_providers cp ON c.id = cp.categoryId
        JOIN courses cr ON cp.providerId = cr.id
        WHERE cr.isPublished = 1
          AND cr.startDate > CURDATE()
          AND c.isActive = 1
      ', []);

      return emit($response, $cats);
    }

    public function availableCategoriesCoursesGet($request, $response, $args)
    {
      $cats = $this->sql->query(
        'SELECT DISTINCT c.id, c.name
        FROM categories c
        JOIN categories_courses cp ON c.id = cp.categoryId
        JOIN courses cr ON cp.courseId = cr.id
        WHERE cr.isPublished = 1
        AND cr.startDate > CURDATE()
        AND c.isActive = 1
      ', []);

      return emit($response, $cats);
    }

     public function availableCountriesCoursesGet($request, $response, $args)
    {
      $cnts = $cats = $this->sql->query(
        'SELECT DISTINCT c.id, c.name
        FROM countries c
        JOIN courses cr ON cr.countryId = c.id
        WHERE cr.isPublished = 1
        AND cr.startDate > CURDATE()
      ', []);

      return emit($response, $cnts);
    }

    public function availableCountriesProvidersGet($request, $response, $args)
    {
      // $cnts = $this->sql->query(
      //   'SELECT id, name
      //   FROM countries
      //   WHERE id IN (SELECT countryId FROM countries_providers);',
      //   []
      // );

      $cnts = $this->sql->query('
        SELECT DISTINCT c.id, c.name
        FROM countries c
        JOIN countries_providers cp ON c.id = cp.countryId
        JOIN providers cr ON cp.providerId = cr.id
      ', []);

      return emit($response, $cnts);
    }




}

 ?>
