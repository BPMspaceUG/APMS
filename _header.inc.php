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
  <link rel="stylesheet" href="../css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/bootstrap-theme.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <!-- JS -->
  <script type="text/javascript" src="js/jquery-2.1.4.min.js"></script>
  <script type="text/javascript" src="js/angular-1.5.8.min.js"></script>
  <script type="text/javascript" src="js/bootstrap.min.js"></script>
  <script type="text/javascript" src="js/clipboard.min.js"></script>
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
