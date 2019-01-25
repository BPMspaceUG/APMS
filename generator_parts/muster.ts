// Plugins (only declared to remove TS Errors)
declare var $: any;
declare var vis: any;

// Enums
enum SortOrder {ASC = 'ASC', DESC = 'DESC'}
enum SelectType {NoSelect = 0, Single = 1, Multi = 2}
enum TableType {obj = 'obj', t1_1 = '1_1', t1_n = '1_n', tn_1 = 'n_1', tn_m = 'n_m'}

// Events
// see here: https://stackoverflow.com/questions/12881212/does-typescript-support-events-on-classes
interface ILiteEvent<T> {
  on(handler: { (data?: T): void }) : void;
  off(handler: { (data?: T): void }) : void;
}
class LiteEvent<T> implements ILiteEvent<T> {
  private handlers: { (data?: T): void; }[] = [];

  public on(handler: { (data?: T): void }) : void {
      this.handlers.push(handler);
  }
  public off(handler: { (data?: T): void }) : void {
      this.handlers = this.handlers.filter(h => h !== handler);
  }
  public trigger(data?: T) {
      this.handlers.slice(0).forEach(h => h(data));
  }
  public expose() : ILiteEvent<T> {
    return this;
  }
}

abstract class GUI {
  public static ID = function () {
    // Math.random should be unique because of its seeding algorithm.
    // Convert it to base 36 (numbers + letters), and grab the first 9 characters
    // after the decimal.
    return Math.random().toString(36).substr(2, 9);
  };
}

//==============================================================
// Class: Database
//==============================================================
abstract class DB {
  private static API_URL: string;

  public static request(command: string, params: any, callback) {
    let me = this;
    let data = {cmd: command}
    // If Params are set, then append them to data object
    if (params)
      data['paramJS'] = params;

    // Request (every Request is processed by this function)
    $.ajax({
      method: "POST",
      url: me.API_URL,
      contentType: 'json',
      data: JSON.stringify(data),
      error: function(xhr, status) {
        // Not Authorized
        if (xhr.status == 401) {
          document.location.assign('login.php') // Redirect to Login-Page
        } else if (xhr.status == 403) {
          alert("Sorry! You dont have the rights to do this.");
        }
      }
    }).done(function(response) {
      callback(response)
    });
  }
}
//==============================================================
// Class: Modal
//==============================================================
class Modal {
  private DOM_ID: string;
  private heading: string;
  private content: string;
  private footer: string;
  private isBig: boolean;
  public options = {
    btnTextClose: 'Close'
  }

  public constructor(heading: string, content: string, footer: string = '', isBig: boolean = false) {
    this.DOM_ID = GUI.ID()
    // Set Params
    this.heading = heading
    this.content = content
    this.footer = footer
    this.isBig = isBig

    // Render and add to DOM-Tree
    let sizeType = ''
    if (this.isBig)
      sizeType = ' modal-xl'
    // Result

    let html = `<div id="${this.DOM_ID}" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog${sizeType}" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${this.heading}</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            ${this.content}
          </div>
          <div class="modal-footer">
            <span class="customfooter d-flex">${this.footer}</span>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              ${this.options.btnTextClose}
            </button>
          </div>
        </div>
      </div>
    </div>`;
    
    // Add generated HTML to DOM
    $('body').append(html);
    // Remove from DOM on close
    $('#'+this.DOM_ID).on('hidden.bs.modal', function (e) {
      $(this).remove();
    });
  }
  public setFooter(html: string) {
    $('#'+this.DOM_ID+' .customfooter').html(html);
  }
  public setContent(html: string) {
    $('#'+this.DOM_ID+' .modal-body').html(html);
  }
  public show(): void {
    $("#"+this.DOM_ID).modal();
    $("#"+this.DOM_ID).modal('show');
  }
  public getDOMID(): string {
    return this.DOM_ID
  }
}
//==============================================================
// Class: StateMachine
//==============================================================
class StateMachine {
  private tablename: string

  constructor(tablename: string){
    this.tablename = tablename
  }
  public openSEPopup() {
    let smLinks, smNodes
    let me = this;
  
    DB.request('getStates', {table: me.tablename}, function(r) {
      smNodes = JSON.parse(r)
      DB.request('smGetLinks', {table: me.tablename}, function(r) {
        smLinks = JSON.parse(r)

        // Finally, when everything was loaded, show Modal
        let M = new Modal('StateMachine', '<div class="statediagram" style="width: 100%; height: 300px;"></div>', '<button class="btn btn-secondary fitsm"><i class="fa fa-expand"></i> Fit</button>', true)
        let container =  document.getElementsByClassName('statediagram')[0]
        let nodes = smNodes
        let edges = smLinks
        for (let i=0; i<nodes.length; i++) {
          if (me.isExitNode(nodes[i].id, smLinks)) {
            // Exit Node
            nodes[i]['color'] = '#c55';
            nodes[i]['shape'] = 'dot';
            nodes[i]['size'] = 10;
          }
          if (nodes[i].entrypoint == 1) {
            // Add EntryPoint Node
            nodes.push({id: 0, color: '#5c5',  shape: 'dot', size: 10});
            edges.push({from: 0, to: nodes[i].id})
          } 
          // every node, except 0 node
          if (nodes[i].id > 0) {
            nodes[i]['label'] = '<i>'+ nodes[i].id +'</i>\n' + nodes[i]['label'];
            nodes[i]['font'] = { multi: 'html'};
          }
        }
      
        let data = {
          nodes: nodes,
          edges: edges
        };
        let options = {
          edges: {
            //smooth: { 'type': 'straightCross', 'forceDirection': 'horizontal'},
            color: '#3598DC',
            shadow: true,
            length: 100,
            arrows: 'to',
            arrowStrikethrough: true,
            dashes: false,
            smooth: {
                //'enabled': true,
                //"type": "cubicBezier",
                "forceDirection": "horizontal"
                //"roundness": 1// 0.2
            }
          },
          nodes: {
            shape: 'box',
            //color: {background: 'white', border: '#333'},
            margin: 20,
            heightConstraint: {
              minimum: 40
            },
            widthConstraint: {
              minimum: 80,
              maximum: 200
            },
            borderWidth: 2,
            size: 24,
            color: {
                border: '#3598DC',
                background: '#fff'
            },
            font: {
                color: '#888888',
                size: 16,
            },
            shapeProperties: {
                useBorderWithImage: false
            },
            scaling: {
                min: 10,
                max: 30
            },
            fixed: {
                x: false,
                y: false
            }
          },
          layout: {
              hierarchical: {
                  enabled: true,
                  direction: 'LR',
                  nodeSpacing: 200,
                  levelSeparation: 225,
                  blockShifting: true,
                  edgeMinimization: true,
                  parentCentralization: true,
                  //sortMethod: 'directed'
              }
          },
          physics: {
            enabled: false
          },
          interaction: {
            /*zoomView:false,*/
            //dragNodes:false
            /*dragView: false*/
          }
        };

        let network = new vis.Network(container, data, options);
        M.show()
        let ID = M.getDOMID();
        $('#' + ID).on('shown.bs.modal', function (e) {
          network.fit({scale: 1, offset: {x:0,y:0}, animation: {duration: 500, easingFunction: 'easeInOutQuad'}});
        })

        $('.fitsm').click(function(e){
          e.preventDefault();
          network.fit({scale: 1, offset: {x:0,y:0}, animation: {duration: 1000, easingFunction: 'easeInOutQuad'}})
        })


      })
    })
  }
  private isExitNode(NodeID: number, links) {
    let res: boolean = true;
    links.forEach(function(e){
      if (e.from == NodeID && e.from != e.to)
        res = false;
    })
    return res
  }
}
//==============================================================
// Class: RawTable
//==============================================================
class RawTable {
  protected tablename: string;
  public Columns: any;
  protected PrimaryColumn: string;
  protected Filter: string;
  protected OrderBy: string;
  protected AscDesc: SortOrder = SortOrder.DESC;
  protected Select: string;
  protected PageLimit: number;
  protected PageIndex: number = 0;
  protected Where: string = '';
  protected Rows: any;
  protected actRowCount: number; // Count total
  protected TableType: TableType = TableType.obj;
  //protected selRowIDs: number[] = [];

