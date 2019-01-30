<?php

defined ('_JEXEC') or die('Restricted access');


if (!class_exists ('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

class plgVmPaymentElectroneum extends vmPSPlugin {

	function __construct (& $subject, $config) {
		

		
		
		$vmtask = JRequest::getVar("vmtask");
		if($vmtask == 'electroneumajax')
		{
			$cart = VirtueMartCart::getCart();
			$cart->prepareCartData();
			
			
			require_once("plugins/vmpayment/electroneum/src/Vendor.php");
			require_once("plugins/vmpayment/electroneum/src/Exception/VendorException.php");
			
			 $etn = JRequest::getVar("etn"); 
			 $paymentid = JRequest::getVar("paymentid"); 
			 $apikey = JRequest::getVar("apikey"); 
			 $secret = JRequest::getVar("secret"); 
			 $outlet = JRequest::getVar("outlet"); 
			 $total = JRequest::getVar("total"); 
			 $virtuemart_order_id = JRequest::getVar("virtuemart_order_id"); 
			 
			 $orderModel = VmModel::getModel('orders');
  		     $orderDetails = $orderModel->getOrder($virtuemart_order_id);
			 
			 
			 $vendor = new \Electroneum\Vendor\Vendor($apikey, $secret);
			 
			 $payload = array();
			 $payload['payment_id'] = $paymentid;
 	         $payload['vendor_address'] = 'etn-it-'.$outlet;
			 
			 $result = $vendor->checkPaymentPoll(json_encode($payload));
			 
			 if (!class_exists ('VirtueMartModelCurrency')) {
					require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
			  }
			  
			  
			 
			  
		  	 $currency = CurrencyDisplay::getInstance ('', $orderDetails['details']['BT']->virtuemart_vendor_id);
			 
			 
			 $currencycode = $currency->_vendorCurrency_code_3;
			 $ordertotal = $orderDetails['details']['BT']->order_total;
		
			 $etnshouldreceive =   $vendor->currencyToEtn($ordertotal, $currencycode);
			 

			 $return = array();
			 $return['showerror'] = 0;
	 	     if($result['status'] == 1) 
			 {
				 if($result['amount'] ==  $etnshouldreceive)
				 {
					 $return['success'] = 1;
					 $return['amount'] = $result['amount'];
					 $result['message'] = '';
				 }
				 else
				 {
					  $return['success'] = 0;
					  $return['showerror'] = 1;
				      $return['message'] = 'ETN Response Amount Not matched to Order Amount';
				 }
			 }
			 else if (!empty($result['message'])) 
			 {
				 $return['success'] = 0;
				 $return['message'] = $result['message'];
			 }
			 else
			 {
				  $return['success'] = 0;
				  $return['message'] = 'Unknown Error was found';
			 }
			echo json_encode($return);
			exit;
			 
			
		}

		parent::__construct ($subject, $config);
		
		
		
		//vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
		$this->setConvertable(array('min_amount','max_amount','cost_per_transaction','cost_min_transaction'));
		$this->setConvertDecimal(array('min_amount','max_amount','cost_per_transaction','cost_min_transaction','cost_percent_total'));
		
		
		JHTML::script('plugins/vmpayment/electroneum/electroneum.js');
	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author Valérie Isaksen
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment electronium Table');
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_min_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)'
		);

		return $SQLfields;
	}


	static function getPaymentCurrency (&$method, $selectedUserCurrency = false) {

		if (empty($method->payment_currency)) {
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			$method->payment_currency = $vendor->vendor_currency;
			return $method->payment_currency;
		} else {

			$vendor_model = VmModel::getModel( 'vendor' );
			$vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies( $method->virtuemart_vendor_id );

			if(!$selectedUserCurrency) {
				if($method->payment_currency == -1) {
					$mainframe = JFactory::getApplication();
					$selectedUserCurrency = $mainframe->getUserStateFromRequest( "virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt( 'virtuemart_currency_id', $vendor_currencies['vendor_currency'] ) );
				} else {
					$selectedUserCurrency = $method->payment_currency;
				}
			}

			$vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
			if(in_array($selectedUserCurrency,$vendor_currencies['all_currencies'])){
				$method->payment_currency = $selectedUserCurrency;
			} else {
				$method->payment_currency = $vendor_currencies['vendor_currency'];
			}

			return $method->payment_currency;
		}

	}

	/**
	 *
	 *
	 * @author Valérie Isaksen
	 */
	function plgVmConfirmedOrder ($cart, $order, $thanks = false) {
		


		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		vmLanguage::loadJLang('com_virtuemart',true);
		vmLanguage::loadJLang('com_virtuemart_orders', TRUE);

		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		$email_currency = $this->getEmailCurrency($method);

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);

		if (!empty($method->payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$method->payment_info = vmText::_ ($method->payment_info);
			}
		}

		$dbValues['payment_name'] = $this->renderPluginName ($method) . '<br />' . $method->payment_info;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_min_transaction'] = $method->cost_min_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$this->storePSPluginInternalData ($dbValues);

		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);

		$html = $this->renderByLayout('post_payment', array(
			'order_number' =>$order['details']['BT']->order_number,
			'order_pass' =>$order['details']['BT']->order_pass,
			'payment_name' => $dbValues['payment_name'],
			'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display'],
			'order_user_id' => $order['details']['BT']->virtuemart_user_id,
			'currency' => $currency,
			'order' => $order,
			'params' => $method, 
			'callpayemnt' => TRUE
		));
		
		
		
		
		/*
		$modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $this->getNewStatus ($method);
		$order['customer_notified'] = 1;
		$order['comments'] = '';
		
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart (); */
		
		
		echo $html;

		return true;
		
		vRequest::setVar ('html', $html);

		return FALSE;
	}

	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		vmLanguage::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

			if (preg_match ('/%$/', $method->cost_percent_total)) {
				$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
			} else {
				$cost_percent_total = $method->cost_percent_total;
			}
			return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
		}
	*/
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	public function plgVmOnCheckoutCheckDataPayment($cart) 
	{
		
		if (!($method = $this->getVmPluginMethod ($cart->virtuemart_paymentmethod_id))) {
			return NULL; 
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		
		$method = $this->getVmPluginMethod ($cart->virtuemart_paymentmethod_id);
		
		$apikey = $method->apikey;
		$secret = $method->secret;
		$outlet = $method->outlet;
		
		$errormsg = array();
		if($apikey == '')
		{
			$errormsg[] = 'VMPAYMENT_ELECTRONEUM_API_KEY_EMPTY';
		}	
		if($secret == '') 
		{
			$errormsg[] = 'VMPAYMENT_ELECTRONEUM_SECRET_KEY_EMPTY';
		}	
		if($outlet == '')
		{
			$errormsg[] = 'VMPAYMENT_ELECTRONEUM_OUTLET_EMPTY';
		}
		
		if(count($errormsg) > 0)
		{
			 $app = JFactory::getApplication();
			 $html = "";
			 foreach ($errormsg as $msg) 
			 {
				$html .= vmText::_($msg) . "<br/>";
			 }
			 $app ->enqueueMessage($html,'warning');
			 //vmWarn($html);
			 return FALSE;
		}
		else
		{
			 return true;
		}
	}
	protected function checkConditions ($cart, $method, $cart_prices) {
		
		$payeealias      = $method->payeealias;
		$mode            = $method->mode;
		$certificatepath = $method->certificatepath;
		$sslkeypath      = $method->sslkeypath;
		$secretpassword  = $method->secretpassword;

		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		if($this->_toConvert){
			$this->convertToVendorCurrency($method);
		}
		//vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}


	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		
		
		$this->displayListFE ($cart, $selected, $htmlIn);
		
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */

	function plgVmOnUserInvoice ($orderDetails, &$data) {

		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}
	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
 
		if(empty($method->email_currency)){

		} else if($method->email_currency == 'vendor'){
			$vendor_model = VmModel::getModel('vendor');
			$vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
			$emailCurrencyId = $vendor->vendor_currency;
		} else if($method->email_currency == 'payment'){
			$emailCurrencyId = $this->getPaymentCurrency($method);
		}


	}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}
	
	function plgVmOnPaymentResponseReceived(&$html) {
		
		
		
		$returnfrom = JRequest::getVar("returnfrom");
		$cancelorder = JRequest::getVar("cancelorder");
		$virtuemart_order_id = JRequest::getVar("virtuemart_order_id");
		
		if($cancelorder == "yes")
		{
			$app = JFactory::getApplication();
			$app->redirect("index.php?option=com_virteumart&view=cart");
		}

		if($returnfrom == "electronium")
		{
			
		
			$virtuemart_order_id = JRequest::getVar("virtuemart_order_id");
			
			
			$orderModel = VmModel::getModel('orders');
			$order = $orderModel->getOrder($virtuemart_order_id);
			
			$virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
			
			if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) 
			{
				return NULL; // Another method was selected, do nothing
	
			}
			
			vmLanguage::loadJLang('com_virtuemart',true);
			vmLanguage::loadJLang('com_virtuemart_orders', TRUE);
	
			$this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
			$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
			$email_currency = $this->getEmailCurrency($method);
	
			$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
			 
			 
			 $payment_name = $this->renderPluginName ($method) . '<br />' . $method->payment_info;
			 
			 $orderlink='';
				$tracking = VmConfig::get('ordertracking','guests');
				if($tracking !='none' and !($tracking =='registered' and empty($order['details']['BT']->virtuemart_user_id) )) {
		
					$orderlink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order['details']['BT']->order_number;
					if ($tracking == 'guestlink' or ($tracking == 'guests' and empty($order['details']['BT']->virtuemart_user_id))) {
						$orderlink .= '&order_pass=' . $order['details']['BT']->order_pass;
					}
				}
	
			
			$html = $this->renderByLayout('post_payment', array(
				'order_number' =>$order['details']['BT']->order_number,
				'order_pass' =>$order['details']['BT']->order_pass,
				'payment_name' => $payment_name,
				'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display'],
				'order_user_id' => $order['details']['BT']->virtuemart_user_id,
				'order' => $order,
				'orderlink' => $orderlink, 
				'params' => $method 
			));
	
			$modelOrder = VmModel::getModel ('orders');
			$order['order_status'] = "C";
			$order['customer_notified'] = 1;
			$order['comments'] = '';
			
			$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
	
			//We delete the old stuff
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart(); 
			
			
			vRequest::setVar ('html', $html);
			return TRUE;
		}
	}

}

// No closing tag
