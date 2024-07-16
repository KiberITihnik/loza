class ElementManager {
    constructor(addElementBtnId, titleInputId, descriptionInputId, imageInputId, gridManagerId) {
        this.addElementBtn = BX(addElementBtnId);
        this.titleInput = BX(titleInputId);
        this.descriptionInput = BX(descriptionInputId);
        this.imageInput = BX(imageInputId);
        this.gridManagerId = gridManagerId;

        this.bindEvents();
    }

    bindEvents() {
        BX.bind(this.addElementBtn, 'click', this.handleAddElement.bind(this));
    }

    /*handleAddElement(event) {
        event.preventDefault();
        let title = this.titleInput.value;
        let description = this.descriptionInput.value;
        let imageFile = this.imageInput.files[0];

        if (!this.validateFieldsForm(title, description)) {
            return;
        }

        let formData = {
            'NAME': title,
            'DESCRIPTION': description,
            'IMAGE': imageFile
        };
        console.log(formData);

        //TODO Нормально почему то не собирается и не могу передать и обработать файл new FormData(form)

        BX.ajax.runComponentAction('loza:complexLoza', 'addElement', {
            mode: 'class',
            data: {
                formData: formData,
                symbolsСode: 'IBLOCKCODELOZOVSKY',
                sessid: BX.bitrix_sessid()
            },
        }).then(this.handleAddElementResponse.bind(this))
            .catch(this.handleAjaxError.bind(this));
    }*/

    handleAddElement(event) {
        event.preventDefault();
        let title = this.titleInput.value;
        let description = this.descriptionInput.value;
        let imageFile = this.imageInput.files[0];

        if (!this.validateFieldsForm(title, description)) {
            return;
        }

        let formData = new FormData();
        formData.append('NAME', title);
        formData.append('DESCRIPTION', description);
        formData.append('IMAGE', imageFile);
        formData.append('SYMBOLSСODE', 'IBLOCKCODELOZOVSKY');
        formData.append('SESSID', BX.bitrix_sessid());

        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        BX.ajax.runComponentAction('loza:complexLoza', 'addElement', {
            mode: 'class',
            data: formData,
            method: 'POST',
        }).then(this.handleAddElementResponse.bind(this))
            .catch(this.handleAjaxError.bind(this));
    }

    handleAddElementResponse(response) {
        if (response.data.success) {
            BX.UI.Notification.Center.notify({
                content: `Элемент успешно добавлен с ID = ${response.data.elementId}`,
            });
            BX.Main.gridManager.reload(this.gridManagerId);
        } else {
            BX.UI.Notification.Center.notify({
                content: `Произошла ошибка: ${response.data.error}`,
            });
        }
    }

    handleAjaxError(response) {
        console.error('Error response:', response);
        alert('Произошла ошибка при отправке запроса');
    }

    deleteElement(elementId) {
        console.log(elementId)
        BX.ajax.runComponentAction('loza:complexLoza', 'deleteElement', {
            mode: 'class',
            data: {
                elementId,
                sessid: BX.bitrix_sessid()
            },
        }).then(this.handleDeleteElementResponse.bind(this))
            .catch(this.handleAjaxError.bind(this));
    }

    handleDeleteElementResponse(response) {
        if (response.data.success) {
            BX.UI.Notification.Center.notify({
                content: `Элемент успешно удален с ID = ${response.data.elementId}`,
            });
            BX.Main.gridManager.reload(this.gridManagerId);
        } else {
            alert('Ошибка при удалении элемента');
        }
    }

    validateFieldsForm(title, description) {
        let isValid = true;

        if (!title) {
            BX('title-error').style.display = 'block';
            BX(this.titleInput).closest('.ui-form-content').classList.add('ui-ctl-warning');
            isValid = false;
        } else {
            BX('title-error').style.display = 'none';
            BX(this.titleInput).closest('.ui-form-content').classList.remove('ui-ctl-warning');
        }

        if (description.length < 10) {
            BX('description-error').style.display = 'block';
            BX(this.descriptionInput).closest('.ui-form-content').classList.add('ui-ctl-warning');
            isValid = false;
        } else {
            BX('description-error').style.display = 'none';
            BX(this.descriptionInput).closest('.ui-form-content').classList.remove('ui-ctl-warning');
        }

        return isValid;
    }
}

//для видимости для вызоваe lementManager.deleteElement
let elementManager;

document.getElementById('element-image').addEventListener('change', function(event) {
    const fileInfo = document.getElementById('file-info');
    const file = event.target.files[0];

    if (file) {
        fileInfo.textContent = 'Загружен файл: ' + file.name;
    }
});

BX.ready(function() {
    elementManager = new ElementManager('add-element-btn', 'element-title', 'element-description', 'element-image', 'loza_iblock');
});
