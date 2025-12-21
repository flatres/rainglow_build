<?php
namespace User;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


class Geo
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
    }

    public function geoGet($request, $response, $args)
    {
      $data = [];

      $data['ip'] = $_SERVER['HTTP_X_REAL_IP'] ?? $_ENV['HTTP_X_REAL_IP'];
      $data['continent'] = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? $_ENV['HTTP_X_FORWARDED_CONTINENT'];
      $countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? $_ENV['HTTP_X_FORWARDED_COUNTRY'];
      $data['country'] = $countryName;
      $country = $this->sql->single(
        'countries',
        'id, name, isoCode, isoCurrency, currencySymbol, phonePrefix',
        'name = ?',
        [$countryName]);

      if (!$country) {
        $country = [
          'id' => 0
        ];
      }
        $data = array_merge($data, $country);

      return emit($response, $data);
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
      $data = $request->getParsedBody();
      $cats = $this->sql->select('countries', '*', 'isActive = 1 ORDER BY name ASC', []);
      return emit($response, $cats);

    }

    public function availableCategoriesGet($request, $response, $args)
    {
      $cats = $this->sql->query(
        'SELECT id, name
        FROM categories
        WHERE id IN (SELECT categoryId FROM categories_providers);',
        []
      );

      return emit($response, $cats);
    }

    public function availableCategoriesCoursesGet($request, $response, $args)
    {
      $cats = $this->sql->query(
        'SELECT id, name
        FROM categories
        WHERE id IN (SELECT categoryId FROM categories_courses);',
        []
      );

      return emit($response, $cats);
    }

    public function availableCountriesGet($request, $response, $args)
    {
      $cnts = $this->sql->query(
        'SELECT id, name
        FROM countries
        WHERE id IN (SELECT countryId FROM countries_providers);',
        []
      );

      return emit($response, $cnts);
    }

    public function availableCountriesCoursesGet($request, $response, $args)
    {
      $cnts = $this->sql->query(
        'SELECT id, name
        FROM countries
        WHERE id IN (SELECT countryId FROM courses);',
        []
      );

      return emit($response, $cnts);
    }


}

 ?>
