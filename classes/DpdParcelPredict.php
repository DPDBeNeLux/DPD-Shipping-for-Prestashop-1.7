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
require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DS . 'classes' . DS . 'Gmaps.php');

class DpdParcelPredict
{
	public $dpdClient;
	public $Gmaps;
	public $DpdAuthentication;

	public function __construct()
	{
		$this->Gmaps = new Gmaps();
		$this->dpdClient = new DpdClient();
		$this->DpdAuthentication = new DpdAuthentication();
	}

	public function getGeoData($postalCode, $isoCode)
	{
		return $this->Gmaps->getGeoData($postalCode, $isoCode);
	}

	public function getParcelShops($postalCode, $isoCode){
		if($this->DpdAuthentication->isConfigured()){
			$this->DpdAuthentication->getAccesToken();
			$geoData = $this->Gmaps->getGeoData($postalCode, $isoCode);
			$geoData['limit'] = Configuration::get('dpdbenelux_parcel_limit');
			return $this->dpdClient->findPacelShopsByGeoData($geoData, Configuration::get('dpdbenelux_delis_id'), Configuration::get('dpdbenelux_acces_token'));
		}else{
			return false;
		}

	}


	public function getParcelShopId($orderId)
	{
		$sql = "SELECT parcelshop_id FROM " . _DB_PREFIX_ . "parcelshop WHERE order_id = " . pSQL($orderId);
		return Db::getInstance()->executeS($sql)[0]['parcelshop_id'];
	}

	public function checkIfDpdSending($orderId)
	{
		if($this->checkIfParcelSending($orderId) ||
			$this->checkIfSaturdayCarrier($orderId)||
			$this->checkIfClassicSaturdayCarrier($orderId) ||
			$this->checkIfPredictCarrier($orderId) ||
			$this->checkIfExpress12Carrier($orderId)||
			$this->checkIfExpress10Carrier($orderId) ||
			$this->checkIfGuarantee18Carrier($orderId) ||
			$this->checkIfClassicCarrier($orderId)
		){

			return true;
		}

		return false;
	}

	public function checkIfParcelSending($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdParcelshopCarrierId = Configuration::get('dpdbenelux_parcelshop');

		if($tempCarrierReferenceId == $dpdParcelshopCarrierId){
			return true;
		}else{
			return false;
		}

	}

	public function checkIfParcelCarrier($orderId){
		$parcelShopId = $this->getParcelShopId($orderId);
		$dpdParcelshopCarrierId = $this->checkIfParcelSending( $orderId);

		if(($parcelShopId != NULL) && ($dpdParcelshopCarrierId)){
			$result = true;
		}else{
			$result = false;
		}
		return $result;
	}

    public static function checkIfPredictCarrier($orderId)
    {
        $tempOrder = new Order($orderId);
        $tempCarrier = new Carrier($tempOrder->id_carrier);
        $tempCarrerreferenceId = $tempCarrier->id_reference;

        $dpdPredictCarrierId = Configuration::get('dpdbenelux_predict');

        if($tempCarrerreferenceId == $dpdPredictCarrierId){
            return true;
        }else{
            return false;
        }

    }

    public function checkIfSaturdayCarrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdSaturdayCarrierId = Configuration::get('dpdbenelux_saturday');

		if($tempCarrierReferenceId == $dpdSaturdayCarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function checkIfClassicSaturdayCarrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdClassicSaturdayCarrierId = Configuration::get('dpdbenelux_classic_saturday');

		if($tempCarrierReferenceId == $dpdClassicSaturdayCarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function checkIfExpress12Carrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdExpress12CarrierId = Configuration::get('dpdbenelux_express12');

		if($tempCarrierReferenceId == $dpdExpress12CarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function checkIfExpress10Carrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdExpress10CarrierId = Configuration::get('dpdbenelux_express10');

		if($tempCarrierReferenceId == $dpdExpress10CarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function checkIfGuarantee18Carrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdGuarantee18CarrierId = Configuration::get('dpdbenelux_guarantee18');

		if($tempCarrierReferenceId == $dpdGuarantee18CarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function checkIfClassicCarrier($orderId)
	{
		$tempOrder = new Order($orderId);
		$tempCarrier = new Carrier($tempOrder->id_carrier);
		$tempCarrierReferenceId = $tempCarrier->id_reference;

		$dpdClassicCarrierId = Configuration::get('dpdbenelux_classic');

		if($tempCarrierReferenceId == $dpdClassicCarrierId){
			return true;
		}else{
			return false;
		}
	}

	public function getLabelNumbersAndWeigth($orderId)
	{
		$sql = new DbQuery();
		$sql->from('dpdshipment_label');
		$sql->select('label_nummer, retour');
		$sql->where('order_id = ' . pSQL($orderId));



		$result = Db::getInstance()->ExecuteS($sql);
		return $result;
	}

}