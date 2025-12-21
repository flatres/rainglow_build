<?php
namespace Admin;
use Psr\Container\ContainerInterface as Container;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

define('EXCHANGE_API', $_ENV["EXCHANGE_API"]);

ini_set('max_execution_time', 2400);

class Categories
{
    /** @var \Slim\Container
     */
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql = $container->get('mysql');
    }

    public function categoriesGet($request, $response, $args)
    {
      $data = [];
      $data['categories'] = $this->sql->query(
          'SELECT
            c.id AS id,
            c.name AS name,
            c.isActive AS isActive,
            c.createdByUserId AS createdByUserId,
            COUNT(DISTINCT cc.id) AS courseCount,
            COUNT(DISTINCT cp.id) AS providerCount
        FROM
            categories c
        LEFT JOIN
            categories_courses cc ON c.id = cc.categoryId
        LEFT JOIN
            categories_providers cp ON c.id = cp.categoryId
        WHERE
            c.isPending = 0
        GROUP BY
            c.id, c.name
        ORDER BY c.name ASC
              ', []);

      $data['pending'] = $this->sql->query(
          'SELECT
            c.id AS id,
            c.name AS name,
            c.isActive AS isActive,
            c.createdByUserId AS createdByUserId,
            COUNT(DISTINCT cc.id) AS course_count,
            COUNT(DISTINCT cp.id) AS provider_count
        FROM
            categories c
        LEFT JOIN
            categories_courses cc ON c.id = cc.categoryId
        LEFT JOIN
            categories_providers cp ON c.id = cp.categoryId
        WHERE
            c.isPending = 1
        GROUP BY
            c.id, c.name
        ORDER BY c.name ASC
              ', []);
      return emit($response, $data);
    }

    public function categoryPost($request, $response, $args)
    {
      $name = $args['name'];
      $this->sql->insert(
        'categories',
        'name, isActive, createdByUserId, isPending',
        [$name, 1, 0, 0]
      );
      return emit($response, [true]);
    }

    public function categoryPut($request, $response, $args)
    {
      $cat = $request->getParsedBody();
      $cnt = $this->sql->single('categories', 'isActive', 'id=?', [$cat['id']]);

      if ($cnt['isActive'] !== $cat['isActive']) {
        // then could be changing active state from pending list so set pending = 0
        $this->sql->update(
          'categories',
          'name=?, isActive=?, isPending=?',
          'id=?',
          [$cat['name'], $cat['isActive'], 0, $cat['id']]
        );
      } else {
        // could be changing the name only in pending
        $this->sql->update(
          'categories',
          'name=?, isActive=?',
          'id=?',
          [$cat['name'], $cat['isActive'], $cat['id']]
        );
      }

      return emit($response, [true]);
    }

    public function categoryDelete($request, $response, $args)
    {
      $id = $args['id'];
      $this->sql->delete(
        'categories',
        'id=?',
        [$id]
      );
      return emit($response, [true]);
    }
}

 ?>




