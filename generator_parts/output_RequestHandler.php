<?php
  // Includes
  $file_DB = __DIR__."/DatabaseHandler.inc.php";
  if (file_exists($file_DB)) include_once($file_DB);
  $file_SM = __DIR__."/StateMachine.inc.php";
  if (file_exists($file_SM)) include_once($file_SM);
  $file_RQ = __DIR__."/ReadQuery.inc.php";
  if (file_exists($file_RQ)) include_once($file_RQ);

  // Global function for StateMachine
  function api($data) {
    $url = API_URL;
    $token = MACHINE_TOKEN;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $headers = array();
    //JWT token for Authentication
    $headers[] = 'Cookie: token='.$token;
    if ($data) {
      $json = json_encode($data);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
      $headers[] = 'Content-Type: application/json';
      $headers[] = 'Content-Length: '.strlen($json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    // Debug Info
    if ($result === FALSE)
      printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    return $result;
  }
  function fmtError($errormessage) {
    return json_encode(['error' => ['msg' => $errormessage]]);
  }

  class Config {
    public static function getConfig() {
      return file_get_contents(__DIR__.'/../'.DB_NAME.'-config.inc.json');
    }
    public static function getColsByTablename($tablename, $data = null) {
      if (is_null($data))
        $data = json_decode(Config::getConfig(), true);
      $cols = $data[$tablename]["columns"];      
      return $cols;
    }
    public static function getColNamesByTablename($tablename) {
      // = string[]
    }
    public static function getPrimaryColsByTablename($tablename, $data = null) {
      $res = array();
      $cols = Config::getColsByTablename($tablename, $data);
      // Find primary columns
      foreach ($cols as $colname => $col) {
        if ($col["is_primary"])
          $res[] = $colname;
      }
      return $res;
    }
    public static function getPrimaryColNameByTablename($tablename) {
      $cols = Config::getPrimaryColsByTablename($tablename);
      try {
        $res = $cols[0];
      } catch (Exception $e) {
        return null;
      }
      return $res;
    }
    public static function getLoginSystemURL() {
      return API_URL_LIAM;
    }
    public static function hasHistory() {
      // TODO: !!!
      return true;
    }
    // Checks
    public static function doesTableExist($tablename) {
      $result = false;
      //$tablename = strtolower($tablename); // always lowercase
      $config = json_decode(Config::getConfig(), true);
      $result = (array_key_exists($tablename, $config));
      return $result;
    }
    public static function doesColExistInTable($tablename, $colname) {
      $cols = Config::getColsByTablename($tablename);
      $colnames = array_keys($cols);
      return in_array($colname, $colnames);
    }
    public static function hasColumnFK($tablename, $colname) {
      $allCols = Config::getColsByTablename($tablename);
      $hasFK = array_key_exists('foreignKey', $allCols[$colname]);
      if (!$hasFK) return false;
      return $allCols[$colname]['foreignKey']['table'] <> '';
    }
    public static function isValidTablename($tablename) {
      // check if contains only vaild letters
      return (!preg_match('/[^A-Za-z0-9_]/', $tablename));
    }
    public static function isValidColname($colname) {
      // = boolean // check if contains only vaild letters
      return (!preg_match('/[^A-Za-z0-9_]/', $colname));
    }
    public static function getVirtualColnames($tablename, $data = null) {
      $res = array();
      $cols = Config::getColsByTablename($tablename, $data);
      // Collect only virtual Columns
      foreach ($cols as $colname => $col) {
        if ($col["is_virtual"])
          $res[] = $colname;
      }
      return $res;
    }
    public static function getRealColnames($tablename) {
      $res = array();
      $cols = Config::getColsByTablename($tablename);
      // Collect only real columns
      foreach ($cols as $colname => $col) {
        if ($col["is_virtual"])
          continue;
        else
          $res[] = $colname;
      }
      return $res;
    }
    public static function getJoinedCols($tablename) {
      $res = array();
      $cols = Config::getColsByTablename($tablename);
      // Find primary columns
      foreach ($cols as $colname => $col) {
        if ($col["foreignKey"]['table'] != '')
          $res[] = array(
            'table' => $col["foreignKey"]['table'],
            'col_id' => $col["foreignKey"]['col_id'],
            'col_subst' => $col["foreignKey"]['col_subst'],
            'replace' => $colname
          );
      }
      return $res;
    }
  }

  class RequestHandler {
    private static function splitQuery($row) {
      $res = array();
      foreach ($row as $key => $value) { 
        $res[] = array("key" => $key, "value" => $value);
      }
      return $res;
    }
    private function readRowByPrimaryID($tablename, $ElementID) {
      $primColName = Config::getPrimaryColNameByTablename($tablename);

      $result = NULL;
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare("SELECT * FROM $tablename WHERE $primColName = ?");
      $stmt->execute(array($ElementID));
      while($row = $stmt->fetch(PDO::FETCH_NAMED)) {
        $result = $row;
      }
      return $result;
    }
    private function getActualStateByRow($tablename, $row) {    
      $result = -1; // default

      $pkColName = Config::getPrimaryColNameByTablename($tablename);
      $id = (int)$row[$pkColName];
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare("SELECT state_id FROM $tablename WHERE $pkColName = ? LIMIT 1");
      $stmt->execute(array($id));
      $row = $stmt->fetch();

      $result = $row['state_id'];
      return $result;
    }
    private function validateParamStruct($allowed_keys, $param) {
      if (!is_array($param)) return false;
      $keys = array_keys($param);
      foreach ($keys as $k) {
        if (!in_array($k, $allowed_keys)) return false;
      }
      return true;
    }
    private function isValidFilterStruct($input) {
      return !is_null($input) && is_array($input) && (array_key_exists('all', $input) || array_key_exists('columns', $input));
    }
    private function fmtCell($dtype, $inp, $test) {      
      //echo $dtype." (".$test.")\n";
      // TIME, DATE, DATETIME, FLOAT, VAR_STRING
      switch ($dtype) {
        case 'TINY': // Bool
        case 'LONG':
        case 'LONGLONG':
          return (int)$inp;
          break;
        
        case 'NEWDECIMAL':
        case 'FLOAT':
          return (float)$inp;
          break;

        default:
          return (string)$inp;
          break;
      }
    }
    private function parseResultData($stmt) {
      $result = [];
      while($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $r = [];
        $x = [];
        foreach($row as $idx => $val) {
          $meta = $stmt->getColumnMeta($idx);
          $table = $meta["table"];
          $col = $meta["name"];
          $dtype = $meta['native_type'];
          $test = implode(' - ', [$meta['len'], $meta['precision']]);
          //echo "$table.$col -> $val<br>";
          // Split Table and place into correct place in origin
          if (strpos($table, '_____') !== FALSE) {
            // Foreign Table or nested Foreign Table
            $splited = explode('_____', $table);
            if (count($splited) == 2) {
              // Layer 1
              if (is_array($x[$splited[1]]))
                $x[$splited[1]][$col] = $this->fmtCell($dtype, $val, $test); // Append
              else {
                // Convert to array
                $x[$splited[1]] = array();
                $x[$splited[1]][$col] = $this->fmtCell($dtype, $val, $test); // Append
              }
            }
            elseif (count($splited) == 3) {
              // Layer 2
              if (is_array($x[$splited[1]][$splited[2]] ))
                $x[$splited[1]][$splited[2]][$col] = $this->fmtCell($dtype, $val, $test); // Append
              else {
                // Convert to array
                $x[$splited[1]][$splited[2]] = array();
                $x[$splited[1]][$splited[2]][$col] = $this->fmtCell($dtype, $val, $test); // Append
              }
            }
          } else {
            // Origin Table
            $x[$col] = $this->fmtCell($dtype, $val, $test);
          }
          $r[$table][$col] = $this->fmtCell($dtype, $val, $test);
        }
        $result[] = $x;
      }
      // Return 
      return $result;
    }
    private function getFormCreate($param) {
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));

      $SM = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      // StateMachine ?
      if ($SM->getID() > 0) {
        // Has StateMachine
        $r = $SM->getCreateFormByTablename();
        if (empty($r))
          $r = "{}"; // default: allow editing (if there are no rules set)
        else
          return $r;
      }
      return '{}';
    }
    private function getNextStates($param) {
      $tablename = $param["table"];
      $stateID = $this->getActualStateByRow($tablename, $param['row']);
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));
      // execute query
      $SE = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      $res = $SE->getNextStates($stateID);
      return json_encode($res);
    }
    private function logHistory($tablename, $value, $isCreate) {
      if (Config::hasHistory()) {
        // Identify via Token
        global $token;
        $token_uid = -1;
        if (property_exists($token, 'uid')) $token_uid = $token->uid;
        // Write into Database
        $sql = "INSERT INTO History (User_id, History_table, History_valuenew, History_created) VALUES (?,?,?,?)";
        $pdo = DB::getInstance()->getConnection();
        $histStmt = $pdo->prepare($sql);
        $histStmt->execute([$token_uid, $tablename, json_encode($value), ($isCreate ? "1" : "0")]);
      }
    }

    //=======================================================
 
    // [OPTIONS]
    private function inititalizeTable($tablename) {
      // Init Vars
      $pdo = DB::getInstance()->getConnection();
      $SE = new StateMachine($pdo, $tablename);
      $param = ["table" => $tablename];
      // TODO: If Table = hidden then return
      // TODO: If Table = readonly then exclude formcreate and statemachine      
      // ---- Structure
      $config = json_decode(Config::getConfig(), true);
      $result = [];
      $result['config'] = $config[$tablename];
      $result['count'] = json_decode($this->count($param), true)[0]['cnt'];
      $result['formcreate'] = $this->getFormCreate($param);
      $result['sm_states'] = $SE->getStates();
      $result['sm_rules'] = $SE->getLinks();
      return $result;
    }
    public function init($param = null) {
      $tablename = $param["table"];
      if (is_null($param) && empty($tablename)) {
        // Read all Tables
        $conf = json_decode(Config::getConfig(), true);
        $result = [];
        foreach ($conf as $tablename => $t) {
          //echo $tablename;
          $result[$tablename] = $this->inititalizeTable($tablename);
        }
      } else {
        // Check Parameter
        if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
        if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));
        $result = $this->inititalizeTable($tablename);
      }
      return json_encode($result);
    }

    // [GET] Reading
    public function read($param) {
      //--------------------- Check Params
      $validParams = ['table', 'limitStart', 'limitSize', 'ascdesc', 'orderby', 'filter'/*, 'page'*/];
      $hasValidParams = $this->validateParamStruct($validParams, $param);
      if (!$hasValidParams) die(fmtError('Invalid parameters! (allowed are: '.implode(', ', $validParams).')'));
      // Parameters and default values
      @$tablename = isset($param["table"]) ? $param["table"] : die(fmtError('Table is not set!'));
      // -- Ordering, Limit, and Pagination
      @$limitStart = isset($param["limitStart"]) ? $param["limitStart"] : null;
      @$limitSize = isset($param["limitSize"]) ? $param["limitSize"] : null;
      @$ascdesc = isset($param["ascdesc"]) ? $param["ascdesc"] : null; 
      @$orderby = isset($param["orderby"]) ? $param["orderby"] : null; // has to be a column name
      @$filter = isset($param["filter"]) ? $param["filter"] : null;

      // Identify via Token
      global $token;
      $token_uid = -1;
      if (property_exists($token, 'uid')) $token_uid = $token->uid;
      // Table
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));

      //--- Limit
      if (!is_null($limitStart) && is_null($limitSize)) die(fmtError("Error in Limit Params (LimitSize is not set)!"));
      if (is_null($limitStart) && !is_null($limitSize)) die(fmtError("Error in Limit Params (LimitStart is not set)!"));
      if (!is_null($limitStart) && !is_null($limitSize)) {
        // Valid structure
        if (!is_numeric($limitStart)) die(fmtError("LimitStart is not numeric!"));
        if (!is_numeric($limitSize)) die(fmtError("LimitSize is not numeric!"));
      } else {
        // default: 1000 rows
        $limitStart = 0;
        $limitSize = 1000;
      }
      //--- OrderBy
      if (!is_null($ascdesc) && is_null($orderby)) die(fmtError("AscDesc can not be set without OrderBy!"));
      if (!is_null($orderby)) {
        if (!Config::isValidColname($orderby)) die(fmtError('OrderBy: Invalid Columnname!'));
        if (!Config::doesColExistInTable($tablename, $orderby)) die(fmtError('OrderBy: Column does not exist in this Table!'));
        //--- ASC/DESC
        $ascdesc = strtolower(trim($ascdesc));
        if ($ascdesc == "") $ascdesc == "ASC";
        elseif ($ascdesc == "asc") $ascdesc == "ASC";
        elseif ($ascdesc == "desc") $ascdesc == "DESC";
        else die(fmtError("AscDesc has no valid value (value has to be empty, ASC or DESC)!"));
      }
      //--- Filter
      if ($this->isValidFilterStruct($filter))
        $filter = json_encode($filter);

      // Prepare Structure
      $p = ['name' => 'sp_'.$tablename, 'inputs' => [$token_uid, $filter, $orderby, $ascdesc, $limitStart, $limitSize]];
      return $this->call($p);
    }
    public function count($param) {
      //--------------------- Check Params
      $validParams = ['table', 'filter'];
      $hasValidParams = $this->validateParamStruct($validParams, $param);
      if (!$hasValidParams) die(fmtError('Invalid parameters! (allowed are: '.implode(', ', $validParams).')'));
      // TODO !!!
      $tablename = $param["table"];
      $filter = isset($param["filter"]) ? $param["filter"] : null;
      $filter = json_encode($filter);
      // Identify via Token
      global $token;
      $token_uid = -1;
      if (property_exists($token, 'uid'))
        $token_uid = $token->uid;

      //--- Filter
      if ($this->isValidFilterStruct($filter))
        $filter = json_encode($filter);
      // Prepare Structure
      $p = ['name' => 'sp_'.$tablename, 'inputs' => [$token_uid, $filter, '', 'ASC', 0, 1000000]];
      $res = $this->call($p);
      // Parse result
      $data = json_decode($res, true);
      return json_encode(array(array('cnt' => count($data))));
    }

    // Stored Procedure can be Read and Write (GET and POST)
    public function call($param) {
      // Strcuture: {name: "sp_table", inputs: ["test", 13, 42, "2019-01-01"]}
      //--------------------- Check Params
      $validParams = ['name', 'inputs'];
      $hasValidParams = $this->validateParamStruct($validParams, $param);
      if (!$hasValidParams) die(fmtError('Invalid parameters! (allowed are: '.implode(', ', $validParams).')'));
      $name = $param["name"];
      $inputs = $param["inputs"];
      $inp_count = count($inputs);
      // Prepare Query
      $keys = array_fill(0, $inp_count, '?');
      $vals = $inputs;
      $keystring = implode(', ', $keys);
      $query = "CALL $name($keystring)";
      // Execute & Fetch
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare($query);
      if ($stmt->execute($vals)) {
        $result = $this->parseResultData($stmt);        
        return json_encode($result); // Return result as JSON
      } else {
        // Query-Error
        echo $stmt->queryString."<br>";
        echo json_encode($vals)."<br>";
        var_dump($stmt->errorInfo());
        exit();
      }
    }

    // [POST] Creating
    public function create($param) {
      // Inputs
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));

      // New State Machine
      $pdo = DB::getInstance()->getConnection();
      $SM = new StateMachine($pdo, $tablename);

      $script_result = []; // init

      //--- Has StateMachine? then execute Scripts
      if ($SM->getID() > 0) {
        // Override/Set EP
        $EP = $SM->getEntryState();
        $param["row"]["state_id"] = (int)$EP['id'];
        // Execute Transition Script
        $script = $SM->getTransitionScriptCreate();
        $script_result[] = $SM->executeScript($script, $param, $tablename);
        $script_result[0]['_entry-point-state'] = $EP; // append info
        $row = $param["row"]; // modify row via Script
      }
      else {
        // NO StateMachine => goto next step (write min data)
        $script_result[] = array("allow_transition" => true);
      }


      //--- If allow transition then Create
      if (@$script_result[0]["allow_transition"] == true) {
      	// Reload row, because maybe the TransitionScript has changed some params
        $keys = array();
        $vals = array();
        $x = RequestHandler::splitQuery($row);
        $cols = Config::getColsByTablename($tablename);
        foreach ($x as $el) {
          // Only add existing Columns of param to query
          if (array_key_exists($el["key"], $cols)) {
            // escape keys and values
            $keys[] = $el["key"];
            $vals[] = $el["value"];
          }
        }

        // --- Operation CREATE
        // Build Query
        $strKeys = implode(",", $keys); // Build str for keys
        // Build array of ? for vals
        $strVals = implode(",", array_fill(0, count($vals), '?'));
        $stmt = $pdo->prepare("INSERT INTO $tablename ($strKeys) VALUES ($strVals)");
        $stmt->execute($vals);
        $newElementID = $pdo->lastInsertId();


        // INSERT successful
        if ($newElementID > 0) {
          // Refresh row (+ add ID)
          $pcol = Config::getPrimaryColNameByTablename($tablename);          
          $param['row'] = $row;
          $param['row'] = [$pcol => $newElementID] + $param['row']; // Add PrimaryID at the beginning  
          $this->logHistory($tablename, $param["row"], true); // Write in History-Table (Create)

          // Execute IN-Script, but only when Insert was successful and Statemachine exists
          // If Statemachine execute IN-Script
          if ($SM->getID() > 0) {
            $script = $SM->getINScript($EP['id']);
            $tmp_script_res = $SM->executeScript($script, $param, $tablename);
            // Append Metadata (New ID and stateID)
            $tmp_script_res["element_id"] = $newElementID;
            $tmp_script_res['_entry-point-state'] = $EP;
            // Append Script
            $script_result[] = $tmp_script_res;
          } else {
            // No Statemachine
            $script_result[0]["element_id"] = $newElementID;
          }
        }
        else {
          // ErrorHandling
          $script_result[0]["element_id"] = 0;
          $script_result[0]["errormsg"] = $stmt->errorInfo()[2];
        }
      }
      // Return
      return json_encode($script_result);
    }

    // [PATCH] Changing
    // TODO: Remove Update function bzw. combine into 1 Function (update = specialcase)
    public function update($param, $allowUpdateFromSM = false) {
       // Parameter
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));

      // Check if has Table has NO state-machine
      if (!$allowUpdateFromSM) {
        $SM = new StateMachine(DB::getInstance()->getConnection(), $tablename);
        $SMID = $SM->getID();
        if ($SMID > 0) die(fmtError("Table with state-machine can not be updated! Use state-machine instead!"));
      }
      // Extract relevant Info via Config     
      $pcol = Config::getPrimaryColNameByTablename($tablename);
      $id = (int)$row[$pcol];

      // Split Row into Key:Value Array
      $keys = array();
      $vals = array();
      $rowElements = RequestHandler::splitQuery($row);
      $cols = Config::getRealColnames($tablename); // only get real colnames
      foreach ($rowElements as $el) {
        // Filter Primary Key
        if ($el["key"] == $pcol)
          continue;
        // Only add existing Columns of param to query
        if (in_array($el["key"], $cols)) {
          // escape keys and values
          $keys[] = $el["key"] . '=?';
          $vals[] = $el["value"];
        }
      }
      // Build Query
      $strKeys = implode(",", $keys); // Build str for keys

      // Execute on Database
      $success = false;
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare("UPDATE $tablename SET $strKeys WHERE $pcol = ?");
      array_push($vals, $id); // Append primary ID to vals
      if ($stmt->execute($vals)) {
        // Check if rows where updated
        $success = ($stmt->rowCount() > 0);
      }
      else {
        // ErrorHandling
        //echo $stmt->queryString."<br />";
        //var_dump($stmt->errorInfo());
        //$script_result[0]["element_id"] = 0;
        //$script_result[0]["errormsg"] = $stmt->errorInfo()[2];
        die(fmtError($stmt->errorInfo()[2]));
      }
      // Log History
      if ($success) {
        $this->logHistory($tablename, $param["row"], false);
      }
      // Output
      return $success ? "1" : "0";
    }
    public function makeTransition($param) {
      // INPUT [table, ElementID, (next)state_id]
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));
      
      // Get Primary Column
      $pcol = Config::getPrimaryColNameByTablename($tablename);
      $ElementID = $param["row"][$pcol];

      // Load all data from Element
      $existingData = $this->readRowByPrimaryID($tablename, $ElementID);
      // overide existing data
      foreach ($param['row'] as $key => $value) {
        $existingData[$key] = $value;
      }
      $param["row"] = $existingData;

      // Statemachine
      $SE = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      // get ActStateID by Element ID
      $actstateID = $this->getActualStateByRow($tablename, $param['row']);

      // Get the next ID for the next State or if none is used try a Save
      if (array_key_exists('state_id', $param['row'])) {
        $nextStateID = $param["row"]["state_id"];
      } else {        
        $nextStateID = $actstateID; // Try a save transition
      }

      // check if transition is allowed
      $transPossible = $SE->checkTransition($actstateID, $nextStateID);
      if ($transPossible) {
        // Execute Scripts
        $feedbackMsgs = array(); // prepare empty array

        //---[1]- Execute [OUT] Script
        $out_script = $SE->getOUTScript($actstateID); // from source state
        $res = $SE->executeScript($out_script, $param, $tablename);
        if (!$res['allow_transition']) {
          $feedbackMsgs[] = $res;
          return json_encode($feedbackMsgs); // Exit -------->
        } else {
          $feedbackMsgs[] = $res;
          // Continue
        }

        //---[2]- Execute [Transition] Script
        $tr_script = $SE->getTransitionScript($actstateID, $nextStateID);
        $res = $SE->executeScript($tr_script, $param, $tablename);
        if (!$res["allow_transition"]) {
          $feedbackMsgs[] = $res;
          return json_encode($feedbackMsgs); // Exit -------->
        } else {
          $feedbackMsgs[] = $res;
          // Continue
        }

        // Update all rows
        $this->update($param, true);

        //---[3]- Execute IN Script
        $in_script = $SE->getINScript($nextStateID); // from target state
        $res = $SE->executeScript($in_script, $param, $tablename);
        $res["allow_transition"] = true;
        $feedbackMsgs[] = $res;
        echo json_encode($feedbackMsgs);
        exit;

      } else 
        die(fmtError("Transition not possible!"));
    }
    // TODO => this function merges update + makeTransition
    public function change($param) {

    }

    // [DELETE] Deleting
    public function delete($param) {
      //---- NOT SUPPORTED FOR NOW [!]
      die(fmtError('The Delete-Command is currently not supported!'));
      // Parameter
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die(fmtError('Invalid Tablename!'));
      if (!Config::doesTableExist($tablename)) die(fmtError('Table does not exist!'));
      // Extract relevant Info via Config
      $pcol = Config::getPrimaryColNameByTablename($tablename);
      $id = (int)$row[$pcol];
      // Execute on Database
      $success = false;
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare("DELETE FROM $tablename WHERE $pcol = ?");
      $stmt->execute(array($id));
      // Check if rows where updated
      $success = ($pdo->rowCount() > 0);
      // Output
      return $success ? "1" : "0";
    }
  

    //---------------------------------- File Handling (check Token) ... [GET]
    public function getFile($param) {
      // Download File from Server

      // Inputs
      $filename = $param["name"];
      $filepath = $param["path"];
      $tmp_parts = explode(".", $param["name"]);
      $filetype = end($tmp_parts);

      // Whitelists
      $whitelist_paths = WHITELIST_PATHS;
      $whitelist_types = WHITELIST_TYPES;

      if (in_array($filepath, $whitelist_paths) && in_array($filetype, $whitelist_types)) {
        //echo "path and type in whitelist\n";
        // File exists
        $filepathcomplete = __DIR__."/../".$filepath . $filename;
        //echo "Filepath: ".$filepathcomplete."\n";
        if (file_exists($filepathcomplete)) {
          //echo "File exists\n";
          $filecontent = file_get_contents($filepathcomplete);
          echo $filecontent;
        } else 
          die(fmtError("error"));
      } else
        die(fmtError("error"));
    }
  }
