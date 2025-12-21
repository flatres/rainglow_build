<?php
namespace Auth\Tools;

/**
 * Create an array of modules availiable in the system and sets booleans for this users get, post, put and delete permissions

 * Usage:

 */

class PermissionsRetriever
{
    protected $container;
    /** @var array */
    public $rolesList = array();
    public $modules = array();
    public $userId = null;
    public $roleId = null;

    public function __construct(\Dependency\Mysql $sql)
    {
       $this->sql= $sql;
    }

    public function getUserPermissions(int $userId)
    {
      $this->userId = $userId;
      return $this->getPermissions();
    }

    public function getRolePermissions(int $roleId)
    {
      $this->roleId = $roleId;
      return $this->getPermissions();
    }

    private function getPermissions()
    {
      $sql = $this->sql;
      $modulesById = array();
      $modulesByName = array();
      $modules = $sql->select('acs_reg_modules', 'id, name, color', '1=1', array());
      foreach($modules as $module){
        $id = $module['id'];
        $name = $module['name'];
        $modulesByName[$name] = array(  'id'        =>$id,
                                        'name'      => $name,
                                        'hasAccess' => false,
                                        'color'     => $module['color'],
                                        'pages'     => array(),
                                        'fromRoles' => array()
                                     );
      }
      foreach($modulesByName as &$module){
        $hasPages = false;
        $pagesById = array();
        $pages = $sql->select('acs_reg_pages', "id, name", 'module_id=?', array($module['id']));
        foreach($pages as $page){
          $hasPages = true;
          $pagesById['p_' . $page['id']] = array(   'id'        => $page['id'],
                                                    'name'      => $page['name'],
                                                    'hasAccess' => false,
                                                    'canCreate' => false,
                                                    'canEdit'   => false,
                                                    'canDelete' => false,
                                                    'fromRoles' => array()
                                                 );
        }
        unset($page);
        if($hasPages) $module['pages'] = $this->setPermissionsByRole($pagesById , $module);

      }

      return $modulesByName;
    }

    //looks at the users roles and sets read / write permissions accordingly
    private function setPermissionsByRole(array $pages, &$module)
    {
      $sql = $this->sql;
      $roles = $this->userId ? $sql->select('acs_roles_users', 'role_id', 'user_id = ?', array($this->userId)) : array(array('role_id'=>$this->roleId));
      foreach($roles as $role){

        $roleId = $role['role_id'];
        $pagesInRole = $sql->select('acs_roles_pages', "page_id, bln_GET, bln_PUT, bln_POST, bln_DELETE", 'role_id=?', array($roleId));

        foreach($pagesInRole as $rolePage){
          $pageId = $rolePage['page_id'];
          $pageKey = 'p_'.$pageId;
          if(isset($pages[$pageKey])){ //only set pages that appear in this module
            if($rolePage['bln_GET']) {
              $pages[$pageKey]['hasAccess'] = true;
              $module['hasAccess'] = true;
              if(!in_array($roleId, $module['fromRoles'])) $module['fromRoles'][] = $roleId;
              $pages[$pageKey]['fromRoles'][] = $roleId;
            }
            if($rolePage['bln_POST']) $pages[$pageKey]['canCreate'] = true;
            if($rolePage['bln_PUT']) $pages[$pageKey]['canEdit'] = true;
            if($rolePage['bln_DELETE']) $pages[$pageKey]['canDelete'] = true;
          }
        }
      }
      //put into an associative array by names
      $pagesByName = array();
      foreach($pages as $page){
        $pagesByName[$page['name']] = $page;
      }
      return $pagesByName;
    }

    /**
     * Creates an array of the user's roles
     *@param int $userId
     *@return array $roles
     */
    public function getUserRoles(int $userId)
    {
      $sql = $this->sql;
      $this->rolesList = array();
      $roles = $sql->select('acs_roles_users', 'role_id', 'user_id = ?', array($userId));

      foreach($roles as $role){
        $this->rolesList[] = $role['role_id'];
        // $d = $sql->select('acs_roles', 'id, name', 'id=?', array($role['role_id']));
        // if(isset($d[0])) $this->rolesList[]=$d[0]['name'];
      }

      return $this->rolesList;
    }

    public function getUsersByRoleName(string $roleName) {
      $role = $this->sql->single('acs_roles', 'id', 'name=?', [$roleName]);
      $userList = [];
      if (!$role) return $users;
      $users = $this->sql->select('acs_roles_users', 'user_id', 'role_id=?', [$role['id']]);
      foreach ($users as $u) {
        $user = new \Entities\People\User($this->sql, $u['user_id']);
        $userList[] = $user;
      }
      return $userList;
    }

}
