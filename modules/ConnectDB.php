<?php
	//error_reporting(-1);

  // Check if Request Method is POST
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
    // Convert the input stream into PHP variables from Angular
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  $params = $_POST;
  
  // Correctly fetch params
  $host = isset($params['host']) ? $params['host'] : null;
  $port = isset($params['port']) ? $params['port'] : null;
  $user = isset($params['user']) ? $params['user'] : null;
  $pwd = isset($params['pwd']) ? $params['pwd'] : null;
  $x_table = isset($params['x_table']) ? $params['x_table'] : null;



  // If all relevant params are available
  if (isset($host) && isset($user) && isset($pwd)) {
    
    // Connect (with different or standard port)
    /*if (isset($port))
      $con = new mysqli($host.":".$port, $user, $pwd);
    else*/ 
      $con = new mysqli($host, $user, $pwd);
    

    // Connection Error ?
    if ($con->connect_error) {
      die("\n\nCould not connect: ERROR NO. " . $con->connect_errno . " : " . $con->connect_error);
    }
    else {

      if (!is_null($x_table)) {
        // Return output [Tables, Specific Schema/DB]
        $json = getTables($con, $x_table);
        header('Content-Type: application/json');
        echo json_encode($json);
      } else {
        // Return output [Schemata/Databases]
        $json = getData($con);
        header('Content-Type: application/json');
        echo json_encode($json);
      }
      // Close Connection
      $con->close();
    }
  }

  // Extracting databases
  function getData($con) {
    $res = array();
    $query = "SHOW DATABASES";
    $result = mysqli_query($con, $query);

    while ($row = $result->fetch_assoc()) {
      $dbName = $row['Database'];
      // Filter information_schema to save resources
      if (strtolower($dbName) != "information_schema") {
        //var_dump($dbName);
        array_push($res, array(
            "database" => $dbName,
            "tables" => array() //getTables($con, $dbName)
          )
        );
      }
    }
    return $res;
  }

  // Extracting tables
  function getTables($con, $db) {
    $query = "SHOW TABLES IN $db";
    $nameParam = "Tables_in_$db";
    $res = array();
    $result = mysqli_query($con, $query);
    
    $tables = array();
    while ($row = $result->fetch_assoc()) {
      $tables[] = $row[$nameParam];
    }
    

    foreach ($tables as $table) {
      $query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table';";
      $res2 = mysqli_query($con, $query);
      $columns = array();
      $TableHasStateMachine = false;

      // has columns ?
      if ($res2) {

        // Loop Columns
        $column_counter = 1;
        while ($row2 = $res2->fetch_assoc()) {
          // Column information
          $column_info = $row2;
          $column_name = $row2["COLUMN_NAME"];
          // Additional information

          //------------------------------------------------------
          // Pre fill foreign keys
          //------------------------------------------------------
          // default
          $fk = array("table" => "", "col_id" => "", "col_subst" => "");

          if ($table == 'state' && $column_name == "statemachine_id") {
            $fk = array("table" => "state_machines", "col_id" => "id", "col_subst" => "CONCAT(tablename, ' (', id ,')')");
          }
          else if ($table == 'state_rules' && $column_name == "state_id_FROM") {
            $fk = array("table" => "state", "col_id" => "state_id", "col_subst" => "CONCAT(t0.name, ' (', t0.state_id, ')')");
          }
          else if ($table == 'state_rules' && $column_name == "state_id_TO") {
            $fk = array("table" => "state", "col_id" => "state_id", "col_subst" => "CONCAT(t1.name, ' (', t1.state_id, ')')");
          }
          else if ($column_name == "state_id" && $table != 'state'){            
            // every other state column            
            $fk = array("table" => "state", "col_id" => "state_id", "col_subst" => "name");
          }

          // Table Has StateMachine?
          if ($column_name == "state_id" && $table != "state")
            $TableHasStateMachine = true;

          // enrich column info
          /*------------------------------
                   C O L U M N S
          ------------------------------*/
          $additional_info = array(
            "column_alias" => ucfirst($column_name),
            "is_in_menu" => true,
            "read_only" => false,
            "is_ckeditor" => false,
            "foreignKey" => $fk,
            "col_order" => (int)$column_counter,
            "is_virtual" => false,
            "virtual_select" => ""
          );
          // Filter columns array
          $allowed  = ['COLUMN_NAME', 'DATA_TYPE', 'COLUMN_TYPE', 'COLUMN_KEY', 'EXTRA'];
          $filtered = array_filter(
            $column_info,
            function ($key) use ($allowed) { return in_array($key, $allowed); },
            ARRAY_FILTER_USE_KEY
          );
          // Merge arrays
          $merged = array_merge($filtered, $additional_info);
          $colname = $merged['COLUMN_NAME'];
          $columns[$colname] = $merged;

          $column_counter++;
        }

        // ------- ADD a custom virtual column for Table state_rules (SM)
        if ($table == 'state_rules') {
          // Add a virtual column
          $columns['virtualColx'] = array(
            'COLUMN_NAME' => 'virtualColx',
            'DATA_TYPE' => "longtext",
            'COLUMN_TYPE' => "longtext",
            'COLUMN_KEY' => "",
            'EXTRA' => "",
            'column_alias' => "SM",
            'is_in_menu' => true,
            'read_only' => false,
            'is_ckeditor' => false,
            'foreignKey' => array(
              'table' => "",
              'col_id' => "",
              'col_subst' => ""
            ),
            'col_order' => 5,
            'is_virtual' => true,
            'virtual_select' => "CONCAT(t0.statemachine_id)"
          );
        }


        //------------------------------------------------ Auto Foreign Keys
        $fKeys = array();
        $query = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME ".
          "FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table'";
        $resX = mysqli_query($con, $query);

        //echo "Table: $table\n";
        while ($row = $resX->fetch_assoc()) {
          $colname = $row["COLUMN_NAME"];
          $fKeys[$colname] = array(
            "refeTable" => $row["REFERENCED_TABLE_NAME"],
            "colID" => $row["REFERENCED_COLUMN_NAME"]
          );
        }

        // Columns and Foreign Keys exist
        if (count($columns) > 0 && count($fKeys)) {
          // make associative
          foreach ($columns as $colname => $col) {
            // check if entry exists
            if (array_key_exists($colname, $fKeys)) {
              // Check if keys are empty
              if (empty($columns[$colname]["foreignKey"]["table"])
              && empty($columns[$colname]["foreignKey"]["col_id"])
              && empty($columns[$colname]["foreignKey"]["col_subst"])
              ) {
                // Save Foreign Keys in existing Array
                $columns[$colname]["foreignKey"]["table"] = $fKeys[$colname]["refeTable"];
                $columns[$colname]["foreignKey"]["col_id"] = $fKeys[$colname]["colID"];
                $columns[$colname]["foreignKey"]["col_subst"] = $fKeys[$colname]["colID"];
              }
            }
          }
        }

      } // Columns finished

      // TODO: Check if is a View => then ReadOnly = true

      // Generate a nice TableAlias
      $table_alias = str_replace("_", "", ucfirst($table));

      /*------------------------------
              T A B L E S
      ------------------------------*/
      //$table = strtolower($table);
      $res[$table] = array(
        "table_name" => $table,
        "table_alias" => $table_alias,
        "is_in_menu" => true,
        "is_read_only" => false,
        "is_nm_table" => false,
        "se_active" => $TableHasStateMachine,
        "columns" => $columns
      );
    }
    // Output
    return $res;
  }

?>