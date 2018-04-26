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
class AdminOrdersController extends AdminOrdersControllerCore
{
	public function __construct()
	{
		parent::__construct();
		$this->bulk_actions['ShippingListDPD'] = array( 'text' => $this->l('Print DPD Shipping List'), 'icon' => 'icon-th-list');
		$this->bulk_actions['PrintDPD'] = array( 'text' => $this->l('Print DPD Label'), 'icon' => 'icon-print');
	}

	public function processBulkPrintDPD()
	{
		if (Tools::isSubmit('submitBulkPrintDPDorder')) {
			$id_lang = Context::getContext()->language->id;
			$params = Array('token' => Tools::getAdminTokenLite('AdminDpdLabels')) ;
			$orderUrl = Dispatcher::getInstance()->createUrl('AdminDpdLabels', $id_lang, $params, false);
			$orderIds = Tools::getValue('orderBox');
			if(!empty($orderIds)) {
				foreach ($orderIds as $orderId) {
					$orderUrl .= '&ids_order[]=' . $orderId;
				}
				header('location:' . $orderUrl);
			}
		}
	}

	public function processBulkShippingListDPD()
	{
		if (Tools::isSubmit('submitBulkShippingListDPDorder')) {
			$id_lang = Context::getContext()->language->id;
			$params = Array('token' => Tools::getAdminTokenLite('AdminDpdShippingList')) ;
			$orderUrl = Dispatcher::getInstance()->createUrl('AdminDpdShippingList', $id_lang, $params, false);
			$orderIds = Tools::getValue('orderBox');
			if(!empty($orderIds)) {
				foreach ($orderIds as $orderId) {
					$orderUrl .= '&ids_order[]=' . $orderId;
				}
				header('location:' . $orderUrl);
			}
		}

	}

	public function setMedia(){
		parent::setMedia();
		$this->addJS(_PS_MODULE_DIR_ . 'dpdbenelux/views/js/dpd.js');
	}


}