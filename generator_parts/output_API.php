<?php
  $ReqMethod = $_SERVER['REQUEST_METHOD'];

  // API Header
  if ($ReqMethod === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type, Authorization, X-HTTP-Method-Override');
    header('Access-Control-Max-Age: 3600');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    die();
  }
  header('Access-Control-Allow-Origin: *');
  header('Content-Type: text/plain; charset=utf-8');

  // Includes
  require_once(__DIR__.'/src/AuthHandler.inc.php');
  include_once(__DIR__."/src/RequestHandler.inc.php");
  //========================================= Authentification
  // Check if authenticated via Token
  if (Config::getLoginSystemURL() != '') {
    // Has to be authenicated via a external token
    $rawtoken = JWT::getBearerToken();
    try {
      $token = JWT::decode($rawtoken, AUTH_KEY);
    }
    catch (Exception $e) {
      // Invalid Token!
      http_response_code(401);
      exit();
    }
    // Token is valid but expired?
    if (property_exists($token, "exp")) {
      if (($token->exp - time()) <= 0) {
        http_response_code(401);
        exit();
      }
    }
  } else {
    // Has no token
    $token = null;
  }
  //========================================= Parameter & Handling
  try {    
    if ($ReqMethod === 'GET') {
      // GET (useful for read, count)
      $command = 'read'; // preset
      $param['table'] = isset($_GET['table']) ? $_GET['table'] : null;
      $param['filter'] = isset($_GET['filter']) ? $_GET['filter'] : null;
    }
    else if ($ReqMethod === 'POST') {
      // POST (useful for: create, call)
      $postData = json_decode(file_get_contents('php://input'), true);
      $command = $postData["cmd"]; // HAS TO EXIST!
      $param = isset($postData["paramJS"]) ? $postData["paramJS"] : null;
    }
  }
  catch (Exception $e) {
    die('Error: Invalid data sent to API');
  }

  // Handle the Requests
  if ($command != "") {
    $RH = new RequestHandler();
    if (!is_null($param)) // are there parameters?
      $result = $RH->$command($param); // execute with params
    else
      $result = $RH->$command(); // execute
    // Output result
    echo $result;
  }
?>