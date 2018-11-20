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
class DPDBenelux extends Module
{
	public $dpdHelper;
	public $dpdCarrier;
	public $dpdParcelPredict;
	public $dpdEncryptionManager;

	private $ownControllers = array(
		'AdminDpdLabels' => 'DPD label',
		'AdminDpdShippingList' => 'DPD ShippingList'
	);
	private $hooks = array(
		'displayAdminOrderTabOrder',
		'displayAdminOrderContentOrder',
		'actionAdminBulkAffectZoneAfter',
		'displayCarrierList',
		'actionCarrierProcess',
        'displayOrderConfirmation',
		'displayBeforeCarrier',
		'actionValidateOrder',

	);

	public function loadHelper()
	{
		require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'helper.php');
	}

	public function loadDpdCarrier()
	{
		require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdCarrier.php');
	}

	public function loadDpdPredictLabel()
	{
		require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdLabelGenerator.php');
	}

	public function loadDpdParcelLabel()
	{
		require_once(_PS_MODULE_DIR_  . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdParcelPredict.php');
	}

	public function loadDpdEncryptionManager()
	{
		require_once(_PS_MODULE_DIR_ .  'dpdbenelux' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'DpdEncryptionManager.php');
	}

	public function __construct()
	{
		// this loads the helper.
		$this->loadHelper();
		$this->dpdHelper = new DpdHelper();
		// this loads the DpdCarrier
		$this->loadDpdCarrier();
		$this->dpdCarrier = new DpdCarrier();

		//this loads the DpdLabelGenerator
		$this->loadDpdPredictLabel();
		
		//this loads the gmaps
		$this->loadDpdParcelLabel();
		$this->dpdParcelPredict = new DpdParcelPredict();
		
		//this loads the encryption manager
		$this->loadDpdEncryptionManager();
		$this->dpdEncryptionManager = new DpdEncryptionManager();
		

		// the information about the plugin.
		$this->version = "1.0";
		$this->name = "dpdbenelux";
		$this->displayName = $this->l("DPDBenelux");
		$this->author = "DPD Nederland B.V.";
		$this->tab = 'shipping_logistics';
		$this->limited_countries = array('be', 'lu', 'nl');

        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];
		$this->need_instance = 1;
		$this->bootstrap = true;
		
		parent::__construct();
	}
	/**
	 * this is triggered when the plugin is installed
	 */
	public function install()
	{
		if(!$this->dpdHelper->checkIfExtensionIsLoaded('soap')) {
			//TODO create a log that soap is not installed
			return false;
		}
		if(parent::install()){
			Configuration::updateValue('dpd', 'dpdbenelux');
			Configuration::updateValue('dpdbenelux_parcel_limit' , 12);
		}
		if(!$this->dpdHelper->installDB()){
			//TODO create log that database could not be installed.
			return false;
		}
		foreach($this->hooks as $hookName){
			if(!$this->registerHook($hookName)) {
			//TODO create a log that hook could not be installed.
			return false;
			}
		}
		if(!$this->dpdHelper->installControllers($this->ownControllers)){
			//TODO create a log that hook could not be installed.
			return false;
		}
		if(!$this->dpdCarrier->createCarriers()){
			//TODO create a log that the carrier could not be installed
			return false;
		}
		return true;
	}

	/**
	 * this is triggered when the plugin is uninstalled
	 */
	public function uninstall()
	{
		if (!parent::uninstall())
		{
			return false;
		}
		else
		{
			$this->dpdCarrier->deleteCarriers();
			Configuration::updateValue('dpd', 'not installed');
			return true;
		}
	}

	public function getContent()
	{
		$output = '';

		if(Tools::isSubmit('submit'.$this->name))
		{
			$delisid = strval(Tools::getValue("delis_id"));
			$delispassword = strval(Tools::getValue("delis_password"));
			if($delispassword == NULL)
			{
				$delispassword = Configuration::get('dpdbenelux_delis_password');
			}else
			{
				$delispassword = $this->dpdEncryptionManager->encrypt($delispassword);
			}
			$company = strval(Tools::getValue("company"));
			$street = strval(Tools::getValue("street"));
			$postalcode = strval(Tools::getValue("postalcode"));
			$place = strval(Tools::getValue("place"));
			$country = strval(Tools::getValue("country"));
			$environment = Tools::getValue('environment');
			$accountType = Tools::getValue('account_type');
			$apiKey = Tools::getValue('api_key');

			if(!(empty($delisid) || empty($company) || empty($street) || empty($postalcode) || empty($place) || empty($country) || empty($environment) || empty($accountType)))
			{
				Configuration::updateValue('PS_API_KEY', $apiKey);
				Configuration::updateValue('dpdbenelux_delis_id', $delisid);
				Configuration::updateValue('dpdbenelux_delis_password', $delispassword);
				Configuration::updateValue('dpdbenelux_account_type', $accountType);
				Configuration::updateValue('dpdbenelux_company', $company);
				Configuration::updateValue('dpdbenelux_street', $street);
				Configuration::updateValue('dpdbenelux_postalcode', $postalcode);
				Configuration::updateValue('dpdbenelux_place', $place);
				Configuration::updateValue('dpdbenelux_country', $country);
				Configuration::updateValue('dpdbenelux_environment', $environment);
				$output .= $this->displayConfirmation($this->l('Settings updated'));

				$this->dpdCarrier->setCarrierForAccountType();
			}
			else
			{
				$output .= $this->displayError($this->l('Invalid Configuration value'));
			}
		}
		$formAccountSettings = array(
			'legend' => array(
				'title' => $this->l('Account Settings'),
			),
			'input' => array(
				array(
					'required' => true,
					'type' => 'select',
					'label' => $this->l('Environment'),
					'name' => 'environment',
					'options' => array(
						'query' => array(
							array('key' => '0', 'name' => $this->l('Please select an environment')),
							array('key' => '1', 'name' => $this->l('Live')),
							array('key' => '2', 'name' => $this->l('Demo')),
						),
						'id' => 'key',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Delis id'),
					'name' => 'delis_id',
					'required' => true
				),
				array(
					'type' => 'password',
					'label' => $this->l('Delis password'),
					'name' => 'delis_password',
					'required' => true
				),
				array(
					'required' => true,
					'type' => 'select',
					'label' => $this->l('DPD Account type'),
					'name' => 'account_type',
					'options' => array(
						'query' => array(
							array('key' => '0', 'name' => $this->l('Please select DPD Account type')),
							array('key' => 'b2b', 'name' => $this->l('B2B')),
							array('key' => 'b2c', 'name' => $this->l('B2C')),
						),
						'id' => 'key',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Google Api Key'),
					'name' => 'api_key',
					'required' => true
				),
			),
		);

		$formAdres = array( 'legend' => array(
		'title' => $this->l('Shipping Address'),
	),
			'input' => array(
		array(
			'type' => 'text',
			'label' => $this->l('Company name'),
			'name' => 'company',
			'required' => true,
		),
		array(
			'type' => 'text',
			'label' => $this->l('Street + house number'),
			'name' => 'street',
			'required' => true
		),
		array(
			'type' => 'text',
			'label' => $this->l('Postal Code'),
			'name' => 'postalcode',
			'required' => true
		),
		array(
			'type' => 'text',
			'label' => $this->l('Place'),
			'name' => 'place',
			'required' => true
		),
		array(
			'type' => 'text',
			'label' => $this->l('Country code'),
			'name' => 'country',
			'required' => true
		),
	),
		'submit' => array(
		'title' => $this->l('Save'),
		'class' => 'btn btn-default pull-right'
	)
		);

		return $output . $this->dpdHelper->displayConfigurationForm($this, $formAccountSettings, $formAdres);

	}

	public function hookDisplayAdminOrderTabOrder($params)
	{
		$orderId = Tools::getValue('id_order');
		$parcelShopId = $this->dpdParcelPredict->getParcelShopId($orderId);

		if($this->dpdParcelPredict->checkIfDpdSending($orderId)) {

			$this->context->smarty->assign(
				array(
					'isDpdCarrier' => $this->dpdParcelPredict->checkifParcelCarrier($orderId),
					'dpdParcelshopId' => $parcelShopId
				)
			);
			return $this->display(__FILE__, '_adminOrderTab.tpl');
		}
	}

	public function hookDisplayAdminOrderContentOrder($params)
	{
		$orderId = Tools::getValue('id_order');
		$parcelShopId = $this->dpdParcelPredict->getParcelShopId($orderId);
		$parcelCarrier = $this->dpdParcelPredict->checkifParcelCarrier($orderId);

		if($this->dpdParcelPredict->checkIfDpdSending($orderId)){
			$link = new LinkCore;
			$urlGenerateLabel = $link->getAdminLink('AdminDpdLabels');
			$urlGenerateLabel = $urlGenerateLabel . '&ids_order[]=' . $orderId;

			$urlGenerateReturnLabel = $urlGenerateLabel . '&return=true';



			$this->context->smarty->assign(
				array(
					'parcelCarrier' => $parcelCarrier,
					'parcelShopId' => $parcelShopId,
					'number' => DpdLabelGenerator::countLabels($orderId),
					'isInDb' => DpdLabelGenerator::getLabelOutOfDb($orderId),
					'urlGenerateLabel' => $urlGenerateLabel,
					'urlGenerateReturnLabel' => $urlGenerateReturnLabel,
					'isReturnInDb'=> DpdLabelGenerator::getLabelOutOfDb($orderId, true),
					'deleteGeneratedLabel' => $urlGenerateLabel . '&delete=true',
					'deleteGeneratedRetourLabel' => $urlGenerateReturnLabel . '&delete=true'
		)
			);
			return $this->display(__FILE__, '_adminOrderTabLabels.tpl');
		}

	}


	public function hookDisplayCarrierList($params)
	{
		if ($params['cart']->id_carrier == $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop"))) {
			$country = new Country($params['address']->id_country);
			$isoCode = $country->iso_code;

			$geoData = $this->dpdParcelPredict->getGeoData($params['address']->postcode, $isoCode);
			$parcelShops = $this->dpdParcelPredict->getParcelShops($params['address']->postcode, $isoCode);
		}

		$this->context->controller->addCSS(_PS_MODULE_DIR_ . 'dpdbenelux' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'dpdLocator.css');
		$this->context->smarty->assign(
			array(
				'parcelshopId' => $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop")),
				'sender' => $params['cart']->id_carrier,
				'key' => Configuration::get('PS_API_KEY'),
				'longitude' => $geoData['longitude'],
				'latitude' => $geoData['latitude'],
				'parcelshops' => $parcelShops,
				'saturdaySenderIsAllowed' => (int)$this->dpdCarrier->checkIfSaturdayAllowed(),
				'saturdaySender' => (int)$this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_saturday")),
				'classicSaturdaySender' => (int)$this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_classic_saturday")),
				'cookieParcelId' => $this->context->cookie->parcelId,
			)
		);

		return $this->display(__FILE__, '_dpdLocator.tpl');

	}


	public function hookActionCarrierProcess($params)
	{
	     //this adds parcel-id to the coockie when the carrier is used
//	    if((int)$params['cart']->id_carrier === (int)$this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop"))){
//	        if(!empty($_POST['parcel-id']) && !($_POST['parcel-id'] == '')) {
//				$this->context->cookie->parcelId = $_POST['parcel-id'];
//            }else{
//	            $this->context->controller->errors[] = $this->l('Please select a parcelshop');
//            }
//        }

		if((int)$params['cart']->id_carrier === (int)$this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop"))){
	    	if(empty($this->context->cookie->parcelId) || $this->context->cookie->parcelId == ''){
				$this->context->controller->errors[] = $this->l('Please select a parcelshop');
			}
		}
	}


    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'];
        if((int)$order->id_carrier === (int)$this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop"))) {
			if(!empty($this->context->cookie->parcelId) && !($this->context->cookie->parcelId == '')) {
				Db::getInstance()->insert('parcelshop', array(
					'order_id' => pSQL($order->id),
					'parcelshop_id' => pSQL($params['cookie']->parcelId)
				));
				unset($this->context->cookie->parcelId) ;
			}
        }
    }


}

