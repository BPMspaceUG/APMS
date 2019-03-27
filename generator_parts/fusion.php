<?php
  function getStateCSS($id, $bgcolor, $color = "white", $border = "none") {
    return ".state$id {background-color: $bgcolor; color: $color;}\n";
  }
  function loadFile($fname) {
    $fh = fopen($fname, "r");
    $content = stream_get_contents($fh);
    fclose($fh);
    return $content;
  }

	// Load data from Angular
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)) {
    $_REQUEST = json_decode(file_get_contents('php://input'), true);
  }  
  // Parameters
  $db_server = $_REQUEST['host']; //.':'.$_REQUEST['port'];
  $db_user = $_REQUEST['user'];
  $db_pass = $_REQUEST['pwd'];
  $db_name = $_REQUEST['db_name'];
  $data = $_REQUEST['data'];
  $createRoleManagement = $_REQUEST['create_RoleManagement'];
  $createHistoryTable = $_REQUEST['create_HistoryTable'];
  $redirectToLoginURL = $_REQUEST['redirectToLogin'];
  $loginURL = $_REQUEST['login_URL'];

  //--------------------------------------
  // Sort Data-Array by subkey values
  function cmp($a, $b) {
    return ((int)$a['order']) - ((int)$b['order']);
  }
  uasort($data, "cmp");
  //--------------------------------------

  // check if LIAM is present and create a Directory if not exists
  $content = "";
  $content = @file_get_contents("../../.git/config");
  echo "Looking for LIAM...\n";
  if (!empty($content) && strpos($content,"https://github.com/BPMspaceUG/LIAM.git")) {
    echo "LIAM found. Looking for APMS_test Directory...\n";
    if (!is_dir('../../APMS_test')) {
      mkdir('../../APMS_test', 0750, true);
      echo "APMS_test Directory created!\n";
    } else {
      echo "APMS_test Directory found.\n";
    }
  }
  echo "\n";

  // Open a new DB-Connection
  define('DB_HOST', $db_server);
  define('DB_NAME', $db_name);
  define('DB_USER', $db_user);
  define('DB_PASS', $db_pass);
  require_once("output_DatabaseHandler.php");

  /* ------------------------------------- Statemachine ------------------------------------- */

  require_once("output_StateEngine.php");
  require_once("output_RequestHandler.php");
  require_once("output_AuthHandler.php");
  // Loop each Table with StateMachine checked create a StateMachine Column

  // -------------------- FormData --------------------

  $tabCount = 0;
  $content_tabs = '';
  $content_tabpanels = '';  
  $content_jsObjects = '';
  $content_css_statecolors = '';
  
  // Add Pseudo Element for Dashboard
  $content_tabs .= "            ".
  "<li class=\"nav-item\">
    <a class=\"nav-link\" href=\"#dashboard\" data-toggle=\"tab\">
      <i class=\"fa fa-dashboard\"></i>&nbsp;
      <span class=\"table_alias\">Dashboard</span>
    </a>
  </li>\n";
  // Add Pseudo Element for Dashboard
  $content_tabpanels .= "            ".
    "<div role=\"tabpanel\" class=\"tab-pane\" id=\"dashboard\">".
    "  <div id=\"dashboardcontent\"></div>".
    "</div>\n";

  $con = DB::getInstance()->getConnection();
  //$tablePrefix = $db_name;  
  //--------------------------------- create RoleManagement
  if ($createRoleManagement) {
    echo "\nCreating Role Management Tables...\n";
    // Table: Role
    $con->exec('CREATE TABLE IF NOT EXISTS `Role` (
      `Role_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `Role_name` varchar(45) DEFAULT NULL,
      PRIMARY KEY (`Role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
    // Table: Role_LIAMUSER
    $con->exec('CREATE TABLE IF NOT EXISTS `Role_LIAMUSER` (
      `Role_User_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `Role_id` bigint(20) NOT NULL,
      `User_id` bigint(20) NOT NULL,
      PRIMARY KEY (`Role_User_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
    // ForeignKeys
    $con->exec('ALTER TABLE `Role_LIAMUSER` ADD INDEX `Role_id_fk` (`Role_id`)');
    $con->exec('ALTER TABLE `Role_LIAMUSER` ADD CONSTRAINT `Role_id_fk` FOREIGN KEY (`Role_id`) REFERENCES `Role` (`Role_id`) ON DELETE NO ACTION ON UPDATE NO ACTION');

  } 
  //--------------------------------- create HistoryTable
  if ($createHistoryTable) {
    echo "\nCreating History Table...\n";
    // Table: History
    $con->exec('CREATE TABLE IF NOT EXISTS `History` (
      `History_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `User_id` bigint(20) NOT NULL,
      `History_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `History_table` varchar(128) NOT NULL,
      `History_valueold` LONGTEXT NOT NULL,
      `History_valuenew` LONGTEXT NOT NULL,
      PRIMARY KEY (`History_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
  }

  foreach ($data as $table) {
    // Get Data
    $tablename = $table["table_name"];
    $se_active = (bool)$table["se_active"];
    $table_type = $table["table_type"];    

    //--- Create HTML Content
    if ($table["is_in_menu"]) {
      // Tabs
      $content_tabs .= "            ".
            "<li class=\"nav-item\">
              <a class=\"nav-link".( $tabCount == 0 ? ' active' : '')."\" href=\"#$tablename\" data-toggle=\"tab\">
                <i class=\"".$table["table_icon"]."\"></i>&nbsp;
                <span class=\"table_alias\">".$table["table_alias"]."</span>
              </a>
            </li>\n";

      // TabPanes
      $content_tabpanels .= "            ".
        "<div role=\"tabpanel\" class=\"tab-pane".( $tabCount == 0 ? ' show active' : '')."\" id=\"$tablename\">".
        "<div class=\"table_$tablename\"></div></div>\n";

      // Init a JS-Object
      $tableVarName = "tbl_$tablename";
      $content_jsObjects .= "      let $tableVarName = new Table('$tablename', 0, function(){
        $tableVarName.GUIOptions.showWorkflowButton = true;
        $tableVarName.loadRows(function(){ $tableVarName.renderHTML('.table_$tablename'); });
      });\n";
    }
    $tabCount += 1;
    //---/Create HTML Content

    // TODO: Check if the "table" is no view

    //--- Create a stored procedure for each Table
    $sp_name = 'sp_'.$tablename;
    $con->exec('DROP PROCEDURE IF EXISTS `'.$sp_name.'`'); // TODO: Ask user if it should be overwritten

    //-- All standard columns
    $colnames = array_keys($table["columns"]);    

    // Generate joins
    unset($joincolsubst); // clear due to loop
    unset($jointexts);
    unset($virtualcols);
    unset($stdcols);
    unset($allcolnames);

    $joincolsubst = [];
    $jointexts = [];
    $virtualcols = [];
    $stdcols = [];
    $allcolnames = [];
    $seperator = '_____';

    foreach ($colnames as $colname) {
      $has_FK = ($table["columns"][$colname]["field_type"] == 'foreignkey');      
      // -- Foreign Key
      if ($has_FK) {
        $fk = $table["columns"][$colname]["foreignKey"];
        $ft = $fk["table"];
        $fkey = $fk["col_id"];
        $fsub = $fk["col_subst"];
        // Template: ' LEFT JOIN [fktable] AS a___[0] ON a.[stdkey] = a____[0].[fkey] '
        $jointexts[] = ' LEFT JOIN '.$ft.' AS a'.$seperator.$colname.' ON a.'.$colname.' = a'.$seperator.$colname.'.'.$fkey;
        $joincolsubst[] = 'a'.$seperator.$colname.'.'.$fkey;
        $allcolnames[] = 'a'.$seperator.$colname.'.'.$fkey;

        // Check if contains more than one
        if (strpos($fsub, "{") !== FALSE) {
          $multifkcols = json_decode($fsub, true);

          foreach ($multifkcols as $c => $val) {
            // Nested FKs         FK(FK)
            if (is_array($val)) {
              $layer1 = 'a'.$seperator.$colname;
              $joincolsubst[] = $layer1.'.'.$c; // The nested FK
              
              // Then normally recursion
              $_table = $multifkcols[$c]["table"];
              $_fkey = $multifkcols[$c]["col_id"];
              $_fsub = $multifkcols[$c]["col_subst"];

              $jointexts[] = ' LEFT JOIN '.$_table.' AS '.$layer1.$seperator.$c.' ON '.$layer1.'.'.$c.' = '.$layer1.$seperator.$c.'.'.$_fkey;
              $joincolsubst[] = 'a'.$seperator.$colname.$seperator.$c.'.'.$_fkey;
              $joincolsubst[] = 'a'.$seperator.$colname.$seperator.$c.'.'.$_fsub;
              
              $allcolnames[] = 'a'.$seperator.$colname.$seperator.$c.'.'.$_fsub;

            }
            else {
              // Normal FK
              $joincolsubst[] = 'a'.$seperator.$colname.'.'.$c;
              $allcolnames[] = 'a'.$seperator.$colname.'.'.$c;
            }
          }
          $allcolnames[] = 'a'.$seperator.$colname.'.'.$c;
        }
        else {
          $joincolsubst[] = 'a'.$seperator.$colname.'.'.$fsub;
          $allcolnames[] = 'a'.$seperator.$colname.'.'.$fsub;
        }
      }


      // -- Virtual Column
      $isVc = $table["columns"][$colname]["is_virtual"];
      if ($isVc) {
        $virtSelect = $table["columns"][$colname]["virtual_select"];
        if (strlen($virtSelect) > 0) {
          $virtualcols[] = addslashes($virtSelect).' AS '.$colname;
          $allcolnames[] = addslashes($virtSelect);
        }
      }
      elseif (!$isVc) {
        $stdcols[] = "a.".$colname;
        $allcolnames[] = "a.".$colname;
      }
    }

    // Prepare for SP -> add prefixes/aliases for each column
    $stdColText = implode(", ", $stdcols);    
    $joinTables = implode("", $jointexts);

    $select = $stdColText;
    if (count($virtualcols) > 0) {
      $select .= ', '.implode(", ", $virtualcols);
    }
    if (count($joincolsubst) > 0) {
      $select .= ', '.implode(", ", $joincolsubst);
    }

    // Filter
    //echo "---> Filter:\n";
    //var_dump($allcolnames);
    // Template: ' WHERE ([col1] LIKE '%[searchtext]%' OR [col2] LIKE '%[searchtext]%')
    $filtertext = "(" . implode(" LIKE \'%', filter ,'%\' OR ", $allcolnames) . " LIKE \'%', filter ,'%\')";

    //===========================================================
    // STORED PROECEDURE START

    $sp = "CREATE PROCEDURE $sp_name(IN token_uid INT, IN filter VARCHAR(256), IN whereParam VARCHAR(256), IN orderCol VARCHAR(100), IN ascDesc VARCHAR(4), IN LimitStart INT, IN LimitSize INT)
BEGIN
  SET @select = '$select';
  SET @joins =  '$joinTables';
  SET @where = CONCAT(' WHERE $filtertext ', COALESCE(CONCAT('AND ', NULLIF(whereParam, '')), ''));
  SET @order = IFNULL(CONCAT(' ORDER BY ', orderCol, ' ', ascDesc), '');
  SET @limit = IFNULL(CONCAT(' LIMIT ', LimitStart, CONCAT(', ', LimitSize)), '');

  SET @s = CONCAT('SELECT ', @select, ' FROM $tablename AS a', @joins, @where, @order, @limit);
  PREPARE stmt FROM @s;
  EXECUTE stmt;
  DEALLOCATE PREPARE stmt;
END";

    echo "----------------------------- Create Stored Procedure for Reading\n";
    echo $sp."\n";
    $res = $con->exec($sp);
    echo "-----------------------------";
    echo ($res == 0 ? 'OK' : 'Fail');
    echo "\n\n";

    // STORED PROECEDURE END
    //===========================================================

    //--- Create StateMachine
    if ($se_active) {

      // ------- StateMachine Creation
      $SM = new StateMachine($con);
      $SM->createDatabaseStructure();
      $SM_ID = $SM->createBasicStateMachine($tablename, $table_type);
      $cols = $table["columns"];

      if ($table_type != 'obj') {
        //----------- RELATION Table
        echo "Create Relation Scripts ($table_type)\n";
        // Load Template
        $templateScript = loadFile("./../template_scripts/".$table_type.".php");
        $templateScript = str_replace("<?php", '', $templateScript); // Remove first chars ('<?php')
        $templateScript = substr($templateScript, 2); // Remove newline char
        $res = $SM->createRelationScripts($templateScript);
        echo "-----------------------------";
        echo ($res == 0 ? 'OK' : 'Fail');
        echo "\n\n";
      } else {
        //----------- OBJECT Table
        // TODO: Create Basic form and set RO, RW
        $rights_ro = [];
        // for all columns and virtual-columns
        foreach ($cols as $colname => $col) {
          if (!($col['is_primary'] || $colname == 'state_id')) {
            // Set the form data
            $rights_ro[$colname] = ["mode_form" => "ro"];
          }
        }
        // Update the inactive state with readonly
        // TODO: get all states
        $formDataRO = json_encode($rights_ro);
        //var_dump($formDataRO);
        //echo "-----------------------------";
        $allstates = $SM->getStates();
        //var_dump($allstates);
        // TODO: loop states and check if they are empty
          //-> if empty, create basic-form if name is (active => [ro] or inactive => [ro])
        foreach ($allstates as $state) {
          $formData = $SM->getFormDataByStateID($state["id"]);
          if (strlen($formData) == 0) {
            // check if statename contains the phrase "active"
            if (strpos($state["name"], "active") !== FALSE) {
              $SM->setFormDataByStateID($state["id"], $formDataRO);
            }
          }
        }
      }


      // Exclude the following Columns:
      $excludeKeys = Config::getPrimaryColsByTablename($tablename, $data);
      $excludeKeys[] = 'state_id'; // Also exclude StateMachine in the FormData
      $vcols = Config::getVirtualColnames($tablename, $data);
      foreach ($vcols as $vc) {
        $excludeKeys[] = $vc;
      }
      
      $queries1 = '';
      $queries1 = $SM->getQueryLog();
      // Clean up
      unset($SM);

      // ------------ Connection to existing structure !

      // Set the default Entrypoint for the Table (when creating an entry the Process starts here)
      $SM = new StateMachine($con, $tablename); // Load correct Machine
      $EP_ID = $SM->getEntryPoint();
      $q_se = "ALTER TABLE `".$db_name."`.`".$tablename."` ADD COLUMN `state_id` BIGINT(20) DEFAULT $EP_ID;";
      $con->query($q_se);

      // Generate CSS-Colors for states
      $allstates = $SM->getStates();
      $initColorHue = rand(0, 360); // le color
      $v = 8;
      foreach ($allstates as $state) {
        $v += 12;
        $tmpStateID = $state['id'];
        if ($table_type == 'obj') {
          // Generate color
          $state_css = getStateCSS($tmpStateID, "hsl($initColorHue, 50%, $v%)");
        } else {
          // NM Table
          if ($v == 20) //$state_css = ".state$tmpStateID {background-color: #328332;}\n"; // Selected
            $state_css = getStateCSS($tmpStateID, "#328332");
          else
            $state_css = getStateCSS($tmpStateID, "#8b0000"); //".state$tmpStateID {background-color: #8b0000;}\n";
        }
        $content_css_statecolors .= $state_css;
      }

      // Add UNIQUE named foreign Key
      $uid = substr(md5($tablename), 0, 8);
      $q_se = "ALTER TABLE `".$db_name."`.`".$tablename."` ADD CONSTRAINT `state_id_".$uid."` FOREIGN KEY (`state_id`) ".
        "REFERENCES `".$db_name."`.`state` (`state_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;";
      $con->query($q_se);
    }
  }

  // ------------------- Load complete Project
  $class_StateEngine = loadFile("./output_StateEngine.php");
  $output_RequestHandler = loadFile("./output_RequestHandler.php");  
  $output_DBHandler = loadFile("./output_DatabaseHandler.php");
  $output_AuthHandler = loadFile("./output_AuthHandler.php");
  $output_API = loadFile("./output_API.php");
  $output_css = loadFile("./muster.css");
  $output_JS = loadFile("./muster.js");
  $output_header = loadFile("./output_header.html");
  $output_content = loadFile("./output_content.html");
  $output_footer = loadFile("./output_footer.html");

  // Replace Names
  $output_DBHandler = str_replace('replaceDBName', $db_name, $output_DBHandler); // For Config-Include
  $output_header = str_replace('replaceDBName', $db_name, $output_header); // For Title
  $output_footer = str_replace('replaceDBName', $db_name, $output_footer); // For Footer
  
  echo "Generated CSS State-Colors\n";
  echo "----------------------------------\n";
  echo $content_css_statecolors;
  echo "----------------------------------\n\n";

  // --- Content
  // Modify HTML for later adaptions
  // Insert Tabs in HTML (Remove last \n)
  $content_tabs = substr($content_tabs, 0, -1);
  $content_tabpanels = substr($content_tabpanels, 0, -1);
  $output_content = str_replace('<!--###TABS###-->', $content_tabs, $output_content);
  $output_content = str_replace('<!--###TAB_PANELS###-->', $content_tabpanels, $output_content);
  $output_content = str_replace('replaceDBName', $db_name, $output_content);
  // Write the init functions for the JS-Table Objects
  $output_footer = str_replace('/*###JS_TABLE_OBJECTS###*/', $content_jsObjects, $output_footer);
  // CSS
  $output_css = str_replace('/*###CSS_STATES###*/', $content_css_statecolors, $output_css);

  // ------------------------------------ Generate Core File
  $output_all = $output_header.$output_content.$output_footer;
  // Output information
  echo "Generating-Time: ".date("Y-m-d H:i:s")."\n\n";
  echo $queries1;
  echo $output_all;

  // ------------------------------------ Generate Config File

  // Generate Secret Key
  $secretKey = 'secretkey_'.sha1('test' . date("Y-m-d")); // Changes every day only

  // Generate a machine token
  $token_data = array();
  $token_data['uid'] = 1337;
  $token_data['firstname'] = 'Machine';
  $token_data['lastname'] = 'Machine';
  $token = JWT::encode($token_data, $secretKey);
  $machine_token = $token;

  $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
  $url_host = explode('APMS', $actual_link)[0];
  $url_apiscript = '/APMS_test/'.$db_name.'/api.php';
  $API_url = $url_host.$url_apiscript;
  $LOGIN_url = $loginURL == '' ? 'http://localhost/Authenticate/' : $loginURL; // default value


  // ---> ENCODE Data as JSON
  $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
  // ----------------------- Config File generator
  $output_config = '<?php
  /*
    APMS Generator
    ==================================================
    Generated: '.date("Y-m-d H:i:s").'
  */

  // Database Login
  define("DB_USER", "'.$db_user.'");
  define("DB_PASS", "'.$db_pass.'");
  define("DB_HOST", "'.$db_server.'");
  define("DB_NAME", "'.$db_name.'");

  // API-URL
  define("API_URL", "'.$API_url.'");
  define("API_URL_LIAM", "'.$LOGIN_url.'"); // for Login
  // AuthKey
  define("AUTH_KEY", "'.$secretKey.'");
  // Machine-Token for internal API Calls
  define("MACHINE_TOKEN", "'.$machine_token.'");

  // WhiteLists for getFile
  @define("WHITELIST_PATHS", array("ordner/test/", "ordner/"));
  @define("WHITELIST_TYPES", array("pdf", "doc", "txt"));

  // Structure Configuration Data
  $config_tables_json = \''.$json.'\';
?>';

  function createSubDirIfNotExists($dirname) {
    if (!is_dir($dirname))
      mkdir($dirname, 0750, true);
  }
  
  function createFile($filename, $content) {
    file_put_contents($filename, $content);
    chmod($filename, 0660);
  }

  //----------------------------------------------
  // ===> Write Project to FileSystem
  //----------------------------------------------
  $Path_APMS_test = __DIR__ . "/../../APMS_test";
	// check if APMS test exists
  if (is_dir($Path_APMS_test)) {
  	// Path for Project
    $project_dir = $Path_APMS_test.'/'.$db_name;

    // Create Project directory
    createSubDirIfNotExists($project_dir);
    createSubDirIfNotExists($project_dir."/css");
    createSubDirIfNotExists($project_dir."/js");
    createSubDirIfNotExists($project_dir."/src");

    //---- Put Files
    // JavaScript
    createFile($project_dir."/js/main.js", $output_JS);
    if (!file_exists($project_dir."/js/custom.js"))
      createFile(
        $project_dir."/js/custom.js",
        "// Custom JS\ndocument.getElementById('dashboardcontent').innerHTML = '<h1>Dashboard</h1>';"
      );
    // Styles
    createFile($project_dir."/css/main.css", $output_css);
    if (!file_exists($project_dir."/css/custom.css"))
      createFile($project_dir."/css/custom.css", "/* Custom Styles */\n");
    // Serverside-Scripts
    createFile($project_dir."/src/RequestHandler.inc.php", $output_RequestHandler);
    createFile($project_dir."/src/StateMachine.inc.php", $class_StateEngine);
    createFile($project_dir."/src/DatabaseHandler.inc.php", $output_DBHandler);
    createFile($project_dir."/src/AuthHandler.inc.php", $output_AuthHandler);
    // Main Directory
    createFile($project_dir."/api.php", $output_API);
    createFile($project_dir."/".$db_name.".html", $output_all);
    createFile($project_dir."/".$db_name."-config.inc.php", $output_config);

    // Create Entrypoint (index)
    if ($redirectToLoginURL) {
      // Redirect to LIAM
      $output_index = '<?php
        // Includes
        require_once(__DIR__."/src/AuthHandler.inc.php");
        include_once(__DIR__."/src/RequestHandler.inc.php");

        function gotoLogin($error = "") {
          $actual_link = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
          if ($error == "") {
            header("Location: ".Config::getLoginSystemURL()."?origin=".$actual_link);
            exit();
          } else {
            echo $error;
            echo "<br><a href=\"".Config::getLoginSystemURL()."?origin=$actual_link\">Goto Login</a>";
            exit();
          }
        }

        $rawtoken = JWT::getBearerToken(); // Check Cookies
        // Check GET Parameter (if has token -> then if valid save as cookie)
        if (is_null($rawtoken) && isset($_GET["token"])) {
          $rawtoken = $_GET["token"];
        }
        //========================================= Authentification
        // No token is set
        if ($rawtoken == "") gotoLogin();
        // Check if authenticated via Token
        try {
          $token = JWT::decode($rawtoken, AUTH_KEY);
        }
        catch (Exception $e) {
          // Invalid Token!
          gotoLogin("This Token is invalid!");
        }
        // Token is valid but expired?
        if (property_exists($token, "exp")) {
          if (($token->exp - time()) <= 0) {
            gotoLogin("This Token is expired!");
          }
        }
        // If Token is not in Cookie -> save Token in a Cookie
        if (is_null(JWT::getBearerToken())) {
          // Save Cookie for 30 days
          setcookie("token", $rawtoken, time()+(3600 * 24 * 30), "", "", false, true);
          header("Location: ".dirname($_SERVER["PHP_SELF"])); // Redirect to remove ugly URL
          exit();
        }
        // Success
        require_once("'.$db_name.'.html");
      ?>';
    } else
      $output_index = "<?php\n\trequire_once(\"".$db_name.".html\");\n?>";
    
    createFile($project_dir."/index.php", $output_index);
  }
?>