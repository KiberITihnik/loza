<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Context;

use Bitrix\Main\Type\ParameterDictionary;
use CIBlockElement;
class ComplexLozaComponent extends \CBitrixComponent implements Controllerable
{
    private $iblockCode;
    private $cacheTime = 3600;
    public function onPrepareComponentParams($arParams)
    {
        $this->iblockCode = $arParams["IBLOCK_CODE"];
        return $arParams;
    }

    public function executeComponent()
    {
        if (!$this->includeIblockModule()) {
            return;
        }

        $iblockId = $this->getIblockId($this->arParams["IBLOCK_CODE"]);
        if (!$iblockId) {
            ShowError("Инфоблок с символьным кодом {$this->arParams['IBLOCK_CODE']} не найден");
            return;
        }

        $this->arResult = [];
        $this->arResult['GRID']['ID'] = 'loza_iblock';

        $gridOptions = new Bitrix\Main\Grid\Options($this->arResult['GRID']['ID']);
        $sort = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
        $navParams = $gridOptions->GetNavParams();
        $nav = new Bitrix\Main\UI\PageNavigation($this->arResult['GRID']['ID']);
        $this->arResult['NAV'] = $nav;
        $this->arResult['NAV']->allowAllRecords(false)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        $this->arResult['COLUMNS'] = self::getColumn();

        $cache = Bitrix\Main\Data\Cache::createInstance();
        $cacheId = 'loza_iblock_elements_' . $iblockId . '_' . $this->arResult['NAV']->getCurrentPage();
        $cachePath = '/loza/';

        if ($cache->initCache($this->cacheTime, $cacheId, $cachePath)) {
            $this->arResult['ROW'] = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $this->arResult['ROW'] = $this->getIblockElements($iblockId, $this->iblockCode);
            if (empty($this->arResult['ROW'])) {
                $cache->abortDataCache();
            } else {
                $cache->endDataCache($this->arResult['ROW']);
            }
        }
        $this->arResult['ROW'] = $this->getIblockElements($iblockId, $this->iblockCode);

        $this->includeComponentTemplate();
    }

    public function configureActions(): array
    {
        return [
            'addElement' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'deleteElement' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
        ];
    }