  constructor (tablename: string) {
    this.tablename = tablename;
    this.actRowCount = 0;
  }
  public getNextStates(data: any, callback) {
    DB.request('getNextStates', {table: this.tablename, row: data}, function(response) {
      callback(response)
    })
  }
  public createRow(data: any, callback) {
    let me = this;
    DB.request('create', {table: this.tablename, row: data}, function(r){
      me.countRows(function(){
        callback(r)
      })
    })
  }
  public deleteRow(RowID: number, callback) {
    let me = this;
    let data = {}
    data[this.PrimaryColumn] = RowID
    DB.request('delete', {table: this.tablename, row: data}, function(response) {
      me.countRows(function(){
        callback(response)
      })
    })
  }
  public updateRow(RowID: number, new_data: any, callback) {
    let data = new_data
    data[this.PrimaryColumn] = RowID
    DB.request('update', {table: this.tablename, row: new_data}, function(response) {
      callback(response)
    })
  }
  public transitRow(RowID: number, TargetStateID: number, trans_data: any = null, callback) {
    let data = {state_id: 0}
    if (trans_data) data = trans_data
    // PrimaryColID and TargetStateID are the minimum Parameters which have to be set
    // also RowData can be updated in the client -> has also be transfered to server
    data[this.PrimaryColumn] = RowID
    data.state_id = TargetStateID
    DB.request('makeTransition', {table: this.tablename, row: data}, function(response) {
      callback(response)
    })
  }
  // Call this function only at [init] and then only on [create] and [delete] and at [filter]
  public countRows(callback) {
    let me = this;
    let data = {
      table: this.tablename,
      select: 'COUNT(*) AS cnt',
      where: this.Where,
      filter: this.Filter
    }
    DB.request('count', data, function(r){
      if (r.length > 0) {
        let resp = JSON.parse(r);
        if (resp.length > 0) {
          me.actRowCount = parseInt(resp[0].cnt);
          // Callback method
          callback()
        }
      }
    })
  }
  public loadRows(callback) {
    let me = this;
    let data = {
      table: this.tablename,
      limitStart: this.PageIndex * this.PageLimit,
      limitSize: this.PageLimit,
      select: this.Select,
      where: this.Where,
      filter: this.Filter,
      orderby: this.OrderBy,
      ascdesc: this.AscDesc
    }
    // HTTP Request
    DB.request('read', data, function(r){
      let response = JSON.parse(r);
      me.Rows = response;
      callback(response);
    })
  }
  public getNrOfRows(): number {
    return this.actRowCount
  }
}
//==============================================================
// Class: Table
//==============================================================
class Table extends RawTable {
  private TableConfig: any;
  private lastModifiedRowID: number;
  private jQSelector: string = '';
  private SM: StateMachine;
  private ReadOnly: boolean;
  private FilterText: string = '';
  private selType: SelectType;
  private selectedIDs: number[];
  private Form_Create: string = '';
  private Form_Modify: string = '';
  private defaultValues = {}; // Default Values in Create-Form
  public GUIOptions = {
    maxCellLength: 30,
    showControlColumn: true,
    showWorkflowButton: false,
    showFilter: true,
    smallestTimeUnitMins: true,
    modalHeaderTextCreate: 'Create Entry',
    modalHeaderTextModify: 'Modify Entry',
    modalButtonTextCreate: 'Create',
    modalButtonTextCreateRelation: 'Create Relationship',
    modalButtonTextModifySave: 'Save',
    modalButtonTextModifySaveAndClose: 'Save &amp; Close',
    modalButtonTextModifyClose: 'Close',
    modalButtonTextSelect: 'Select',
    filterPlaceholderText: 'Search...',
    statusBarTextNoEntries: 'No Entries',
    statusBarTextEntries: 'Showing Entries {lim_from} - {lim_to} of {count} Entries'
  }
  // Events
  private readonly onSelectionChanged = new LiteEvent<void>();
  private readonly onEntriesModified = new LiteEvent<void>(); // Created, Deleted, Updated

