<?php

/**
 * Description

 * Usage:

 */
 // https://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
namespace Admin\Logs;
use Psr\Container\ContainerInterface as Container;

class Access
{
    protected $container;

    public function __construct(Container $container)
    {
      $this->ada = $container->get('ada');
    }

    //retrives roles along with names of those with that role
    public function accessPages_GET($request, $response, $args)
    {
      $pages = [];
      $users = [];
      $user = new \Entities\People\User($this->ada);

      $log = $this->ada->query(
        'SELECT id, userId, pageId, timeStamp
        FROM usr_page_log
        WHERE id > ?
        ORDER BY timeStamp ASC
        LIMIT 10000 ',
      [1]);
      foreach ($log as &$l) {
          $id = $l['userId'];
          $pageId = $l['pageId'];

          $l['name'] = $users[$id] ?? $user->displayName($id);
          $users[$id] = $l['name'];

          if (isset($pages[$pageId])) {
            $l['page'] = $pages[$pageId]['page'];
            $l['module'] = $pages[$pageId]['module'];
          } else {
            $p = $this->ada->select('acs_reg_pages', 'name, module_id', 'id = ?', [$pageId]);
            if (isset($p[0])) {
              $page = $p[0]['name'];
              $l['page'] = $page;

              $m = $this->ada->select('acs_reg_modules', 'name', 'id = ?', [$p[0]['module_id']]);
              $module = $m[0]['name'] ?? '';
              $pages[$pageId] = [
                'page'  => $page,
                'module'  => $module
              ];
              $l['module'] = $module;
            }
          }
      }

    return emit($response, $log);
  }

}
