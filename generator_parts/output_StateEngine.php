<?php

  class StateMachine {
    // Variables
    private $db;
    private $ID = -1;
    private $table = "";
    private $query_log = "";


    public function __construct($PDO_Connection, $tablename = "") {
      $this->db = $PDO_Connection;
      $this->table = $tablename;
      if ($this->table != "")
      	$this->ID = $this->getSMIDByTablename($tablename);
    }

    private function log($text) {
      $this->query_log .= $text."\n\n";
    }
    public function getQueryLog() {
      return $this->query_log;
    }

    private function getSMIDByTablename($tablename) {
      $result = -1; //NULL;
      $stmt = $this->db->prepare("SELECT MIN(id) AS 'id' FROM state_machines WHERE tablename = ?");
      $stmt->execute(array($tablename));
      while($row = $stmt->fetch()) {
        $result = $row['id'];
      }
      return $result;
    }
    private function getStateAsObject($stateid) {
      $result = -1; //NULL;
      $stmt = $this->db->prepare("SELECT state_id AS 'id', name AS 'name' FROM state WHERE state_id = ?");
      $stmt->execute(array($stateid));  
      while($row = $stmt->fetch()) {
        $result = $row;
      }
      return $result;
    }



    public function createDatabaseStructure() {
    	// ------------------------------- T A B L E S
    	//---- Create Table 'state_machines'
		  $query = "CREATE TABLE IF NOT EXISTS `state_machines` (
			  `id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `tablename` varchar(45) DEFAULT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
      $this->log($query); 

      // Add Form_data column to state_machines if not exists
      $query = "SHOW COLUMNS FROM `state_machines`;";
      $rows = $this->db->query($query);
      // Build one string with all columnnames
      $columnstr = "";
      foreach ($rows as $row) $columnstr .= $row["Field"];
      // Column [form_data] does not yet exist
      if (strpos($columnstr, "form_data") === FALSE) {
        $query = "ALTER TABLE `state_machines` ADD COLUMN `form_data` LONGTEXT NULL AFTER `tablename`;";
        $this->db->query($query);
        $this->log($query);
      }
      // Column [form_data_default] does not yet exist
      if (strpos($columnstr, "form_data_default") === FALSE) {
        $query = "ALTER TABLE `state_machines` ADD COLUMN `form_data_default` LONGTEXT NULL AFTER `tablename`;";
        $this->db->query($query);
        $this->log($query);
      }
      // Column [form_data] does not yet exist
      if (strpos($columnstr, "transition_script") === FALSE) {
        $query = "ALTER TABLE `state_machines` ADD COLUMN `transition_script` LONGTEXT NULL AFTER `tablename`;";
        $this->db->query($query);
        $this->log($query);
      }

		  //---- Create Table 'state'
		  $query = "CREATE TABLE IF NOT EXISTS `state` (
			  `state_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `name` varchar(45) DEFAULT NULL,
			  `form_data` longtext,
			  `entrypoint` tinyint(1) NOT NULL DEFAULT '0',
			  `statemachine_id` bigint(20) NOT NULL DEFAULT '1',
        `script_IN` longtext,
        `script_OUT` longtext,
			  PRIMARY KEY (`state_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
      $this->log($query); 

      // Add columns script_IN and script_OUT
      $query = "SHOW COLUMNS FROM  `state`;";
      $rows = $this->db->query($query);
      // Build one string with all columnnames
      $columnstr = "";
      foreach ($rows as $row) $columnstr .= $row["Field"];
      // Column [script_IN] does not yet exist
      if (strpos($columnstr, "script_IN") === FALSE) {
        $query = "ALTER TABLE `state` ADD COLUMN `script_IN` LONGTEXT NULL AFTER `statemachine_id`;";
        $this->db->query($query);
        $this->log($query);
      }
      // Column [script_OUT] does not yet exist
      if (strpos($columnstr, "script_OUT") === FALSE) {
        $query = "ALTER TABLE `state` ADD COLUMN `script_OUT` LONGTEXT NULL AFTER `script_IN`;";
        $this->db->query($query);
        $this->log($query); 
      }

		  // Create Table 'state_rules'
		  $query = "CREATE TABLE IF NOT EXISTS `state_rules` (
			  `state_rules_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `state_id_FROM` bigint(20) NOT NULL,
			  `state_id_TO` bigint(20) NOT NULL,
			  `transition_script` longtext,
			  PRIMARY KEY (`state_rules_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		  $this->db->query($query);
      $this->log($query); 
			// ------------------------------- F O R E I G N - K E Y S
		  // 'state_rules'
		  $query = "ALTER TABLE `state_rules` ".
		    "ADD INDEX `state_id_fk1_idx` (`state_id_FROM` ASC), ".
		    "ADD INDEX `state_id_fk_to_idx` (`state_id_TO` ASC);";
		  $this->db->query($query);
      $this->log($query); 
		  $query = "ALTER TABLE `state_rules` ".
		  	"ADD CONSTRAINT `state_id_fk_from` FOREIGN KEY (`state_id_FROM`) ".
		  	"REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION, ".
		  	"ADD CONSTRAINT `state_id_fk_to` FOREIGN KEY (`state_id_TO`) ".
		  	"REFERENCES `state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;";
		  $this->db->query($query);
      $this->log($query); 
		  // 'state'
		  $query = "ALTER TABLE `state` ADD INDEX `state_machine_id_fk` (`statemachine_id` ASC);";
		  $this->db->query($query);
      $this->log($query);

		  $query = "ALTER TABLE `state` ".
		  	"ADD CONSTRAINT `state_machine_id_fk` FOREIGN KEY (`statemachine_id`) ".
		  	"REFERENCES `state_machines` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;";
		  $this->db->query($query);
      $this->log($query); 
		  // TODO: Foreign Key for [state <-> state_machines]
    }



    private function createNewState($statename, $isEP) {
      $result = -1;
      $query = "INSERT INTO state (name, form_data, statemachine_id, entrypoint) VALUES (?,?,?,?)";
      $stmt = $this->db->prepare($query);
      $stmt->execute(array($statename, '', $this->ID, $isEP));  
      $result = $this->db->lastInsertId();
      $this->log($query);
      return $result;
    }
    private function createTransition($from, $to) {
      $result = -1;
      $query = "INSERT INTO state_rules (state_id_FROM, state_id_TO) VALUES (?,?)";
      $stmt = $this->db->prepare($query);
      $stmt->execute(array($from, $to));  
      $result = $this->db->lastInsertId();
      $this->log($query);
      return $result;
    }
    public function createBasicStateMachine($tablename) {
      // check if a statemachine already exists for this table
      $ID = $this->getSMIDByTablename($tablename);
      if ($ID > 0) return $ID; // SM already exists
      $this->log("-- [Start] Creating a Basic StateMachine for Table '$tablename'"); 

      // Insert new statemachine for a table
      $stmt = $this->db->prepare("INSERT INTO state_machines (tablename) VALUES (?)");
      $stmt->execute(array($tablename));
      $ID = $this->db->lastInsertId(); // returns the ID for the created SM
      $this->ID = $ID;
      $this->log($query);

      // Insert states (new, active, inactive)
      $ID_new = $this->createNewState('new ('.$tablename.')', 1);
      $ID_active = $this->createNewState('active', 0);
      $ID_update = $this->createNewState('update', 0);
      $ID_inactive = $this->createNewState('inactive', 0);

      // Insert rules (new -> active, active -> inactive)
      $this->createTransition($ID_new, $ID_new);
      $this->createTransition($ID_active, $ID_active);
      $this->createTransition($ID_update, $ID_update);
      $this->createTransition($ID_new, $ID_active);
      $this->createTransition($ID_active, $ID_update);
      $this->createTransition($ID_update, $ID_active);
      $this->createTransition($ID_active, $ID_inactive);

      $this->log("-- [END] Basic StateMachine created for Table '$tablename'"); 
      return $ID;
    }

    // [START]   FORM - Elements
    private function getFormElementStd($isVisible, $label, $content, $fk = '') {
      return "<div class=\"form-group row".($isVisible ? '' : ' collapse')."\">$fk\n\t".
        "<label class=\"col-sm-3 col-form-label\">".$label."</label>\n\t".
        "<div class=\"col-sm-9\">\n\t\t".$content."\n\t".
        "</div>\n</div>\n";
    }
    private function getFormElement($isVisible, $key, $alias, $default, $data_type, $FKTable, $substCol) {
      
      // Special case if $key == 'state_id'      
      if ($key == 'state_id') {
        return $this->getFormElementStd($isVisible, $alias, '<div class="input-group">
          <input type="hidden" name="'.$key.'" value="'.$default.'"/>
          <span class="input-group-text label-state"></span>
        </div>');
      }
      //----------------------------------
      else if ($FKTable != '') {
        // FOREIGN KEY
        return $this->getFormElementStd($isVisible, $alias,
'<div class="tbl_fk_'.$FKTable.'_'.$key.'"></div>
<input type="hidden" name="'.$key.'" value="'.$default.'" class="inputFK"/>
<script>
  // Wait for element to exist.
  function elLoaded(el, cb) {if ($(el).length) {cb($(el));} else {setTimeout(function(){ elLoaded(el, cb) }, 100);}};

  (function(){
    let x = new Table("'.$FKTable.'", ".tbl_fk_'.$FKTable.'_'.$key.'", 1, function(){
      x.loadRows(function(){
        const selRow = $("input[name='.$key.']").val();
        x.setSelectedRows([selRow]);
        x.renderHTML();
      });
    });
    x.SelectionHasChanged.on(function(){
      const selRow = x.getSelectedRows()[0];
      $("input[name='.$key.']").val(selRow);
    })
  })();
</script>');
      }
      else if (strtolower($data_type) == 'int') {
        // Number
        return $this->getFormElementStd($isVisible, $alias, '<input type="number" class="form-control" name="'.$key.'">');
      }
      else if (strtolower($data_type) == 'tinyint') {
        // Boolean
        return $this->getFormElementStd($isVisible, $alias, '<div class="custom-control custom-checkbox">'.
          '<input type="checkbox" class="custom-control-input" id="customCheck1" name="'.$key.'"><label class="custom-control-label" for="customCheck1">&nbsp;</label></div>');
      }
      else if (strtolower($data_type) == 'longtext') {
        // TextEditor (Code)
        return $this->getFormElementStd($isVisible, $alias, '<textarea rows="4" class="form-control editor" name="'.$key.'">'.$default.'</textarea>');
      }
      else if (strtolower($data_type) == 'time') {
        // TIME
        return $this->getFormElementStd($isVisible, $alias, '<input type="time" class="form-control" name="'.$key.'">');
      }
      else if (strtolower($data_type) == 'date') {
        // DATE
        return $this->getFormElementStd($isVisible, $alias, '<input type="date" class="form-control" name="'.$key.'">');
      }
      else if (strtolower($data_type) == 'datetime') {        
        // DATETIME
        return $this->getFormElementStd($isVisible, $alias, "<div class=\"row\">\n".
        "  <div class=\"col-7\"><input type=\"date\" class=\"form-control\" name=\"".$key."\"></div>\n". // DATE
        "  <div class=\"col-5\"><input type=\"time\" class=\"form-control\" name=\"".$key."\"></div>\n". // TIME
        "</div>");
      }
      else if (strtolower($data_type) == 'table') {
        // TABLE
        return $this->getFormElementStd($isVisible, $alias, '<div class="extern_table'.$key.'"></div>');
      }
      // Standard = TEXT
      return $this->getFormElementStd($isVisible, $alias, '<input type="text" class="form-control" name="'.$key.'" value="'.$default.'">');
    }
    public function getBasicFormDataByColumns($tablename, $config, $colData, $excludeKeys, $withoutReverseFKs = false) {
      $header = "<form>\n";
      $footer = "</form>";
      $content = '';
      $config = json_decode($config, true);
      
      if (!$withoutReverseFKs) {
        // check for N:M Tables
        // REVERSE FOREIGN KEYS
        $reverseFKs = array();
        foreach ($config as $tbl => $tbl_content) {
          if ($config[$tbl]['is_nm_table']) {
            // Save linked tables, except state_id
            $cols = $config[$tbl]['columns'];
            foreach ($cols as $colname => $col) {
              $src = $col['foreignKey']['table'];
              $dest = $tbl;
              $destCol = $colname;
              // Add to map
              if (strlen($src) > 0 && strlen($dest) > 0 && strlen($destCol) > 0) {
                //echo $src . ' -> ' . $dest.'<br>';
                // Check if already exists in array -> No Double Foreign Keys
                if (!in_array(array($src, $dest, $destCol), $reverseFKs))
                  $reverseFKs[] = array($src, $dest, $destCol);
              }
            }
          }
        }
      }

      // Loop every column
      foreach ($colData as $colname => $value) {
        $key = $colname;
        $visible = $value['is_in_menu'];
        $alias = $value['column_alias'];
        $data_type = $value['DATA_TYPE'];
        $FKTable = $value['foreignKey']['table'];
        $substCol = $value['foreignKey']['col_subst'];
        $default = '';
        // Check if exclude
        if (!in_array($key, $excludeKeys)) {
          // Check if not visible
          $content .= $this->getFormElement($visible, $key, $alias, $default, $data_type, $FKTable, $substCol);
        }
      }

      if (!$withoutReverseFKs) {
        foreach ($reverseFKs as $link) {
          $src = $link[0];
          $dest = $link[1];
          $destCol = $link[2];
          //echo $src . ' -> ' . $dest.'<br>';

          // Add extra column for reverse N:M
          if ($tablename == $src) {
            $nm_table = $dest;
            $nm_table_alias = $config[$nm_table]['table_alias'];

            // TODO: optimize, get primary column
            $array = $config[$tablename]['columns'];
            $this_primary = array_keys($array)[0];
            $foreignPrimaryColname = $destCol; // TODO: Change

            $content .= $this->getFormElement(true, $dest, $nm_table_alias, $nm_table, 'table', null, null);
            $content .= '
  <script>
    // Wait for element to exist.
    function elLoaded(el, cb) {if ($(el).length) {cb($(el));} else {setTimeout(function(){ elLoaded(el, cb) }, 100);}};

    (function(){
      elLoaded(\'input[name='.$this_primary.']\', function(el) {
        let PrimID = $(\'input[name='.$this_primary.']\').val();
        let x = new Table(\''.$nm_table.'\', \'.extern_table'.$dest.'\', 0, function(){
          x.Columns[\''.$foreignPrimaryColname.'\'].is_in_menu = false;
          x.loadRows(function(){
            x.renderHTML();
          })
        }, \'a.'.$foreignPrimaryColname.' = \'+PrimID, {'.$foreignPrimaryColname.': [PrimID, \'Already selected\']});
      });
    })();
  </script>';
          }
        }
      }

      // OUTPUT
      return $header.$content.$footer;
    }
    // [END]   FORM - Elements




    public function getFormDataByStateID($StateID) {
      if (!($this->ID > 0)) return "";
      $result = '';
      $sql = 'SELECT IF(form_data IS NULL OR form_data = \'\', (SELECT form_data_default FROM state_machines WHERE id = :SMID), form_data) AS fd'.
        ' FROM state WHERE statemachine_id = :SMID AND state_id = :SID';
      $stmt = $this->db->prepare($sql);
      $stmt->execute(array(':SMID' => $this->ID, ':SID' => $StateID));
      while($row = $stmt->fetch()) {
        $result = $row['fd'];
      }
      return $result;
    }
    public function getCreateFormByTablename() {
      if (!($this->ID > 0)) return "";
      $result = '';
      $sql = 'SELECT IF(form_data IS NULL OR form_data = \'\', form_data_default, form_data) AS fd FROM state_machines WHERE id = ?';
      $stmt = $this->db->prepare($sql);
      $stmt->execute(array($this->ID));
      while($row = $stmt->fetch()) {
        $result = $row['fd'];
      }
      return $result;
    }
    public function getID() {
    	return $this->ID;
    }
    public function getStates() {
      $result = array();
      //$stmt = $this->db->prepare("SELECT s.state_id AS 'id', s.name, s.name AS 'label', s.entrypoint, (SELECT COUNT(*) FROM ? AS x WHERE x.state_id = s.state_id) as 'NrOfTokens' FROM state AS s WHERE s.statemachine_id = ?");
      $stmt = $this->db->prepare("SELECT state_id AS 'id', name AS 'name', name AS 'label', entrypoint FROM state WHERE statemachine_id = ?");
      $stmt->execute(array($this->ID));
      while($row = $stmt->fetch()) {
        $result[] = $row;
      }
      return $result;
    }
    public function getLinks() {
      $result = array();
      $stmt = $this->db->prepare("SELECT state_id_FROM AS 'from', state_id_TO AS 'to' FROM state_rules ".
        "WHERE state_id_FROM AND state_id_TO IN (SELECT state_id FROM state WHERE statemachine_id = ?)");
      $stmt->execute(array($this->ID));
      while($row = $stmt->fetch()) {
        $result[] = $row;
      }
      return $result;
    }
    public function getEntryPoint() {
    	if (!($this->ID > 0)) return -1;
      $result = -1;
      $stmt = $this->db->prepare("SELECT state_id AS 'id' FROM state WHERE entrypoint = 1 AND statemachine_id = ?");
      $stmt->execute(array($this->ID));
      while($row = $stmt->fetch()) {
        $result = $row['id'];
      }
      return $result;
    }
    public function getNextStates($actStateID) {
      $result = array();
      $stmt = $this->db->prepare("SELECT a.state_id_TO AS 'id', b.name AS 'name' FROM state_rules AS a ".
        "JOIN state AS b ON a.state_id_TO = b.state_id WHERE state_id_FROM = ?");
      $stmt->execute(array($actStateID));
      while($row = $stmt->fetch()) {
        $result[] = $row;
      }
      return $result;
    }
    public function executeScript($script, &$param = null) {
      $standardResult = array("allow_transition" => true, "show_message" => false, "message" => "");
      // Check if script is not empty
      if (!empty($script)) {
        // Execute Script (WARNING -> eval = evil)
        eval($script);
        // This parameter comes from the script itself
        // check results, if no result => standard result
        if (empty($script_result))
          return $standardResult;
        else
          return $script_result;
      }
      return $standardResult;
    }
    public function checkTransition($fromID, $toID) {
      $stmt = $this->db->prepare("SELECT * FROM state_rules WHERE state_id_FROM = ? AND state_id_TO = ?");
      $stmt->execute(array($fromID, $toID));
      $cnt = $stmt->rowCount();
      return ($cnt > 0);
    }
    public function getTransitionScript($fromID, $toID) {
      $result = '';
      $stmt = $this->db->prepare("SELECT transition_script FROM state_rules WHERE state_id_FROM = ? AND state_id_TO = ?");
      $stmt->execute(array($fromID, $toID));
      while($row = $stmt->fetch()) {
        $result = $row['transition_script'];
      }
      return $result;
    }
    public function getTransitionScriptCreate() {
      if (!($this->ID > 0)) return ""; // check for valid state machine
      $result = '';
      $stmt = $this->db->prepare("SELECT transition_script FROM state_machines WHERE id = ?");
      $stmt->execute(array($this->ID));
      while($row = $stmt->fetch()) {
        $result = $row['transition_script'];
      }
      return $result;
    }
    public function getINScript($StateID) {
      if (!($this->ID > 0)) return ""; // check for valid state machine
      $stmt = $this->db->prepare("SELECT script_IN FROM state WHERE state_id = ?");
      $stmt->execute(array($StateID));
      while($row = $stmt->fetch()) {
        $result = $row['script_IN'];
      }
      return $result;
    }
    public function getOUTScript($StateID) {
      if (!($this->ID > 0)) return ""; // check for valid state machine
      $stmt = $this->db->prepare("SELECT script_OUT FROM state WHERE state_id = ?");
      $stmt->execute(array($StateID));
      while($row = $stmt->fetch()) {
        $result = $row['script_OUT'];
      }
      return $result;
    }

  }
?>