  constructor(tablename: string, DOMSelector: string, SelType: SelectType = SelectType.NoSelect, callback: any = function(){}, whereFilter: string = '', defaultObj = {}) {
    // Call parent constructor
    super(tablename)

    let me = this;
    this.jQSelector = DOMSelector;
    this.defaultValues = defaultObj;
    this.selType = SelType;
    this.Where = whereFilter;
    // Inherited
    this.PageIndex = 0;
    this.PageLimit = 10;
    this.selectedIDs = []; // empty array
    this.tablename = tablename
    this.Filter = '';
    this.OrderBy = '';

    DB.request('init', {table: tablename, where: whereFilter}, function(resp) {
      if (resp.length > 0) {
        resp = JSON.parse(resp);
        // Save Form Data
        me.Form_Create = resp['formcreate'];
        me.Form_Modify = resp['formmodify'];
        me.actRowCount = resp['count'];
        me.TableConfig = resp['config'];
        // Initialize StateMachine for the Table
        if (me.TableConfig['se_active'])
          me.SM = new StateMachine(tablename);
        else
          me.SM = null;
        me.Columns = me.TableConfig.columns;
        me.ReadOnly = me.TableConfig.is_read_only;
        me.TableType = me.TableConfig.table_type;
        //console.log('InitTable -> ', me.tablename, '[', me.TableType, ']');

        // check if is read only and no select then hide first column
        if (me.ReadOnly && me.selType == SelectType.NoSelect)
          me.GUIOptions.showControlColumn = false;
        // Loop all cloumns form this table
        Object.keys(me.Columns).forEach(function(col){
          // Get Primary and SortColumn
          if (me.Columns[col].is_in_menu && me.OrderBy == '') {
            // DEFAULT: Sort by first visible Col
            if (me.Columns[col].foreignKey['table'] != '')
              me.OrderBy = 'a.'+col;
            else
              me.OrderBy = col;
          }
          if (me.Columns[col].EXTRA == 'auto_increment') me.PrimaryColumn = col;
        })
        // Initializing finished
        callback();
      }
    })
  }
  private addClassToDataRow(id: number, classname: string) {
    $(this.jQSelector + ' .datarow').removeClass(classname); // Remove class from all other rows
    $(this.jQSelector + ' .row-' + id).addClass(classname);
  }
  private toggleSort(ColumnName: string): void {
    let me = this;
    this.AscDesc = (this.AscDesc == SortOrder.DESC) ? SortOrder.ASC : SortOrder.DESC
    // Check if column is a foreign key
    if (me.Columns[ColumnName].foreignKey['table'] != '')
      this.OrderBy = 'a.' + ColumnName;
    else
      this.OrderBy = ColumnName;
    // Refresh
    this.loadRows(function(){
      me.renderHTML()
    })
  }
  private setPageIndex(targetIndex: number): void {
    let me = this
    var newIndex = targetIndex
    var lastPageIndex = this.getNrOfPages() - 1
    // Check borders
    if (targetIndex < 0) newIndex = 0 // Lower limit
    if (targetIndex > lastPageIndex) newIndex = lastPageIndex // Upper Limit
    // Set new index
    this.PageIndex = newIndex
    // Refresh
    this.loadRows(function(){me.renderHTML()})
  }
  private getNrOfPages(): number {
    return Math.ceil(this.getNrOfRows() / this.PageLimit);
  }
  private getPaginationButtons(): number[] {
    const MaxNrOfButtons: number = 5
    var NrOfPages: number = this.getNrOfPages()
    // Pages are less then NrOfBtns => display all
    if (NrOfPages <= MaxNrOfButtons) {
      var pages: number[] = new Array(NrOfPages)
      for (var i: number=0;i<pages.length;i++)
        pages[i] = i - this.PageIndex
    } else {
      // Pages > NrOfBtns display NrOfBtns
      pages = new Array(MaxNrOfButtons)
      // Display start edge
      if (this.PageIndex < Math.floor(pages.length / 2))
        for (var i=0;i<pages.length;i++) pages[i] = i - this.PageIndex
      // Display middle
      else if ((this.PageIndex >= Math.floor(pages.length / 2))
        && (this.PageIndex < (NrOfPages - Math.floor(pages.length / 2))))
        for (var i=0;i<pages.length;i++) pages[i] = -Math.floor(pages.length / 2) + i 
      // Display end edge
      else if (this.PageIndex >= NrOfPages - Math.floor(pages.length / 2)) {
        for (var i=0;i<pages.length;i++) pages[i] = NrOfPages - this.PageIndex + i - pages.length
      }
    }
    return pages
  }
  private getHTMLStatusText(): string {
    if (this.getNrOfRows() > 0 && this.Rows.length > 0) {
      let text = this.GUIOptions.statusBarTextEntries;
      // Replace Texts
      text = text.replace('{lim_from}', ''+ ((this.PageIndex * this.PageLimit) + 1) );
      text = text.replace('{lim_to}', ''+ ((this.PageIndex * this.PageLimit) + this.Rows.length) );
      text = text.replace('{count}', '' + this.getNrOfRows() );
      return text;
    }
    else {
      // No Entries
      return this.GUIOptions.statusBarTextNoEntries;
    }
  }
  private getFormModify(data: any, callback): void {
    var me: Table = this;
    DB.request('getFormData', {table: me.tablename, row: data}, function(response) {
      callback(response)
    })
  }
  private readDataFromForm(MID: string): any {
    let me = this
    let data = {}
    let inputs = $(MID+' :input')
  
    inputs.each(function(){
      const e = $(this);
      const key = e.attr('name');
      const val = e.val();

      if (key) {
        let column = null;
        try {
          column = me.Columns[key];
        } catch (error) {
          column = null; // Column doesnt exist in current Table
        }
  
        if (column) {
          const DataType = column.DATA_TYPE.toLowerCase()
          //console.log('[', DataType, ']', key, ' -> ', val );

          //  if empty then value should be NULL
          if ( (val == '' || val == null ) && (DataType.indexOf('text') < 0 || column.foreignKey.table != '')) {
            data[key] = null;
          } else {
            // [NO FK]          
            if (DataType == 'datetime') {
              // For DATETIME
              if (e.attr('type') == 'date')
                data[key] = val // overwrite
              else if (e.attr('type') == 'time')
                data[key] += ' '+val // append
            }
            else if (DataType == 'tinyint') {
              // Boolean
              data[key] = e.prop('checked') ? '1' : '0';
            }
            else {
              data[key] = val
            }
          }
        } else {
          // Virtual Element in FormData
          data[key] = val
        }
      }
    })
    return data
  }
  private writeDataToForm(MID: string, data: any): void {
    let me = this
    let inputs = $(MID+' :input')

    inputs.each(function(){
      let e = $(this);
      let col = e.attr('name')
      let value = data[col]  
      // isFK?
      if (value) {
        if ((typeof value === "object") && (value !== null)) {
          //--- ForeignKey
          // -> Save in hidden input
          //console.log('wD2F', value)
          const primCol = Object.keys(value)[0];
          const val = value[primCol];
          e.val(val)
        }
        else {
          //--- Normal
          if (col) {
            var DataType = me.Columns[col].DATA_TYPE.toLowerCase()
  
            if (DataType == 'datetime') {
              // DateTime -> combine vals
              if (e.attr('type') == 'date')
                e.val(value.split(" ")[0])
              else if (e.attr('type') == 'time') {
                // Remove seconds from TimeString
                if (me.GUIOptions.smallestTimeUnitMins) {
                  var timeArr = value.split(':');
                  timeArr.pop();
                  value = timeArr.join(':')
                }
                e.val(value.split(" ")[1])
              }
            }
            else if (DataType == 'time') {
              // Remove seconds from TimeString
              if (me.GUIOptions.smallestTimeUnitMins) {
                var timeArr = value.split(':');
                timeArr.pop();
                value = timeArr.join(':')
              }
              e.val(value)
            }
            else if (DataType == 'tinyint') {
              // Checkbox
              e.prop('checked', parseInt(value) !== 0); // Boolean
            } else
              e.val(value)
          }
        }
      }
    })
  }
  private renderEditForm(RowID: number, htmlForm: string, nextStates: any, ExistingModalID: string = undefined) {
    let t = this
    let TheRow = null
    // get The Row
    this.Rows.forEach(row => {
      if (row[t.PrimaryColumn] == RowID)
        TheRow = row
    });

    // Create a new Modal or get the Existing Modal by DOM-ID
    let EditMID = null;
    let M: Modal = null;
    if (!ExistingModalID) {
      let TableAlias = 'in <i class="'+this.TableConfig.table_icon+'"></i> ' + this.TableConfig.table_alias;
      let TitleText = this.GUIOptions.modalHeaderTextModify + '<span class="text-muted mx-3">('+RowID+')</span><span class="text-muted ml-3">'+ TableAlias +'</span>'
      M = new Modal(TitleText, htmlForm, '', true)
      M.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose;
      EditMID = M.getDOMID();
    } else {
      EditMID = ExistingModalID;
      $('#'+EditMID+' .modal-body').html(htmlForm);
    }

    let btns = '';
    let saveBtn = '';
    let actStateID = TheRow.state_id['state_id'] // ID
    let actStateName = TheRow.state_id['name'] // ID
    let cssClass = ' state' + actStateID;

    // Check States -> generate Footer HTML
    if (nextStates.length > 0) {
      let cnt_states = 0;
      // Header
      btns = '<div class="btn-group dropup ml-0 mr-auto">'
      btns += '<button type="button" class="btn ' + cssClass + ' text-white dropdown-toggle" data-toggle="dropdown">' + actStateName + '</button>';
      btns += '<div class="dropdown-menu p-0">';

      // Loop States
      nextStates.forEach(function(state){
        let btn_text = state.name
        let btn = '';
        // Override the state-name if it is a Loop (Save)
        if (actStateID == state.id) {
          saveBtn = '<div class="btn-group ml-auto mr-0" role="group">';
          saveBtn += '<button class="btn btn-primary btnState btnStateSave" data-rowid="'+RowID+'" data-targetstate="'+state.id+'" type="button">'+
          '<i class="fa fa-floppy-o"></i> '+t.GUIOptions.modalButtonTextModifySave +'</button>';
          saveBtn += '<button class="btn btn-primary btnState btnSaveAndClose" data-rowid="'+RowID+'" data-targetstate="'+state.id+'" type="button">'+
            t.GUIOptions.modalButtonTextModifySaveAndClose +'</button>';
          saveBtn += '</div>';
        } else {
          cnt_states++;
          btn = '<a class="dropdown-item btnState btnStateChange state' + state.id + '" data-rowid="'+RowID+'" data-targetstate="'+state.id+'">' + btn_text + '</a>';
        }
        btns += btn;
      })
      // Footer
      btns += '</div></div>';      
      // Save buttons
      if (cnt_states == 0)
        btns = '<button type="button" class="btn '+cssClass+' text-white" tabindex="-1" disabled>' + actStateName + '</button>'; // Reset html if only Save button exists      
    } else {
      // No Next States
      btns = '<button type="button" class="btn '+cssClass+' text-white" tabindex="-1" disabled>' + actStateName + '</button>';
    }
    btns += saveBtn;
    // TODO: Rewrite to MID
    //M.setFooter(btns);
    $('#'+EditMID+' .customfooter').html(btns);

    // Bind function to StateButtons
    $('#'+EditMID+' .btnState').click(function(e){
      e.preventDefault();
      let RowID = $(this).data('rowid')
      let TargetStateID = $(this).data('targetstate')
      t.setState(EditMID, RowID, TargetStateID)
      // Check Class and Close window
      if ($(this).hasClass("btnSaveAndClose"))
        $('#'+EditMID).modal('hide');
    })

    $('#'+EditMID+' .label-state').addClass('state' + actStateID).text(TheRow.state_id[1]);  
    // Update all Labels
    this.updateLabels(EditMID)
    // Save origin Table in all FKeys
    $('#'+EditMID+' .inputFK').data('origintable', t.tablename);
    // Load data from row and write to input fields with {key:value}
    t.writeDataToForm('#'+EditMID, TheRow)
    // Add PrimaryID in stored Data
    $('#'+EditMID+' .modal-body').append('<input type="hidden" name="'+t.PrimaryColumn+'" value="'+RowID+'">')

    //--- finally show Modal if it is a new one
    if (M) M.show()
  }
  private saveEntry(MID: string, closeModal: boolean = true){
    let t = this
    let data = t.readDataFromForm('#'+MID)
    // REQUEST
    t.updateRow(data[t.PrimaryColumn], data, function(r){
      if (r.length > 0) {
        if (r != "0") {
          // Success
          if (closeModal)
            $('#'+MID).modal('hide')
          t.lastModifiedRowID = data[t.PrimaryColumn]
          t.loadRows(function(){
            t.renderHTML()
            t.onEntriesModified.trigger();
          })
        } else {
          // Fail
          alert("Element could not be updated!")
        }
      }
    })
  }
  private updateLabels(MID: string) {
    let me = this
    let labels = $('#'+MID+' label');
    // Update all Labels
    labels.each(function(){
      let label = $(this);
      let colname = label.parent().find('[name]').attr('name');
      if (colname) {
        let aliasCol = me.Columns[colname];
        if (aliasCol) {
          label.text(aliasCol.column_alias);
        }
      }
    });
  }
  private setState(MID: string, RowID: number, targetStateID: number): void {
    let t = this
    let data = {}
    let actState = undefined
    let newState = undefined

    // Get Actual State
    for (const row of t.Rows) {
      if (row[t.PrimaryColumn] == RowID)
        actState = row['state_id'];
    }
    // Set a loading icon or indicator when transition is running
    if (MID != '') {
      // Remove all Error Messages
      $('#'+MID+' .modal-body .alert').remove();
      // Read out all input fields with {key:value}
      data = t.readDataFromForm('#'+MID);
      $('#'+MID+' .modal-title').prepend(`
        <span class="loadingtext">
          <div class="spinner-border" role="status"></div>
        </span>`);
      $('#'+MID+' :input').prop("disabled", true);
    }

    // REQUEST
    t.transitRow(RowID, targetStateID, data, function(r) {
      // When a response came back
      if (MID != '') {
        $('#'+MID+' .loadingtext').remove();
        $('#'+MID+' :input').prop("disabled", false);
      }
      // Try to parse result messages
      let parsedData = undefined;
      try {
        parsedData = JSON.parse(r);
      }
      catch(err) {
        let resM = new Modal('<b class="text-danger">Script Error!</b>', r);
        resM.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose
        resM.show();
        return
      }

      // Remove all Error Messages
      if (MID != '')
        $('#'+MID+' .modal-body .alert').remove();

      // Handle Transition Feedback
      let counter = 0;
      let messages = [];
      parsedData.forEach(msg => {
        // Show Messages
        if (msg.show_message)
          messages.push({type: counter, text: msg.message});
        // Increase Counter for Modals
        counter++;
      });
      // Resort the messages
      messages.reverse(); // like the process => [Out, Transit, In]

      // Check if Transition was successful
      if (counter >= 2) {
        // Refresh Form-Data
        if (MID != '') {
          t.getFormModify(data, function(r){
            if (r.length > 0) {
              let htmlForm = r;
              // Refresh Modal Buttons
              t.getNextStates(data, function(re){
                if (re.length > 0) {
                  let nextstates = JSON.parse(re);
                  // Set Form-Content
                  t.renderEditForm(RowID, htmlForm, nextstates, MID);
                }
              })
            }
          })
        }

        // Mark rows
        if (RowID != 0)
          t.lastModifiedRowID = RowID;

        t.loadRows(function(rows){
          // Get Actual State
          for (const row of rows) {
            if (row[t.PrimaryColumn] == RowID)
              newState = row['state_id'];
          }
          // Show Result Messages
          for (const msg of messages) {
            const stateFrom = t.renderStateButton(actState['state_id'], actState['name']);
            const stateTo = t.renderStateButton(newState['state_id'], newState['name']);

            let tmplTitle = '';
            if (msg.type == 0) tmplTitle = `OUT <span class="text-muted ml-2">${stateFrom} &rarr;</span>`;
            if (msg.type == 1) tmplTitle = `Transition <span class="text-muted ml-2">${stateFrom} &rarr; ${stateTo}</span>`;
            if (msg.type == 2) tmplTitle = `IN <span class="text-muted ml-2">&rarr; ${stateTo}</span>`;

            let resM = new Modal(tmplTitle, msg.text)
            resM.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose
            resM.show();
          }
          t.renderHTML();
          t.onEntriesModified.trigger();
        })
      }

    })
  }
  //-------------------------------------------------- PUBLIC METHODS
  public createEntry(): void {
    let me = this

    const TableIcon = '<i class="'+this.TableConfig.table_icon+'"></i>';
    const TableAlias = this.TableConfig.table_alias;
    const ModalTitle = this.GUIOptions.modalHeaderTextCreate + '<span class="text-muted ml-3">in '+TableIcon + ' ' + TableAlias+'</span>';
    let CreateBtn = '<button class="btn btn-success btnCreateEntry" type="button">'+
      '<i class="fa fa-plus"></i>&nbsp;'+ this.GUIOptions.modalButtonTextCreate + ' ' + TableAlias + '</button>';
    
    // Create Modal
    let M = new Modal(ModalTitle, me.Form_Create, CreateBtn, true);
    M.options.btnTextClose = me.GUIOptions.modalButtonTextModifyClose
    let ModalID = M.getDOMID()
  
    this.updateLabels(ModalID) // Update all Labels
    this.writeDataToForm('#'+ModalID, me.defaultValues) // Update Default values
  
    // Save origin Table in all FKeys
    $('#'+ModalID+' .inputFK').data('origintable', me.tablename);
  
    // Bind Buttonclick
    $('#'+ModalID+' .btnCreateEntry').click(function(e){
      e.preventDefault();
      // Read out all input fields with {key:value}
      let data = me.readDataFromForm('#'+ModalID)

      me.createRow(data, function(r){
        let msgs = []
        // Remove all Error Messages
        $('#' + ModalID + ' .modal-body .alert').remove();
        try {
          msgs = JSON.parse(r)
        }
        catch(err) {
          // Show Error
          $('#' + ModalID + ' .modal-body').prepend('<div class="alert alert-danger" role="alert">'+
          '<b>Script Error!</b>&nbsp;'+ r +
          '</div>')
          return
        }
        // Handle Transition Feedback
        let counter = 0; // 0 = trans, 1 = in -- but only at Create!
        msgs.forEach(msg => {
          // Show Message
          if (msg.show_message) {
            const textTransScriptCreate = 'Transition-Script [Create]';
            const textINScript = 'IN-Script <button class=""';
            let resM = new Modal('Feedback <small>'+ (counter == 0 ? textTransScriptCreate : textINScript) +'</small>', msg.message)
            resM.options.btnTextClose = me.GUIOptions.modalButtonTextModifyClose
            resM.show()
          }
          // Check if Element was created
          if (msg.element_id) {
            // Success?
            if (msg.element_id > 0) {
              // Reload Data from Table
              me.lastModifiedRowID = msg.element_id              
              // load rows and render Table
              me.countRows(function(){
                me.loadRows(function(){
                  me.renderHTML()
                  me.onEntriesModified.trigger();
                  // TODO: Overwrite the new Content from Database
                  //me.modifyRow(msg.element_id, ModalID)
                  $('#'+ModalID).modal('hide')
                })
              })
            }
            // ElementID has to be 0! otherwise the transscript aborted
            if (msg.element_id == 0) {
              $('#' + ModalID + ' .modal-body').prepend(
                '<div class="alert alert-danger" role="alert">'+
                '<b>Database Error!</b>&nbsp;'+ msg.errormsg +
                '</div>'
              )
            }
          }
          // Special Case for Relations (reactivate them)
          if (counter == 0 && !msg.show_message && msg.message == 'RelationActivationCompleteCloseTheModal') {
            // Reload Data from Table
            me.lastModifiedRowID = msg.element_id
            // load rows and render Table
            me.countRows(function(){
              me.loadRows(function(){
                me.renderHTML()
                me.onEntriesModified.trigger();
                $('#'+ModalID).modal('hide')
              })
            })
          }
          counter++;
        });
      });
    })
    M.show()
  }
  public modifyRow(id: number, ExistingModalID: string = undefined) {
    let me = this
    // Check Selection-Type
    if (this.selType == SelectType.Single) {
      //------------------------------------
      // SINGLE SELECT
      //------------------------------------
      this.selectedIDs = []
      this.selectedIDs.push(id)
      this.renderHTML()
      this.onSelectionChanged.trigger();
      return
    }
    else if (this.selType == SelectType.Multi) {
      //------------------------------------
      // MULTI SELECT
      //------------------------------------
      let pos = this.selectedIDs.indexOf(id)
      // Check if already exists in array -> then remove
      if (pos >= 0) {
        // Remove from List and reindex array
        this.selectedIDs.splice(pos, 1)
      } else {
        // Add to List
        this.selectedIDs.push(id)
      }
      this.renderHTML();
      this.onSelectionChanged.trigger();
      return
    }
    else {
      //------------------------------------
      // NO SELECT / EDITABLE / READ-ONLY
      //------------------------------------
      // Exit if it is a ReadOnly Table
      if (this.ReadOnly) return
      // Indicate which row is getting modified
      //this.addClassToDataRow(id, 'table-warning');
      $(this.jQSelector+' .datarow .controllcoulm').html('<i class="fa fa-pencil"></i>'); // for all
      $(this.jQSelector+' .row-'+id+' .controllcoulm').html('<i class="fa fa-pencil text-primary"></i>');
      // Set Form
      if (this.SM) {
        // EDIT-Modal WITH StateMachine
        let PrimaryColumn: string = this.PrimaryColumn;
        let data = {}
        data[PrimaryColumn] = id
        // Get Forms
        me.getFormModify(data, function(r){
          if (r.length > 0) {
            var htmlForm = r;
            me.getNextStates(data, function(re){
              if (re.length > 0) {
                var nextstates = JSON.parse(re);
                me.renderEditForm(id, htmlForm, nextstates, ExistingModalID);
              }
            })
          }
        })
      } else {

        // EDIT-Modal WITHOUT StateMachine
        let M: Modal = undefined;
        let ModalID = undefined;
        if (!ExistingModalID) {
          let TableAlias = 'in <i class="'+this.TableConfig.table_icon+'"></i> ' + this.TableConfig.table_alias;
          let TitleText = this.GUIOptions.modalHeaderTextModify + '<span class="text-muted mx-3">('+id+')</span><span class="text-muted ml-3">'+TableAlias+'</span>';        
          M = new Modal(TitleText, this.Form_Modify, '', true);
          M.options.btnTextClose = this.GUIOptions.modalButtonTextModifyClose;
          ModalID = M.getDOMID();
        } else {
          ModalID = ExistingModalID;
        }

        // Save origin Table in all FKeys
        $('#'+ModalID+' .inputFK').data('origintable', this.tablename);

        // Save buttons
        let btn: string = '<div class="btn-group ml-auto mr-0" role="group">'
        btn += '<button class="btn btn-primary btnSave" type="button">'+
          '<i class="fa fa-floppy-o"></i> '+this.GUIOptions.modalButtonTextModifySave +'</button>';
        btn += '<button class="btn btn-primary btnSaveAndClose" type="button">'+
          this.GUIOptions.modalButtonTextModifySaveAndClose + '</button>';
        btn += '</div>'

        // TODO: Set Footer
        //M.setFooter(btn);
        $('#'+ModalID+' .customfooter').html(btn);

        // Bind functions to Save Buttons
        $('#'+ModalID+' .btnSave').click(function(e){
          e.preventDefault();
          me.saveEntry(ModalID, false)
        })
        $('#'+ModalID+' .btnSaveAndClose').click(function(e){
          e.preventDefault();
          me.saveEntry(ModalID)
        })

        // Add the Primary RowID
        $('#'+ModalID+' .modal-body').append('<input type="hidden" name="'+this.PrimaryColumn+'" value="'+id+'">')
        // Write all input fields with {key:value}
        let r = null
        me.Rows.forEach(row => {
          if (row[me.PrimaryColumn] == id)
            r = row
        });
        this.writeDataToForm('#'+ModalID, r)
        // Finally show Modal if none existed
        if (M) M.show()
      }
    }
  }
  public getSelectedRows(): Array<number> {
    return this.selectedIDs
  }
  public setSelectedRows(selRows: Array<number>) {
    this.selectedIDs = selRows;
    this.renderHTML()
  }
  private renderStateButton(ID: number, name: string, withDropdown: boolean = false) {
    const cssClass = 'state' + ID;

    if (withDropdown) {
      // With Dropdown
      return `<div class="dropdown showNextStates">
            <button class="btn dropdown-toggle btnGridState btn-sm label-state ` + cssClass + `" data-toggle="dropdown">` + name + `</button>
            <div class="dropdown-menu p-0">
              <p class="m-0 p-3 text-muted"><i class="fa fa-spinner fa-pulse"></i> Loading...</p>
            </div>
          </div>`;
    } else {
      // Without Dropdown
      return `<button onclick="return false;" class="btn btnGridState btn-sm label-state ` + cssClass + `">` + name + `</button>`;
    }
  }

