function JSFolderPathPropExtension(wrapElement, folderPathData) {
    //alert('Connect');
    var $curapp = this;
    $curapp.wrapObj = wrapElement;
    $curapp.data = folderPathData;
    $curapp.ajaxUrl = '/local/ajax/';
    $curapp.MENU_FOLDER_EXIST = {};
    $curapp.MENU_FOLDER_NOT_EXIST = {};
    $curapp.RID_INPUT = {}; //rid input
    $curapp.FILE_WRAP = {};
    $curapp.FILE_LIST = {};
    $curapp.FILE_UPDATE = false; // if need update file
    $curapp.EDIT_RID = {}; // btn change rid
    $curapp.CREATE_FOLDER_BTN = {};
    $curapp.RID_NUM = {}; //tag rid
    $curapp.ERROR = {};
    $curapp.UPLOAD_BTN = {}
    $curapp.UPLOAD_HIDDEN_INPUT = {}

    $curapp.init();
}

JSFolderPathPropExtension.prototype.init = function () {
    let $curapp = this;

    $curapp.MENU_FOLDER_EXIST = BX.findChild($curapp.wrapObj, {tag : 'div', className : 'menu-folder-exist'}, true);
    $curapp.MENU_FOLDER_NOT_EXIST = BX.findChild($curapp.wrapObj, {tag : 'div', className : 'menu-folder-not-exist'}, true);
    $curapp.RID_INPUT = BX.findChild($curapp.wrapObj, {tag : 'input', className : 'rid-input'}, true);
    $curapp.EDIT_RID = BX('edit-rid');
    $curapp.CREATE_FOLDER_BTN = BX('create-folder-path');
    $curapp.FILE_WRAP = BX.findChild($curapp.wrapObj, {tag : 'div', className : 'folder-path-file-wrap'}, true);
    $curapp.FILE_LIST = BX.findChild($curapp.FILE_WRAP, {tag : 'div', className : 'folder-path-file-list'}, true);
    $curapp.RID_NUM = BX.findChildren($curapp.wrapObj, {tag : 'span', className : 'rid-num'}, true);
    $curapp.UPLOAD_BTN = BX.findChildren($curapp.wrapObj, {tag : 'span', className : 'file-path-upload-btn'}, true);
    $curapp.UPLOAD_HIDDEN_INPUT = BX.findChildren($curapp.wrapObj, {tag : 'input', className : 'file-path-upload-input'}, true);

    if ($curapp.data.FOLDER_CONTENTS) {
        $curapp.updateFolderPath();
    }

    //show input rid field on click
    BX.bind($curapp.EDIT_RID, 'click', function () {
        $($curapp.RID_INPUT).toggle("slide");
    })
    //create folder deal on click
    BX.bind($curapp.CREATE_FOLDER_BTN, 'click', function () {
        $curapp._createFolderDeal();
    })
    //console.log('findMenuFolderExist', div);

    //set bind to upload file
    $($curapp.UPLOAD_BTN).on('click', function () {
        //console.log('click to upload btn');
        $($curapp.UPLOAD_HIDDEN_INPUT).trigger('click');
    });

    //set watcher on hidden input type file
    $($curapp.UPLOAD_HIDDEN_INPUT).change(async function () {
        let file = $($curapp.UPLOAD_HIDDEN_INPUT)[0].files[0];
        if (file) {
            //check exists file
            if ($curapp.data.FOLDER_CONTENTS.includes(file.name)) {

                let reload = confirm('Такой файл уже существует, перезаписать его?')
                if (reload) {

                    //del file
                    await $curapp._delFileInFolderPath(file.name);
                    //upload file
                    let res = await $curapp._uploadFile(file);

                    if (res.success) {
                        await $curapp.setResult();
                    }
                }
            } else {
                let res = await $curapp._uploadFile(file);
                //console.log('res $curapp._uploadFile', res);
                if (res.success) {
                    await $curapp.setResult();
                }
            }
        }

    });

}
//hide need feild depending on result
JSFolderPathPropExtension.prototype.updateFolderPath = function () {
    var $curapp = this;

//folder is exist and have rid num
    if ($curapp.data.OBJECT_RID > 0) {

        //set interface
        $($curapp.MENU_FOLDER_NOT_EXIST).addClass('d-none');
        $($curapp.MENU_FOLDER_EXIST).removeClass('d-none');
        $($curapp.RID_INPUT).val($curapp.data.OBJECT_RID).hide();
        $($curapp.RID_NUM).text($curapp.data.OBJECT_RID);
        $($curapp.FILE_WRAP).show();

        //show files list

        if($curapp.data.FOLDER_CONTENTS){
            let list = '<ul class="list-group">';
            $.each($curapp.data.FOLDER_CONTENTS , function (index, value) {
                list += '<li class="list-item">';
                //name file
                list += '<a href="' + $curapp.data.PATH + value + '" target="_blank">';
                list += '<span class="ui-icon ui-icon-file ui-icon-file-empty"><i></i></span> <span class="name-file">' + value + '</span></a>';
                //download icon
                list += '<a href="' + $curapp.data.PATH + value + '" download ><span class="ui-btn  ui-btn-sm ui-btn-light ui-btn-icon-download" title="Скачать"></span></a>';
                //del icon
                list +=' <span class="ui-btn  ui-btn-sm ui-btn-light ui-btn-icon-remove del-file-btn" data-file-name="' + value + '" ></span>';
                list += '</li>';
            });

            list += '</ul>';

            //insert html list in dom
            $($curapp.FILE_LIST).html(list);

            //set bind delete folder btn
            $('.del-file-btn').click(async function () {
                let el = this;
                $(el).attr('disabled');
                await $curapp._delFileInFolderPath($(el).data('file-name'));
                $(el).closest('li').remove();

            });
        }

    } else {
        // object no check
        $($curapp.RID_INPUT).hide();
        $($curapp.FILE_WRAP).hide();
    }
}

