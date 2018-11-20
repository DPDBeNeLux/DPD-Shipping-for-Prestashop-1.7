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
require_once (_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdClient.php');

class DpdAuthentication
{

	private $dpdClient;

	public function __construct()
	{
		$this->dpdClient = new DpdClient();
	}

	public function getDelisId()
	{
		return Configuration::get('dpdbenelux_delis_id');
	}

	public function getDepot()
	{
		return Configuration::get('dpdbenelux_acces_token_depot');
	}

	public function getAccesToken()
	{
		$accesTokenCreatedTimestamp =  Configuration::get('dpdbenelux_acces_token_created');
		// need to check if the token is older then 12 hours or is not set if so generate a new one.
		if((int)$accesTokenCreatedTimestamp > time() - 12 * 60 * 60)
		{
			// get the cached token
			return Configuration::get('dpdbenelux_acces_token');
		}
		else {
			// create a new token
			$delisid = Configuration::get('dpdbenelux_delis_id');
			$delispassword = Configuration::get('dpdbenelux_delis_password');
			//TODO #11791 decrypt the password
			$result =  $this->dpdClient->login($delisid, $delispassword);

			Configuration::updateValue('dpdbenelux_acces_token', $result['authToken']);
			Configuration::updateValue('dpdbenelux_acces_token_depot', $result['depot']);
			Configuration::updateValue('dpdbenelux_acces_token_created', time());

			return $result['authToken'];
		}
	}

	public function isConfigured()
	{
		$configurations['delisId'] = Configuration::get('dpdbenelux_delis_id');
		$configurations['delisPassword'] = Configuration::get('dpdbenelux_delis_password');
		$configurations['company'] = Configuration::get('dpdbenelux_company');
		$configurations['street'] = Configuration::get('dpdbenelux_street');
		$configurations['postalcode'] = Configuration::get('dpdbenelux_postalcode');
		$configurations['place'] = Configuration::get('dpdbenelux_place');
		$configurations['country'] = Configuration::get('dpdbenelux_country');
 		$output = true;
		foreach ($configurations as $configuration){
			if(empty($configuration)){
				$output =  false;
			}
		}
		return $output;

	}
}