  private formatCell(cellContent: any, isHTML: boolean = false) {
    if (isHTML) return cellContent;
    let t = this;
    // string -> escaped string
    function escapeHtml(string) {
      let entityMap = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'};
      return String(string).replace(/[&<>"'`=\/]/g, function (s) {
        return entityMap[s];
      });
    }
    // check cell type
    if (typeof cellContent == 'string') {
      // String, and longer than X chars
      if (cellContent.length > this.GUIOptions.maxCellLength)
        return escapeHtml(cellContent.substr(0, this.GUIOptions.maxCellLength) + "\u2026");      
    }
    else if ((typeof cellContent === "object") && (cellContent !== null)) {
      //-----------------------
      // Foreign Key
      //-----------------------
      let content = '';
      const split = (100 * (1 / (Object.keys(cellContent).length - 1))).toFixed(0);
      content += '<table class="w-100 border-0"><tr class="border-0">';
      let cnt = 0;
      Object.keys(cellContent).forEach(c => {
        if (cnt != 0) {
          let val = cellContent[c];
          if ((typeof val === "object") && (val !== null)) {
            if (c === 'state_id') {
              if (val['state_id'])
                content += '<td class="border-0" style="width: '+ split +'%">' + t.renderStateButton(val['state_id'], val['name'], false) + '</td>';
              else 
                content += '<td class="border-0">&nbsp;</td>';
            } else
              content += '<td class="border-0" style="width: '+ split +'%">' + JSON.stringify(val) + '</td>';
          } else {
            //console.log('-', val);
            if (val)
              content += '<td class="border-0" style="width: '+ split +'%">' + escapeHtml(val) + '</td>';
            else
              content += '<td class="border-0">&nbsp;</td>';
          }
        }
        cnt += 1;
      })
      content += '</tr></table>';
      return content;
    }
    // Cell is no String and no Object   
    return escapeHtml(cellContent);
  }
  private renderCell(row: any, col: string) {
    let t = this;
    let value: any = row[col];

    // Return if null
    if (!value) return '&nbsp;';

    // Check data type
    if (t.Columns[col].DATA_TYPE == 'date') {
      //--- DATE
      let tmp = new Date(value)
      if(!isNaN(tmp.getTime()))
        value = tmp.toLocaleDateString('de-DE')
      else
        value = '';
      return value;
    }
    else if(t.Columns[col].DATA_TYPE == 'time') {
      //--- TIME
      if (t.GUIOptions.smallestTimeUnitMins) {
        // Remove seconds from TimeString
        let timeArr = value.split(':');
        timeArr.pop();
        value = timeArr.join(':');
        return value;
      }
    }
    else if (t.Columns[col].DATA_TYPE == 'datetime') {
      //--- DATETIME
      let tmp = new Date(value)
      if(!isNaN(tmp.getTime())) {
        value = tmp.toLocaleString('de-DE')
        // Remove seconds from TimeString
        if (t.GUIOptions.smallestTimeUnitMins) {
          let timeArr = value.split(':');
          timeArr.pop();
          value = timeArr.join(':');
        }
      } else
        value = '';
      return value;
    }
    else if (t.Columns[col].DATA_TYPE == 'tinyint') {
      //--- BOOLEAN
      return parseInt(value) !== 0 ? '<i class="fa fa-check text-center"></i>&nbsp;' : '';
    }
    else if (col == 'state_id' && t.tablename != 'state') {
      //--- STATE
      return t.renderStateButton(value['state_id'], value['name'], true);
    }
    else if (
      (t.tablename == 'state' && col == 'name') || (t.tablename == 'state_rules' && (col == 'state_id_FROM' || col == 'state_id_TO'))
    ) {
      //------------- Render [State] as button
      let stateID = 0;
      let text = '';

      if((typeof value === "object") && (value !== null)) {
        stateID = parseInt(value['state_id']);
        text = value['name'];
      } else {
        // Table: state -> then the state is a string
        stateID = parseInt(row['state_id']);
        text = value;
      }
      return t.renderStateButton(stateID, text);
    }
    else {
      //--- OTHER
      let isHTML = t.Columns[col].is_virtual;
      value = t.formatCell(value, isHTML);
      return value;
    }
  }
  
