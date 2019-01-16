<?php
  // Includes
  $file_DB = __DIR__."/DatabaseHandler.inc.php";
  if (file_exists($file_DB)) include_once($file_DB);
  $file_SM = __DIR__."/StateMachine.inc.php";
  if (file_exists($file_SM)) include_once($file_SM);


  
  // Global function for StateMachine
  function api($data) {
    $url = API_URL;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, $url);
    $headers = array();
    //JWT token for Authentication
    $headers[] = 'Cookie: token='.MACHINE_TOKEN;
    if ($data) {
      $json = json_encode($data);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
      $headers[] = 'Content-Type: application/json';
      $headers[] = 'Content-Length: ' . strlen($json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($ch);
  }


  class Config {
    // In this class the configuration should be loaded in the constructor once
    // there should be a method for getting easy the primary column by tablename
    // also other helper functions
    // i.e.

    public static function getConfig() {
      global $config_tables_json;
      return $config_tables_json;
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
      foreach ($cols as $col) {
        if ($col["COLUMN_KEY"] == "PRI")
          $res[] = $col["COLUMN_NAME"];
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
    public static function doesTableExist($tablename) {
      $result = false;
      //$tablename = strtolower($tablename); // always lowercase
      $config = json_decode(Config::getConfig(), true);
      $result = (array_key_exists($tablename, $config));
      return $result;
    }
    public static function doesColExistInTable($tablename, $colname) {
      //= boolean
    }
    public static function hasColumnFK($tablename, $colname) {
      $allCols = Config::getColsByTablename($tablename);
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
      foreach ($cols as $col) {
        if ($col["is_virtual"])
          $res[] = $col["COLUMN_NAME"];
      }
      return $res;
    }
    public static function getRealColnames($tablename) {
      $res = array();
      $cols = Config::getColsByTablename($tablename);
      // Collect only real columns
      foreach ($cols as $col) {
        if ($col["is_virtual"])
          continue;
        else
          $res[] = $col["COLUMN_NAME"];
      }
      return $res;
    }
    public static function getJoinedCols($tablename) {
      $res = array();
      $cols = Config::getColsByTablename($tablename);
      // Find primary columns
      foreach ($cols as $col) {
        if ($col["foreignKey"]['table'] != '')
          $res[] = array(
            'table' => $col["foreignKey"]['table'],
            'col_id' => $col["foreignKey"]['col_id'],
            'col_subst' => $col["foreignKey"]['col_subst'],
            'replace' => $col["COLUMN_NAME"]
          );
      }
      return $res;
    }
    public static function checkSQLParts($queryPart) {
      $disAllow = array(
        '--', 'SELECT', 'INSERT','UPDATE','DELETE','UNION','RENAME','DROP','CREATE','TRUNCATE','ALTER','COMMIT',
        'ROLLBACK','MERGE','CALL','EXPLAIN','LOCK','GRANT','REVOKE','SAVEPOINT','TRANSACTION','SET'
      );
      // Convert array to pipe-seperated string
      $disAllow = implode('|', $disAllow);
      // Check if no other harmfull statements exist
      if (preg_match('/('.$disAllow.')/', strtoupper($queryPart)) == 0) {
        // Execute query
        return true;
      }
      return false;
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
    // -------------------------------------------------- Database Access Methods
    // TODO: Use the stored procedure
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

    //======= INIT (Load the configuration to the client)
    public function init($param = null) {
      if (is_null($param))
        return Config::getConfig();
      else {
        // Send only data from a specific Table
        // Send info: structure (from config) the createForm and Count of all entries
        $tablename = $param["table"];
        $where = $param["where"] != '' ? $param["where"] : '';
        // Check Parameter
        if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
        if (!Config::doesTableExist($tablename)) die('Table does not exist!');

        $pdo = DB::getInstance()->getConnection();
        $result = [];
        // ---- Structure
        $config = json_decode(Config::getConfig(), true);
        $result['config'] = $config[$tablename];
        // ---- Count
        $result['count'] = json_decode($this->count($param), true)[0]['cnt'];
        // ---- Forms
        $result['formcreate'] = $this->getFormCreate($param);
        $result['formmodify'] = $this->getFormData($param);  
        // Return result as JSON
        return json_encode($result);
      }
    }
    //================================== CREATE
    public function create($param) {
      // Inputs
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      // New State Machine
      $pdo = DB::getInstance()->getConnection();
      $SM = new StateMachine($pdo, $tablename);
      $script_result = array();

      //--- Has StateMachine? then execute Scripts
      if ($SM->getID() > 0) {
        // Override/Set EP
        $EP = $SM->getEntryPoint();
        $param["row"]["state_id"] = (int)$EP;
        // Execute Transition Script
        $script = $SM->getTransitionScriptCreate();
        $script_result[] = $SM->executeScript($script, $param, $tablename);
        $row = $param["row"];
      }
      else {
        // NO StateMachine => goto next step
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

        // Execute IN-Script, but only when Insert was successful and Statemachine exists
        if (($SM->getID() > 0) && ($newElementID > 0)) {
          $script = $SM->getINScript($SM->getEntryPoint());
          // Refresh row (add ID)
          $pcol = Config::getPrimaryColNameByTablename($tablename);
          $param['row'] = $row;
          $param['row'][$pcol] = (string)$newElementID;
          // IN-Script          
          $tmp_script_res = $SM->executeScript($script, $param, $tablename);
          // Append the ID from new Element
          $tmp_script_res["element_id"] = $newElementID;
          $script_result[] = $tmp_script_res;
        } else {
          // No Statemachine
          $script_result[0]["element_id"] = $newElementID;
          // ErrorHandling
          if ($newElementID == 0) {
            $script_result[0]["errormsg"] = $stmt->errorInfo()[2];
          }
        }

      }
      // Return
      return json_encode($script_result);
    }
    public function count($param) {
      // TODO: Make a special count function maybe inside of SP
      global $token;
      $tablename = $param["table"];
      $where = $param["where"] != '' ? $param["where"] : '';
      $token_uid = $token->uid;
      $params = array(
        'table' => $tablename,
        'token' => $token_uid,
        'filter' => '',
        'where' => $where,
        'orderby' => '',
        'ascdesc' => null,
        'limitstart' => null,
        'limitsize' => null
      );
      $data = json_decode($this->call($params), true);
      return json_encode(array(array('cnt' => count($data))));
    }
    //================================== CALL
    public function call($param) {
      $tablename = $param["table"];
      $keys = [];
      $vals = [];
      foreach ($param as $key => $value) {
        if ($key != "table") { // Exclude tablename
          $keys[] = '?';
          $vals[] = $value;
        }
      }
      //$valstring = implode(', ', $vals);
      $keystring = implode(', ', $keys);
      $query = 'CALL sp_'.$tablename.'('.$keystring.')';
      // Execute & Fetch
      $result = array();
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare($query);
      if ($stmt->execute($vals)) {

        // Query executed successfully
        while($row = $stmt->fetch(PDO::FETCH_NUM)) {
          $r = [];
          $x = [];
          foreach($row as $idx => $val) {
            $meta = $stmt->getColumnMeta($idx);            
            $table = $meta["table"];
            $col = $meta["name"];
            //echo "$table.$col -> $val<br>";

            // Split Table and place into correct place in origin
            if (strpos($table, '_____') !== FALSE) {
              // Foreign Table or nested Foreign Table
              $splited = explode('_____', $table);

              if (count($splited) == 2) {
                // Layer 1
                if (is_array($x[$splited[1]]))
                  $x[$splited[1]][$col] = $val; // Append
                else {
                  // Convert to array
                  $x[$splited[1]] = array();
                  $x[$splited[1]][$col] = $val; // Append
                }
              }
              elseif (count($splited) == 3) {
                // Layer 2
                if (is_array($x[$splited[1]][$splited[2]] ))
                  $x[$splited[1]][$splited[2]][$col] = $val; // Append
                else {
                  // Convert to array
                  $x[$splited[1]][$splited[2]] = array();
                  $x[$splited[1]][$splited[2]][$col] = $val; // Append
                }
              }

            } else {
              // Origin Table
              $x[$col] = $val;
            }
            $r[$table][$col] = $val;

          }
          $result[] = $x;
        }


      } else {
        echo $stmt->queryString."<br>";
        echo json_encode($vals)."<br>";
        var_dump($stmt->errorInfo());
      }
      // Return result as JSON
      return json_encode($result);
    }
    //================================== READ
    public function read($param) {
      // Parameters and default values
      try {
        @$tablename = $param["table"];
        // Limit
        @$limitStart = isset($param["limitStart"]) ? $param["limitStart"] : null;
        @$limitSize = isset($param["limitSize"]) ? $param["limitSize"] : null;
        @$limit = isset($param["limit"]) ? $param["limit"] : null;
        // OrderBy
        @$ascdesc = isset($param["ascdesc"]) ? $param["ascdesc"] : null; 
        @$orderby = isset($param["orderby"]) ? $param["orderby"] : null; // has to be a column name
        // Where / Filter
        @$filter = isset($param["filter"]) ? $param["filter"] : "";
        @$where = isset($param["where"]) ? $param["where"] : "";
      } catch (Exception $e) {
        die("Invalid Parameter-Data!");
      }
      // Give all params to SP like (filter, orderBY, ASCDESC, LIMIT-start, LIMIT-size, [spare1, spare2, spare3, spare4, spare5])

      //--- Token
      global $token;
      $token_uid = $token->uid;
      //--- ASC/DESC
      $ascdesc = strtolower(trim($ascdesc));
      if ($ascdesc == "") $ascdesc == "ASC";
      elseif ($ascdesc == "asc") $ascdesc == "ASC";
      elseif ($ascdesc == "desc") $ascdesc == "DESC";
      else die("AscDesc has no valid value (value has to be empty, ASC or DESC)!");
      $params = array(
        'table' => $tablename,
        'token' => $token_uid,
        'filter' => $filter,
        'where' => $where,
        'orderby' => $orderby,
        'ascdesc' => $ascdesc,
        'limitstart' => $limitStart,
        'limitsize' => $limitSize
      );

      return $this->call($params);
    }
    //================================== UPDATE
    public function update($param, $allowUpdateFromSM = false) {
       // Parameter
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      // Check if has Table has NO state-machine
      if (!$allowUpdateFromSM) {
        $SM = new StateMachine(DB::getInstance()->getConnection(), $tablename);
        $SMID = $SM->getID();
        if ($SMID > 0) die("Table with state-machine can not be updated! Use state-machine instead!");
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
      } else {
        echo $stmt->queryString."<br />";
        var_dump($stmt->errorInfo());
      }
      // Output
      return $success ? "1" : "0";
    }
    //================================== DELETE (sec)
    public function delete($param) {

      // NOT SUPPORTED FOR NOW
      die('The Delete-Command is currently not supported!');

      // Parameter
      $tablename = $param["table"];
      $row = $param["row"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');
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

    //----------------------------------

    public function getFormData($param) {
      // Inputs
      $tablename = $param["table"];
      @$row =  $param['row'];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      $SM = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      // Check if has state machine ?
      if ($SM->getID() > 0) {
        $stateID = $this->getActualStateByRow($tablename, $row);
        $r = $SM->getFormDataByStateID($stateID);
        if (empty($r)) $r = "1"; // default: allow editing (if there are no rules set)
        return $r;
      }
      else {
        // Has NO StateMachine -> Return standard form
        $cols = Config::getColsByTablename($tablename);
        $PrimKey = array(Config::getPrimaryColNameByTablename($tablename));
        $VirtKeys = Config::getVirtualColnames($tablename);
        $excludeKeys = array_merge($PrimKey, $VirtKeys);        
        $r = $SM->getBasicFormDataByColumns($tablename, Config::getConfig(), $cols, $excludeKeys);
        return $r;
      }
    }
    public function getFormCreate($param) {
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      $SM = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      // StateMachine ?
      if ($SM->getID() > 0) {
        // Has StateMachine
        $r = $SM->getCreateFormByTablename();
        if (empty($r))
          $r = "1"; // default: allow editing (if there are no rules set)
        else
          return $r;
      }
      // Return standard form
      $cols = Config::getColsByTablename($tablename);
      $PrimKey = array(Config::getPrimaryColNameByTablename($tablename));
      $VirtKeys = Config::getVirtualColnames($tablename);
      $excludeKeys = array_merge($PrimKey, $VirtKeys, array('state_id'));
      $r = $SM->getBasicFormDataByColumns($tablename, Config::getConfig(), $cols, $excludeKeys, true);
      return $r;
    }
    public function getNextStates($param) {
      // Inputs
      $tablename = $param["table"];
      $stateID = $this->getActualStateByRow($tablename, $param['row']);
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      // execute query
      $SE = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      $res = $SE->getNextStates($stateID);
      return json_encode($res);
    }
    public function makeTransition($param) {
      // INPUT [table, ElementID, (next)state_id]
      // Get the next ID for the next State
      $nextStateID = $param["row"]["state_id"];
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

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
          return json_encode($feedbackMsgs);
        } else {
          $feedbackMsgs[] = $res;
        }
        //---[2]- Execute [Transition] Script
        $tr_script = $SE->getTransitionScript($actstateID, $nextStateID);
        $res = $SE->executeScript($tr_script, $param, $tablename);
        if (!$res["allow_transition"]) {
          $feedbackMsgs[] = $res;
          return json_encode($feedbackMsgs);
        } else {
          $feedbackMsgs[] = $res;
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

      } else {
        echo "Transition not possible!";
        exit;
      }
    }
    public function getStates($param) {
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');
    
      $SE = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      $res = $SE->getStates();
      return json_encode($res);
    }
    public function smGetLinks($param) {
      $tablename = $param["table"];
      // Check Parameter
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');

      $SE = new StateMachine(DB::getInstance()->getConnection(), $tablename);
      $res = $SE->getLinks();
      return json_encode($res);
    }
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
          die("error");
      } else
        die("error");
    }
  }
?>