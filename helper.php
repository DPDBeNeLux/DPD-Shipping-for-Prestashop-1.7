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

class DpdHelper
{
	const MODULENAME = 'dpdbenelux';

	public function displayConfigurationForm($module, $formAccountSettings, $formAdress)
	{
		// Get the default language id of the shop
		$default_lang_id = (int)Configuration::get('PS_LANG_DEFAULT');

		// Set al the fields of the form
		$fields_form[0]['form'] = $formAccountSettings;
		$fields_form[1]['form'] = $formAdress;

		$helperForm = new HelperForm();
		$helperForm->module = $module;
		$helperForm->name_controller = $module->name;
		$helperForm->token = Tools::getAdminTokenLite('AdminModules');
		$helperForm->currentIndex = AdminController::$currentIndex.'&configure='.$module->name;

		// Language
		$helperForm->default_form_language = $default_lang_id;
		$helperForm->allow_employee_form_lang = $default_lang_id;

		// Title
		$helperForm->title = $module->displayName;
		$helperForm->show_toolbar = true;
		$helperForm->toolbar_scroll = true;
		$helperForm->submit_action = 'submit'.$module->name;

		// Load current value
		$helperForm->fields_value['delis_id'] = Configuration::get('dpdbenelux_delis_id');
		$helperForm->fields_value['company'] = Configuration::get('dpdbenelux_company');
		$helperForm->fields_value['account_type'] = Configuration::get('dpdbenelux_account_type');
		$helperForm->fields_value['street'] = Configuration::get('dpdbenelux_street');
		$helperForm->fields_value['postalcode'] = Configuration::get('dpdbenelux_postalcode');
		$helperForm->fields_value['place'] = Configuration::get('dpdbenelux_place');
		$helperForm->fields_value['country'] = Configuration::get('dpdbenelux_country');
		$helperForm->fields_value['environment'] = Configuration::get('dpdbenelux_environment');
		$helperForm->fields_value['api_key'] =  Configuration::get('PS_API_KEY');

		return $helperForm->generateForm($fields_form);

	}

	public function checkIfExtensionIsLoaded($extension = 'soap')
	{
		return(extension_loaded($extension));
	}

	public function installDB()
	{
			$query = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'install.sql');

			$query = preg_replace('/_PREFIX_/', _DB_PREFIX_, $query);

			return Db::getInstance()->execute($query);
	}

	public function installControllers($controllerNames)
	{
		foreach($controllerNames as $controllerName => $userReadableName)
		{
			$tab = new Tab();
			$tab->active = 1;
			$tab->class_name = $controllerName;
			// Hide the tab from the menu.
			$tab->id_parent = -1;
			$tab->module = self::MODULENAME;
			$tab->name = array();
			foreach (Language::getLanguages(true) as $lang) {
				$tab->name[$lang['id_lang']] = $userReadableName;
			}
			$tab->add();
		}
		return true;
	}

	public function cacheGeoData($parcelPredict, $params, $cookie)
	{
		$cookie->address = serialize(
			array(
				'postcode' => $params['address']->postcode,
				'city' => $params['address']->city
			)
		);

		$geoData = $parcelPredict->getGeoData($params['address']->postcode, $params['address']->city);
		$parcelShops = $parcelPredict->getParcelShops($params['address']->postcode, $params['address']->city);
//		var_dump($parcelShops); die;
		$cookie->geoData = serialize($geoData);
		$cookie->parcelShops = base64_encode(json_encode($parcelShops));
//		var_dump(($cookie->parcelShops)); die;


		unset($cookie->parcelId);
	}
}