  private async htmlHeaders(colnames) {
    let t = this;
    let th = '';

    // Pre fill with 1 because of selector
    if (t.GUIOptions.showControlColumn)
      th = '<th class="border-0" scope="col"></th>';
    // Loop Columns
    for (const colname of colnames) {
      if (t.Columns[colname].is_in_menu) {
        //--- Alias (+Sorting)
        const ordercol = t.OrderBy.replace('a.', '');
        th += '<th scope="col" data-colname="'+colname+'" class="border-0 p-0 align-middle datatbl_header'+(colname == ordercol ? ' sorted' : '')+'">'+
        // Title
        '<div class="float-left pl-1 pb-1">' + t.Columns[colname].column_alias + '</div>' +
        // Sorting
        '<div class="float-right pr-3">' + (colname == ordercol ? '&nbsp;' + (
          t.AscDesc == SortOrder.ASC ? '<i class="fa fa-sort-asc">' : (t.AscDesc == SortOrder.DESC ? '<i class="fa fa-sort-desc">' : '')
        ) + '' : '') + '</div>';

        //---- Foreign Key Column
        if (t.Columns[colname].foreignKey.table != '') {
          let cols = {};
          try {
            cols = JSON.parse(t.Columns[colname].foreignKey.col_subst);
          } catch (error) {
            cols[t.Columns[colname].foreignKey.col_subst] = 1; // only one FK => TODO: No subheader
          }
          //-------------------
          const colsnames = Object.keys(cols);
          if (colsnames.length > 1) {

            // Get the config from the remote table
            let getSubHeaders = new Promise((resolve, reject) => {
              let subheaders = '';
              let tmpTable = new Table(t.Columns[colname].foreignKey.table, '', 0, function(){
                const split = (100 * (1 / colsnames.length)).toFixed(0);
                for (const c of colsnames) {
                  const tmpAlias = tmpTable.Columns[c].column_alias;
                  subheaders += '<td class="border-0 align-middle" style="width: '+ split +'%">' + tmpAlias + '</td>';
                };
                resolve(subheaders);
              });
            });

            const res = await getSubHeaders;
            //console.log(res);
            th += `<table class="w-100 border-0"><tr>${res}</tr></table>`;
          }
          //-------------------
        }

        // Clearfix
        th += '<div class="clearfix"></div>';
        th += '</th>';
      }
    }
    return th;
  }


