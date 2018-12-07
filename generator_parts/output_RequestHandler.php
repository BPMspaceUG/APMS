<?php
  // Includes
  $file_DB = __DIR__."/DatabaseHandler.inc.php";
  if (file_exists($file_DB)) include_once($file_DB);
  $file_SM = __DIR__."/StateMachine.inc.php";
  if (file_exists($file_SM)) include_once($file_SM);


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
    public static function getVirtualColnames($tablename) {
      $res = array();
      $cols = Config::getColsByTablename($tablename);
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
    private function readRowByPrimaryID($tablename, $ElementID) {
      $primColName = Config::getPrimaryColNameByTablename($tablename);

      $result = NULL;
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare("SELECT * FROM $tablename WHERE $primColName = ?");
      $stmt->execute(array($ElementID));   
      while($row = $stmt->fetch()) {
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
        $where = $param["where"] != '' ? $param["where"] : '1=1';
        // Check Parameter
        if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
        if (!Config::doesTableExist($tablename)) die('Table does not exist!');

        $pdo = DB::getInstance()->getConnection();
        $result = [];

        // ---- Structure
        $config = json_decode(Config::getConfig(), true);
        $result['config'] = $config[$tablename];

        // ---- Count
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM $tablename AS a WHERE $where");
        if ($stmt->execute()) {
          $row = $stmt->fetch(PDO::FETCH_NAMED);
          $result['count'] = $row['cnt'];
        } else {
          echo $stmt->queryString."<br />";
          var_dump($stmt->errorInfo());
        }

        // ---- CreateForm
        $result['formcreate'] = $this->getFormCreate($param);
  
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
        $script_result[] = $SM->executeScript($script, $param);
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
          $tmp_script_res = $SM->executeScript($script, $param);
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
      global $token;
      $tablename = $param["table"];
      $token_uid = $token->uid;

      $query = 'CALL sp_'.$tablename.'('.$token_uid.')';
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare($query);
      $stmt->execute(array());
      return json_encode(array(array('cnt' => $stmt->rowCount())));
    }
    //================================== CALL
    public function call($param) {
      global $token;
      $tablename = $param["table"];
      $token_uid = $token->uid;

      $query = 'CALL sp_'.$tablename.'('.$token_uid.')';

      // Execute & Fetch
      $result = array();
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare($query);
      if ($stmt->execute(array())) {
        while($row = $stmt->fetch(PDO::FETCH_NAMED)) {
          $result[] = $row;
        }
      } else {
        echo $stmt->queryString."<br />";
        var_dump($stmt->errorInfo());
      }

      // Return result as JSON
      return json_encode($result);
    }
    //================================== READ
    public function read($param) {

      //return $this->call($param);


      // Parameters and default values
      try {
        @$tablename = $param["table"];
        @$ascdesc = isset($param["ascdesc"]) ? $param["ascdesc"] : "";      
        @$limitStart = isset($param["limitStart"]) ? $param["limitStart"] : null;
        @$limitSize = isset($param["limitSize"]) ? $param["limitSize"] : null;
        @$limit = isset($param["limit"]) ? $param["limit"] : null;
        @$orderby = isset($param["orderby"]) ? $param["orderby"] : "";
        @$filter = isset($param["filter"]) ? $param["filter"] : "";
        @$select = isset($param["select"]) ? $param["select"] : "*";
        @$where = isset($param["where"]) ? $param["where"] : "";
      } catch (Exception $e) {
        die("Invalid Parameter-Data!");
      }

      // Check Parameters
      if (!Config::checkSQLParts($select)) die('Select Param contains blacklisted words!');
      if (!Config::checkSQLParts($where)) die('Where Param contains blacklisted words!');     
      if (!Config::isValidTablename($tablename)) die('Invalid Tablename!');
      if (!Config::doesTableExist($tablename)) die('Table does not exist!');
      
      // For internal use only (values of the prepared stmt)
      $vals = array();

      //--- ORDER BY
      if (trim($orderby) <> "") {
        // TODO: Check if orderby is a valid Column name and does not contain any special chars
        if (!Config::isValidColname($orderby)) die('Param OrderBy has invalid chars in it!');
        //--- ASC/DESC (sec)
        $ascdesc = strtolower(trim($ascdesc));
        if ($ascdesc == "") $ascdesc == "";
        elseif ($ascdesc == "asc") $ascdesc == "ASC";
        elseif ($ascdesc == "desc") $ascdesc == "DESC";
        else die("AscDesc has no valid value (value has to be empty, ASC or DESC)!");
        // Check if is a foreign key then add 'a.' at front
        if (Config::hasColumnFK($tablename, $orderby))
        $orderby = 'a.'.$orderby;
        // Build query
        $sql_orderby = " ORDER BY ".$orderby." ".$ascdesc;
      } else {
        // No Orderby is set
        $sql_orderby = " "; // ORDER BY replacer_id DESC";
      }
      
      //--- LIMIT (sec)
      $sql_limit = '';
      if (!is_null($limit)) {
        if (!is_int($limit)) die("Limit is no integer!");
        // Only use one limit param
        $sql_limit = " LIMIT ".$limit;
      }
      if (!is_null($limitStart) || !is_null($limitSize)) {
        if (is_null($limitStart) || is_null($limitSize))
        die('Limit-Start and Limit-Size have to be set.');
        if (!is_int($limitSize)) die("Limit-Size is no integer!");
        if (!is_int($limitStart)) die("Limit-Start is no integer!");
        $sql_limit = " LIMIT ".$limitStart.",".$limitSize;
      }
      
      //--- JOINS
      // Only get Joins from Server-Side
      $joins = Config::getJoinedCols($tablename);
      $join_from = $tablename." AS a"; // if there is no join
      $sel = array();
      $sel_raw = array();
      $sel_str = "";
      if (count($joins) > 0) {
        // Multi-join
        for ($i=0;$i<count($joins);$i++) {
          $substCol = $joins[$i]["col_subst"];
          // The FROM Part
          $join_from .= " LEFT JOIN ".$joins[$i]["table"]." AS t$i ON ".
            "t$i.".$joins[$i]["col_id"]."= a.".$joins[$i]["replace"];
          // The SELECT Part
          if (strpos($substCol, '(')) {
            // Check if contains a function parenthesis, then handle differently
            $sel[] = $substCol." AS '".$joins[$i]["replace"]."'";
            $sel_raw[] = $substCol; // This is only for the filter later
          }
          else {
            // No concat function involved
            $sel[] = "t$i.".$substCol." AS '".$joins[$i]["replace"]."'";
            $sel_raw[] = "t$i.".$substCol; // This is only for the filter later
          }          
        }
        $sel_str = implode(",", $sel);
      }

      // Check for virtual columns
      $cols = Config::getColsByTablename($tablename);
      $virtCols = Config::getVirtualColnames($tablename);
      $virtSelects = [];
      if (count($virtCols) > 0) {
        foreach ($virtCols as $vcol) {
          $virtSel = $cols[$vcol]['virtual_select'];
          $virtSelects[] = $virtSel;
          $sel_str .= ",".$virtSel.' AS '.$vcol;
        }
      }

      //--- WHERE (SEARCH / Filter)
      if ($where <> "" && $filter == "") {
        $where = " WHERE ".$where;
      }
      else if ($filter <> "") {
        //------------ FILTER

        // TODO: Maybe get the columns from the config file!
        // Get columns from the table -> also is faster than a new request
        $stmt = DB::getInstance()->getConnection()->prepare("SHOW COLUMNS FROM $tablename");
        $k = [];
        $stmt->execute();
        while ($row = $stmt->fetch()) {
          $k[] = $row[0];
        }

        
        $k = array_merge($k, $sel_raw); // Additional JOIN-columns
        $k = array_merge($k, $virtSelects); // Also add virtual columns

        // xxx LIKE = '%".$param["filter"]."%' OR yyy LIKE '%'
        $q_str = "";
        foreach ($k as $key) {
          $prefix = "";
          // if no "." in string and no function in key then refer to first table
          if (strpos($key, ".") === FALSE && strpos($key, '(') === FALSE)
            $prefix = "a.";
          $q_str .= " ".$prefix.$key." LIKE :filter OR ";
        }
        // Remove last 'OR '
        $q_str = substr($q_str, 0, -3);
        $vals[':filter'] = '%'.$filter.'%';

        // Build WHERE String
        if ($where == '')
          $where = " WHERE $q_str";
        else
          $where = " WHERE $where AND ($q_str)";
      }

      //--- SELECT
      if ($select == 'COUNT(*) AS cnt') $sql_select = 'COUNT(*) AS cnt';
      elseif ($select == 'a.*') $sql_select = "a.*";
      elseif ($select == '*') {
        $sql_select = "a.*";
        if ($sel_str <> "")
          $sql_select .= ", ".$sel_str;
      }
      else $sql_select = $select.', '.$sel_str;
      // bugfix
      $sql_select = str_replace(', ,', ',', $sql_select);

      // TODO: sel_str can only contain columnnames and , and COUNT(*)

      $query = "SELECT " . $sql_select . " FROM " . $join_from . $where . $sql_orderby . $sql_limit;
      // Clean up a bit
      $query = str_replace("  ", " ", $query);

      // For debugging
      /*
      if (strpos($query, 'COUNT') === FALSE) {
        file_put_contents("query-log.txt", $query);
      }
      */

      // Execute & Fetch
      $result = array();
      $pdo = DB::getInstance()->getConnection();
      $stmt = $pdo->prepare($query);
      if ($stmt->execute($vals)) {
        while($row = $stmt->fetch(PDO::FETCH_NAMED)) {
          $result[] = $row;
        }
      } else {
        echo $stmt->queryString."<br />";
        var_dump($stmt->errorInfo());
      }

      // Return result as JSON
      return json_encode($result);
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
      $row =  $param['row'];
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
      } else {
        // respond true if no statemachine (means: allow editing)
        return "1"; 
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
        if (empty($r)) $r = "1"; // default: allow editing (if there are no rules set)
      } else {
        // Has NO StateMachine -> Return standard form
        $cols = Config::getColsByTablename($tablename);   

        $PrimKey = array(Config::getPrimaryColNameByTablename($tablename));
        $VirtKeys = Config::getVirtualColnames($tablename);
        $excludeKeys = array_merge($PrimKey, $VirtKeys);
        
        $r = $SM->getBasicFormDataByColumns($tablename, Config::getConfig(), $cols, $excludeKeys);
      }
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
        $res = $SE->executeScript($out_script, $param);
        if (!$res['allow_transition']) {
          $feedbackMsgs[] = $res;
          return json_encode($feedbackMsgs);
        } else {
          $feedbackMsgs[] = $res;
        }
        //---[2]- Execute [Transition] Script
        $tr_script = $SE->getTransitionScript($actstateID, $nextStateID);
        $res = $SE->executeScript($tr_script, $param);
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
        $res = $SE->executeScript($in_script, $param);
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