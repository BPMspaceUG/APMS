
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
		      <a href="#loadDb" name="load" data-toggle="modal" id="loadF" class="btn btn-default "
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
        <p><i class="fa fa-cog fa-spin"></i> Loading...</p>
      </div>
      <!-- Error Message -->
      <div class="alert alert-danger" ng-show="isError">
        <p><i class="fa fa-exclamation"></i> <strong>Error:</strong> Login data is not correct.</p>
      </div>

      <!-- DB Configuration -->
		  <div ng-if="dbNames">

        <!-- 1. Select Database -->
        <div class="card mb-3">
          <div class="card-body">
            <h5><span class="badge badge-success mr-2">1</span> Select a Database</h5>
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
          <div class="card-body">
		        <h5><span class="badge badge-warning text-white mr-2">Optional</span> Load Configuration</h5>
            <div class="row mb-0">
              <!-- Automatically load config -->
              <div class="col-6">
                <p><strong>[1] Automatically load config</strong></p>
                <button class="btn btn-default" ng-click="loadConfigByName()"><i class="fa fa-search"></i> Look for last config</button>
              </div>
              <!-- Manually load config -->
              <div class="col-6">
                <p><strong>[2] Manually load config</strong></p>
                <textarea class="form-control configtxt" ng-model="configtext" placeholder="Paste Content of the Config-File here"></textarea>
                <button class="btn btn-default" ng-click="loadconfig(configtext)">
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
          <div class="card-body">
            <!-- Tables -->
            <div class="row">
              <h5 class="mr-5"><span class="badge badge-success mr-2">2</span>Tables & Configuration</h5>
              <i>{{dbNames.model+' ,'}} {{tables.length}} Tabelle{{tables.length > 1 ? 'n' : ''}}</i>
              <table class="table table-sm table-striped" id="loadedtables" ng-model="tbl" id="row{{$index}}">
                <thead>
                  <tr>
                    <th width="200px">
                    </th>
                    <th width="250px"><span class="text-muted">Order</span></th>
                    <th width="20%">TABLENAME</th>
                    <th width="25%">ALIAS</th>
                    <th width="5%"><a href="" ng-click="tbl_toggle_sel_all()">IN MENU</a></th>
                    <th width="5%">STATE-ENGINE</th>
                    <th width="5%">RO (View)</th>
                    <!--<th width="5%">N:M or N:1</th>-->
                    <th width="30%">ICON</th>
                  </tr>
                </thead>
                <tbody ng-repeat="(name, tbl) in tables">
                  <!-- Table START -->
                  <tr>
                    <!-- Order Tabs -->
                    <td>
                      <div style="white-space:nowrap;overflow:hidden;">
                        <input type="text" style="width: 40px" ng-model="col.col_order">
                        <a ng-click="changeSortOrder(col, 1)"><i class="fa fa-angle-down p-1 pl-2"></i></a>
                        <a ng-click="changeSortOrder(col, -1)"><i class="fa fa-angle-up p-1"></i></a>
                      </div>
                    </td>
                    <td>
                      <!-- Expand / Collapse -->
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

                    <td>
                      <!-- Tablename -->
                      <p><b>{{name}}</b></p>
                    </td>
                    <td>
                      <input type="text" class="form-control" ng-model="tbl.table_alias"/>
                    </td>
                    <td>
                      <input type="checkbox" class="form-control" ng-model="tbl.is_in_menu">
                    </td>
                    <td>
                      <input type="checkbox" class="form-control" ng-model="tbl.se_active"
                        ng-disabled="tbl.table_name == 'state' || tbl.table_name == 'state_rules'">
                    </td>
                    <td><input type="checkbox" class="form-control" ng-model="tbl.is_read_only"></td>
                    
                    <!--
                    <td><input type="checkbox" class="form-control" ng-model="tbl.is_nm_table"></td>
                    -->

                    <!-- Icon -->
                    <td class="align-middle">
                      <div class="row">
                        <div class="col-3"><i class="{{tbl.table_icon}}"></i></div>
                        <div class="col-9"><input type="text" class="form-control" ng-model="tbl.table_icon"/></div>
                      </div>
                    </td>
                  </tr>
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
                    <td class="align-middle" colspan="2">
                      <div class="row">
                        <div class="col-9">
                          <b>{{col.COLUMN_NAME}}</b>
                        </div>
                        <div class="col-3">
                          <!--{{col.COLUMN_TYPE}}-->
                          <select class="custom-select custom-select-sm">
                            <option value="1">Text SL</option>
                            <option value="2">Text ML</option>
                            <option value="3">Number</option>
                            <option value="4">Date</option>
                            <option value="5">Time</option>
                            <option value="6">Date & Time</option>
                          </select>
                        </div>
                      </div>
                    </td>
                    <td><input type="text" class="form-control form-control-sm" ng-model="col.column_alias"></td>

                    <td colspan="4" ng-if="!col.is_virtual">
                      <input type="checkbox" class="mr-2" ng-model="col.is_in_menu">Vis
                      <!--<input type="checkbox" ng-model="col.is_read_only"> RO&nbsp;&nbsp;&nbsp;-->
                      <!--<input type="checkbox" ng-model="col.is_ckeditor"> CKEditor-->                      
                      &nbsp;-<b>FK:</b>
                      <input type="text" style="width: 80px" ng-model="col.foreignKey.table" placeholder="Table">
                      <input type="text" style="width: 80px" ng-model="col.foreignKey.col_id" placeholder="JoinID">
                      <input type="text" style="width: 120px" ng-model="col.foreignKey.col_subst" placeholder="ReplacedCloumn">
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
          <div class="card-body">
            <h5><span class="badge badge-success mr-2">3</span>Generate</h5>
            <div>
              <!-- Create Button -->
              <button name="createScript" ng-disabled="GUI_generating" class="btn btn-danger" id="createScript" ng-click="create_fkt()">
                <i class="fa fa-rocket"></i> Generate!</button>
              <!-- Open Project -->
              <button class="btn btn-default" href="#" ng-click="openProject(e)" target="_blank"><i class="fa fa-folder-open"></i> Open Project</button>
              <!-- Open Test Dir Button -->
              <!--
              <button class="btn btn-default mr-3" href="../APMS_test/" target="_blank"><i class="fa fa-folder-open"></i> Open Test-Directory</button>
              -->
              <!-- Generating -->
              <div class="d-inline text-center h1 mt-5 text-muted" ng-if="GUI_generating">
                <i class="fa fa-cog fa-spin fa-fw"></i> Generating Project...
              </div>
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

    <!-- Load Modal -->
    <!--
    <div class="modal fade" id="loadDb">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
              <h3 class="modal-title">Stored Connections</h3>
            </div>
            <div class="modal-body">
              <h5 class="text-center">List of Stored connections in Database</h5>
              <table class="table table-striped" id="tblGrid">
                <thead id="tblHead_1">
                <tr>
                  <th>Id</th>
                  <th>Host</th>
                  <th>Username</th>
                  <th>Port</th>
                  <th>Actions</th>
                </tr>
                </thead>
                <tbody class="connection-values">
                </tbody>
              </table>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default " data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
    </div>
    -->

  </div>
</div>
<!-- Footer -->
<?php  include_once "_footer.inc.php" ?>