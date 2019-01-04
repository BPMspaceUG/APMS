<?php
  $LIAM_PATH_ABS = "http://dev.bpmspace.org:4040/~daniel/";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>BPMspace APMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="shortcut icon" href="images/favicon.png">
  <!-- CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <!-- JS -->
  <script type="text/javascript" src="js/jquery-2.1.4.min.js"></script>
  <script type="text/javascript" src="js/angular-1.5.8.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
</head>
<body>
  <div class="container" style="background-color:#003296 ">
    <div class="row">
      <div class="col-md-8">
        <img style="margin-left: -14px;" src="images/BPMspace_logo_small.png" class="img-responsive" alt="BPMspace Development"/>
      </div>
      <div class="col-md-4" style="margin-top: 8px; margin-right: 0px;">
        <?php //include_once '../_header_LIAM.inc.php'; ?>
      </div>
    </div>
  </div>
  <?php
    /* presente $error_messages when not empty */
    if (!empty ($_GET ["error_messages"])) {
      echo '<div class="container alert alert-danger centering 90_percent" role="alert"; > <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>';
      echo '&nbsp;error:&nbsp;' . htmlspecialchars($_GET ["error_messages"]);
      echo '</div></br>';
    }
  ?>
  <!--MODAL-->
