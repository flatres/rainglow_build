<?php
namespace Search;
use Psr\Container\ContainerInterface as Container;

class Favourites
{
    protected $container;

    public function __construct(Container $container)
    {
       $this->sql= $container->get('mysql');
       $this->container = $container;
    }

    public function foldersGet($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();
      $url = $data['url'];

      $folders = $this->sql->select('favourites_urls', 'folderId', 'userId = ? AND url=?', [$userId, $url]);

      $default = $this->sql->select('favourites_folders', 'id, name, TRUE as locked, FALSE as inFolder', 'userId is NULL ORDER BY name ASC', []);
      $custom = $this->sql->select('favourites_folders', 'id, name, FALSE as locked, FALSE as inFolder', 'userId = ? ORDER BY name ASC', [$userId]);
      $data = array_merge($default, $custom);

      foreach($data as &$d) {
        foreach ($folders as $f) {
          if ($d['id'] == $f['folderId']) $d['inFolder'] = 1;
        }
      }

      return emit($response, $data);
    }

    public function favouritePost($request, $response, $args)
    {
      global $userId;
      $data = $request->getParsedBody();

      if ($data['inFolder'] == 0) {
        $this->sql->delete('favourites_urls', 'folderId=? AND userId=? AND url=?', [$data['folderId'], $userId, $data['url']]);
      } else {
        $this->sql->insert(
          'favourites_urls',
          'folderId, userId, url, name, courseId, providerId',
          [$data['folderId'], $userId, $data['url'], $data['title'], $data['courseId'] ?? null, $data['providerId']]);
      }

      return emit($response, [true]);
    }

    public function folderPost($request, $response, $args)
    {
      global $userId;
      $data = $request->getParsedBody();

      $folderId = $this->sql->insert('favourites_folders', 'userId, name', [$userId, $data['name']]);
      $this->sql->insert(
          'favourites_urls',
          'folderId, userId, name, url, courseId, providerId',
          [$folderId, $userId, $data['title'], $data['url'], $data['courseId'] ?? null, $data['providerId']]);

      return emit($response, [true]);
    }

    public function favouritesCoursesGet($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();
      $f = $this->sql->select(
        'favourites_urls',
        'courseId as id',
        'userId=? AND courseId > 0',
        [$userId]
      );

      return emit($response, $f);
    }

     public function favouritesProvidersGet($request, $response, $args)
    {
      global $userId;

      $data = $request->getParsedBody();
      $f = $this->sql->select(
        'favourites_urls',
        'providerId as id',
        'userId=? AND courseId IS NULL',
        [$userId]
      );

      return emit($response, $f);
    }

}

 ?>
