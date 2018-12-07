<?php
  // Check if Request Method is POST
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
    // Convert the input stream into PHP variables from Angular
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  $params = $_POST;

  //============================ Parse by Content
	// Param
	$data = @htmlspecialchars($params['config_data']);
	$data = trim($data);
	$data = html_entity_decode($data);
	// check
	if ($data != "") {
		// Write config to file
		file_put_contents("tmp.php", $data);
		include_once("tmp.php");
		// get data
		$prjname = DB_NAME;
		$jsvar = $config_tables_json;
		// delete file
		unlink("tmp.php");
		// Return result
		echo json_encode(array("DBName" => $prjname, "data" => $jsvar));
		exit();
	}

	//============================ Parse by Filename
	// Param
	$data = @htmlspecialchars($params['file_name']);
	$data = trim($data);
	$data = html_entity_decode($data);
	// check
	if ($data != "") {
		// get data
		$fname = __DIR__ . "/../../IPMS_test/".$data."/".$data."-config.inc.php";
		if (file_exists($fname)) {
			include_once($fname);
			$prjname = DB_NAME;
			$jsvar = $config_tables_json;
			echo json_encode(array("DBName" => $prjname, "data" => $jsvar));
		}
		exit();
	}
?>