  public async renderHTML() {
    let t = this

    // Order Headers by col_order
    function compare(a, b) {
      a = parseInt(t.Columns[a].col_order);
      b = parseInt(t.Columns[b].col_order);
      return a < b ? -1 : (a > b ? 1 : 0);
    }
    let sortedColumnNames = Object.keys(t.Columns).sort(compare);


    let p1 = new Promise((resolve, reject) => {
      resolve(t.htmlHeaders(sortedColumnNames));
    });
    let ths = await p1;

    
    // Pagination
    let pgntn = ''
    let PaginationButtons = t.getPaginationButtons()
    // Only Display Buttons, when more than one Button exists
    if (PaginationButtons.length > 1)
      PaginationButtons.forEach(
        btnIndex => {
          if (t.PageIndex == t.PageIndex + btnIndex) {
            // Active
            pgntn += `<li class="page-item active">
              <span class="page-link">${t.PageIndex + 1 + btnIndex}</span></li>`;
          } else {
            pgntn += `<li class="page-item">
              <a class="page-link" data-pageindex="${t.PageIndex + btnIndex}">${t.PageIndex + 1 + btnIndex}</a></li>`;
          }
        })
    else
      pgntn += '';
  

    // ---- Header
    let header: string = '<div class="element shadow-sm">'; // TODO: improve html -> remove divs
    let footer: string = '';
    let GUID: string = GUI.ID();

    // Filter
    if (t.GUIOptions.showFilter) {
      // Pre-Selected Row
      if (t.selectedIDs.length > 0) {
        if (t.selectedIDs[0] != null)
          t.FilterText = '' + t.selectedIDs[0];
        else
          t.FilterText = '';
      } else {
        // Filter was set
        t.FilterText = t.Filter;
      }
      //header += '<div class="col-12">'
      header += '<div class="input-group">'
      header += '  <input type="text"class="form-control filterText text-muted bg-light" '+
        (t.FilterText != '' ? 'value="'+t.FilterText+'"' : '') +
        ' placeholder="'+t.GUIOptions.filterPlaceholderText+'">'
      header += '  <div class="input-group-append">';      
      if (!t.ReadOnly) {
        const TableAlias = t.TableConfig.table_alias;
        // Create Button
        header += '<button class="btn btn-success btnCreateEntry">';
        header += '<i class="fa fa-plus"></i>'; 
        if (t.TableType == 'obj')
          header += '&nbsp;'+t.GUIOptions.modalButtonTextCreate + ' ' + TableAlias;
        header += '</button>';
      }
      if (t.SM && t.GUIOptions.showWorkflowButton && t.selType == SelectType.NoSelect) {
        // Workflow Button
        header += '    <button class="btn btn-secondary text-muted bg-light border-left-0 btnShowWorkflow"><i class="fa fa-random"></i>&nbsp; Workflow</button>'
      }
      if (t.selType == SelectType.Single) {
        header += '    <button class="btn btn-secondary text-muted bg-light resetSelection" type="button"><i class="fa fa-times"></i></button>';
        header += '    <button class="btn btn-secondary text-muted bg-light border-left-0" type="button" data-toggle="collapse" data-target=".'+GUID+'"><i class="fa fa-angle-down"></i></button>';
      }
      header += '  </div>'
      header += '</div>'
 //     header += '</div>'
    }
    header += '</div>'; //</div>';

    //------ Table Header
    
    if (t.Rows.length > 0) {
      header += '<div class="card-body '+GUID+' p-0'+(t.selType == SelectType.Single ? ' collapse' : '')+'">';
      header += '<div class="tablewrapper border border-top-0"><table class="table table-striped table-hover m-0 table-sm datatbl">';
      header += '<thead><tr>'+ths+'</tr></thead><tbody>';
      footer = '</tbody></table></div>';
    }
    // TODO:
    if (t.selType == SelectType.NoSelect && t.TableType == 'obj') {
      footer += 
        '<div class="card-footer text-muted p-0 px-2">'+
          '<p class="float-left m-0 mb-1"><small>'+t.getHTMLStatusText()+'</small></p>'+
          '<nav class="float-right"><ul class="pagination pagination-sm m-0 my-1">'+pgntn+'</ul></nav>'+
          '<div class="clearfix"></div>'+
        '</div>';
    } else {
      if (t.Rows.length >= t.PageLimit)
        footer += '<nav class="float-right"><ul class="pagination pagination-sm m-0 my-1">'+pgntn+'</ul></nav>';
    }
    footer += '</div>';

    //============================== data

    let tds: string = '';

    // Loop Rows
    if (!t.Rows) return
    t.Rows.forEach(function(row){
      let data_string: string = '';
      
      // If a Control Column is set then Add one before each row
      if (t.GUIOptions.showControlColumn) {
        data_string = '<td scope="row" class="controllcoulm modRow align-middle border-0" data-rowid="'+row[t.PrimaryColumn]+'">';
        // Entries are selectable?
        if (t.selType == SelectType.Single) {
          data_string += '<i class="fa fa-circle-o"></i>';
        } else if (t.selType == SelectType.Multi) {
          data_string += '<i class="fa fa-square-o"></i>';
        } else {
          // Entries are editable
          if (!t.ReadOnly) data_string += '<i class="fa fa-pencil"></i>';
        }
        data_string += '</td>';
      }

      // Generate HTML for Table-Data Cells sorted
      sortedColumnNames.forEach(function(col) {
        // Check if it is displayed
        if (t.Columns[col].is_in_menu) 
          data_string += '<td class="align-middle p-0 border-0">' + t.renderCell(row, col) + '</td>';
      })

      // Add row to table
      if (t.GUIOptions.showControlColumn) {
        // Edit via first column
        tds += '<tr class="datarow row-'+row[t.PrimaryColumn]+'">'+data_string+'</tr>';
      } else {
        if (t.ReadOnly) {
          // Edit via click
          tds += '<tr class="datarow row-'+row[t.PrimaryColumn]+'" data-rowid="'+row[t.PrimaryColumn]+'">'+data_string+'</tr>';
        } else {
          // Edit via click on full Row
          tds += '<tr class="datarow row-'+row[t.PrimaryColumn]+' editFullRow modRow" data-rowid="'+row[t.PrimaryColumn]+'">'+data_string+'</tr>';
        }
      }

    })

    
    // GUI
    const content = header + tds + footer;
    $(t.jQSelector).empty()
    $(t.jQSelector).append(content);

    
    //---------------- Bind Events

    function filterEvent(t: Table) {
      t.PageIndex = 0; // jump to first page
      t.Filter = $(t.jQSelector + ' .filterText').val();
      t.countRows(function(){
        if (t.getNrOfRows() > 0)
          t.loadRows(function(){ t.renderHTML(); })
        else {
          t.Rows = [];
          t.renderHTML();
        }
      })
    }

    // Filter-Button clicked
    $(t.jQSelector+' .btnFilter').off('click').on('click', function(e){
      e.preventDefault();
      filterEvent(t);
    })
    // hitting Return on searchbar at Filter
    $(t.jQSelector+' .filterText').off('keydown').on('keydown', function(e){
      if (e.keyCode == 13) {
        e.preventDefault();
        filterEvent(t)
      }
    })
    // Show Workflow Button clicked
    $(t.jQSelector+' .btnShowWorkflow').off('click').on('click', function(e){
      e.preventDefault();
      t.SM.openSEPopup();
    })
    // Reset Selection Button clicked
    $(t.jQSelector+' .resetSelection').off('click').on('click', function(e){
      e.preventDefault();
      //console.log('Reset selection', t);
      t.modifyRow(null);
    })
    // Show Workflow Button clicked
    $(t.jQSelector+' .btnCreateEntry').off('click').on('click', function(e){
      e.preventDefault();
      t.createEntry()
    })
    // Edit Row
    $(t.jQSelector+' .modRow').off('click').on('click', function(e){
      e.preventDefault();
      let RowID = $(this).data('rowid');
      t.modifyRow(RowID);
    })
    // PopUp Menu
    $(t.jQSelector+' .showNextStates').off('show.bs.dropdown').on('show.bs.dropdown', function(e){
      let jQRow = $(this).parent().parent();
      let RowID = jQRow.find('td:first').data('rowid');
      let PrimaryColumn: string = t.PrimaryColumn;
      let data = {}
      data[PrimaryColumn] = RowID
      t.getNextStates(data, function(re){
        if (re.length > 0) {
          jQRow.find('.dropdown-menu').html('<p class="m-0 p-3 text-muted"><i class="fa fa-times"></i> No transition possible</p>');
          let nextstates = JSON.parse(re);
          // Any Target States?
          if (nextstates.length > 0) {
            jQRow.find('.dropdown-menu').empty();
            let btns = '';
            nextstates.map(state => {
              btns += '<a class="dropdown-item btnState btnStateChange state' + state.id + '" data-rowid="'+RowID+'" data-targetstate="'+state.id+'">' + state.name + '</a>';
            });
            jQRow.find('.dropdown-menu').html(btns);
            // Bind function to StateButtons
            $('.btnState').click(function(e){
              e.preventDefault();
              let RowID = $(this).data('rowid')
              let TargetStateID = $(this).data('targetstate')
              t.setState('', RowID, TargetStateID)
            })
          }
        }
      })
    })    
    // Table-Header - Sort
    $(t.jQSelector+' .datatbl_header').off('click').on('click', function(e){
      e.preventDefault();
      let colname = $(this).data('colname');
      t.toggleSort(colname)
    })
    // Pagination Button
    $(t.jQSelector+' a.page-link').off('click').on('click', function(e){
      e.preventDefault();
      let newPageIndex = $(this).data('pageindex');
      t.setPageIndex(newPageIndex)
    })

    //-------------------------------

    // Autofocus Filter
    if (t.Filter.length > 0) {
      let el = $(t.jQSelector+' .filterText');
      el.focus();
      el.val('').val(t.Filter);
    }

    // Mark last modified Row
    if (t.lastModifiedRowID) {
      if (t.lastModifiedRowID != 0) {
        t.addClassToDataRow(t.lastModifiedRowID, 'table-info')
        t.lastModifiedRowID = 0;
      }
    }

    // Mark Elements which are in Array of SelectedIDs
    if (t.selectedIDs) {
      if (t.selectedIDs.length > 0) {
        t.selectedIDs.forEach(selRowID => {
          if (t.GUIOptions.showControlColumn) {
            if (t.selType == SelectType.Single)
              $(t.jQSelector + ' .row-' + selRowID+ ' td:first').html('<i class="fa fa-dot-circle-o"></i>');
            else
              $(t.jQSelector + ' .row-' + selRowID+ ' td:first').html('<i class="fa fa-check-square-o"></i>');
          }
          $(t.jQSelector + ' .row-' + selRowID).addClass('table-success');
        });
      }
    }
  }
  //-------------------------------------------------- EVENTS
  public get SelectionHasChanged() {
    return this.onSelectionChanged.expose();
  }
  public get EntriesHaveChanged() {
    return this.onEntriesModified.expose();
  }
}

