<?php
namespace Search;
use Psr\Container\ContainerInterface as Container;

class Bookmarks
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;

       $this->courses = new \Search\Courses($container);
       $this->providers = new \Search\Providers($container);
    }

    public function foldersGet($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();

      $default = $this->sql->query('
        SELECT f.id, f.name, TRUE as locked, COUNT(u.id) AS urls_count
        FROM favourites_folders f
        LEFT JOIN favourites_urls u ON f.id = u.folderId AND u.userId = ?
        WHERE f.userId is NULL
        GROUP BY f.id',
        [$userId]);

      $custom = $this->sql->query('
        SELECT f.id, f.name, FALSE as locked, COUNT(u.id) AS urls_count
        FROM favourites_folders f
        LEFT JOIN favourites_urls u ON f.id = u.folderId AND u.userId = ?
        WHERE f.userId = ?
        GROUP BY f.id',
        [$userId, $userId]);

      $data = array_merge($default, $custom);

      return emit($response, $data);
    }

    public function bookmarkDelete($request, $response, $args)
    {
      global $userId;
      $id = $args['id'];
      $this->sql->delete('favourites_urls', 'id=? AND userId=?', [$id, $userId]);
      return emit($response, true);
    }

    public function bookmarkFolderDelete($request, $response, $args)
    {
      global $userId;
      $id = $args['id'];
      $this->sql->delete('favourites_folders', 'id=? AND userId=?', [$id, $userId]);
      return emit($response, true);
    }

    public function bookmarkFolderPost($request, $response, $args)
    {
      global $userId;
      $d = $request->getParsedBody();

      $d['id'] = $this->sql->insert('favourites_folders', 'name, userId', [$d['name'], $userId]);
      return emit($response, $d);
    }

    public function bookmarkFolderPut($request, $response, $args)
    {
      global $userId;
      $d = $request->getParsedBody();

      $this->sql->update('favourites_folders', 'name=?', 'id=? AND userId=?', [$d['name'], $d['id'], $userId]);
      return emit($response, $d);
    }

    public function folderGet($request, $response, $args)
    {
      global $userId;

      $id = $args['id'];

      $bookmarks = [];

      $data = $this->sql->query(
        'SELECT fu.*,
                CASE WHEN fu.courseId IS NULL THEN FALSE ELSE TRUE END AS isCourse
        FROM favourites_urls fu
        WHERE fu.userId = ? AND fu.folderId = ?
        ORDER BY fu.timestamp DESC
        ',
        [$userId, $id]
      );

      foreach ($data as $d) {
        $item = [];
        if ($d['isCourse']) {
          $item = $this->courses->getCourseById($d['courseId']) ?? [];
          $item['isCourse'] = true;
          $item['courseId'] = $item['id'];
          $item['id'] = $d['id'];
        } else {
          if ($d['providerId']) {
            $item = $this->providers->getProviderById($d['providerId']) ?? [];
            $item['isCourse'] = false;
            $item['providerId'] = $item['id'];
            $item['id'] = $d['id'];
          }
        }
        $bookmarks[] = $item;
      }

      return emit($response, $bookmarks);
    }

}

 ?>
