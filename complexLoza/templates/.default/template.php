<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

\Bitrix\Main\Loader::includeModule('ui');
\Bitrix\Main\UI\Extension::load("ui.notification");

$APPLICATION->SetTitle("Комплексный компонент управления элементами инфоблока");

$this->addExternalJS($templateFolder . '/assets/script.js');
$this->addExternalCss($templateFolder . '/assets/styles.css');

?>

<form class="ui-form" style="background-color: #f5f5f5; padding: 20px; margin-bottom: 20px" id="form-loza-iblock">
    <div class="ui-form-row-inline" style="display: flex; justify-content: space-around; align-items: flex-start;">
        <div class="ui-form-left-section" style="flex: 1; margin-right: 20px;">
            <div class="ui-form-row ui-form-lg">
                <div class="ui-form-label">
                    <div class="ui-ctl-label-text">Название</div>
                </div>
                <div class="ui-form-content">
                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                        <input type="text" class="ui-ctl-element" id="element-title" placeholder="Название">
                    </div>
                </div>
                <div id="title-error" class="error-message" style="display: none;">Поле обязательно</div>
            </div>
            <div class="ui-form-row ui-form-lg">
                <div class="ui-form-label">
                    <div class="ui-ctl-label-text">Краткое описание</div>
                </div>
                <div class="ui-form-content">
                    <div class="ui-ctl ui-ctl-textarea ui-ctl-no-resize ui-ctl-w100">
                        <textarea class="ui-ctl-element" id="element-description"></textarea>
                    </div>
                </div>
                <div id="description-error" class="error-message" style="display: none;">Минимальное количество символов 10</div>
            </div>
        </div>
        <div class="ui-form-left-section" style="flex: 1; margin-right: 20px;">
            <div class="ui-form-row">
                <div class="ui-form-label">
                    <div class="ui-ctl-label-text">Изображение анонса</div>
                </div>
                <div class="ui-form-content">
                    <label class="ui-ctl ui-ctl-file-btn">
                        <input type="file" class="ui-ctl-element" id="element-image">
                        <div class="ui-ctl-label-text">Добавить фотографию</div>
                    </label>
                </div>
            </div>
        </div>
        <div class="ui-form-right-section" style="display: flex; align-items: center;">
            <div class="ui-form-content" style="margin-top: 24px;">
                <div class="ui-ctl ui-ctl-button">
                    <button type="submit" class="ui-btn ui-btn-primary ui-btn-md" id="add-element-btn">Добавить элемент</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php

file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/template.log", '$arResult'. print_r($arResult, true), FILE_APPEND);

$rows = [];
foreach ($arResult['ROW'] as $item) {
    $imageHtml = $item['URL'] ? '<img src="' . htmlspecialchars($item['URL']) . '" alt="Изображение" style="max-width: 200px; max-height: 200px;">' : 'Изображение не добавлено☹️';

    $rows[] = [
        'data' => [
            'ID' => $item['ID'],
            'NAME' => $item['NAME'],
            'DESCRIPTION' => $item['DESCRIPTION_VALUE'],
            'IMAGE' => $imageHtml
        ],
        "actions" => [
            [
                "type" => "button",
                "text" => "Удалить",
                "onclick" => "if(confirm('Вы действительно хотите удалить этот элемент?')) { elementManager.deleteElement(" . $item["ID"] . "); }"
            ]
        ]
    ];
}

$APPLICATION->IncludeComponent(
    "bitrix:main.ui.grid",
    "",
    [
        'GRID_ID' => $arResult['GRID']['ID'],
        'COLUMNS' => $arResult['COLUMNS'],
        'ROWS' => $rows,
        'FILTER' => $arResult['COLUMNS'],

        'SHOW_ROW_CHECKBOXES' => false,
        'NAV_OBJECT' => $arResult['NAV'],
        'AJAX_MODE' => 'Y',
        'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'PAGE_SIZES' => [
            ['NAME' => "5", 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50'],
            ['NAME' => '100', 'VALUE' => '100']
        ],
        'AJAX_OPTION_JUMP'          => 'N',
        'SHOW_CHECK_ALL_CHECKBOXES' => false,
        'SHOW_ROW_ACTIONS_MENU'     => true,
        'SHOW_GRID_SETTINGS_MENU'   => true,
        'SHOW_NAVIGATION_PANEL'     => true,
        'SHOW_PAGINATION'           => true,
        'SHOW_SELECTED_COUNTER'     => false,
        'SHOW_TOTAL_COUNTER'        => true,
        "TOTAL_ROWS_COUNT"          => $arResult['NAV']->getRecordCount(),
        'SHOW_PAGESIZE'             => true,
        'SHOW_ACTION_PANEL'         => false,
        'ALLOW_COLUMNS_SORT'        => true,
        'ALLOW_COLUMNS_RESIZE'      => true,
        'ALLOW_HORIZONTAL_SCROLL'   => true,
        'ALLOW_SORT'                => true,
        'ALLOW_PIN_HEADER'          => true,
        'AJAX_OPTION_HISTORY'       => 'Y',
    ],
    $this->getComponent()
);
?>