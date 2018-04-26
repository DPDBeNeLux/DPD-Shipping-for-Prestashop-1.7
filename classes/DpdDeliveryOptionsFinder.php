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

/**
 * this is only for prestashop 1.7
 */
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
class DpdDeliveryOptionsFinder extends DeliveryOptionsFinder
{
	public function __construct(Context $context,
								\Symfony\Component\Translation\TranslatorInterface $translator,
								\PrestaShop\PrestaShop\Adapter\ObjectPresenter $objectPresenter,
								PriceFormatter $priceFormatter
	)
	{
		parent::__construct($context, $translator, $objectPresenter, $priceFormatter);
		$this->dpdCarrier = new DpdCarrier();
	}

	public function getDeliveryOptions()
	{
		$carriers_available = parent::getDeliveryOptions();

		$saturdayCarrierId = $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get('dpdbenelux_saturday'));
		$classicSaturdayCarrierId = $this->dpdCarrier->getLatestCarrierByReferenceId(Configuration::get('dpdbenelux_classic_saturday'));

		if(!$this->dpdCarrier->checkIfSaturdayAllowed()) {
			unset($carriers_available[$saturdayCarrierId . ',']);
			unset($carriers_available[$classicSaturdayCarrierId . ',']);
		}

		return $carriers_available;
	}
}