//-------------------------------------------
// Bootstrap-Helper-Method: Overlay of many Modal windows (newest on top)
$(document).on('show.bs.modal', '.modal', function () {
  //-- Stack modals correctly
  let zIndex = 2040 + (10 * $('.modal:visible').length);
  $(this).css('z-index', zIndex);
  setTimeout(function() {
    $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
  }, 0);
});
// Focus first Input in Modal (Input, Textarea, or Select)
$(document).on('shown.bs.modal', function() {
  $('.modal').find('input,textarea,select').filter(':visible:first').trigger('focus');
  // On keydown
  $("input[type=number]").keydown(function (e) {
    // INTEGER
    // comma 190, period 188, and minus 109, . on keypad
    // key == 190 || key == 188 || key == 109 || key == 110 ||
    // Allow: delete, backspace, tab, escape, enter and numeric . (180 = .)
    if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 109, 110, 173, 190, 188]) !== -1 ||
        // Allow: Ctrl+A, Command+A
        (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) || 
        // Allow: home, end, left, right, down, up
        (e.keyCode >= 35 && e.keyCode <= 40)) {
          // let it happen, don't do anything
          return;
    }
    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
      e.preventDefault();
    }
  });
});
// Helper method
$(document).on('hidden.bs.modal', '.modal', function () {
  $('.modal:visible').length && $(document.body).addClass('modal-open');
});