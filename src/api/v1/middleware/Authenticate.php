<?php

namespace MiddleWare;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;


class Authenticate
{
  private $sql;
  private $log;

  public function __construct($mysql, $log)
  {
     $this->sql= $mysql;
     $this->log = $log;
  }

  // The __invoke() method is called when a script tries to call an object as a function.
  public function __invoke(Request $request, RequestHandler $handler): Response
  {
    global $userId;

    $auth =  $request->getHeader('Authorization')[0];
    $auth = str_replace('Bearer ', '', $auth);
    $d= $this->sql->select('usr_sessions', 'user_id', 'token=? AND expired=0', array($auth));

    // $rObj['route'] = $request->getAttribute('route')->getArgument('pattern');
    if($d) {
      $userId = $d[0]['user_id'];
      $isAllowed = $this->checkPermissions($request, $userId);

      if($isAllowed) {
        $request = $request->withAttribute('userId', (int)$userId);
        $request = $request->withAttribute('auth', $auth);
        $response = $handler->handle($request);
        //$response = $next($request, $response);
        return $response->withStatus(200);
      }else{
        $data = array('message'=>'Unauthorised Role', 'error'=>true);
        $response = $handler->handle($request);
        $response->getBody()->write(json_encode($data));
        return $response->withStatus(401);
      }

    } else{
      // $this->log->addWarning("Unauthorised Access Request - usr: $userId - path: {$request->getUri()->getPath()}");
      $data = array('message'=>'Unauthorised', 'error'=>true);
      // $response->getBody()->write(json_encode($data));
      return $response->withStatus(401);
    }
  }

  private function checkPermissions($request, int $userId)
  {
      $sql = $this->sql;
      $path = $request->getUri()->getPath();
      $method = $request->getMethod();
      $permissionGranted = false;
      $methodColumnName = "bln_".$method;

      //check to see if the module exists and if not, allow route
      $pageId = $this->pageSearch($path);
      if(!$pageId) return true;

      //find roles assigned to this userId
      $roles = $sql->select('acs_roles_users', 'role_id', 'user_id=?', array($userId));
      foreach($roles as $role){
        $roleId = $role['role_id'];
        $result = $sql->select('acs_roles_pages', $methodColumnName, 'role_id=? AND page_id=?', array($roleId, $pageId));
        //grant permission if method boolean set to true
        if($result){
            $sql->insert('usr_page_log', 'userId, pageId', [$userId, $pageId]);
            $methodFlag = $result[0][$methodColumnName];
            if($methodFlag) return true;
        }
      }
      return false;
  }

  //search for this module in the module definitions, starting with the full path and working down to the base group
  private function moduleSearch($path)
  {
    $sql = $this->sql;
    $pathArray = explode('/', $path);
    $arraySize = count($pathArray);

    //first search whole array and then
    for($i = $arraySize; $i > 0; $i-- ){
      $path = implode('/', $pathArray);
      $searchResult = $sql->select('acs_reg_modules', 'id', 'api_route=?', array($path));
      if($searchResult){
        return $searchResult[0]['id'];
      }
      array_pop($pathArray);
    }
    return false;
  }

  //search for this page in the page definitions, starting with the full path and working down to the base group
  private function pageSearch($path)
  {
    $sql = $this->sql;
    $pathArray = explode('/', $path);
    $arraySize = count($pathArray);

    //first search whole array and then
    for($i = $arraySize; $i > 0; $i-- ){
      $path = implode('/', $pathArray);
      $searchResult = $sql->select('acs_reg_pages', 'id', 'api_route=?', array($path));
      if($searchResult){
        return $searchResult[0]['id'];
      }
      array_pop($pathArray);
    }
    return false;
  }
}




 ?>
