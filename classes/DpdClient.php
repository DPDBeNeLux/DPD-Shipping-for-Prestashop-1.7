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
require_once(_PS_MODULE_DIR_ . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdEncryptionManager.php');

class DpdClient
{
	const DPD_LOGIN_SERVICE_URL = 'LoginService.svc?singleWsdl';
	const DPD_SHIPMENT_SERVICE_URL = 'ShipmentService.svc?singleWsdl';

	const DPD_PARCELSHOP_URL_POSTFIX = 'ParcelShopFinderService.svc?singleWsdl';
	const DPD_AUTH_URL = 'http://dpd.com/common/service/types/Authentication/2.0';
	const DPD_STAGING_SERVICE_URL = 'https://public-dis-stage.dpd.nl/Services/';
	const DPD_LIVE_SERVICE_URL = 'https://public-dis.dpd.nl/Services/';

	public $dpdEncryptionManager;

	public function __construct()
	{
		$this->dpdEncryptionManager = new DpdEncryptionManager();
	}

	/**
	 * @param $delisid
	 * @param $delispassword
	 */
	public function login($delisid, $delispassword)
	{

		$client = $this->getSoapClient($this->getUrl(self::DPD_LOGIN_SERVICE_URL));
		$result = $client->getAuth(
			array(
				'delisId' => $delisid,
				'password' => $this->dpdEncryptionManager->decrypt($delispassword),
				'messageLanguage' => 'nl_NL'
			)
		);
		return [
			'authToken' => $result->return->authToken,
			'depot' => $result->return->depot
		];
	}

	public function genereateLabel($shippingInfo, $delisId, $accessToken)
	{
		$soapHeaderBody = array(
			'delisId' => $delisId,
			'authToken' => $accessToken,
			'messageLanguage' => 'nl_NL'
			);

			$header = new SOAPHeader(self::DPD_AUTH_URL, 'authentication', $soapHeaderBody, false);
			$client = $this->getSoapClient($this->getUrl(self::DPD_SHIPMENT_SERVICE_URL));

			$soapHeader = $header;
			$client->__setSoapHeaders($soapHeader);
			$result = $client->storeOrders($shippingInfo);
			 return $result->orderResult;
	}

	public function storeOrders($mpsId, $labelNummers, $orderId, $label, $return)
	{
		$tempOrder = new Order($orderId);
		// checks if the the order is being shipped or is delivered
		if($tempOrder->current_state == 4 || $tempOrder->current_state == 5)
		{
			$shipped = 1;
		}else{
			$shipped = 0;
		}

		if($return){
			$return = 1;
		}else{
			$return = 0;
		}
		$serializedLabelNummers = serialize($labelNummers);
		Db::getInstance()->insert('dpdshipment_label', array(
				'mps_id' => $mpsId,
				'label_nummer' => $serializedLabelNummers,
				'order_id' => (int)$orderId,
				'created_at' => (string)date('y-m-d h:i:s'),
				'shipped' => $shipped,
				'label' => addslashes($label),
				'retour' => $return,
			)
		);
		return true;
	}


	public function getUrl($serviceUrl)
	{
		$environmentId = Configuration::get('dpdbenelux_environment');
		// 0 for when you haven't made a choice yet.
		// 1 for live
		// 2 for demo
		if($environmentId == 1)
		{
			return  self::DPD_LIVE_SERVICE_URL . $serviceUrl;
		}

        return self::DPD_STAGING_SERVICE_URL . $serviceUrl;
	}

	public function findPacelShopsByGeoData($parameters, $delisId, $accessToken)
	{
		$url = $this->getUrl(self::DPD_PARCELSHOP_URL_POSTFIX);
		$soapClient = $this->getSoapClient($url);
		$soapHeaderBody = array(
			'delisId' => $delisId,
			'authToken' => $accessToken,
			'messageLanguage' => 'nl_NL'
		);
		$header = new SoapHeader(self::DPD_AUTH_URL, 'authentication', $soapHeaderBody);
		$soapClient->__setSoapHeaders($header);
		try {
			return json_encode($soapClient->__soapCall('findParcelShopsByGeoData', array($parameters)));
		}catch(Exception $e){
			//TODO create log that there is a soap fault.
		}

	}


	private function getSoapClient($url)
	{

		$cacheDir = _PS_CACHE_DIR_;
		$md5Key = md5($url);
		$tempFile = $cacheDir . $md5Key . '.wsdl';
		if(!file_exists($tempFile))
		{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            $wsdl = curl_exec($ch);
            curl_close($ch);
            file_put_contents($tempFile, $wsdl);
		}
		$client = new SoapClient($tempFile);

		return $client;
	}
}