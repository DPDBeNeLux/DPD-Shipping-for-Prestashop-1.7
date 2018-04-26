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

class DpdCheckoutDeliveryStep extends CheckoutDeliveryStep
{
	public function render(array $extraParams = array()){
		$templates = $this->renderTemplate(
			$this->getTemplate(),
			$extraParams,
			array(
				'hookDisplayBeforeCarrier' => Hook::exec('displayBeforeCarrier', array('cart' => $this->getCheckoutSession()->getCart())),
				'hookDisplayAfterCarrier' => Hook::exec('displayAfterCarrier', array('cart' => $this->getCheckoutSession()->getCart())),
				'id_address' => $this->getCheckoutSession()->getIdAddressDelivery(),
				'delivery_options' => $this->getCheckoutSession()->getDeliveryOptions(),
				'delivery_option' => $this->getCheckoutSession()->getSelectedDeliveryOption(),
				'recyclable' => $this->getCheckoutSession()->isRecyclable(),
				'recyclablePackAllowed' => $this->isRecyclablePackAllowed(),
				'delivery_message' => $this->getCheckoutSession()->getMessage(),
				'gift' => array(
					'allowed' => $this->isGiftAllowed(),
					'isGift' => $this->getCheckoutSession()->getGift()['isGift'],
					'label' => $this->getTranslator()->trans(
						'I would like my order to be gift wrapped %cost%',
						array('%cost%' => $this->getGiftCostForLabel()),
						'Shop.Theme.Checkout'
					),
					'message' => $this->getCheckoutSession()->getGift()['message'],
				),
			)
		);

		// own code

		$address = new Address($this->context->cart->id_address_delivery);


		$geoData = $this->dpdParcelPredict->getGeoData($address->postcode, $address->city);
		$parcelShops = $this->dpdParcelPredict->getParcelShops($address->postcode, $address->city);

		$parcelShopInfo = array(
			'baseUri' => __PS_BASE_URI__,
			'parcelshopId' => $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get("dpdbenelux_parcelshop")),
			'sender' => $this->context->cart->id_carrier,
			'key' => Configuration::get('PS_API_KEY'),
			'longitude' => $geoData['longitude'],
			'latitude' => $geoData['latitude'],
			'parcelshops' => $parcelShops,
			'cookieParcelId' => $this->context->cookie->parcelId,
		);
		$this->context->smarty->assign($parcelShopInfo);

		$templates .= $this->renderTemplate(_PS_MODULE_DIR_ . 'dpdbenelux' . DS . 'views' . DS . 'templates' . DS . '1.7' . DS . '_dpdLocator1.7.tpl');

		file_put_contents('log.txt', print_r($this->context->cookie, true));
		return $templates;
	}
}