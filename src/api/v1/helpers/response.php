<?php

function emit($response, $data){

  // foreach((array)$data as &$d){
  //   if (is_object($d)){
  //       if (property_exists($d, 'conn')) $d = null;
  //   }
  //   if (is_array($d)) {
  //     foreach ($d as &$e) {
  //       if (is_object($e)){
  //           if (property_exists($e, 'conn')) $e = null;
  //       }
  //       if (is_object($d)){
  //           if (property_exists($d, 'conn')) $d = null;
  //       }
  //     }
  //   }
  // }
  // return;
  
  /* Old version
  $packagedResponse = $response->withJson($data, 200, JSON_INVALID_UTF8_IGNORE);
  return $packagedResponse;
*/
$response->getBody()->write(json_encode($data, JSON_INVALID_UTF8_IGNORE));
return $response->withHeader('Content-Type','application/json')->withStatus(200);
}

function emitRaw($response, $data){

  $response->getBody()->write(serialize($data));
  return $response;

}

// 400 = bad request
function emitError($response, $code, $message){

  $data = ['error'=>true, 'message' => $message];

  $response->getBody()->write(json_encode($data, $code));
return $response->withHeader('Content-Type','application/json')->withStatus($code);

}

?>