    public function addElementAction()
    {
        $request = Context::getCurrent()->getRequest();
        $formData = $request->getPostList();
        $fileData = $request->getFileList();

        $name = $formData->get('NAME');
        $description = $formData->get('DESCRIPTION');
        $symbolsCode = $formData->get('SYMBOLSСODE');
        $sessid = $formData->get('SESSID');
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/addElementAction.log", '$name'. print_r($name, true), FILE_APPEND);

        //$this->logAction('addElementAction', $formData);

        if (!$this->checkSessid($sessid)) {
            return ['success' => false, 'error' => 'Invalid session ID'];
        }

        //TODO Проверка на 10 символов, единственное сделал проверку и вывод сообщения уже на клиенте, так как зачем отправлять лишний запрос
        if (strlen($description) < 10) {
            return ['success' => false, 'error' => 'Минимальное количество символов в описании должно быть 10'];
        }

        if (!$this->includeIblockModule()) {
            return ['success' => false, 'error' => 'Module not loaded'];
        }
        if (json_decode($symbolsCode) == 'IBLOCKCODELOZOVSKY') {
            return ['success' => false, 'error' => 'Не верный символьный код Инфоблока'];
        }

        $iblockId = $this->getIblockIdByCode($symbolsCode);
        if (!$iblockId) {
            return ['success' => false, 'error' => 'Info block not found'];
        }

        try {
            $elementId = $this->addElement($iblockId, $formData, $fileData);
            file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/addElementAction.log",'Элемент успешно добавлен с ID: ' . print_r($elementId, true), FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/addElementAction.log", 'Ошибка: ' . print_r($e->getMessage(), true), FILE_APPEND);
        }
        $this->clearCache();
        return ['success' => true, 'elementId' => $elementId];
    }

    public function addElement($iblockId, ParameterDictionary $formData, ParameterDictionary $fileData) {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль инфоблоков не загружен');
        }

        $fields = array(
            'IBLOCK_ID' => $iblockId,
            'NAME' => $formData['NAME'],
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => array(
                'DESCRIPTION' => $formData['DESCRIPTION'],
            ),
        );

        if (isset($fileData['IMAGE']) && $fileData['IMAGE']['error'] === 0) {
            $fields['PROPERTY_VALUES']['IMAGE'] = CFile::MakeFileArray($fileData['IMAGE']['tmp_name']);
            $fields['PROPERTY_VALUES']['IMAGE']['name'] = $fileData['IMAGE']['name'];
        }

        $el = new CIBlockElement();
        $elementId = $el->Add($fields);

        if (!$elementId) {
            throw new \Exception('Ошибка добавления элемента: ' . $el->LAST_ERROR);
        }

        return $elementId;
    }

    public function deleteElementAction($elementId, $sessid)
    {
        $this->logAction('deleteElementAction', $elementId);

        if (!$this->checkSessid($sessid)) {
            return ['success' => false, 'error' => 'Invalid session ID'];
        }

        if (!$this->includeIblockModule()) {
            return ['success' => false, 'error' => 'Module not loaded'];
        }

        $deleted = $this->deleteElement($elementId);
        $this->clearCache();
        return ['success' => $deleted, 'elementId' => $elementId];
    }

    /**
     * Получает элементы информационного блока.
     *
     * @param int $iblockId Идентификатор информационного блока.
     * @param string $iblockCode Код информационного блока.
     * @return array Элементы информационного блока.
     */
    private function getIblockElements($iblockId, $iblockCode)
    {
        $totalCount = ElementTable::getCount(['IBLOCK_ID' => $iblockId]);

        $this->arResult['NAV']->setRecordCount($totalCount);
        $startIndex = $this->arResult['NAV']->getOffset();
        $endIndex = $this->arResult['NAV']->getLimit();

        $elements = ElementTable::getList([
            'select' => ['ID'],
            'filter' => ['IBLOCK_ID' => $iblockId],
            'order' => ['ID' => 'DESC'],
            'offset' => $startIndex,
            'limit' => $endIndex,
        ])->fetchAll();

        return array_map(function($element) use ($iblockCode) {
            return $this->mapElementWithProperties($element, $iblockCode);
        }, $elements);
    }

    private function includeIblockModule()
    {
        if (!Loader::includeModule("iblock")) {
            ShowError("Модуль Информационных блоков не установлен");
            return false;
        }
        return true;
    }

    /**
     * Получает идентификатор информационного блока по его коду.
     *
     * @param string $iblockCode Код информационного блока.
     * @return int Идентификатор информационного блока.
     */
    private function getIblockId($iblockCode)
    {
        $iblock = IblockTable::getList([
            'select' => ['ID'],
            'filter' => ['=CODE' => $iblockCode]
        ])->fetch();

        return $iblock['ID'];
    }

    /**
     * Преобразует элемент информационного блока с добавлением свойств.
     *
     * @param array $element Элемент информационного блока.
     * @param string $iblockCode Код информационного блока.
     * @return array Элемент с добавленными свойствами.
     */
    private function mapElementWithProperties($element, $iblockCode)
    {
        $className = '\Bitrix\Iblock\Elements\Element' . $iblockCode . 'Table';
        $additionalProperties = $className::getByPrimary($element['ID'], [
            'select' => ['NAME', 'DESCRIPTION_' => 'DESCRIPTION', 'IMAGE_' => 'IMAGE'],
        ])->fetch();

        if ($additionalProperties) {
            $element = array_merge($element, $additionalProperties);
        }
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/mapElementWithProperties.log", print_r($element, true), FILE_APPEND);

        return $this->addImageUrlToElement($element);
    }

    private function addImageUrlToElement($element)
    {
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/addImageUrlToElement.log", print_r($element, true), FILE_APPEND);

        if (isset($element['IMAGE_IBLOCK_GENERIC_VALUE'])) {
            $imageId = (int)$element['IMAGE_IBLOCK_GENERIC_VALUE'];
            $file = FileTable::getById($imageId)->fetch();
            if ($file) {
                $element['URL'] = '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];
            }
        }
        file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/addImageUrlToElement.log", print_r($element, true), FILE_APPEND);

        return $element;
    }

    private function getIblockIdByCode($iblockCode)
    {
        $res = CIBlock::GetList([], ['CODE' => $iblockCode, 'CHECK_PERMISSIONS' => 'N']);
        if ($ar_res = $res->Fetch()) {
            return $ar_res['ID'];
        }
        return null;
    }

    /**
     * Добавляет элемент в информационный блок.
     *
     * @param int $iblockId Идентификатор информационного блока.
     * @param array $formData Данные элемента для добавления, включая название и описание.
     * @param array $fileData Данные файла, который нужно прикрепить к элементу.
     * @return int|null Идентификатор добавленного элемента или null в случае ошибки.
     * @throws \Exception Если отсутствуют необходимые данные.
     */

    /**
     * Удаляет элемент из информационного блока.
     *
     * @param int $elementId Идентификатор элемента для удаления.
     * @return array Результат удаления.
     */
    private function deleteElement($elementId)
    {
        $element = ElementTable::getList([
            'filter' => ['ID' => $elementId],
            'select' => ['ID']
        ])->fetch();

        if (!$element) {
            echo "Element with ID $elementId does not exist in the infoblock.";
            return false;
        } else {
            //TODO ElementTable::Delete Метод заблокирован.
            if (CIBlockElement::Delete($elementId)) {
                return true;
            } else {
                global $APPLICATION;
                if ($exception = $APPLICATION->GetException()) {
                    echo "Error deleting element: " . $exception->GetString();
                } else {
                    echo "Unknown error deleting element.";
                }
                return false;
            }
        }
    }

    private function clearCache()
    {
        $cachePath = '/loza/';
        $cache = Bitrix\Main\Data\Cache::createInstance();
        $cache->cleanDir($cachePath);
    }

    private function logAction($action, $data)
    {
        $logDir = $_SERVER["DOCUMENT_ROOT"] . "/local/log/loza/";

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        //TODO для лоигрования можно добавить в каждый методом
        file_put_contents($logDir . "{$action}.log", print_r($data, true), FILE_APPEND);
    }

    private static function getColumn()
    {
        $column = [
            ["id" => "ID", "name" => "ID", "sort" => "ID", "default" => true],
            ["id" => "NAME", "name" => "Название", "sort" => "NAME", "default" => true],
            ["id" => "DESCRIPTION", "name" => "Описание", "sort" => "DESCRIPTION", "default" => true],
            ['id' => 'IMAGE', 'name' => 'Изображение', 'sort' => false, 'default' => true, 'type' => 'html']
        ];
        return $column;
    }

    private function checkSessid($sessid)
    {
        return bitrix_sessid() === $sessid;
    }
}
?>
