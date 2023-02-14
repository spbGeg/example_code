<?php


namespace CompanyName\Helper;


use Bitrix\Crm\Timeline\TimelineEntry;
use Bitrix\Crm\Timeline\TimelineType;
use Bitrix\Main\UserTable;
use CompanyName\ORM\Entity\ImportTable;

class HelperImportXml{

	public $importErrors = [];
	public $enumList = []; //array elements fields type enum
	public $enumFields = []; //array fields type enum
	public $companyList = []; //array all company isset in import
	public $contactList = []; //array all company isset in import
	public $companyAssignedList = []; //array all assigned user company isset in import
	public $userList = []; //array all users
	public $multiField = [];
	public $firstStartAddElEnum = false;
	public $PHOTO_DIR;
	public $ownerType;
	public $defaultUserId = 1;


	public function getCompanyByXmlId($xml_id){
		$company = \CCrmCompany::getListEx([], ['=ORIGIN_ID' => $xml_id, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['*'])->Fetch();

		return $company;
	}

	/**
	 * method return bitrix id company for ORIGIN_ID()
	 *
	 * @param $elId
	 * @param $xml_id
	 * @param string $propCode
	 *
	 * @return mixed|null
	 */

	public function searchCompanyBxId($xml_id, $propCode = ''){

		$companyIdBx = null;

		if($xml_id){

			if(in_array($xml_id, $this->companyList)){
				$companyIdBx = $this->companyList[$xml_id];

			}else{
				$company = $this->getCompanyByXmlId($xml_id);

				//save id in list, that not to look in next time
				if($company){

					$this->companyList[$xml_id] = $company['ID'];
					$this->companyAssignedList[$xml_id] = $company['ASSIGNED_BY_ID'];

					$companyIdBx = $company['ID'];
				}else{
					$this->importErrors[] = 'Поле '.$propCode.' тип "Привязка к элементам СРМ" не установлено т.к компании с XML_ID="'.$xml_id.'" не найдено';
				}
			}
		}

		return $companyIdBx;
	}

	/**
	 * @param $xml_id
	 *
	 * @return mixed
	 */
	public function getContactByXmlId($xml_id){
		$contact = \CCrmContact::getListEx([], ['=ORIGIN_ID' => $xml_id, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['*'])->Fetch();

		return $contact;
	}

	/**
	 * @param $xml_id
	 * @param $propCode
	 *
	 * @return mixed|null
	 */
	public function searchContactBxId($xml_id, $propCode = ''){

		$contactIdBx = null;

		if($xml_id){

			if(in_array($xml_id, $this->contactList)){
				$contactIdBx = $this->contactList[$xml_id];

			}else{
				$contact = $this->getContactByXmlId($xml_id);
				//save id in list, that not to look in next time
				if($contact){
					$this->contactList[$xml_id] = $contact['ID'];
					$contactIdBx = $contact['ID'];
				}else{
					$this->importErrors[] = 'Поле '.$propCode.' тип "Привязка к элементам СРМ" не установлено т.к контакта с XML_ID="'.$xml_id.'" не найдено';
				}
			}
		}

		return $contactIdBx;
	}

	/**
	 * @param $elId
	 * @param $xml_id
	 * @param string $propCode
	 *
	 * @return |null
	 */
	public function searchUsertBxId($xml_id, $propCode = ''){

		$contactIdBx = null;

		if($xml_id){
			$xml_id = trim($xml_id);
			if(array_key_exists($xml_id, $this->userList)){
				$contactIdBx = $this->userList[$xml_id]['ID'];
			}else{
				$this->importErrors[] = 'Поле '.$propCode.' тип "Привязка к контакту" не заполнено т.к контакта с XML_ID="'.$xml_id.'" не найдено';

			}
		}

		return $contactIdBx;
	}

	/**
	 * prepare to save value element field has type list
	 *
	 * @param $prop
	 * @param $propCode
	 * @param $id
	 *
	 * @return array
	 */
	protected function prepareEnumProp($prop, $propCode){
		$prop = html_entity_decode($prop);
		$propBx = [];
		if( ! empty($prop)){
			$propAr = explode(', ', $prop);
			foreach($propAr as $propItem){

				if(isset($this->enumList[$propCode.'_VALUE'][$propItem])){
					$this->firstStartAddElEnum = false;

					$propBx[] = $this->enumList[$propCode.'_VALUE'][$propItem]['ID'];

				}else if( ! $this->firstStartAddElEnum){

					//loop protection
					$this->firstStartAddElEnum = true;

					//add new el list in DB
					$res = $this->addElInEnumList($propCode, $propItem);

					if($res){

						$this->importErrors[] = 'Добавлен элемент '.$propItem.' в список поля '.$propCode;

						//update object enum list
						$this->setEnumList();

						//return add el enum in array
						return $this->prepareEnumProp($prop, $propCode);
					}
				}
			}

			return $propBx;
		}

		return null;
	}

	/**
	 * Add value list if it is missing
	 *
	 * @param $propCode
	 * @param $prop
	 */
	protected function addElInEnumList($propCode, $prop){
		$nextEl = $this->getLastEnumElement($propCode);
		$res = $this->obEnum->SetEnumValues($this->enumList[$propCode]['ID'], array(
				"n0" => array(
					"VALUE" => $prop,
					'XML_ID' => $nextEl,
				),
			));

		return $res;

	}

	/**
	 * Find in field has type list at last element to add next element
	 *
	 * @param $propCode
	 *
	 * @return string
	 */
	protected function getLastEnumElement($propCode){
		//получаем все xml_ID списка
		$arXmlIdList = [];
		$arXmlIdList = array_keys($this->enumList[$propCode]);

		//Ищем название последнего элемента и добавляем следующее
		if( ! empty($arXmlIdList)){

			for($i = 1; $i <= count($arXmlIdList); $i++){
				$existKey = '';

				for($j = 0; $j < count($arXmlIdList) + 1; $j++){

					if($arXmlIdList[$j] == 'X'.$i){
						$existKey = true;
						break;
					}
				}
				if( ! $existKey){
					//возвращаем следущий после последнего ключ для добавления в список
					return 'X'.$i;
				}
			}
		}

		return 'X1';
	}


	/**
	 * Prepare date eny format in object date bitrix
	 *
	 * @param $date
	 *
	 * @return \Bitrix\Main\Type\DateTime|string
	 */

	protected function prepareDate($date){
		if($date != ''){
			$dateUnix = strtotime($date);
			$obDate = \Bitrix\Main\Type\DateTime::createFromTimestamp($dateUnix);

			return $obDate;
			//Tools::logFile($obDate, '$do CRMCompany');
		}

		return '';
	}

	/**
	 * Prepare field tel, email, fax for save
	 *
	 * @param $typeId
	 * @param $valueType
	 * @param $value
	 */

	protected function addMultiField($entity, $typeId, $valueType, $value){
		if($value){
			$fieldMulti = [
				'ENTITY_ID' => $entity, //CONTACT
				'ELEMENT_ID' => '',
				'TYPE_ID' => $typeId,// 'PHONE',
				'VALUE_TYPE' => $valueType, //'WORK'
				'VALUE' => $value, //+7911258845
			];
			$this->multiField[] = $fieldMulti;
		}
	}

	/**
	 * Prepare field address
	 *
	 * @param $typeId
	 * @param $valueType
	 * @param $value
	 */

	protected function addAddAddress($entity, $typeId, $valueType, $value){
		//TODO[vadim] not ready must do

		//		if($value){
		//			$fieldMulti = [
		//				'ENTITY_ID' => $entity, //CONTACT
		//				'ELEMENT_ID' => '',
		//				'TYPE_ID' => $typeId,// 'PHONE',
		//				'VALUE_TYPE' => $valueType, //'WORK'
		//				'VALUE' => $value, //+7911258845
		//			];
		//			$this->multiField[] = $fieldMulti;
		//		}
	}


	/**
	 * Has register photo in db bitrix and return id
	 *
	 * @param $namePhoto
	 * @param string $propCode
	 *
	 * @return bool|int|string|null
	 */
	protected function getPhoto($namePhoto, $propCode = ''){
		if($namePhoto){
			$file = \CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'].'/upload'.$this->PHOTO_DIR.'/'.$namePhoto, false, false, ''
			);
			//if file exist register it in db bitrix and get it id
			if($file){
				$fileSave = \CFile::SaveFile($file, $this->PHOTO_DIR, false, false
				);

				return $fileSave;
			}else{
				$this->importErrors[] = 'Файл '.$namePhoto.' не найден в директории /upload'.$this->PHOTO_DIR.', поле '.$propCode.'  не заполнено';
			}
		}

		return null;
	}

	/**
	 * Update Enum el list
	 *
	 * @param $entityName string //'CRM_CONTACT'
	 */
	protected function setEnumList(){
		//clear enum list
		$this->enumList = [];
		$arFieldRes = \CUserTypeEntity::GetList(['ID' => 'ASC'], ['ENTITY_ID' => $this->entityName, 'FIELD_NAME' => array_keys($this->enumFields), 'USER_TYPE_ID' => 'enumeration']);
		while($arField = $arFieldRes->fetch()){
			$this->obEnum = new \CUserFieldEnum();
			$enumRes = $this->obEnum->GetList(array(), array('USER_FIELD_ID' => $arField['ID']));
			while($enumEl = $enumRes->Fetch()){
				$enumList[$arField['FIELD_NAME']][$enumEl['XML_ID']] = $enumEl;
				$enumList[$arField['FIELD_NAME'].'_VALUE'][$enumEl['VALUE']] = $enumEl;
			}
			$enumList[$arField['FIELD_NAME']]['ID'] = $arField['ID'];
			$this->enumList = $enumList;

		}
		//Tools::logFile($this->enumList, '$this->enumList setEnumList');
	}

	/**
	 * get and set in class prop all users
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	protected function setUserList(){
		$userList = UserTable::getList([
				'select' => ['*'],
				'filter' => [
					'!XML_ID' => '',
				],
			])->fetchAll();
		$this->userList = array_combine(array_column($userList, 'XML_ID'), $userList);
	}

	/**
	 * Change stage 3=>4 and save in db errors
	 *
	 * @param $el
	 * @param $stage
	 * @param array $arErrors
	 *
	 * @return mixed
	 */

	protected function updateStageImport($el, $stage, $arErrors = []){

		if($stage == 3 && ! empty($arErrors)){
			//change stage and save errors
			ImportTable::update($el['ID'], ['UF_IU_STATE' => $stage, 'UF_IU_ERRORS' => json_encode($arErrors)]);

			//prepare error
			foreach($arErrors as $key => $error){
				$res['errors'][] = 'Элемент XML_ID="'.$el['ID'].'": '.$error;
			}
		}else{
			ImportTable::update($el['ID'], ['UF_IU_STATE' => $stage]);
		}

		return $res['errors'];
	}

	/**
	 * Update entity multi field
	 *
	 * @param $el array
	 * @param $type string // 'update'
	 * @param $entity string // COMPANY, CONTACT, ...
	 */
	protected function updateMulteFields($el, $type, $entity){
		if( ! empty($this->multiField)){
			if($type == 'update'){
				$this->CCrmFieldMulti->DeleteByElement($entity, $el['ID']);
			}
			foreach($this->multiField as $newFieldMulti){
				$newFieldMulti['ELEMENT_ID'] = $el['ID'];
				$this->CCrmFieldMulti->Add($newFieldMulti);
			}
		}
	}

	/**
	 * Update field in timeline bitrix field
	 *
	 * @param $el
	 * @param $summary
	 * @param $summaryModDate
	 * @param $summaryBy
	 * @param $ownerType
	 *
	 * @return void
	 */
	protected function setTimeLineField($el, $summary, $summaryModDate, $summaryBy, $ownerType){
		if( ! empty($summary)){
			$timelineFields = [
				'TYPE_ID' => TimelineType::COMMENT,
				'TYPE_CATEGORY_ID' => 0,
				'CREATED' => $summaryModDate,
				'AUTHOR_ID' => $summaryBy ?? $this->defaultUserId,
				'ASSOCIATED_ENTITY_TYPE_ID' => 0,
				'ASSOCIATED_ENTITY_CLASS_NAME' => '',
				'ASSOCIATED_ENTITY_ID' => 0,
				'COMMENT' => $summary,
				'SETTINGS' => [
					'HAS_FILES' => 'N',
				],
			];
			$tlItem = \Bitrix\Crm\Timeline\Entity\TimelineTable::getList([
					'filter' => [
						'=TYPE_ID' => TimelineType::COMMENT,
						'=BINDINGS.ENTITY_TYPE_ID' => $ownerType,
						'=BINDINGS.ENTITY_ID' => $el['ID'],
						'=CREATED' => $summaryModDate,
					],
					'order' => [
						'CREATED' => 'desc',
					],
					'limit' => 1,
					'select' => [
						'ID',
					],
				])->fetch();
			if($tlItem){
				unset($timelineFields['TYPE_ID']);
				unset($timelineFields['CREATED']);
				$tlRes = \Bitrix\Crm\Timeline\Entity\TimelineTable::update($tlItem['ID'], $timelineFields);
				if($tlRes->isSuccess()){
					$tlID = $tlRes->getId();
					TimelineEntry::buildSearchContent($tlID);
				}
			}else{
				$tlRes = \Bitrix\Crm\Timeline\Entity\TimelineTable::add($timelineFields);
				if($tlRes->isSuccess()){
					$tlID = $tlRes->getId();
					$bindings = [
						[
							'ENTITY_TYPE_ID' => $ownerType,
							'ENTITY_ID' => $el['ID'],
						],
					];
					TimelineEntry::registerBindings($tlID, $bindings);
					TimelineEntry::buildSearchContent($tlID);
				}else{
					$this->importErrors[] = 'Не создан комментарий в компании XML_ID="'.$el['ID'].'"';
				}
			}
		}
	}

}