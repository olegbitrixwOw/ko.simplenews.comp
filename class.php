<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var CBitrixComponent $this */
/** @var array $arParams */
/** @var array $arResult */
/** @var string $componentPath */
/** @var string $componentName */
/** @var string $componentTemplate */
/** @global CDatabase $DB */
/** @global CUser $USER */
/** @global CMain $APPLICATION */

/** @global CIntranetToolbar $INTRANET_TOOLBAR */

global $INTRANET_TOOLBAR;
use Bitrix\Main\Context,	
	Bitrix\Main\Application,
	Bitrix\Main\Type\DateTime,
	Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Iblock;


class SimplenewsCompComponent extends CBitrixComponent 
{

    protected $request;

    public function onIncludeComponentLang()
    {
        Loc::loadMessages(__FILE__);
    }

    public function onPrepareComponentParams($arParams) {
        if(!isset($arParams["CACHE_TIME"]))
            $arParams["CACHE_TIME"] = 300;

        $arParams['PAGER_SHOW_ALL'] = "N";
        $arParams["IBLOCK_ID"] = trim($arParams["IBLOCK_ID"]);

        return $arParams;
    }
    
    protected function checkModules() {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Не загружен модуль информационные блоки');
        }

        return true;
    }
    
    /**
     * метод получает список годов (для фильтрации новостей)
     * метод навеное избыточные для этого задания )))
     * @return array
     */
    protected function getYears(){
        $YEARS = array();

        $dbItems = \Bitrix\Iblock\ElementTable::getList(array(
            'select' => array('ID','ACTIVE_FROM'),
            'filter' =>  array("IBLOCK_ID" => $this->arParams["IBLOCK_ID"], "ACTIVE" => "Y"),
        ));

        while ($arItem = $dbItems->fetch()){ 
            $activeFromTS = MakeTimeStamp($arItem['ACTIVE_FROM']);
            $arItem['ACTIVE_FROM'] = strtolower(FormatDate("Y", $activeFromTS));
            if(!in_array($arItem['ACTIVE_FROM'],$YEARS)){
                $YEARS[] = $arItem['ACTIVE_FROM'];
            }
        }
        return $YEARS;
    }

    /**
    * метод является точкой входа в класс компонента
     *	здесь мы делаем запос на выборку данных
     *	получаем список новостей, объект постраничной навигации которые сохраняем в поле arResult 
    */
    public function executeComponent() {
        

        global $APPLICATION;
        global $USER;
        global $INTRANET_TOOLBAR;

        $bUSER_HAVE_ACCESS = true;
		$startResultCache = array(
			false, 
			$bUSER_HAVE_ACCESS, 
			$arNavigation, 
			$arrFilter, 
			$pagerParameters
		);

        try
        {
                $this->checkModules();
                $this->request = Application::getInstance()->getContext()->getRequest(); 

                if($this->startResultCache(false, $startResultCache))
				{

				$this->arResult['ITEMS'] = array();
				$this->arResult['NAV'] = '';
				$this->arResult["NAME"] = '';
				$this->arResult['SELECTED_YEAR'] = '';
				$this->arResult["ID"] = $this->arParams["IBLOCK_ID"];

				// $this->arResult['YEARS'] = array('2010','2022');
               	// список вкладок по годам
	            $this->arResult['YEARS'] = $this->getYears(); // не знаю нужно ли было делать такой метод

				// pr($arParams);
				// pr($arParams["IBLOCK_ID"]);

				$offset = 0;
				$limit =  !empty($this->arParams["NEWS_COUNT"])?$this->arParams["NEWS_COUNT"]:20; 
				$pageNumber = 1;
				$year = "";
				$titleYears = 'все годы'; // тект для заголовка страницы

				// FILTER
				$arFilter = array(
				    "IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
				    "ACTIVE" => "Y",
				);

				// SELECT
				$arSelect = array("ID", "NAME", "PREVIEW_TEXT", "ACTIVE_FROM","PREVIEW_PICTURE");


				if(isset($this->request["PAGEN_1"])){
					$pageNumber = (int) $this->request->getQuery("PAGEN_1");
					$offset = $pageNumber - 1;
				}

				if(isset($this->request["year"])){
					$year = $this->request->getQuery("year");	
					
					if($year){
					    $firstMonth = ConvertTimeStamp(strtotime('01.01.'.$year),"FULL"); // начало года
					    $lastMonth = ConvertTimeStamp(strtotime('31.12.'.$year),"FULL"); // конец года

					    // фильтрация по году публикации
					    $arFilter['LOGIC'] = 'AND';
					    $arFilter['>=ACTIVE_FROM'] = $firstMonth;
					    $arFilter['<=ACTIVE_FROM'] = $lastMonth;

					    $titleYears = $year.' год';
					    $this->arResult["SELECTED_YEAR"] = $year;
					}
				}


				// запрос к API D7 для получения списка элементов инфоблока
				$dbItems = \Bitrix\Iblock\ElementTable::getList(array(
					'select' => $arSelect,
					'filter' =>  $arFilter,
					'order' => array("ACTIVE_FROM" => 'DESC'),
					'offset' => $offset,
					'limit' => $limit,
					'count_total' => true,
				));

				// ELEMENTS LIST
				while ($arItem = $dbItems->fetch()){  
						$arItem['ACTIVE_FROM'] = strtolower(FormatDate("Y.m.d", $arItem['ACTIVE_FROM']));

					    if($arItem["PREVIEW_PICTURE"]){
					        $arItem["PREVIEW_PICTURE"] = CFile::GetPath($arItem["PREVIEW_PICTURE"]);
					    }

				  		$arButtons = CIBlock::GetPanelButtons(
								$this->arParams["IBLOCK_ID"],
								$arItem["ID"],
								0,
								array("SECTION_BUTTONS" => false, "SESSID" => false)
						);

						$arItem["EDIT_LINK"] = $arButtons["edit"]["edit_element"]["ACTION_URL"];
						$arItem["DELETE_LINK"] = $arButtons["edit"]["delete_element"]["ACTION_URL"];
						$id = (int)$arItem["ID"];
						$this->arResult["ITEMS"][$id] = $arItem;
						$this->arResult["ELEMENTS"][] = $id;
				}

				// количество записей в выборке
				$nums = $dbItems->getCount();

				if($limit < $nums){ // PAGINATION
					$nav_ = new \CDBResult();
					$nav_->NavStart($limit);
					$nav_->NavPageCount = round($nums / $limit);
					$nav_->NavPageNomer = $pageNumber;
					$navPage = $nav_->GetPageNavStringEx($navComponentObject, '', '', 'Y');
					$this->arResult['NAV'] = $navPage;
				}

				$uri = new \Bitrix\Main\Web\Uri($this->request->getRequestUri());
				$this->arResult["PATH"] =  $uri->getPath(); // текущая страница


				$this->setResultCacheKeys(array(
					"ID",
					"ELEMENTS",
					"NAV_CACHED_DATA",
					"PATH",
					"YEARS",
					"ITEMS",
					"NAV"
				));

				$this->includeComponentTemplate();

				}

				if($this->arParams["IBLOCK_ID"]) // если есть IBLOCK_ID
				{
					$arTitleOptions = null;

					// pr($this->arParams["INTRANET_TOOLBAR"]);

					// добавление элемента в ежиме редакирования
					if($USER->IsAuthorized())
					{
							if(
								$APPLICATION->GetShowIncludeAreas()
								|| (is_object($GLOBALS["INTRANET_TOOLBAR"]))
								
							)
							{
									$arButtons = CIBlock::GetPanelButtons(
										$this->arParams["IBLOCK_ID"],
										0,
										$this->arParams["PARENT_SECTION"],
										array("SECTION_BUTTONS"=>false)
									);
									// // если кнопка "Показать включаемые области" нажата
									if($APPLICATION->GetShowIncludeAreas())
										$this->addIncludeAreaIcons(CIBlock::GetComponentMenu($APPLICATION->GetPublicShowMode(), $arButtons));

									if(
										is_array($arButtons["intranet"])
										&& is_object($INTRANET_TOOLBAR)
										&& $this->arParams["INTRANET_TOOLBAR"]!=="N"
									)
									{
										$APPLICATION->AddHeadScript('/bitrix/js/main/utils.js');
										foreach($arButtons["intranet"] as $arButton)
											$INTRANET_TOOLBAR->AddButton($arButton);
									}
							}
					}

					// SET TiTLE
					$this->arResult["NAME"] = 'Список новостей ('.$nums.' шт.) выбранных за '.$titleYears;;
					$APPLICATION->SetTitle($this->arResult["NAME"]);
					return $this->arResult["ELEMENTS"];
				}

        }
        catch (SystemException $e)
        {
            ShowError($e->getMessage());
        }
    }
} 