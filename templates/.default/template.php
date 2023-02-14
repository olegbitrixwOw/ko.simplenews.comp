<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

// pr($arResult["NAV_STRING"]);
// pr($arResult);
?>


<?if($arResult['YEARS']):?>
    <div class="years">
        <?foreach ($arResult['YEARS'] as $key => $value):?>
                <a href="<?=$arResult["PATH"]?>?year=<?=$value?>" 
                    class="btn <?if($arResult['SELECTED_YEAR'] == $value):?>active<?endif?>"><?=$value?></a>
        <?endforeach?>
        <a href="<?=$arResult["PATH"]?>" class="btn">За все годы</a>
    </div>
<?endif?> 

<?if($arResult['ITEMS']):?>
    <?foreach ($arResult['ITEMS'] as $key => $arItem):?>
    <?
	$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
	$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
	?>
        <div class="post" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
            <span class="news-date-time"><?=$arItem['ACTIVE_FROM']?>&nbsp;&nbsp;</span>
            <!-- <a href="/news/<?=$arItem['ID']?>/"><?=$arItem['NAME']?></a> -->
            <a href="<?echo $arItem["DETAIL_PAGE_URL"]?>"><?echo $arItem["NAME"]?></a>

            <?if($arItem['PREVIEW_PICTURE']):?>
	            	<img src="<?=$arItem['PREVIEW_PICTURE']?>"/> 
            <?endif?>

            <div class="block-text">
            	<?echo $arItem["PREVIEW_TEXT"];?>
            </div>
            
        </div>
    <?endforeach?>
<?endif?>

<?=$arResult["NAV"]?>


