var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : new P(function (resolve) { resolve(result.value); }).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
// Enums
var SortOrder;
(function (SortOrder) {
    SortOrder["ASC"] = "ASC";
    SortOrder["DESC"] = "DESC";
})(SortOrder || (SortOrder = {}));
var SelectType;
(function (SelectType) {
    SelectType[SelectType["NoSelect"] = 0] = "NoSelect";
    SelectType[SelectType["Single"] = 1] = "Single";
})(SelectType || (SelectType = {}));
var TableType;
(function (TableType) {
    TableType["obj"] = "obj";
    TableType["t1_1"] = "1_1";
    TableType["t1_n"] = "1_n";
    TableType["tn_1"] = "n_1";
    TableType["tn_m"] = "n_m";
})(TableType || (TableType = {}));
class LiteEvent {
    constructor() {
        this.handlers = [];
    }
    on(handler) {
        this.handlers.push(handler);
    }
    off(handler) {
        this.handlers = this.handlers.filter(h => h !== handler);
    }
    trigger(data) {
        this.handlers.slice(0).forEach(h => h(data));
    }
    expose() {
        return this;
    }
}
// Generates GUID for jQuery DOM Handling
class GUI {
}
GUI.getID = function () {
    // Math.random should be unique because of its seeding algorithm.
    // Convert it to base 36 (numbers + letters), and grab the first 9 characters
    // after the decimal.
    function chr4() { return Math.random().toString(16).slice(-4); }
    return chr4() + chr4() + chr4() + chr4() + chr4() + chr4() + chr4() + chr4();
    //return Math.random().toString(36).substr(2, 9);
};
//==============================================================
// Class: Database (Communication via API)
//==============================================================
class DB {
    static request(command, params, callback) {
        let me = this;
        let data = { cmd: command };
        // If Params are set, then append them to data object
        if (params)
            data['paramJS'] = params;
        // Request (every Request is processed by this function)
        $.ajax({
            method: "POST",
            url: me.API_URL,
            contentType: 'json',
            data: JSON.stringify(data),
            error: function (xhr, status) {
                // Not Authorized
                if (xhr.status == 401) {
                    document.location.assign('login.php'); // Redirect to Login-Page
                }
                else if (xhr.status == 403) {
                    alert("Sorry! You dont have the rights to do this.");
                }
            }
        }).done(function (response) {
            callback(response);
        });
    }
}
//==============================================================
// Class: Modal (Dynamic Modal Generation and Handling)
//==============================================================
class Modal {
    constructor(heading, content, footer = '', isBig = false) {
        this.options = {
            btnTextClose: 'Close'
        };
        this.DOM_ID = GUI.getID();
        // Set Params
        this.heading = heading;
        this.content = content;
        this.footer = footer;
        this.isBig = isBig;
        // Render and add to DOM-Tree
        let sizeType = '';
        if (this.isBig)
            sizeType = ' modal-xl';
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
            <button type="button" class="btn btn-light" data-dismiss="modal">
              ${this.options.btnTextClose}
            </button>
          </div>
        </div>
      </div>
    </div>`;
        // Add generated HTML to DOM
        $('body').append(html);
        // Remove from DOM on close
        $('#' + this.DOM_ID).on('hidden.bs.modal', function (e) {
            $(this).remove();
        });
    }
    setHeader(html) {
        $('#' + this.DOM_ID + ' .modal-title').html(html);
    }
    setFooter(html) {
        $('#' + this.DOM_ID + ' .customfooter').html(html);
    }
    setContent(html) {
        $('#' + this.DOM_ID + ' .modal-body').html(html);
    }
    show() {
        $("#" + this.DOM_ID).modal();
        $("#" + this.DOM_ID).modal('show');
    }
    close() {
        $("#" + this.DOM_ID).modal('hide');
    }
    getDOMID() {
        return this.DOM_ID;
    }
}
//==============================================================
// Class: StateMachine
//==============================================================
class StateMachine {
    constructor(table) {
        this.myTable = table;
    }
    openSEPopup() {
        let smLinks, smNodes;
        let me = this;
        const tablename = me.myTable.getTablename();
        DB.request('getStates', { table: tablename }, function (r) {
            smNodes = JSON.parse(r);
            DB.request('smGetLinks', { table: tablename }, function (r) {
                smLinks = JSON.parse(r);
                // Finally, when everything was loaded, show Modal
                let M = new Modal('<i class="fa fa-random"></i> Workflow <span class="text-muted ml-3">of ' + me.myTable.getTableIcon() + ' ' + me.myTable.getTableAlias() + '</span>', '<div class="statediagram" style="width: 100%; height: 300px;"></div>', '<button class="btn btn-secondary fitsm"><i class="fa fa-expand"></i> Fit</button>', true);
                let container = document.getElementsByClassName('statediagram')[0];
                const idOffset = 10000;
                for (let i = 0; i < smNodes.length; i++) {
                    smNodes[i].id = parseInt(smNodes[i].id) + idOffset;
                }
                for (let i = 0; i < smLinks.length; i++) {
                    smLinks[i].from = parseInt(smLinks[i].from) + idOffset;
                    smLinks[i].to = parseInt(smLinks[i].to) + idOffset;
                }
                let nodes = smNodes;
                let edges = smLinks;
                for (let i = 0; i < nodes.length; i++) {
                    //--- Add EntryPoint Node and Edge
                    if (nodes[i].entrypoint == 1) {
                        nodes.push({ id: i + 1, color: '#5c5', shape: 'dot', size: 10 });
                        edges.push({ from: i + 1, to: nodes[i].id });
                    }
                    const isExitNode = me.isExitNode(nodes[i].id, smLinks);
                    const cssClass = 'state' + (nodes[i].id - idOffset);
                    const element = $('<div class="' + cssClass + ' delete-this-div"></div>').appendTo('html');
                    const colBG = element.css("background-color");
                    const colFont = element.css("color");
                    $('.delete-this-div').remove(); // Delete the divs
                    if (isExitNode) {
                        // Exit Node
                        nodes[i]['color'] = colBG;
                        nodes[i]['shape'] = 'dot';
                        nodes[i]['size'] = 10;
                        nodes[i]['font'] = { multi: 'html', color: 'black' };
                        nodes[i]['title'] = 'StateID: ' + (nodes[i].id - idOffset);
                    }
                    // every node, except 0 node
                    if (nodes[i].id >= idOffset && !isExitNode) {
                        // Get color
                        nodes[i]['font'] = { multi: 'html', color: colFont };
                        nodes[i]['color'] = colBG;
                        nodes[i]['title'] = 'StateID: ' + (nodes[i].id - idOffset);
                    }
                }
                let data = {
                    nodes: nodes,
                    edges: edges
                };
                let options = {
                    edges: {
                        //smooth: { 'type': 'straightCross', 'forceDirection': 'horizontal'},
                        color: { color: '#888888' },
                        shadow: true,
                        length: 100,
                        arrows: 'to',
                        arrowStrikethrough: true,
                        dashes: false,
                        smooth: {}
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
                        borderWidth: 0,
                        size: 24,
                        /*
                        color: {
                          border: '#fff',
                          background: '#fff'
                        },
                        */
                        font: { color: '#888888', size: 16 },
                        shapeProperties: {
                            useBorderWithImage: false
                        },
                        scaling: { min: 10, max: 30 },
                        fixed: { x: false, y: false }
                    },
                    layout: {
                        improvedLayout: true,
                        hierarchical: {
                            enabled: true,
                            direction: 'LR',
                            nodeSpacing: 200,
                            levelSeparation: 225,
                            blockShifting: false,
                            edgeMinimization: false,
                            parentCentralization: false,
                            sortMethod: 'directed'
                        }
                    },
                    physics: {
                        enabled: false
                    },
                    interaction: {}
                };
                let network = new vis.Network(container, data, options);
                M.show();
                let ID = M.getDOMID();
                $('#' + ID).on('shown.bs.modal', function (e) {
                    network.fit({ scale: 1, offset: { x: 0, y: 0 } });
                });
                $('.fitsm').click(function (e) {
                    e.preventDefault();
                    network.fit({ scale: 1, offset: { x: 0, y: 0 } });
                });
            });
        });
    }
    isExitNode(NodeID, links) {
        let res = true;
        links.forEach(function (e) {
            if (e.from == NodeID && e.from != e.to)
                res = false;
        });
        return res;
    }
}
//==============================================================
// Class: RawTable
//==============================================================
class RawTable {
    constructor(tablename) {
        this.AscDesc = SortOrder.DESC;
        this.PageIndex = 0;
        this.Where = '';
        this.TableType = TableType.obj;
        this.tablename = tablename;
        this.actRowCount = 0;
    }
    getNextStates(data, callback) {
        DB.request('getNextStates', { table: this.tablename, row: data }, function (r) { callback(r); });
    }
    createRow(data, callback) {
        DB.request('create', { table: this.tablename, row: data }, function (r) { callback(r); });
    }
    deleteRow(RowID, callback) {
        let me = this;
        let data = {};
        data[this.PrimaryColumn] = RowID;
        DB.request('delete', { table: this.tablename, row: data }, function (response) {
            me.countRows(function () {
                callback(response);
            });
        });
    }
    updateRow(RowID, new_data, callback) {
        let data = new_data;
        data[this.PrimaryColumn] = RowID;
        DB.request('update', { table: this.tablename, row: new_data }, function (response) {
            callback(response);
        });
    }
    transitRow(RowID, TargetStateID, trans_data = null, callback) {
        let data = { state_id: 0 };
        if (trans_data)
            data = trans_data;
        // PrimaryColID and TargetStateID are the minimum Parameters which have to be set
        // also RowData can be updated in the client -> has also be transfered to server
        data[this.PrimaryColumn] = RowID;
        data.state_id = TargetStateID;
        DB.request('makeTransition', { table: this.tablename, row: data }, function (response) {
            callback(response);
        });
    }
    // Call this function only at [init] and then only on [create] and [delete] and at [filter]
    countRows(callback) {
        let me = this;
        let data = {
            table: this.tablename,
            where: this.Where,
            filter: this.Filter
        };
        DB.request('count', data, function (r) {
            if (r.length > 0) {
                const resp = JSON.parse(r);
                if (resp.length > 0) {
                    me.actRowCount = parseInt(resp[0].cnt);
                    // Callback method
                    callback();
                }
            }
        });
    }
    loadRows(callback) {
        let me = this;
        let data = {
            table: this.tablename,
            limitStart: this.PageIndex * this.PageLimit,
            limitSize: this.PageLimit,
            orderby: this.OrderBy,
            ascdesc: this.AscDesc
        };
        // Append extra
        if (this.Filter)
            data['filter'] = this.Filter;
        if (this.Where)
            data['where'] = this.Where;
        // HTTP Request
        DB.request('read', data, function (r) {
            let response = JSON.parse(r);
            me.Rows = response;
            callback(response);
        });
    }
    getNrOfRows() {
        return this.actRowCount;
    }
    getTablename() {
        return this.tablename;
    }
}
//==============================================================
// Class: Table
//==============================================================
class Table extends RawTable {
    constructor(tablename, SelType = SelectType.NoSelect, callback = function () { }, whereFilter = '', defaultObj = {}) {
        super(tablename); // Call parent constructor
        this.FilterText = ''; // TODO: Remove
        this.SM = null;
        this.isExpanded = true;
        this.defaultValues = {}; // Default Values in Create-Form TODO: Remove
        this.diffFormCreateObject = {};
        this.GUIOptions = {
            maxCellLength: 30,
            showControlColumn: true,
            showWorkflowButton: false,
            smallestTimeUnitMins: true,
            modalHeaderTextCreate: 'Create Entry',
            modalHeaderTextModify: 'Modify Entry',
            modalButtonTextCreate: 'Create',
            modalButtonTextModifySave: 'Save',
            modalButtonTextModifySaveAndClose: 'Save &amp; Close',
            modalButtonTextModifyClose: 'Close',
            filterPlaceholderText: 'Search...',
            statusBarTextNoEntries: 'No Entries',
            statusBarTextEntries: 'Showing Entries {lim_from} - {lim_to} of {count} Entries'
        };
        // Events
        this.onSelectionChanged = new LiteEvent();
        this.onEntriesModified = new LiteEvent(); // Created, Deleted, Updated
        let me = this;
        this.GUID = GUI.getID();
        // Check this values
        this.defaultValues = defaultObj;
        this.selType = SelType;
        this.Where = whereFilter;
        // Inherited
        this.PageIndex = 0;
        this.PageLimit = 10;
        this.selectedRow = undefined;
        this.tablename = tablename;
        this.Filter = '';
        this.OrderBy = '';
        DB.request('init', { table: tablename, where: whereFilter }, function (resp) {
            if (resp.length > 0) {
                resp = JSON.parse(resp);
                // Save Form Data
                me.TableConfig = resp['config'];
                me.actRowCount = resp['count'];
                me.diffFormCreateObject = JSON.parse(resp['formcreate']);
                me.Columns = me.TableConfig.columns;
                me.ReadOnly = me.TableConfig.is_read_only;
                me.TableType = me.TableConfig.table_type;
                // Initialize StateMachine for the Table
                if (me.TableConfig['se_active'])
                    me.SM = new StateMachine(me);
                // check if is read only and no select then hide first column
                if (me.ReadOnly && me.selType == SelectType.NoSelect)
                    me.GUIOptions.showControlColumn = false;
                // Loop all cloumns from this table
                for (const col of Object.keys(me.Columns)) {
                    // Get Primary and SortColumn
                    if (me.Columns[col].show_in_grid && me.OrderBy == '') {
                        // DEFAULT: Sort by first visible Col
                        if (me.Columns[col].foreignKey['table'] != '')
                            me.OrderBy = 'a.' + col;
                        else
                            me.OrderBy = col;
                    }
                    if (me.Columns[col].EXTRA == 'auto_increment')
                        me.PrimaryColumn = col;
                }
                // Initializing finished
                callback();
            }
        });
    }
    getTableIcon() {
        return `<i class="${this.TableConfig.table_icon}"></i>`;
    }
    getTableAlias() {
        return this.TableConfig.table_alias;
    }
    toggleSort(ColumnName) {
        let me = this;
        this.AscDesc = (this.AscDesc == SortOrder.DESC) ? SortOrder.ASC : SortOrder.DESC;
        // Check if column is a foreign key
        if (me.Columns[ColumnName].foreignKey['table'] != '')
            this.OrderBy = 'a.' + ColumnName;
        else
            this.OrderBy = ColumnName;
        // Refresh
        this.loadRows(function () {
            me.renderContent();
        });
    }
    setPageIndex(targetIndex) {
        return __awaiter(this, void 0, void 0, function* () {
            let me = this;
            var newIndex = targetIndex;
            var lastPageIndex = this.getNrOfPages() - 1;
            // Check borders
            if (targetIndex < 0)
                newIndex = 0; // Lower limit
            if (targetIndex > lastPageIndex)
                newIndex = lastPageIndex; // Upper Limit
            // Set new index
            this.PageIndex = newIndex;
            // Refresh
            this.loadRows(function () {
                return __awaiter(this, void 0, void 0, function* () {
                    yield me.renderContent();
                    yield me.renderFooter();
                });
            });
        });
    }
    getNrOfPages() {
        return Math.ceil(this.getNrOfRows() / this.PageLimit);
    }
    getPaginationButtons() {
        const MaxNrOfButtons = 5;
        var NrOfPages = this.getNrOfPages();
        // Pages are less then NrOfBtns => display all
        if (NrOfPages <= MaxNrOfButtons) {
            var pages = new Array(NrOfPages);
            for (var i = 0; i < pages.length; i++)
                pages[i] = i - this.PageIndex;
        }
        else {
            // Pages > NrOfBtns display NrOfBtns
            pages = new Array(MaxNrOfButtons);
            // Display start edge
            if (this.PageIndex < Math.floor(pages.length / 2))
                for (var i = 0; i < pages.length; i++)
                    pages[i] = i - this.PageIndex;
            else if ((this.PageIndex >= Math.floor(pages.length / 2))
                && (this.PageIndex < (NrOfPages - Math.floor(pages.length / 2))))
                for (var i = 0; i < pages.length; i++)
                    pages[i] = -Math.floor(pages.length / 2) + i;
            else if (this.PageIndex >= NrOfPages - Math.floor(pages.length / 2)) {
                for (var i = 0; i < pages.length; i++)
                    pages[i] = NrOfPages - this.PageIndex + i - pages.length;
            }
        }
        return pages;
    }
    getFormModify(data, callback) {
        const me = this;
        DB.request('getFormData', { table: me.tablename, row: data }, function (response) {
            callback(response);
        });
    }
    renderEditForm(RowID, diffObject, nextStates, ExistingModal = undefined) {
        let t = this;
        let TheRow = null;
        // get The Row
        this.Rows.forEach(row => { if (row[t.PrimaryColumn] == RowID)
            TheRow = row; });
        //--- Overwrite and merge the differences from diffObject
        let defaultFormObj = t.getDefaultFormObject();
        let newObj = mergeDeep({}, defaultFormObj, diffObject);
        for (const key of Object.keys(TheRow)) {
            //const value = TheRow[key];
            //newObj[key].value = isObject(value) ? value[Object.keys(value)[0]] : value;
            newObj[key].value = TheRow[key];
        }
        // Generate a Modify-Form
        const newForm = new FormGenerator(t, RowID, newObj);
        const htmlForm = newForm.getHTML();
        // create Modal if not exists
        const TableAlias = 'in <i class="' + this.TableConfig.table_icon + '"></i> ' + this.TableConfig.table_alias;
        const ModalTitle = this.GUIOptions.modalHeaderTextModify + '<span class="text-muted mx-3">(' + RowID + ')</span><span class="text-muted ml-3">' + TableAlias + '</span>';
        let M = ExistingModal || new Modal(ModalTitle, '', '', true);
        M.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose;
        M.setContent(htmlForm);
        newForm.initEditors();
        let btns = '';
        let saveBtn = '';
        let actStateID = TheRow.state_id['state_id']; // ID
        let actStateName = TheRow.state_id['name']; // ID
        let cssClass = ' state' + actStateID;
        // Check States -> generate Footer HTML
        if (nextStates.length > 0) {
            let cnt_states = 0;
            // Header
            btns = `<div class="btn-group dropup ml-0 mr-auto">
        <button type="button" class="btn ${cssClass} text-white dropdown-toggle" data-toggle="dropdown">${actStateName}</button>
      <div class="dropdown-menu p-0">`;
            // Loop States
            nextStates.forEach(function (state) {
                let btn_text = state.name;
                let btn = '';
                // Override the state-name if it is a Loop (Save)
                if (actStateID == state.id) {
                    saveBtn = `<div class="ml-auto mr-0">
<button class="btn btn-primary btnState btnStateSave mr-1" data-rowid="${RowID}" data-targetstate="${state.id}" data-targetname="${state.name}" type="button">
  <i class="fa fa-floppy-o"></i>&nbsp;${t.GUIOptions.modalButtonTextModifySave}</button>
<button class="btn btn-outline-primary btnState btnSaveAndClose" data-rowid="${RowID}" data-targetstate="${state.id}" data-targetname="${state.name}" type="button">
  ${t.GUIOptions.modalButtonTextModifySaveAndClose}
</button>
</div>`;
                }
                else {
                    cnt_states++;
                    btn = '<a class="dropdown-item btnState btnStateChange state' + state.id + '" data-rowid="' + RowID + '" data-targetstate="' + state.id + '" data-targetname="' + state.name + '">' + btn_text + '</a>';
                }
                btns += btn;
            });
            // Footer
            btns += '</div></div>';
            // Save buttons
            if (cnt_states == 0)
                btns = '<button type="button" class="btn ' + cssClass + ' text-white" tabindex="-1" disabled>' + actStateName + '</button>'; // Reset html if only Save button exists      
        }
        else {
            // No Next States
            btns = '<button type="button" class="btn ' + cssClass + ' text-white" tabindex="-1" disabled>' + actStateName + '</button>';
        }
        btns += saveBtn;
        M.setFooter(btns);
        //--------------------- Bind function to StateButtons
        $('#' + M.getDOMID() + ' .btnState').click(function (e) {
            e.preventDefault();
            const RowID = $(this).data('rowid');
            const TargetStateID = $(this).data('targetstate');
            const TargetStateName = $(this).data('targetname');
            const closeModal = $(this).hasClass("btnSaveAndClose");
            // Set new State
            t.setState(newForm.getValues(), RowID, { state_id: TargetStateID, name: TargetStateName }, M, closeModal);
        });
        //newForm.todo_setValues();
        //$('#' + M.getDOMID() + ' .label-state').addClass('state' + actStateID).text(TheRow.state_id[1]);    
        //$('#' + M.getDOMID() + ' .inputFK').data('origintable', t.tablename); // Save origin Table in all FKeys
        //$('#' + M.getDOMID() + ' .modal-body').append('<input type="hidden" name="'+t.PrimaryColumn+'" value="'+RowID+'">'); // Add PrimaryID in stored Data
        //t.writeDataToForm('#' + M.getDOMID(), TheRow); // Load data from row and write to input fields with {key:value}    
        //--- finally show Modal if it is a new one
        if (M)
            M.show();
    }
    saveEntry(SaveModal, data, closeModal = true) {
        let t = this;
        // REQUEST
        t.updateRow(data[t.PrimaryColumn], data, function (r) {
            if (r.length > 0) {
                if (r == "1") {
                    // Success
                    if (closeModal)
                        SaveModal.close();
                    t.lastModifiedRowID = data[t.PrimaryColumn];
                    t.loadRows(function () {
                        t.renderContent();
                        t.onEntriesModified.trigger();
                    });
                }
                else {
                    // Fail
                    const ErrorModal = new Modal('Error', '<b class="text-danger">Element could not be updated!</b><br><pre>' + r + '</pre>');
                    ErrorModal.show();
                }
            }
        });
    }
    setState(data, RowID, targetState, myModal, closeModal) {
        let t = this;
        //let data = {}
        let parsedData = [];
        let actState = undefined;
        // Get Actual State
        for (const row of t.Rows) {
            if (row[t.PrimaryColumn] == RowID)
                actState = row['state_id'];
        }
        // Set a loading icon or indicator when transition is running
        /*
        if (MID != '') {
          // Remove all Error Messages
          $('#'+MID+' .modal-body .alert').remove();
          // Read out all input fields with {key:value}
          //data = t.readDataFromForm('#'+MID);
          $('#'+MID+' .modal-title').prepend(`<span class="loadingtext"><i class="fa fa-spinner fa-pulse"></i></span>`);
          $('#'+MID+' :input').prop("disabled", true);
        }
        */
        // REQUEST
        t.transitRow(RowID, targetState.state_id, data, function (r) {
            // When a response came back
            /*if (MID != '') {
              $('#'+MID+' .loadingtext').remove();
              $('#'+MID+' :input').prop("disabled", false);
            }*/
            // Try to parse result messages
            try {
                parsedData = JSON.parse(r);
            }
            catch (err) {
                let resM = new Modal('<b class="text-danger">Script Error!</b>', r);
                resM.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose;
                resM.show();
                return;
            }
            // Remove all Error Messages from Modal
            if (myModal)
                $('#' + myModal.getDOMID() + ' .modal-body .alert').remove();
            // Handle Transition Feedback
            let counter = 0;
            let messages = [];
            parsedData.forEach(msg => {
                // Show Messages
                if (msg.show_message)
                    messages.push({ type: counter, text: msg.message });
                // Increase Counter for Modals
                counter++;
            });
            // Re-Sort the messages
            messages.reverse(); // like the process => [Out, Transit, In]
            // Check if Transition was successful
            if (counter == 3) {
                // Refresh Form-Data if Modal exists        
                if (myModal) {
                    t.getFormModify(data, function (r) {
                        if (r.length > 0) {
                            const diffObject = JSON.parse(r);
                            // Refresh Modal Buttons
                            t.getNextStates(data, function (re) {
                                if (re.length > 0) {
                                    let nextstates = JSON.parse(re);
                                    // Set Form-Content
                                    t.renderEditForm(RowID, diffObject, nextstates, myModal); // The circle begins again
                                }
                            });
                        }
                    });
                }
                // Mark rows
                if (RowID != 0)
                    t.lastModifiedRowID = RowID;
                // Reload all rows
                t.loadRows(function () {
                    t.renderContent();
                    t.onEntriesModified.trigger();
                    // close Modal if it was save and close
                    if (myModal && closeModal)
                        myModal.close();
                });
            }
            // Show all Script-Result Messages
            for (const msg of messages) {
                const stateFrom = t.renderStateButton(actState.state_id, actState.name);
                const stateTo = t.renderStateButton(targetState.state_id, targetState.name);
                let tmplTitle = '';
                if (msg.type == 0)
                    tmplTitle = `OUT <span class="text-muted ml-2">${stateFrom} &rarr;</span>`;
                if (msg.type == 1)
                    tmplTitle = `Transition <span class="text-muted ml-2">${stateFrom} &rarr; ${stateTo}</span>`;
                if (msg.type == 2)
                    tmplTitle = `IN <span class="text-muted ml-2">&rarr; ${stateTo}</span>`;
                let resM = new Modal(tmplTitle, msg.text);
                resM.options.btnTextClose = t.GUIOptions.modalButtonTextModifyClose;
                resM.show();
            }
        });
    }
    getDefaultFormObject() {
        const me = this;
        let FormObj = {};
        // Generate the Form via Config -> Loop all columns from this table
        for (const colname of Object.keys(me.Columns)) {
            const ColObj = me.Columns[colname];
            FormObj[colname] = ColObj;
            // Add foreign key -> Table
            if (ColObj.field_type == 'foreignkey')
                FormObj[colname]['fk_table'] = ColObj.foreignKey.table;
        }
        return FormObj;
    }
    //-------------------------------------------------- PUBLIC METHODS
    createEntry() {
        let me = this;
        const ModalTitle = this.GUIOptions.modalHeaderTextCreate + '<span class="text-muted ml-3">in ' + this.getTableIcon() + ' ' + this.getTableAlias() + '</span>';
        const CreateBtns = `<div class="ml-auto mr-0">
  <button class="btn btn-success btnCreateEntry andReopen" type="button">
    <i class="fa fa-plus"></i>&nbsp;${this.GUIOptions.modalButtonTextCreate}
  </button>
  <button class="btn btn-outline-success btnCreateEntry ml-1" type="button">
    ${this.GUIOptions.modalButtonTextCreate} &amp; Close
  </button>
</div>`;
        //--- Overwrite and merge the differences from diffObject
        let defFormObj = me.getDefaultFormObject();
        const diffFormCreate = me.diffFormCreateObject;
        let newObj = mergeDeep({}, defFormObj, diffFormCreate);
        // set default values
        for (const key of Object.keys(me.defaultValues)) {
            newObj[key].value = me.defaultValues[key]; // overwrite value
            newObj[key].mode_form = 'ro'; // and also set to read-only
        }
        // In the create form do not use reverse foreign keys
        for (const key of Object.keys(newObj)) {
            if (newObj[key].field_type == 'reversefk')
                newObj[key].mode_form = 'hi';
        }
        // Create a new Create-Form
        const fCreate = new FormGenerator(me, undefined, newObj);
        // Create Modal
        let M = new Modal(ModalTitle, fCreate.getHTML(), CreateBtns, true);
        M.options.btnTextClose = me.GUIOptions.modalButtonTextModifyClose;
        const ModalID = M.getDOMID();
        fCreate.initEditors();
        // Bind Buttonclick
        $('#' + ModalID + ' .btnCreateEntry').click(function (e) {
            e.preventDefault();
            // Read out all input fields with {key:value}
            let data = fCreate.getValues();
            const reOpenModal = $(this).hasClass('andReopen');
            me.createRow(data, function (r) {
                let msgs = [];
                $('#' + ModalID + ' .modal-body .alert').remove(); // Remove all Error Messages
                // Try to parse Result
                try {
                    msgs = JSON.parse(r);
                }
                catch (err) {
                    // Show Error
                    $('#' + ModalID + ' .modal-body').prepend(`<div class="alert alert-danger" role="alert"><b>Script Error!</b>&nbsp;${r}</div>`);
                    return;
                }
                // Handle Transition Feedback
                let counter = 0; // 0 = trans, 1 = in -- but only at Create!
                msgs.forEach(msg => {
                    // Show Message
                    if (msg.show_message) {
                        const stateEntry = msg['_entry-point-state'];
                        const stateTo = me.renderStateButton(stateEntry['id'], stateEntry['name']);
                        let tmplTitle = '';
                        if (counter == 0)
                            tmplTitle = `Transition <span class="text-muted ml-2">Create &rarr; ${stateTo}</span>`;
                        if (counter == 1)
                            tmplTitle = `IN <span class="text-muted ml-2">&rarr; ${stateTo}</span>`;
                        let resM = new Modal(tmplTitle, msg.message);
                        resM.options.btnTextClose = me.GUIOptions.modalButtonTextModifyClose;
                        resM.show();
                    }
                    // Check if Element was created
                    if (msg.element_id) {
                        // Success?
                        if (msg.element_id > 0) {
                            // Reload Data from Table
                            me.lastModifiedRowID = msg.element_id;
                            // load rows and render Table
                            me.countRows(function () {
                                me.loadRows(function () {
                                    me.renderContent();
                                    me.renderFooter();
                                    me.renderHeader();
                                    me.onEntriesModified.trigger();
                                    // Reopen Modal
                                    if (reOpenModal)
                                        me.modifyRow(me.lastModifiedRowID, M);
                                    else
                                        M.close();
                                });
                            });
                        }
                        // ElementID has to be 0! otherwise the transscript aborted
                        if (msg.element_id == 0) {
                            $('#' + ModalID + ' .modal-body').prepend(`<div class="alert alert-danger" role="alert"><b>Database Error!</b>&nbsp;${msg.errormsg}</div>`);
                        }
                    }
                    // Special Case for Relations (reactivate them)
                    if (counter == 0 && !msg.show_message && msg.message == 'RelationActivationCompleteCloseTheModal') {
                        // Reload Data from Table
                        me.lastModifiedRowID = msg.element_id;
                        // load rows and render Table
                        me.countRows(function () {
                            me.loadRows(function () {
                                me.renderContent();
                                me.renderFooter();
                                me.renderHeader();
                                me.onEntriesModified.trigger();
                                M.close();
                            });
                        });
                    }
                    counter++;
                });
            });
        });
        // Show Modal
        M.show();
    }
    modifyRow(id, ExistingModal = undefined) {
        let me = this;
        // Check Selection-Type
        if (this.selType == SelectType.Single) {
            //------------------------------------ SINGLE SELECT
            me.selectedRow = me.Rows[id];
            for (const row of me.Rows) {
                if (row[me.PrimaryColumn] == id) {
                    me.selectedRow = row;
                    break;
                }
            }
            this.isExpanded = false;
            // Render HTML
            this.renderContent();
            this.renderHeader();
            this.renderFooter();
            this.onSelectionChanged.trigger();
            return;
        }
        else {
            //------------------------------------ NO SELECT / EDITABLE / READ-ONLY
            // Exit if it is a ReadOnly Table
            if (this.ReadOnly)
                return;
            // Set Form
            if (this.SM) {
                //-------- EDIT-Modal WITH StateMachine
                let data = {};
                data[me.PrimaryColumn] = id;
                // Get Forms
                me.getFormModify(data, function (r) {
                    if (r.length > 0) {
                        const diffJSON = JSON.parse(r);
                        me.getNextStates(data, function (re) {
                            if (re.length > 0) {
                                let nextstates = JSON.parse(re);
                                me.renderEditForm(id, diffJSON, nextstates, ExistingModal);
                            }
                        });
                    }
                });
            }
            else {
                //-------- EDIT-Modal WITHOUT StateMachine
                const tblTxt = 'in ' + this.getTableIcon() + ' ' + this.getTableAlias();
                const ModalTitle = this.GUIOptions.modalHeaderTextModify + '<span class="text-muted mx-3">(' + id + ')</span><span class="text-muted ml-3">' + tblTxt + '</span>';
                let t = this;
                let TheRow = null;
                // get The Row
                this.Rows.forEach(row => { if (row[t.PrimaryColumn] == id)
                    TheRow = row; });
                //--- Overwrite and merge the differences from diffObject
                let FormObj = mergeDeep({}, t.getDefaultFormObject());
                for (const key of Object.keys(TheRow)) {
                    const value = TheRow[key];
                    FormObj[key].value = isObject(value) ? value[Object.keys(value)[0]] : value;
                }
                let fModify = new FormGenerator(me, id, FormObj);
                let M = ExistingModal || new Modal('', fModify.getHTML(), '', true);
                M.options.btnTextClose = this.GUIOptions.modalButtonTextModifyClose;
                fModify.initEditors();
                // Set Modal Header
                M.setHeader(ModalTitle);
                // Save buttons
                M.setFooter(`<div class="ml-auto mr-0">
          <button class="btn btn-primary btnSave" type="button">
            <i class="fa fa-floppy-o"></i> ${this.GUIOptions.modalButtonTextModifySave}
          </button>
          <button class="btn btn-outline-primary btnSave andClose" type="button">
            ${this.GUIOptions.modalButtonTextModifySaveAndClose}
          </button>
        </div>`);
                // Bind functions to Save Buttons
                $('#' + M.getDOMID() + ' .btnSave').click(function (e) {
                    e.preventDefault();
                    const closeModal = $(this).hasClass('andClose');
                    me.saveEntry(M, fModify.getValues(), closeModal);
                });
                // Add the Primary RowID
                $('#' + M.getDOMID() + ' .modal-body form').append('<input type="hidden" class="rwInput" name="' + this.PrimaryColumn + '" value="' + id + '">');
                // Finally show Modal if none existed
                if (M)
                    M.show();
            }
        }
    }
    getSelectedRowID() {
        return this.selectedRow[this.PrimaryColumn];
    }
    renderStateButton(ID, name, withDropdown = false) {
        const cssClass = 'state' + ID;
        if (withDropdown) {
            // With Dropdown
            return `<div class="dropdown showNextStates">
            <button title="State-ID: ${ID}" class="btn dropdown-toggle btnGridState btn-sm label-state ${cssClass}" data-toggle="dropdown">${name}</button>
            <div class="dropdown-menu p-0">
              <p class="m-0 p-3 text-muted"><i class="fa fa-spinner fa-pulse"></i> Loading...</p>
            </div>
          </div>`;
        }
        else {
            // Without Dropdown
            return `<button title="State-ID: ${ID}" onclick="return false;" class="btn btnGridState btn-sm label-state ${cssClass}">${name}</button>`;
        }
    }
    formatCell(cellContent, isHTML = false) {
        if (isHTML)
            return cellContent;
        let t = this;
        // string -> escaped string
        function escapeHtml(string) {
            let entityMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;' };
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
            const nrOfCells = Object.keys(cellContent).length;
            const split = (nrOfCells == 1 ? 100 : (100 * (1 / (nrOfCells - 1))).toFixed(0));
            let content = '<table class="w-100 border-0"><tr class="border-0">';
            let cnt = 0;
            Object.keys(cellContent).forEach(c => {
                if (nrOfCells > 1 && cnt == 0) {
                    cnt += 1;
                    return;
                }
                let val = cellContent[c];
                if ((typeof val === "object") && (val !== null)) {
                    if (c === 'state_id') {
                        if (val['state_id'])
                            content += '<td class="border-0" style="width: ' + split + '%">' + t.renderStateButton(val['state_id'], val['name'], false) + '</td>';
                        else
                            content += '<td class="border-0">&nbsp;</td>';
                    }
                    else
                        content += '<td class="border-0" style="width: ' + split + '%">' + JSON.stringify(val) + '</td>';
                }
                else {
                    if (val)
                        content += '<td class="border-0" style="width: ' + split + '%">' + escapeHtml(val) + '</td>';
                    else
                        content += '<td class="border-0">&nbsp;</td>';
                }
                cnt += 1;
            });
            content += '</tr></table>';
            return content;
        }
        // Cell is no String and no Object   
        return escapeHtml(cellContent);
    }
    renderCell(row, col) {
        let t = this;
        let value = row[col];
        // Return if null
        if (!value)
            return '&nbsp;';
        // Check data type
        if (t.Columns[col].field_type == 'date') {
            //--- DATE
            let tmp = new Date(value);
            if (!isNaN(tmp.getTime()))
                value = tmp.toLocaleDateString('de-DE');
            else
                value = '';
            return value;
        }
        else if (t.Columns[col].field_type == 'time') {
            //--- TIME
            if (t.GUIOptions.smallestTimeUnitMins) {
                // Remove seconds from TimeString
                let timeArr = value.split(':');
                timeArr.pop();
                value = timeArr.join(':');
                return value;
            }
        }
        else if (t.Columns[col].field_type == 'datetime') {
            //--- DATETIME
            let tmp = new Date(value);
            if (!isNaN(tmp.getTime())) {
                value = tmp.toLocaleString('de-DE');
                // Remove seconds from TimeString
                if (t.GUIOptions.smallestTimeUnitMins) {
                    let timeArr = value.split(':');
                    timeArr.pop();
                    value = timeArr.join(':');
                }
            }
            else
                value = '';
            return value;
        }
        else if (t.Columns[col].field_type == 'switch') {
            //--- BOOLEAN
            return (parseInt(value) !== 0 ?
                '<i class="fa fa-check text-success text-center"></i>&nbsp;' :
                '<i class="fa fa-times text-danger text-center"></i>&nbsp;');
        }
        else if (col == 'state_id' && t.tablename != 'state') {
            //--- STATE
            return t.renderStateButton(value['state_id'], value['name'], !t.ReadOnly);
        }
        else if ((t.tablename == 'state' && col == 'name') || (t.tablename == 'state_rules' && (col == 'state_id_FROM' || col == 'state_id_TO'))) {
            //------------- Render [State] as button
            let stateID = 0;
            let text = '';
            if ((typeof value === "object") && (value !== null)) {
                stateID = parseInt(value['state_id']);
                text = value['name'];
            }
            else {
                // Table: state -> then the state is a string
                stateID = parseInt(row['state_id']);
                text = value;
            }
            return t.renderStateButton(stateID, text);
        }
        //--- OTHER
        const isHTML = t.Columns[col].is_virtual || t.Columns[col].field_type == 'htmleditor';
        value = t.formatCell(value, isHTML);
        return value;
    }
    htmlHeaders(colnames) {
        return __awaiter(this, void 0, void 0, function* () {
            let t = this;
            let th = '';
            // Pre fill with 1 because of selector
            if (t.GUIOptions.showControlColumn)
                th = `<th class="border-0 align-middle text-center text-muted" scope="col">
        ${t.selType == SelectType.Single ?
                    '<i class="fa fa-link"></i>' :
                    (t.TableType == TableType.obj ? '<i class="fa fa-cog"></i>' : '<i class="fa fa-link"></i>')}
      </th>`;
            // Loop Columns
            for (const colname of colnames) {
                if (t.Columns[colname].show_in_grid) {
                    //--- Alias (+Sorting)
                    const ordercol = t.OrderBy.replace('a.', '');
                    th += '<th scope="col" data-colname="' + colname + '" class="border-0 p-0 align-middle datatbl_header' + (colname == ordercol ? ' sorted' : '') + '">' +
                        // Title
                        '<div class="float-left pl-1 pb-1">' + t.Columns[colname].column_alias + '</div>' +
                        // Sorting
                        '<div class="float-right pr-3">' + (colname == ordercol ? '&nbsp;' + (t.AscDesc == SortOrder.ASC ? '<i class="fa fa-sort-asc">' : (t.AscDesc == SortOrder.DESC ? '<i class="fa fa-sort-desc">' : '')) + '' : '') + '</div>';
                    //---- Foreign Key Column
                    if (t.Columns[colname].foreignKey.table != '') {
                        let cols = {};
                        try {
                            cols = JSON.parse(t.Columns[colname].foreignKey.col_subst);
                        }
                        catch (error) {
                            cols[t.Columns[colname].foreignKey.col_subst] = 1; // only one FK => TODO: No subheader
                        }
                        //-------------------
                        const colsnames = Object.keys(cols);
                        if (colsnames.length > 1) {
                            // Get the config from the remote table
                            let getSubHeaders = new Promise((resolve, reject) => {
                                let subheaders = '';
                                let tmpTable = new Table(t.Columns[colname].foreignKey.table, 0, function () {
                                    const split = (100 * (1 / colsnames.length)).toFixed(0);
                                    for (const c of colsnames) {
                                        const tmpAlias = tmpTable.Columns[c].column_alias;
                                        subheaders += '<td class="border-0 align-middle" style="width: ' + split + '%">' + tmpAlias + '</td>';
                                    }
                                    ;
                                    resolve(subheaders);
                                });
                            });
                            const res = yield getSubHeaders;
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
        });
    }
    getHeader() {
        let t = this;
        // TODO: 
        // Pre-Selected Row
        if (t.selectedRow) {
            // Set the selected text -> concat foreign keys
            const vals = recflattenObj(t.selectedRow);
            t.FilterText = '' + vals.join('  |  ');
        }
        else {
            // Filter was set
            t.FilterText = t.Filter;
        }
        const hasEntries = t.Rows && (t.Rows.length > 0);
        let NoText = 'No Objects';
        if (t.TableType != TableType.obj)
            NoText = 'No Relations';
        return `<form class="tbl_header form-inline">
    <div class="form-group m-0 p-0">
      <input type="text" ${(!hasEntries ? 'readonly disabled ' : '')}class="form-control${(!hasEntries ? '-plaintext' : '')} mr-1 filterText"
        ${(t.FilterText != '' ? ' value="' + t.FilterText + '"' : '')}
        placeholder="${(!hasEntries ? NoText : t.GUIOptions.filterPlaceholderText)}">
    </div>
    ${(t.ReadOnly ? '' :
            `<!-- Create Button -->
      <button class="btn btn-success btnCreateEntry mr-1">
        ${t.TableType != TableType.obj ? '<i class="fa fa-link"></i> Add Relation' : `<i class="fa fa-plus"></i> ${t.GUIOptions.modalButtonTextCreate} ${t.getTableAlias()}`}
      </button>`) +
            ((t.SM && t.GUIOptions.showWorkflowButton) ?
                `<!-- Workflow Button -->
      <button class="btn btn-info btnShowWorkflow mr-1">
        <i class="fa fa-random"></i>&nbsp; Workflow
      </button>` : '') +
            (t.selType == SelectType.Single ?
                `<!-- Reset & Expand -->
      <button class="btn btn-secondary btnExpandTable ml-auto mr-0" title="Expand or Collapse Table" type="button">
        ${t.isExpanded ? '<i class="fa fa-chevron-up"></i>' : '<i class="fa fa-chevron-down"></i>'}
      </button>`
                : '')}
    </form>`;
    }
    renderHeader() {
        let t = this;
        const output = t.getHeader();
        $('.' + t.GUID).parent().find('.tbl_header').replaceWith(output);
        //---------------------- Link jquery
        // Edit Row
        function filterEvent(t) {
            return __awaiter(this, void 0, void 0, function* () {
                t.PageIndex = 0; // jump to first page
                t.Filter = $('.' + t.GUID).parent().find('.filterText').val();
                t.loadRows(function () {
                    return __awaiter(this, void 0, void 0, function* () {
                        if (t.Rows.length == t.PageLimit) {
                            t.countRows(function () {
                                return __awaiter(this, void 0, void 0, function* () {
                                    yield t.renderFooter();
                                });
                            });
                        }
                        else {
                            t.actRowCount = t.Rows.length;
                            yield t.renderFooter();
                        }
                        yield t.renderContent();
                    });
                });
            });
        }
        // hitting Return on searchbar at Filter
        $('.' + t.GUID).parent().find('.filterText').off('keydown').on('keydown', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                filterEvent(t);
            }
        });
        // Show Workflow Button clicked
        $('.' + t.GUID).parent().find('.btnShowWorkflow').off('click').on('click', function (e) {
            e.preventDefault();
            t.SM.openSEPopup();
        });
        // Create Button clicked
        $('.' + t.GUID).parent().find('.btnCreateEntry').off('click').on('click', function (e) {
            e.preventDefault();
            t.createEntry();
        });
        // Expand Table
        $('.' + t.GUID).parent().find('.btnExpandTable').off('click').on('click', function (e) {
            e.preventDefault();
            t.isExpanded = !t.isExpanded;
            t.renderContent();
            t.renderHeader();
            t.renderFooter();
        });
    }
    getContent() {
        return __awaiter(this, void 0, void 0, function* () {
            let tds = '';
            let t = this;
            // Order Headers by col_order
            function compare(a, b) {
                a = parseInt(t.Columns[a].col_order);
                b = parseInt(t.Columns[b].col_order);
                return a < b ? -1 : (a > b ? 1 : 0);
            }
            let sortedColumnNames = Object.keys(t.Columns).sort(compare);
            let p1 = new Promise((resolve) => {
                resolve(t.htmlHeaders(sortedColumnNames));
            });
            let ths = yield p1;
            // Loop Rows
            t.Rows.forEach(function (row) {
                const RowID = row[t.PrimaryColumn];
                let data_string = '';
                let isSelected = false;
                // Check if selected
                if (t.selectedRow) {
                    isSelected = (t.selectedRow[t.PrimaryColumn] == RowID);
                }
                // [Control Column] is set then Add one before each row
                if (t.GUIOptions.showControlColumn) {
                    data_string = `<td scope="row" class="controllcoulm modRow align-middle border-0" data-rowid="${row[t.PrimaryColumn]}">
          ${(t.selType == SelectType.Single ? (isSelected ? '<i class="fa fa-dot-circle-o"></i>' : '<i class="fa fa-circle-o"></i>') : '<i class="fa fa-pencil"></i>')}
        </td>`;
                }
                // Generate HTML for Table-Data Cells sorted
                sortedColumnNames.forEach(function (col) {
                    // Check if it is displayed
                    if (t.Columns[col].show_in_grid)
                        data_string += '<td class="align-middle py-0 border-0">' + t.renderCell(row, col) + '</td>';
                });
                // Add row to table
                if (t.GUIOptions.showControlColumn) {
                    // Edit via first column
                    tds += `<tr class="datarow row-${row[t.PrimaryColumn] + (isSelected ? ' table-info' : '')}">${data_string}</tr>`;
                }
                else {
                    if (t.ReadOnly) {
                        // Edit via click
                        tds += '<tr class="datarow row-' + row[t.PrimaryColumn] + '" data-rowid="' + row[t.PrimaryColumn] + '">' + data_string + '</tr>';
                    }
                    else {
                        // Edit via click on full Row
                        tds += '<tr class="datarow row-' + row[t.PrimaryColumn] + ' editFullRow modRow" data-rowid="' + row[t.PrimaryColumn] + '">' + data_string + '</tr>';
                    }
                }
            });
            return `<div class="tbl_content ${t.GUID} mt-1 p-0${((t.selType == SelectType.Single && !t.isExpanded) ? ' collapse' : '')}">
      ${(t.Rows && t.Rows.length > 0) ?
                `<div class="tablewrapper border">
        <table class="table table-striped table-hover m-0 table-sm datatbl">
          <thead>
            <tr>${ths}</tr>
          </thead>
          <tbody>
            ${tds}
          </tbody>
        </table>
      </div>` : (t.Filter != '' ? 'Sorry, nothing found.' : '')}
    </div>`;
        });
    }
    renderContent() {
        return __awaiter(this, void 0, void 0, function* () {
            let t = this;
            const output = yield t.getContent();
            $('.' + t.GUID).replaceWith(output);
            //---------------------- Link jquery
            // Table-Header - Sort
            $('.' + t.GUID + ' .datatbl_header').off('click').on('click', function (e) {
                e.preventDefault();
                let colname = $(this).data('colname');
                t.toggleSort(colname);
            });
            // Edit Row
            $('.' + t.GUID + ' .modRow').off('click').on('click', function (e) {
                e.preventDefault();
                let RowID = $(this).data('rowid');
                t.modifyRow(RowID);
            });
            // State PopUp Menu
            $('.' + t.GUID + ' .showNextStates').off('show.bs.dropdown').on('show.bs.dropdown', function (e) {
                let jQRow = $(this).parent().parent();
                let RowID = jQRow.find('td:first').data('rowid');
                let PrimaryColumn = t.PrimaryColumn;
                let data = {};
                data[PrimaryColumn] = RowID;
                t.getNextStates(data, function (re) {
                    if (re.length > 0) {
                        jQRow.find('.dropdown-menu').html('<p class="m-0 p-3 text-muted"><i class="fa fa-times"></i> No transition possible</p>');
                        let nextstates = JSON.parse(re);
                        // Any Target States?
                        if (nextstates.length > 0) {
                            jQRow.find('.dropdown-menu').empty();
                            let btns = '';
                            nextstates.map(state => {
                                btns += '<a class="dropdown-item btnState btnStateChange state' + state.id +
                                    '" data-rowid="' + RowID + '" data-targetstate="' + state.id + '" data-targetname="' + state.name + '">' + state.name + '</a>';
                            });
                            jQRow.find('.dropdown-menu').html(btns);
                            // Bind function to StateButtons
                            $('.' + t.GUID + ' .btnState').click(function (e) {
                                e.preventDefault();
                                const RowID = $(this).data('rowid');
                                const TargetStateID = $(this).data('targetstate');
                                const TargetStateName = $(this).data('targetname');
                                t.setState('', RowID, { state_id: TargetStateID, name: TargetStateName }, undefined, false);
                            });
                        }
                    }
                });
            });
        });
    }
    getFooter() {
        let t = this;
        if (!t.Rows || t.Rows.length <= 0)
            return '<div class="tbl_footer"></div>';
        // Pagination
        let pgntn = '';
        let PaginationButtons = t.getPaginationButtons();
        // Only Display Buttons if more than one Button exists
        if (PaginationButtons.length > 1) {
            PaginationButtons.forEach(btnIndex => {
                if (t.PageIndex == t.PageIndex + btnIndex) {
                    pgntn += `<li class="page-item active"><span class="page-link">${t.PageIndex + 1 + btnIndex}</span></li>`;
                }
                else {
                    pgntn += `<li class="page-item"><a class="page-link" data-pageindex="${t.PageIndex + btnIndex}">${t.PageIndex + 1 + btnIndex}</a></li>`;
                }
            });
        }
        if (t.selType == SelectType.Single && !t.isExpanded)
            return `<div class="tbl_footer"></div>`;
        //--- StatusText
        let statusText = '';
        if (this.getNrOfRows() > 0 && this.Rows.length > 0) {
            let text = this.GUIOptions.statusBarTextEntries;
            // Replace Texts
            text = text.replace('{lim_from}', '' + ((this.PageIndex * this.PageLimit) + 1));
            text = text.replace('{lim_to}', '' + ((this.PageIndex * this.PageLimit) + this.Rows.length));
            text = text.replace('{count}', '' + this.getNrOfRows());
            statusText = text;
        }
        else {
            // No Entries
            statusText = this.GUIOptions.statusBarTextNoEntries;
        }
        // Normal
        return `<div class="tbl_footer">
      <div class="text-muted p-0 px-2">
        <p class="float-left m-0 mb-1"><small>${statusText}</small></p>
        <nav class="float-right"><ul class="pagination pagination-sm m-0 my-1">${pgntn}</ul></nav>
        <div class="clearfix"></div>
      </div>
    </div>`;
    }
    renderFooter() {
        let t = this;
        const output = t.getFooter();
        $('.' + t.GUID).parent().find('.tbl_footer').replaceWith(output);
        //---------------------- Link jquery
        // Pagination Button
        $('.' + t.GUID).parent().find('a.page-link').off('click').on('click', function (e) {
            e.preventDefault();
            let newPageIndex = $(this).data('pageindex');
            t.setPageIndex(newPageIndex);
        });
    }
    renderHTML(DOMSelector) {
        return __awaiter(this, void 0, void 0, function* () {
            let t = this;
            // GUI
            const content = t.getHeader() + (yield t.getContent()) + t.getFooter();
            $(DOMSelector).empty();
            $(DOMSelector).append(content);
            yield t.renderHeader();
            yield t.renderContent();
            yield t.renderFooter();
        });
    }
    //-------------------------------------------------- EVENTS
    get SelectionHasChanged() {
        return this.onSelectionChanged.expose();
    }
    get EntriesHaveChanged() {
        return this.onEntriesModified.expose();
    }
}
//==============================================================
// Class: FormGenerator (Generates HTML-Bootstrap4 Forms from JSON)
//==============================================================
class FormGenerator {
    constructor(originTable, originRowID, rowData) {
        this.editors = {};
        this.GUID = GUI.getID();
        /*
        // Tests
        const testForm1 = {
          column_name_1: {field_type: 'text', column_alias: '', mode_form: 'ro', value: 'standard-value'},
          column_name_2: {field_type: 'number', column_alias: 'Number of Questions', mode_form: 'rw'},
          column_name_3: {field_type: 'textarea', column_alias: 'Description', mode_form: 'ro'},
          column_name_7: {field_type: 'date', column_alias: 'Date', mode_form: 'rw', value: '2019-01-01'},
          column_name_8: {field_type: 'time', column_alias: 'Time', mode_form: 'ro', value: "13:37"},
          column_name_6: {field_type: 'foreignkey', column_alias: 'Select a ForeignKey', mode_form: 'rw'},
          column_name_4: {field_type: 'switch', column_alias: 'Correct', mode_form: 'rw', value: 1},
          column_name_5: {field_type: 'switch', column_alias: 'Just another Switch', mode_form: 'ro', value: 0},
        };
        const testForm2 = {
          column_n: {field_type: 'foreignkey', column_alias: 'First element', mode_form: 'rw'},
          column_m: {field_type: 'foreignkey', column_alias: 'Second element', mode_form: 'rw'},
        };
        */
        //if (!Input) Input = testForm1;
        // Save data internally
        this.oTable = originTable;
        this.oRowID = originRowID;
        this.data = rowData;
        //console.log('--- New Form was generated!');
    }
    getElement(key, el) {
        let result = '';
        if (el.mode_form == 'hi')
            return '';
        // Label?
        const form_label = el.column_alias ? `<label class="col-sm-2 col-form-label" for="inp_${key}">${el.column_alias}</label>` : '';
        //--- Textarea
        if (el.field_type == 'textarea') {
            result += `<textarea name="${key}" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}" ${el.mode_form == 'ro' ? ' readonly' : ''}>${el.value ? el.value : ''}</textarea>`;
        }
        else if (el.field_type == 'text') {
            result += `<input name="${key}" type="text" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>`;
        }
        else if (el.field_type == 'number') {
            result += `<input name="${key}" type="number" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>`;
        }
        else if (el.field_type == 'time') {
            result += `<input name="${key}" type="time" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>`;
        }
        else if (el.field_type == 'date') {
            result += `<input name="${key}" type="date" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>`;
        }
        else if (el.field_type == 'password') {
            result += `<input name="${key}" type="password" id="inp_${key}" class="form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>`;
        }
        else if (el.field_type == 'datetime') {
            result += `<div class="input-group">
        <input name="${key}" type="date" id="inp_${key}" class="dtm form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value.split(' ')[0] : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>
        <input name="${key}" type="time" id="inp_${key}_time" class="dtm form-control${el.mode_form == 'rw' ? ' rwInput' : ''}"
        value="${el.value ? el.value.split(' ')[1] : ''}"${el.mode_form == 'ro' ? ' readonly' : ''}/>
      </div>`;
        }
        else if (el.field_type == 'foreignkey') {
            // rwInput ====> Special case!
            // Concat value if is object
            let ID = 0;
            const x = el.value;
            if (x) {
                ID = x;
                if (isObject(x)) {
                    ID = x[Object.keys(x)[0]];
                    const vals = recflattenObj(x);
                    el.value = vals.join('  |  ');
                }
            }
            result += `
        <input type="hidden" name="${key}" value="${ID != 0 ? ID : ''}" class="inputFK${el.mode_form != 'hi' ? ' rwInput' : ''}">
        <div class="external-table">
          <div class="input-group" ${el.mode_form == 'rw' ? 'onclick="test(this)"' : ''} data-tablename="${el.fk_table}">
            <input type="text" class="form-control filterText${el.mode_form == 'rw' ? ' bg-white' : ''}" ${el.value ? 'value="' + el.value + '"' : ''} placeholder="Nothing selected" readonly>
            <div class="input-group-append">
              <button class="btn btn-primary btnLinkFK" title="Link Element" type="button"${el.mode_form == 'ro' ? ' disabled' : ''}>
                <i class="fa fa-chain-broken"></i>
              </button>
            </div>
          </div>
        </div>`;
        }
        else if (el.field_type == 'reversefk') {
            const tmpGUID = GUI.getID();
            const ext_tablename = el.revfk_tablename;
            const hideCol = el.revfk_colname;
            const OriginRowID = this.oRowID;
            // Default values
            let defValues = {};
            defValues[hideCol] = OriginRowID;
            result += `<div class="${tmpGUID}"></div>`; // Container for Table
            //--- Create new Table
            let tmp = new Table(ext_tablename, SelectType.NoSelect, function () {
                tmp.Columns[hideCol].show_in_grid = false; // Hide the origin column
                tmp.ReadOnly = (el.mode_form == 'ro');
                tmp.GUIOptions.showControlColumn = !tmp.ReadOnly;
                tmp.loadRows(function () {
                    tmp.renderHTML('.' + tmpGUID);
                });
            }, 'a.' + hideCol + ' = ' + OriginRowID, // Where-filter
            defValues // Default Values
            );
        }
        else if (el.field_type == 'htmleditor') {
            this.editors[key] = el.mode_form; // reserve key
            result += `<div><div class="htmleditor"></div></div>`;
        }
        else if (el.field_type == 'rawhtml') {
            result += el.value;
        }
        else if (el.field_type == 'switch') {
            result = '';
            result += `<div class="custom-control custom-switch mt-2">
      <input name="${key}" type="checkbox" class="custom-control-input${el.mode_form == 'rw' ? ' rwInput' : ''}" id="inp_${key}"${el.mode_form == 'ro' ? ' disabled' : ''}${el.value == 1 ? ' checked' : ''}>
      <label class="custom-control-label" for="inp_${key}">${el.column_alias}</label>
    </div>`;
        }
        // ===> HTML Output
        result =
            `<div class="form-group row">
      ${form_label}
      <div class="col-sm-10 align-middle">
        ${result}
      </div>
    </div>`;
        // Return
        return result;
    }
    getValues() {
        let result = {};
        $('#' + this.GUID + ' .rwInput').each(function () {
            let inp = $(this);
            const key = inp.attr('name');
            const type = inp.attr('type');
            let value = undefined;
            //--- Format different Types
            // Checkbox
            if (type == 'checkbox')
                value = inp.is(':checked') ? 1 : 0;
            else if (type == 'time' && inp.hasClass('dtm')) {
                if (key in result)
                    value = result[key] + ' ' + inp.val(); // append Time to Date
            }
            else
                // Other
                value = inp.val();
            //----
            // Only add to result object if value is valid
            if (!(value == '' && (type == 'number' || type == 'date' || type == 'time' || type == 'datetime')))
                result[key] = value;
        });
        // Editors
        let editors = this.editors;
        for (const key of Object.keys(editors)) {
            const edi = editors[key];
            result[key] = edi.root.innerHTML; //edi.getContents();
        }
        // Output
        return result;
    }
    getHTML() {
        let html = `<form id="${this.GUID}">`;
        const data = this.data;
        const keys = Object.keys(data);
        for (const key of keys) {
            html += this.getElement(key, data[key]);
        }
        return html + '</form>';
    }
    initEditors() {
        // HTML Editor
        let t = this;
        for (const key of Object.keys(t.editors)) {
            if (t.editors[key] == 'ro')
                t.editors[key] = new Quill('.htmleditor', { theme: 'snow', modules: { toolbar: false }, readOnly: true });
            else
                t.editors[key] = new Quill('.htmleditor', { theme: 'snow' });
            t.editors[key].root.innerHTML = t.data[key].value || '';
        }
    }
}
//-------------------------------------------
// Bootstrap-Helper-Method: Overlay of many Modal windows (newest Modal on top)
$(document).on('show.bs.modal', '.modal', function () {
    //-- Stack modals correctly
    let zIndex = 2040 + (10 * $('.modal:visible').length);
    $(this).css('z-index', zIndex);
    setTimeout(function () {
        $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
});
$(document).on('shown.bs.modal', function () {
    // Focus first visible Input in Modal (Input, Textarea, or Select)
    let el = $('.modal').find('input,textarea,select').filter(':visible:first');
    el.trigger('focus');
    const val = el.val();
    el.val('');
    el.val(val);
    // On keydown
    // Restrict input to digits and '.' by using a regular expression filter.
    $("input[type=number]").keydown(function (e) {
        // INTEGER
        // comma 190, period 188, and minus 109, . on keypad
        // key == 190 || key == 188 || key == 109 || key == 110 ||
        // Allow: delete, backspace, tab, escape, enter and numeric . (180 = .)
        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 109, 110, 173, 190, 188]) !== -1 ||
            // Allow: Ctrl+A, Command+A
            (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
            (e.keyCode === 67 && e.ctrlKey === true) || // Ctrl + C
            (e.keyCode === 86 && e.ctrlKey === true) || // Ctrl + V (!)
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
$(document).on('hidden.bs.modal', '.modal', function () {
    $('.modal:visible').length && $(document.body).addClass('modal-open');
});
// Show the actual Tab in the URL and also open Tab by URL
$(function () {
    let hash = window.location.hash;
    hash && $('ul.nav a[href="' + hash + '"]').tab('show');
    $('.nav-tabs a').click(function (e) {
        $(this).tab('show');
        const scrollmem = $('body').scrollTop() || $('html').scrollTop();
        window.location.hash = this.hash;
        $('html,body').scrollTop(scrollmem);
    });
});
//-------------------------------------------
//--- Special Object merge functions
function isObject(item) {
    return (item && typeof item === 'object' && !Array.isArray(item));
}
function mergeDeep(target, ...sources) {
    if (!sources.length)
        return target;
    const source = sources.shift();
    if (isObject(target) && isObject(source)) {
        for (const key in source) {
            if (isObject(source[key])) {
                if (!target[key]) {
                    Object.assign(target, { [key]: {} });
                }
                else {
                    target[key] = Object.assign({}, target[key]);
                }
                mergeDeep(target[key], source[key]);
            }
            else {
                Object.assign(target, { [key]: source[key] });
            }
        }
    }
    return mergeDeep(target, ...sources);
}
function recflattenObj(x) {
    if (isObject(x)) {
        let res = Object.keys(x).map(e => { return isObject(x[e]) ? recflattenObj(x[e]) : x[e]; });
        return res;
    }
}
//--- Expand foreign key
function test(x) {
    let me = $(x);
    const randID = GUI.getID();
    const FKTable = me.data('tablename');
    let fkInput = me.parent().parent().parent().find('.inputFK');
    fkInput.val(''); // Reset Selection
    me.parent().parent().parent().find('.external-table').replaceWith('<div class="' + randID + '"></div>');
    let tmpTable = new Table(FKTable, 1, function () {
        tmpTable.loadRows(function () {
            return __awaiter(this, void 0, void 0, function* () {
                yield tmpTable.renderHTML('.' + randID);
                $('.' + randID).find('.filterText').focus();
            });
        });
    });
    tmpTable.SelectionHasChanged.on(function () {
        const selRowID = tmpTable.getSelectedRowID();
        if (selRowID)
            fkInput.val(selRowID);
        else
            fkInput.val("");
    });
}
