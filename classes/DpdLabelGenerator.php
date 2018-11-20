<?php
/**
 * This file is part of the Prestashop Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2017  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdAuthentication.php');
require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdError.php');


class DpdLabelGenerator
{
	public $dpdClient;
	public $dpdAuth;
	public $errors;
	public $dpdError;
	public $dpdParcelPredict;


	public function __construct()
	{
		$this->dpdClient = new DpdClient();
		$this->dpdAuth = new DpdAuthentication();
		$this->dpdError = new DpdError();
		$this->dpdParcelPredict = new DpdParcelPredict();
	}

	public function generateLabel($orderIds, $parcels, $return){
		if(isset($this->errors['LOGIN_8'])){
			$this->errors['LOGIN_8'] = $this->dpdError->get('LOGIN_8');
		}
		$multipleOrders = count($orderIds) > 1;
		if($parcels > 50){
			$this->errors[] = $this->dpdError->get('TO_MANY_PARCELS');
		}
		if ($multipleOrders && empty($this->errors)) {
			//then its a bulk action
			$zip = new ZipArchive();
			$zipfile = tempnam(sys_get_temp_dir(), "zip");
			$res = $zip->open($zipfile, ZipArchive::CREATE);
		}
		foreach ($orderIds as $orderId) {
			if ( $this->dpdParcelPredict->checkIfDpdSending($orderId)
			) {
				$result = $this->getLabelOutOfDb($orderId, $return);
					if ($result) {
						$pdf = $result[0]['label'];
						$mpsId = $result[0]['mps_id'];
					} else {
						$shipmentInfo = $this->generateShipmentInfo($orderId, $parcels, $return);

						if(empty($this->errors)) {
							try {
								$label = $this->dpdClient->genereateLabel($shipmentInfo, Configuration::get('dpdbenelux_delis_id'), Configuration::get('dpdbenelux_acces_token'));
								$mpsId = $label->shipmentResponses->mpsId;
								$labelNumbers = $label->shipmentResponses->parcelInformation;
								$pdf = $label->parcellabelsPDF;
								foreach ($labelNumbers as $labelNumber)
								{
									// adds the weight to every parcel, because every weight is the same anyway. because the weight is devided by the number of parcels.
									$labelNumber->weight = $shipmentInfo['order']['parcels'][0]['weight'];
								}
								$this->dpdClient->storeOrders($mpsId, $labelNumbers, $orderId, $pdf, $return);
								$tempOrder = new Order($orderId);
								$tempOrder->setWsShippingNumber($mpsId);

								if(!$multipleOrders) {
									$link = Self::generateOrderViewUrl($orderId);
									header('location: ' . $link);
								}
							}catch (Exception $e){
								$this->errors[] = $this->dpdError->get('PRINT_LABEL');
								//TODO create a error log that logs the $e variable
							}
						}
					}
					if(empty($this->errors)) {
						if ($multipleOrders) {
							$fileName = $mpsId . '.pdf';
							if ($res === TRUE) {
								$zip->addFromString($fileName, $pdf);
							}
						} else {
							// then its one so it needs to return a pdf
							header("Content-type:application/pdf");
							header('Content-disposition: inline; filename="dpd-label-' . date("Ymdhis") . '.pdf');
							echo $pdf;
						}
					}

			} else {
				$this->errors[] = $this->dpdError->get('NOT_SHIPPED_BY_DPD', $orderId);
				//TODO create log that the order is not shipped by DPD Predict
			}
		}
		if ($multipleOrders) {
			$zip->close();
            if(empty($this->errors)) {
                header("Content-Type: application/zip");
                header('Content-Disposition: attachment; filename="dpd-label-' . date("Ymdhis") . '.zip"');

                echo file_get_contents($zipfile);
            }
			unlink($zipfile);
		}
	}

	public function generateShipmentInfo($orderId, $parcels, $return)
	{
		$tempOrder = new Order($orderId);
		$orderDetails = OrderDetail::getList($orderId);
		$address = new Address((int)$tempOrder->id_address_delivery);
		if(empty($address->phone)){
			$phone = $address->phone_mobile;
		}else{
			$phone = $address->phone;
		}
		$country = new Country($address->id_country);
		$customer = $tempOrder->getCustomer();
		$product = 'CL';
		$weightTotal = 0;
		foreach($orderDetails as $orderDetail){
			if($orderDetail['product_weight'] == 0){
				$orderDetail['product_weight'] = 5;
			}
			$weightTotal += $orderDetail['product_weight'] * $orderDetail['product_quantity'];
		}
		$weightTotal *= 100;
		$serviceData = array(
			'orderType' => 'consignment'
		);
		if($this->dpdParcelPredict->checkIfPredictCarrier($orderId) || $this->dpdParcelPredict->checkIfSaturdayCarrier($orderId))
		{
			$serviceData['predict'] = array(
				'channel' => 1,
				'value' => $customer->email
			);
		}

		if($this->dpdParcelPredict->checkifParcelCarrier($orderId)){
			$parcelShopID = $this->dpdParcelPredict->getParcelShopId($orderId);
			$serviceData['parcelShopDelivery'] = array(
				'parcelShopId' => $parcelShopID,
				'parcelShopNotification' => array(
					'channel' => 1,
					'value' => $customer->email
				)
			);

		}elseif(($this->dpdParcelPredict->checkIfSaturdayCarrier($orderId) ||  $this->dpdParcelPredict->checkIfClassicSaturdayCarrier($orderId)) && !$return){
			$serviceData['saturdayDelivery'] = true;
		}
		//TODO when plugin create's a shipper check if the order uses the predict sending.
		// Error reporting
		if(empty($orderId)){
			$this->errors[] = $this->dpdError->get('ID_IS_NOT_SET', $orderId);
			//TODO create log that the order_id is not set.
		}
		if($tempOrder->id_address_delivery == NULL) {
			$this->errors[] = $this->dpdError->get('ORDER_ID_DOES_NOT_EXIST', $orderId);
			//TODO create log that the order doesn't exist
		}
		//checks if the order's state is canceled.
		if($tempOrder->current_state == 6){
			$this->errors[] = $this->dpdError->get('CANCELED', $orderId);
			//TODO create log taht the order's state is cancelled
		}
		if($weightTotal / $parcels >= 31.5 * 100){
			$this->errors[] = $this->dpdError->get('WEIGHT_TO_HEAVY');
			//TODO create log that the weight is to big
		}

		if(!($address->address2 == NULL)){
			$street = $address->address1 .' '. $address->address2;
		}else{
			$street = $address->address1;
		}

		if(($address->lastname != NULL) && ($address->firstname != NULL)){
			$fullName = $address->firstname .' '.  $address->lastname;
		}elseif($address->lastname == NULL){
			$fullName = $address->firstname;
		}elseif($address->firstname == NULL){
			$fullName = $address->lastname;
		}

		// if it is express 12
		if($this->dpdParcelPredict->checkIfExpress12Carrier($orderId) && !$return)
		{
			$product = 'E12';
		}elseif($this->dpdParcelPredict->checkIfExpress10Carrier($orderId) && !$return){
			$product = 'E10';
		}elseif($this->dpdParcelPredict->checkIfGuarantee18Carrier($orderId) && !$return){
			$product = 'E18';
		}

		$shipment =array( 'printOptions' => array(
			'printerLanguage' => 'PDF'
			,'paperFormat' => 'A4'
		)
		,'order' => array(
				'generalShipmentData' => array(
					'sendingDepot' => Configuration::get('dpdbenelux_acces_token_depot')
				,'product' => $product
				,'sender' => array(
						'name1' => Configuration::get('dpdbenelux_company')
					,'street' => Configuration::get('dpdbenelux_street')
					,'country' => Configuration::get('dpdbenelux_country')
					,'zipCode' => Configuration::get('dpdbenelux_postalcode')
					,'city' => Configuration::get('dpdbenelux_place')
					)
				,'recipient' => array(
						'name1' =>  $fullName
					,'street' => $street
					,'country' => $country->iso_code
					,'zipCode' => $address->postcode // No spaces in zipCode!
					,'city' => $address->city
					)
				)
			,'productAndServiceData' => $serviceData
			)
		);
		$shipment['order']['parcels'] = array();
		for($x = 1; $x <= $parcels; $x++)
		{

			$parcelInfo = 	array(
				'customerReferenceNumber1' => $orderId,
				'weight' => $weightTotal / $parcels,
			);

			if((boolean)$return)
			{
				$parcelInfo['returns'] = true;
			}

			array_push($shipment['order']['parcels'] , $parcelInfo);
		}

		// if its express 12 so we add contact to the recipient
		if(($this->dpdParcelPredict->checkIfExpress12Carrier($orderId) ||
			$this->dpdParcelPredict->checkIfExpress10Carrier($orderId) ||
			$this->dpdParcelPredict->checkIfGuarantee18Carrier($orderId) ||
			$this->dpdParcelPredict->checkIfClassicCarrier($orderId)) && !$return)
		{
			$shipment['order']['generalShipmentData']['recipient']['contact'] = "Contact";
			$shipment['order']['generalShipmentData']['recipient']['phone'] = $phone;
			$shipment['order']['generalShipmentData']['recipient']['email'] = $customer->email;
		}
		return $shipment;

	}

	public static function getLabelOutOfDb($orderId, $return = false)
	{
		$sql = new DbQuery();
		$sql->from('dpdshipment_label');
		$sql->select('*');
		$sql->where('order_id=' . pSQL($orderId) . ' AND retour = ' . pSQL((int)$return));

		$result = Db::getInstance()->executeS($sql);
		if (empty($result)) {
			return false;
		} else {
			return $result;
		}
	}

	public static function countLabels($orderId, $return = false)
	{
		$databaseLabel = Self::getLabelOutOfDb($orderId, $return);
		if($databaseLabel){
			$labelNumbers = unserialize($databaseLabel[0]['label_nummer']);
			$result = count($labelNumbers);
		}else{
			$result = 0;
		}
		return $result;
	}

	public static function deleteLabelFromDb($ordersId, $return)
	{
		foreach($ordersId as $orderId){
			Db::getInstance()->delete('dpdshipment_label', 'order_id=' . pSQL($orderId) . ' AND retour=' . pSQL((int)$return), 1);

			$tempOrder = new Order($orderId);
			// so it empty the shipping number.
			$tempOrder->setWsShippingNumber('');

			$link = Self::generateOrderViewUrl($orderId);

		}
		header('location: '. $link);
		return true;
	}

	public static function generateOrderViewUrl($orderId)
	{
		$link = new LinkCore;
		$link = $link->getAdminLink('AdminOrders');
		$link = $link . '&id_order=' . $orderId . '&vieworder';

		return $link;
	}

}