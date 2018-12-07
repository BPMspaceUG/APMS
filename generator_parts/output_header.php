<?php
  // Includes
  require_once(__DIR__.'/src/DatabaseHandler.inc.php'); // For AuthKey from Config
  require_once(__DIR__.'/src/AuthHandler.inc.php');

  // Check if authenticated via Token
  $rawtoken = JWT::getBearerToken();
  try {
    $token = JWT::decode($rawtoken, AUTH_KEY);
  }
  catch (Exception $e) {
    // Invalid Token!
    http_response_code(401);
    header("Location: login.php");
    exit();
  }
  // Token vaild but expired
  if (property_exists($token, "exp")) {
    if (($token->exp - time()) <= 0) {
      http_response_code(401);
      header("Location: login.php");
      exit();
    }
  }

  //---------------> Token vaild    
  /*
  if (property_exists($token, "exp"))
    echo "Your Token will expire in ".number_format((($token->exp - time()) / 60), 2, ',', '.')."min. To log out, clear the cookies.";
  else
    echo "Your Token will never be expiring.";
  */
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <!-- Title -->
  <title>replaceDBName - [APMSProject]</title>
  <!-- CSS via CDN -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.css">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="css/muster.css">
  <style>
    html {position: relative; min-height: 100%;}
    body {margin-bottom: 60px;}
    .footer {position: absolute; bottom: 0; width: 100%; height: 60px; line-height: 60px; background-color: #f5f5f5; font-size: 12px;}
  </style>
  <!-- JS via CDN -->
  <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis.min.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body>