JSFolderPathPropExtension.prototype._uploadFile = async function (file) {
    $curapp = this;
    if (window.FormData === undefined) {
        $curapp.showNote('В вашем браузере FormData не поддерживается');
    } else {
        var formData = new FormData();

        //form array for send
        formData.append('file', file);
        formData.append('PATH', $curapp.data.PATH);
        return $curapp._ajaxUploadFile(formData);

    }
}

//create deal folder
JSFolderPathPropExtension.prototype._createFolderDeal = async function () {
    var $curapp = this;
    let res = await $curapp._ajax('createFolderPath');

    if (res) {
        $curapp.data['OBJECT_RID'] = res['UF_IU_RID'];
        $curapp.data['PATH'] += $curapp.data['OBJECT_RID'] + '/' + $curapp.data['DEAL_ID'] + '/';
        //update folder path
        await $curapp.setResult(res)
    }
}
//save some var
JSFolderPathPropExtension.prototype.setResult =  async function () {
    var $curapp = this;

    let res = await $curapp._getFilesInFolderPath();
    if(res){
        $curapp.data.FOLDER_CONTENTS = res;
    }
    $curapp.updateFolderPath();

//tell bitrix that field is changed
    var entityEditor = BX.Crm.EntityEditor.defaultInstance;
    entityEditor._userFieldManager._activeFields['UF_IU_PATH'].markAsChanged();
}

//get files in folder path
JSFolderPathPropExtension.prototype._getFilesInFolderPath = async function () {
    var $curapp = this;
    return new Promise((resolve, reject) => {
        let res =  $curapp._ajax('getFilesFolderPath');
        //save files list in arr
        if (res) {
            resolve(res);
        }else{
            reject('No files');
        }
    });
}

JSFolderPathPropExtension.prototype._delFileInFolderPath = async function (fileName) {
    var $curapp = this;
    $curapp.data.DEL_FILE = fileName;

    return new Promise((resolve, reject) => {
        let res =  $curapp._ajax('delFileFolderPath');
        //save files list in arr
        if (res) {
            resolve(res);
        } else {
            reject('File not found');
        }
    });
}

JSFolderPathPropExtension.prototype._ajaxUploadFile = async function (formData) {
    $curapp = this;
    let url = $curapp.ajaxUrl + 'uploadFileInFolderPath.php';
    return new Promise((resolve, reject) => {
        if (!formData) {
            reject(`No formData given`)
        }
        BX.showWait();

        $.ajax({
            type : "POST", url : url, cache : false, contentType : false, processData : false, data : formData, dataType : 'json', success : function (data) {
                //console.log('data success', data);
                if (data.success === true) {
                    resolve(data);
                } else {
                    reject('No file');
                }
            }
        });
        BX.closeWait();
    });


};

JSFolderPathPropExtension.prototype._ajax = async function (action, addintionalData = false) {
    var $curapp = this;
    let params = [];

    if (addintionalData) {
        params = addintionalData;
    } else {
        params = $curapp.data;
    }

    return new Promise((resolve, reject) => {
        if (!params) {
            reject(`No action given`)
        }
        BX.showWait();
        let dataType = (params.hasOwnProperty('dataType')) ? params.dataType : 'json';
        let processData = (params.hasOwnProperty('processData')) ? params.processData : true;
        let url = $curapp.ajaxUrl + action + '.php';

        BX.ajax({
            url : url, data : params, method : 'POST', dataType : dataType, contentType : false, timeout : 30, async : false, processData : processData, scriptsRunFirst : false, emulateOnload : false, start : true, cache : false, onsuccess : function (data) {
                //console.log('data ajax result', data);
                if (data != null) {
                    if (data.success) {
                        //console.log('ajax success', data.result);
                        resolve(data.result);
                    } else {
                        if (data.error) {
                            $curapp.showNote(data.error)
                            reject(data.error);
                        } else {
                            $curapp.showNote('Не известная ошибка при обмене с' + action)
                            reject('Не известная ошибка при обмене с ' + action);
                        }
                    }
                } else {
                    reject('data is null')
                }
                BX.closeWait();
            }, onfailure : function (errorThrown) {
                $curapp.showNote(errorThrown)
                console.log('onfailure', errorThrown);

                BX.closeWait();
            }
        });

    });
}


/**
 * show popup note
 * @param msg
 */
JSFolderPathPropExtension.prototype.showNote = function (msg = '') {
    //console.log('show Note');
    let msg1 = msg
    if (typeof msg == typeof Array) {
        msg1 = msg.join('<br />', msg)
    }

    let messageBox = new BX.UI.Dialogs.MessageBox({
        message : msg1, modal : true, buttons : BX.UI.Dialogs.MessageBoxButtons.OK, onOk : function () {

            messageBox.close(true);
        }
    });
    messageBox.show();
}
