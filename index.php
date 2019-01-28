
<?php  include_once '_header.inc.php'; ?>
<!-- Content -->
<div ng-app="APMS">
  <div ng-controller="APMScontrol">

    <!-- CONNECT Part -->
		<div class="container" style="padding-top: 30px">
			<form class="bpm-server-connect" action="modules/ConnectDB.php">
		  <h3 class="title">connect</h3>
		  <div class="form-group row">
		    <label for="sqlServer" class="col-sm-2 form-control-label">Hostname</label>
		    <div class="col-sm-2">
		      <input type="text" class="form-control" autocomplete="off" name="host" id="sqlServer" ng-model="sqlServer" value='{{sqlServer}}'>
		    </div>
		    <label for="sqlPort" class="col-sm-2 form-control-label">Port</label>
		    <div class="col-sm-2">
		      <input type="number" autocomplete="off" class="form-control" name="port" id="sqlPort" ng-model="sqlPort" value={{sqlPort}}>
		    </div>
		    <div class="col-sm-1">
		      <button id="new" type="reset" class="btn btn-success" name="new">New</button>
		    </div>
		    <div class="col-sm-1">
		      <button id="" type="button" class="btn btn-info" name="_connect" value="true" ng-click="connectToDB()">Connect</button>
		    </div>
		  </div>
		  <div class="form-group row">
		    <label for="username" class="col-sm-2 form-control-label">Username</label>
		    <div class="col-sm-2">
		        <input type="text" autocomplete="off"  class="form-control" id="username" name="user" ng-model="username" value='{{username}}'>
		    </div>
		    <label for="password" class="col-sm-2 form-control-label">Password</label>
		    <div class="col-sm-2">
		      <input type="password"  autocomplete="off" class="form-control" id="sqlPass" name="pwd" ng-model="pw" value='{{pw}}'>
		    </div>
		    <!-- Button: Load -->
		    <div class="col-sm-1">
		      <a href="#loadDb" name="load" data-toggle="modal" id="loadF" class="btn btn-secondary "
		         name="load">Load</a>
		    </div>
		    <!-- Button: Save -->
		    <div class="col-sm-1">
		      <button type="button" name="save" id="save" class="btn btn-primary" name="save">Save</button>
		    </div>
		    <!-- Hidden area -->
		    <div class="col-sm-2">
		      <i class="fa fa-check" aria-hidden="true" style="display: none"></i>
		      <i class="fa fa-minus-circle" aria-hidden="true" style="display: none"></i>
		    </div>
		  </div>
			</form>
		</div>

    <!-- CONTENT -->
    <div class="container">

      <!-- Loading -->
      <div class="alert alert-info" ng-show="isLoading">
        <p class="m-0"><i class="fa fa-cog fa-spin"></i> Loading...</p>
      </div>
      <!-- Error Message -->
      <div class="alert alert-danger" ng-show="isError">
        <p class="m-0"><i class="fa fa-exclamation"></i> <strong>Error:</strong> Login data is not correct.</p>
      </div>

      <!-- DB Configuration -->
		  <div ng-if="dbNames">

        <!-- 1. Select Database -->
        <div class="card mb-3">
          <div class="card-header">
            <span class="badge badge-success mr-2">1</span> Select a Database
          </div>
          <div class="card-body">
            <div class="input-group">
              <select class="custom-select" id="repeatSelect" name="repeatSelect" ng-model="dbNames.model" ng-change="changeSelection()">
                <option ng-repeat="name in dbNames.names" value="{{name}}">{{name}}</option>
              </select>
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" ng-click="changeSelection()" type="button"><i class="fa fa-refresh"></i> Refresh</button>
              </div>
            </div>
          </div>
        </div>

		    <!-- Load Config -->
		    <div class="card mb-3">
          <div class="card-header">
            <span class="badge badge-warning text-white mr-2">Optional</span> Load Configuration
          </div>
          <div class="card-body">
            <div class="row mb-0">
              <!-- Automatically load config -->
              <div class="col-6">
                <p><strong>[1] Automatically load config</strong></p>
                <button class="btn btn-outline-secondary" ng-click="loadConfigByName()"><i class="fa fa-search"></i> Look for last config</button>
              </div>
              <!-- Manually load config -->
              <div class="col-6">
                <p><strong>[2] Manually load config</strong></p>
                <textarea class="form-control configtxt" ng-model="configtext" placeholder="Paste Content of the Config-File here"></textarea>
                <button class="btn btn-outline-secondary mt-1" ng-click="loadconfig(configtext)">
                  <i class="fa fa-arrow-right"></i> Parse and Load configuration file
                </button>
              </div>
            </div>
		      </div>
          <div class="card-footer text-center font-weight-bold">
            <p class="m-0 p-0">
              &nbsp;
              <span class="text-danger" ng-if="configFileWasNotFound"><i class="fa fa-times"></i> Error when loading Configuration</span>
              <span class="text-success" ng-if="configFileWasFound"><i class="fa fa-check"></i> Configuration loaded successfully</span>
            </p>
          </div>
		    </div>

        <!-- Content of Databases -->        
        <div class="card mb-3">
          <div class="card-header">
            <span class="badge badge-success mr-2">2</span>Tables & Configuration
          </div>
          <div class="card-body">

            <h5 class="mb-3">
              <span class="text-primary mr-3">{{ dbNames.model }}</span>
              <span class="text-muted">{{cntTables() + ' Table' + (cntTables() != 1 ? 's' : '')}}</span>
            </h5>

            <div class="font-weight-bold my-3">
              <label class="m-0 mr-3"><input type="checkbox" ng-model="meta.createRoles" class="mr-2">Create Role-Management</label>
              <label class="m-0"><input type="checkbox" ng-model="meta.createHistory" class="mr-2">Create History</label>
            </div>

            <!-- Tables -->
            <div class="row">
              <table class="table table-sm table-striped" id="loadedtables" ng-model="tbl" id="row{{$index}}">
                <thead>
                  <tr>
                    <th width="200px"><span class="text-muted">Order</span></th>
                    <th width="250px">Options</th>
                    <th width="20%">TABLENAME</th>
                    <th width="25%">ALIAS</th>
                    <th width="5%"><a href="" ng-click="tbl_toggle_sel_all()">IN MENU</a></th>
                    <th width="5%">STATE-ENGINE</th>
                    <th width="5%">RO (View)</th>
                    <th width="30%">ICON</th>
                  </tr>
                </thead>

                <tbody ng-repeat="(name, tbl) in tables">

                  <!-- ===================== Table ======================== -->

                  <tr ng-class="{'table-primary' : tbl.table_type == 'obj', 'table-info' : tbl.table_type != 'obj'}">
                    <!-- Order Tabs -->
                    <td>
                      <div style="white-space:nowrap;overflow:hidden;">
                        <input type="text" style="width: 40px" ng-model="col.col_order">
                        <a ng-click="changeSortOrder(col, 1)"><i class="fa fa-angle-down p-1 pl-2"></i></a>
                        <a ng-click="changeSortOrder(col, -1)"><i class="fa fa-angle-up p-1"></i></a>
                      </div>
                    </td>
                    <!-- Expand / Collapse -->
                    <td>
                      <div style="white-space:nowrap; overflow: hidden;">
                        <a class="btn" ng-click="toggle_kids(tbl)" title="Show column settings">
                          <i class="fa fa-plus-square" ng-if="!tbl.showKids"></i>
                          <i class="fa fa-minus-square" ng-if="tbl.showKids"></i>
                        </a>
                        <button class="btn btn-sm btn-success" ng-click="add_virtCol(tbl)">+VCol</button>
                        <select class="custom-select" ng-model="tbl.table_type" style="width: 80px;">
                          <option value="obj">Obj</option>
                          <option value="1_1">1:1</option>
                          <option value="1_n">1:N</option>
                          <option value="n_1">N:1</option>
                          <option value="n_m">N:M</option>
                        </select>
                      </div>
                    </td>
                    <!-- Tablename -->
                    <td class="align-middle">
                      <b>{{name}}</b>
                    </td>
                    <!--Table-Alias -->
                    <td>
                      <input type="text" class="form-control" ng-model="tbl.table_alias"/>
                    </td>
                    <!-- Visible -->
                    <td>
                      <input type="checkbox" class="form-control" ng-model="tbl.is_in_menu">
                    </td>
                    <!-- Has Statemachine? -->
                    <td>
                      <input type="checkbox" class="form-control" ng-model="tbl.se_active"
                        ng-disabled="tbl.table_name == 'state' || tbl.table_name == 'state_rules'">
                    </td>
                    <!-- ReadOnly? -->
                    <td>
                      <input type="checkbox" class="form-control" ng-model="tbl.is_read_only">
                    </td>                    
                    <!-- Table-Icon -->
                    <td class="align-middle">
                      <div class="row">
                        <div class="col-3"><i class="{{tbl.table_icon}}"></i></div>
                        <div class="col-9"><input type="text" class="form-control" ng-model="tbl.table_icon"/></div>
                      </div>
                    </td>
                  </tr>

                  <!-- C O L U M N S -->
                  <!-- Columns START -->

                  <tr ng-repeat="col in convertObjToArr(tbl.columns) | orderBy: 'col_order'" ng-show="tbl.showKids" ng-class="{'bg-warning' : col.is_virtual}" style="font-size: .8em;">
                    <!-- Column Order -->
                    <td>
                      <div style="white-space:nowrap;overflow:hidden;">
                        <input type="text" style="width: 40px" ng-model="col.col_order">
                        <a ng-click="changeSortOrder(col, 1)"><i class="fa fa-angle-down p-1 pl-2"></i></a>
                        <a ng-click="changeSortOrder(col, -1)"><i class="fa fa-angle-up p-1"></i></a>
                      </div>
                    </td>
                    <!-- Column Name and Type -->
                    <td class="align-middle">
                      <div>
                          <b>{{col.COLUMN_NAME}}</b><br>
                          <small class="float-left">{{col.COLUMN_TYPE}} {{col.EXTRA}}</small>
                      </div>
                    </td>

                    <td>
                      <select class="custom-select custom-select-sm" ng-if="(col.EXTRA != 'auto_increment' && col.COLUMN_NAME != 'state_id')">
                        <option value="1">Input</option>
                        <option value="2">Textarea</option>
                        <option value="3">Number</option>
                        <option value="4">Date</option>
                        <option value="5">Time</option>
                        <option value="6">DateTime</option>
                        <option value="7">Passwordfield</option>
                        <option value="8">HTML-Editor</option>
                      </select>
                    </td>

                    <!-- Alias(es) -->
                    <td>
                      <input type="text" class="form-control form-control-sm" ng-model="col.column_alias">
                      <input type="text" class="form-control form-control-sm" ng-if="(tbl.table_type != 'obj' && col.foreignKey.table != '')" ng-model="col.rel_caption">    
                    </td>

                    <!-- Visibility -->
                    <td ng-if="!col.is_virtual">
                      <label class="m-0"><input type="checkbox" class="mr-2" ng-model="col.is_in_menu">G+F</label>
                      <!--<br>
                      <label class="m-0"><input type="checkbox" class="mr-2" ng-model="col.is_in_menu">F</label>-->
                    </td>

                    <!-- Show FK Menu if it is no Primary column -->
                    <td colspan="3" ng-if="(col.EXTRA != 'auto_increment')">
                      <b>ForeignKey:</b>
                      <select class="custom-select custom-select-sm" style="width: 120px; display: inline !important;" ng-model="col.foreignKey.table">
                        <option value="" selected></option>
                        <option ng-repeat="tbl in tables" value="{{tbl.table_name}}">{{tbl.table_name}}</option>
                      </select>
                      <input ng-if="(col.foreignKey.table != '')" type="text" class="form-control form-control-sm" style="width: 100px; display: inline !important;" ng-model="col.foreignKey.col_id" placeholder="JoinID">
                      <span>
                        <input ng-if="(col.foreignKey.table != '')" type="text" class="form-control form-control-sm w-100" ng-model="col.foreignKey.col_subst" placeholder="ReplacedCloumn">
                      </span>
                    </td>
                    
                    <td colspan="4" ng-if="col.is_virtual">
                      <span>SELECT ( i.e. CONCAT(a, b) ): </span>
                      <input type="text" ng-model="col.virtual_select" style="width: 300px" placeholder="CONCAT(id, col1, col2)">
                      <button class="btn btn-sm btn-danger" ng-click="del_virtCol(tbl, col)">delete</button>
                    </td>

                  </tr>
                  <!-- Columns END -->
                </tbody>
              </table>
            </div>
          </div>
        </div>


        <!-- Create Button -->
        <div class="card">
          <div class="card-header">
            <span class="badge badge-success mr-2">3</span>Generate
          </div>
          <div class="card-body">
            <!-- Create Button -->
            <button name="createScript" ng-disabled="GUI_generating" class="btn btn-danger" id="createScript" ng-click="create_fkt()">
              <i class="fa fa-rocket"></i> Generate!
            </button>
            <!-- Open Project -->
            <button class="btn btn-secondary" href="#" ng-click="openProject(e)" target="_blank"><i class="fa fa-folder-open"></i> Open Project</button>
            <!-- Generating -->
            <div class="d-inline h4 ml-2 text-center mt-5 text-muted" ng-if="GUI_generating">
              <i class="fa fa-cog fa-spin fa-fw"></i> Generating Project...
            </div>
          </div>
        </div>

        <!-- File String -->
        <div class="row">
          <div class="col-md-12" id="code">
              <div readonly style="width: 100%; min-height: 100px; max-height: 300px; resize: none; padding:50px 0 0; margin:0 0 50px; overflow:auto;" class="bpm-textarea" id="bpm-code">
                Currently Empty
              </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<!-- Footer -->
<?php  include_once "_footer.inc.php" ?>