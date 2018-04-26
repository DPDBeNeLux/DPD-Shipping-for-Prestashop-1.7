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
require_once (_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'classes' . DS . 'DpdCarrier.php');
require_once (_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'classes' . DS . 'DpdDeliveryOptionsFinder.php');
require_once (_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'classes' . DS . 'DpdCheckoutDeliveryStep.php');

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class OrderController extends OrderControllerCore
{
	public $dpdCarrier;

	public function __construct()
	{
		parent::__construct();
		$this->dpdCarrier = new DpdCarrier();
	}

	public function _assignWrappingAndTOS()
	{
		parent::_assignWrappingAndTOS();
		$deliveryOptionList = $this->context->cart->getDeliveryOptionList();

		$saturdayCarrierId = $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get('dpdbenelux_saturday'));
		$classicSaturdayCarrierId = $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get('dpdbenelux_classic_saturday'));

		foreach($deliveryOptionList as &$carriers){
			if(!$this->dpdCarrier->checkIfSaturdayAllowed()) {
				unset($carriers[$saturdayCarrierId . ',']);
				unset($carriers[$classicSaturdayCarrierId . ',']);
			}
		}

		$this->context->smarty->assign(array(
			'delivery_option_list' => $deliveryOptionList,
		));
	}

	/**
	 * @return CheckoutSession
	 * this is only for prestashop 1.7
	 */
	public function getCheckoutSession()
	{
		$deliveryOptionsFinder = new DpdDeliveryOptionsFinder(
			$this->context,
			$this->getTranslator(),
			$this->objectPresenter,
			new PriceFormatter()
		);
		$session = new CheckoutSession(
			$this->context,
			$deliveryOptionsFinder
		);
		return $session;
	}

	protected function bootstrap()
	{
		$translator = $this->getTranslator();
		$session = $this->getCheckoutSession();
		$this->checkoutProcess = new CheckoutProcess(
			$this->context,
			$session
		);
		$this->context->controller->addCSS(_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'views' . DS . 'css' . DS . 'dpdLocator.css');
		$this->checkoutProcess
			->addStep(new CheckoutPersonalInformationStep(
				$this->context,
				$translator,
				$this->makeLoginForm(),
				$this->makeCustomerForm()
			))
			->addStep(new CheckoutAddressesStep(
				$this->context,
				$translator,
				$this->makeAddressForm()
			));
		//own code
		$this->context->controller->addCSS(_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'views' . DS . 'css' . DS . 'dpdLocator.css');
		if (!$this->context->cart->isVirtualCart()) {
			$checkoutDeliveryStep = new DpdCheckoutDeliveryStep(
				$this->context,
				$translator
			);
			$checkoutDeliveryStep
				->setRecyclablePackAllowed((bool) Configuration::get('PS_RECYCLABLE_PACK'))
				->setGiftAllowed((bool) Configuration::get('PS_GIFT_WRAPPING'))
				->setIncludeTaxes(
					!Product::getTaxCalculationMethod((int) $this->context->cart->id_customer)
					&& (int) Configuration::get('PS_TAX')
				)
				->setDisplayTaxesLabel((Configuration::get('PS_TAX') && !Configuration::get('AEUC_LABEL_TAX_INC_EXC')))
				->setGiftCost(
					$this->context->cart->getGiftWrappingPrice(
						$checkoutDeliveryStep->getIncludeTaxes()
					)
				);
			$this->checkoutProcess->addStep($checkoutDeliveryStep);
		}
		$this->checkoutProcess
			->addStep(new CheckoutPaymentStep(
				$this->context,
				$translator,
				new PaymentOptionsFinder(),
				new ConditionsToApproveFinder(
					$this->context,
					$translator
				)
			))
		;
	}

}