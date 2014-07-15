<?php
include_once DIR_WS_INCLUDES . 'gocoinlib/src/GoCoin.php';
class gocoinpay extends base {

    /**
     * $code determines the internal 'code' name used to designate "this" payment module
     *
     * @var string
     */
    var $code;

    /**
     * $title is the displayed name for this payment method
     *
     * @var string
     */
    var $title;

    /**
     * $description is a soft name for this payment method
     *
     * @var string
     */
    var $description;

    /**
     * $enabled determines whether this module shows or not... in catalog.
     *
     * @var boolean
     */
    var $enabled;

    /**
     * log file folder
     *
     * @var string
     */
    var $_logDir = '';

    /**
     * vars
     */
    var $gateway_mode;
    var $reportable_submit_data;
    var $authorize;
    var $auth_code;
    var $transaction_id;
    var $order_status;
    var $gocoin_ipn_tbl;
    var $gocoin_ses_tbl;
    var $pay_url;

    /**
     * @return authorizenet
     */
    function gocoinpay() {

        global $order;
        $this->pay_url = 'https://gateway.gocoin.com/merchant/';
        $this->gocoin_ipn_tbl = DB_PREFIX . 'gocoin_ipn';
        $this->gocoin_ses_tbl = DB_PREFIX . 'gocoin_session';

        $this->code = 'gocoinpay';
        $this->domain = ($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER;
        $this->baseUrl = $this->domain . DIR_WS_CATALOG;

        $this->title = MODULE_PAYMENT_GOCOIN_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_GOCOIN_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_GOCOIN_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_GOCOIN_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_GOCOIN_STATUS == 'True') ? true : false);
        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
          $php_version_allowed = true ;
        }
        else{
          $php_version_allowed = false ;
        }
   
        if($php_version_allowed ==false){
            $this->enabled = false;
        }
        
        // $this->baseUrl        = $this->getBaseUrl();
        if ((int) MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID;
        }

        if (is_object($order))
            $this->update_status();
    }

    /**
     * Inserts the hidden variables in the HTML FORM required for SIM
     * Invokes hmac function to calculate fingerprint.
     *
     * @param string $loginid
     * @param string $txnkey
     * @param float $amount
     * @param string $sequence
     * @param float $currency
     * @return string
     */
    function InsertFP($loginid, $txnkey, $amount, $sequence, $currency = "") {
        $tstamp = time();
        $fingerprint = $this->hmac($txnkey, $loginid . "^" . $sequence . "^" . $tstamp . "^" . $amount . "^" . $currency);
        $security_array = array('x_fp_sequence' => $sequence,
            'x_fp_timestamp' => $tstamp,
            'x_fp_hash' => $fingerprint);
        return $security_array;
    }

    // end authorize.net-provided code
    // class methods
    /**
     * Calculate zone matches and flag settings to determine whether this module should display to customers or not
     */
    function update_status() {
        global $order, $db;

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_AUTHORIZENET_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_AUTHORIZENET_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    /**
     * JS validation which does error-checking of data-entry if this module is selected for use
     * (Number, Owner Lengths)
     *
     * @return string
     */
    function javascript_validation() {

        return false;
    }

    /**
     * Display Credit Card Information Submission Fields on the Checkout Payment Page
     *
     * @return array
     */
    function selection() {
        global $order;
        $pay_type[] = array('id' => 'BTC', 'text' => 'Bitcoin');
        $pay_type[] = array('id' => 'XDG', 'text' => 'Dogecoin');
        $pay_type[] = array('id' => 'LTC', 'text' => 'Litecoin');

        $selection = array('id' => $this->code,
            'module' => $this->title,
            'fields' => array(array('title' => MODULE_PAYMENT_GOCOIN_PAYTYPE,
                    'field' => zen_draw_pull_down_menu('pay_type', $pay_type),
        )));

        return $selection;
    }

    /**
     * Evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
     *
     */
    function pre_confirmation_check() {
        return false;
    }

    /**
     * Display Credit Card Information on the Checkout Confirmation Page
     *
     * @return array
     */
    function confirmation() {
        return false;
    }

    /**
     * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
     * This sends the data to the payment gateway for processing.
     * (These are hidden fields on the checkout confirmation page)
     *
     * @return string
     */
    function process_button() {
        $buttonArray[] = zen_draw_hidden_field('paytype', $_POST['pay_type']);
        $process_button_string = "\n" . implode("\n", $buttonArray) . "\n";
        return $process_button_string;
    }

    /**
     * Store the CC info to the order and process any results that come back from the payment gateway
     *
     */
    function before_process() {
        return false;
    }

    function after_process() {
        global $messageStack;
        global $insert_id, $db, $order, $sendto, $currency;

        $customer_id = $_SESSION['customer_id'];
        $coin_currency = isset($_POST['paytype']) && !empty($_POST['paytype']) ? $_POST['paytype'] : '';
        $customer = $order->billing['firstname'] . ' ' . $order->billing['lastname'];
        $callback_url = zen_href_link('gocoinnotify.php', '', 'SSL', false, false, true);
        $return_url = zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL');
        $error_log_path = DIR_FS_LOGS.'/gocoin_error.log';
        $errorlog       = '';

        $options = array(
            'price_currency' => $coin_currency,
            'base_price' => $order->info['total'],
            'base_price_currency' => "USD", //$order_info['currency_code'],
            'callback_url' => $callback_url,
            'redirect_url' => $return_url,
            'order_id' => $insert_id,
            'customer_name' => $customer,
            'customer_address_1' => $order->billing['street_address'],
            'customer_address_2' => '',
            'customer_city' => $order->delivery['city'],
            'customer_region' => $order->delivery['state'],
            'customer_postal_code' => $order->customer['postcode'],
            'customer_country' => $order->billing['country']['title'],
            'customer_phone' => $order->customer['telephone'],
            'customer_email' => $order->customer['email_address'],
        );

        $key                       = $this->getGUID();
        $signature                 = $this->getSignatureText($options, $key);
        $options['user_defined_8'] = $signature;

        $client_id      = MODULE_PAYMENT_GOCOIN_MERCHANT_ID;
        $client_secret  = MODULE_PAYMENT_GOCOIN_ACCESS_KEY;
        $access_token   = MODULE_PAYMENT_GOCOIN_TOKEN;
        $gocoin_url     = $this->pay_url;

        
        if (empty($access_token)) { //-----------If  Token not found 
            $result = 'error';
            $json['error'] = 'GoCoin Payment Paramaters not Set. Please report this to Site Administrator.';
            $errorlog      .= 'Access Token Blank';
        } else { // If  Token  found 
            try {
                $user = GoCoin::getUser($access_token); //----------- If no Error in user creation from token

                if ($user) { //----------- If user Variable  is  not blank 
                    $merchant_id = $user->merchant_id; 
                    if (!empty($merchant_id)) {//----------- If merchant_id Variable is not blank 
                        $invoice = GoCoin::createInvoice($access_token, $merchant_id, $options);

                        if (isset($invoice->errors)) { 
                            $result = 'error';
                            $errormsg = isset($invoice->errors->currency_code[0])? $invoice->errors->currency_code[0] : '';
                            $json['error'] = "Error in Processing Order using GoCoin:". $errormsg; 
                            $errorlog      .=  $errormsg;
                        } elseif (isset($invoice->error)) {
                            $result = 'error';
                            $json['error'] = "Error in Processing Order using GoCoin ".$invoice->error;
                            $errorlog      .=  $invoice->error;
                        } elseif (isset($invoice->merchant_id) && $invoice->merchant_id != '' && isset($invoice->id) && $invoice->id != '') {
                            $url = $gocoin_url . $invoice->merchant_id . "/invoices/" . $invoice->id;
                            $result = 'success';
                            $messages = 'success';
                            $json['success'] = $url;

                            $json_array = array(
                                'order_id' => $invoice->order_id,
                                'invoice_id' => $invoice->id,
                                'url' => $url,
                                'status' => 'invoice_created',
                                'btc_price' => $invoice->price,
                                'price' => $invoice->base_price,
                                'currency' => $invoice->base_price_currency,
                                'currency_type' => $invoice->price_currency,
                                'invoice_time' => $invoice->created_at,
                                'expiration_time' => $invoice->expires_at,
                                'updated_time' => $invoice->updated_at,
                                'fingerprint' => $signature,
                            );
                            $this->addTransaction_v1('payment', $json_array);
                        }
                        else { //-----------  if $invoice is balnk 
                            $result = 'error';
                            $json['error'] = 'Error in Processing Order using GoCoin';
                            $errorlog      .=  'invoice variable blank ';
                        }
                    }
                    else { //----------- If merchant_id Variable is blank 
                        $result = 'error';
                        $json['error'] = 'Error in Processing Order using GoCoin, please try selecting other payment options';
                        $errorlog      .=  'merchant_id variable blank ';
                    }
                } 
                else { //----------- If user Variable is blank 
                    $result = 'error';
                    $json['error'] = 'Error in Processing Order using GoCoin, please try selecting other payment options';
                    $errorlog      .=  'User variable blank ';
                }
            } 
            catch (Exception $e) {
                //----------- If  error in user creation from token
                $result = 'error';
                $json['error'] = 'Error in Processing Order using GoCoin, please try selecting other payment options';
                $errorlog      .=  'error in user creation from token';
            }
        }

        if (isset($json['error']) && $json['error'] != '') {
            error_log($date = date('d.m.Y h:i:s').':'.$json['error'].$errorlog.'\n', 3, $error_log_path);
            $messageStack->add_session('checkout_payment', $json['error'] . '<!-- [' . $this->code . '] -->', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        } else {
            zen_redirect($url);
        }

        return false;
    }

    /**
     * Check to see whether module is installed
     *
     * @return boolean
     */
    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_GOCOIN_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * Install the payment module and its configuration settings
     *
     */
    function install() {
        global $db, $messageStack;
        if (defined('MODULE_PAYMENT_GOCOIN_STATUS')) {
            $messageStack->add_session('Gocoin module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=gocoinpay', 'NONSSL'));
            return 'failed';
        }
        $btn_code = 'you can click button to get access token from gocoin.com <br><button style="" onclick="get_api_token(); return false;" class="scalable " title="Get API Token" id="btn_get_token"><span><span><span>Get API Token</span></span></span></button>';
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable GoCoin Method', 'MODULE_PAYMENT_GOCOIN_STATUS', 'False', 'Do you want to accept GoCoin Method payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Client ID', 'MODULE_PAYMENT_GOCOIN_MERCHANT_ID', '', '', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Client Secret', 'MODULE_PAYMENT_GOCOIN_ACCESS_KEY', '', '', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Access Token', 'MODULE_PAYMENT_GOCOIN_TOKEN', '', '', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_GOCOIN_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Deafult Order Status', 'MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID', '0', 'Set the status of prepared orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set GoCoin Acknowledged Order Status', 'MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('', 'MODULE_PAYMENT_GOCOIN_CREATE_TOKEN', '', '', '6', '0', 'zen_call_function(\'create_gocoin_token\', \'\',  ', now())");

        $this->getDbTable();
    }

    /**
     * Remove the module and all its settings
     *
     */
    function remove() {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    /**
     * Internal list of configuration keys used for configuration of the module
     *
     * @return array
     */
    function keys() {
        return array('MODULE_PAYMENT_GOCOIN_STATUS',
            'MODULE_PAYMENT_GOCOIN_MERCHANT_ID',
            'MODULE_PAYMENT_GOCOIN_ACCESS_KEY',
            'MODULE_PAYMENT_GOCOIN_TOKEN',
            'MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID',
            'MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID',
            'MODULE_PAYMENT_GOCOIN_SORT_ORDER',
            'MODULE_PAYMENT_GOCOIN_CREATE_TOKEN',
        );
    }

    public function getDbTable() {
        global $db;
        $sql_ipn = "CREATE TABLE IF NOT EXISTS `" . $this->gocoin_ipn_tbl . "` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `order_id` int(10) unsigned DEFAULT NULL,
                    `invoice_id` varchar(200) NOT NULL,
                    `url` varchar(400) NOT NULL,
                    `status` varchar(100) NOT NULL,
                    `btc_price` decimal(16,8) NOT NULL,
                    `price` decimal(16,8) NOT NULL,
                    `currency` varchar(10) NOT NULL,
                    `currency_type` varchar(10) NOT NULL,
                    `invoice_time` datetime NOT NULL,
                    `expiration_time` datetime NOT NULL,
                    `updated_time` datetime NOT NULL,
                    `fingerprint`  varchar(250) NOT NULL,
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

        $query = $db->Execute($sql_ipn);
    }

    public function getGUID() {
        if (function_exists('com_create_guid')) {
            $guid = com_create_guid();
            $guid = str_replace("{", "", $guid);
            $guid = str_replace("}", "", $guid);
            return $guid;
        } else {
            mt_srand((double) microtime() * 10000); //optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45); // "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                    . substr($charid, 8, 4) . $hyphen
                    . substr($charid, 12, 4) . $hyphen
                    . substr($charid, 16, 4) . $hyphen
                    . substr($charid, 20, 12); // .chr(125) //"}"
            return $uuid;
        }
    }

    public function getSignatureText($data, $uniquekey) {
        $query_str = '';
        $include_params = array('price_currency', 'base_price', 'base_price_currency', 'order_id', 'customer_name', 'customer_city', 'customer_region', 'customer_postal_code', 'customer_country', 'customer_phone', 'customer_email');
        if (is_array($data)) {
            ksort($data);
            $querystring = "";
            foreach ($data as $k => $v) {
                if (in_array($k, $include_params)) {
                    $querystring = $querystring . $k . "=" . $v . "&";
                }
            }
        } else {
            if (isset($data->payload)) {
                $payload_obj = $data->payload;
                $payload_arr = get_object_vars($payload_obj);
                ksort($payload_arr);
                $querystring = "";
                foreach ($payload_arr as $k => $v) {
                    if (in_array($k, $include_params)) {
                        $querystring = $querystring . $k . "=" . $v . "&";
                    }
                }
            }
        }
        $query_str = substr($querystring, 0, strlen($querystring) - 1);
        $query_str = strtolower($query_str);
        $hash2 = hash_hmac("sha256", $query_str, $uniquekey, true);
        $hash2_encoded = base64_encode($hash2);
        return $hash2_encoded;
    }
    
    public function addTransaction_v1($type = 'payment', $details) {
    global $db;
    return $db->Execute("
          INSERT INTO " . DB_PREFIX . "gocoin_ipn (order_id, invoice_id, url, status, btc_price,
          price, currency, currency_type, invoice_time, expiration_time, updated_time,fingerprint)
          VALUES ( 
              '" . $db->prepare_input($details['order_id']) . "',
              '" . $db->prepare_input($details['invoice_id']) . "',
              '" . $db->prepare_input($details['url']) . "',
              '" . $db->prepare_input($details['status']) . "',
              '" . $db->prepare_input($details['btc_price']) . "',
              '" . $db->prepare_input($details['price']) . "',
              '" . $db->prepare_input($details['currency']) . "',
              '" . $db->prepare_input($details['currency_type']) . "',
              '" . $db->prepare_input($details['invoice_time']) . "',
              '" . $db->prepare_input($details['expiration_time']) . "',
              '" . $db->prepare_input($details['updated_time']) . "' ,
              '" . $db->prepare_input($details['fingerprint']) . "' )");
}

}

function create_gocoin_token() {

    if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
          $php_version_allowed = true ;
      }
    else{
          $php_version_allowed = false ;
      }
   
    $domain = ($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER;
    $baseUrl = $domain . DIR_WS_CATALOG;
    $str = '<input type="hidden" id="cid" value="'.MODULE_PAYMENT_GOCOIN_MERCHANT_ID.'"/>
                <input type="hidden" id="csec" value="'.MODULE_PAYMENT_GOCOIN_ACCESS_KEY.'"/><b>you can click button to get access token from gocoin.com</b><input type="button" value="Get API TOKEN" onclick="return get_api_token();">';
   
     if($php_version_allowed ==false){
              $str.='<br><div style="color: #ff0000;font-weight: bold;">The minimum PHP version required for GoCoin plugin is 5.3.0 </div>'; 
         }
         
    $str.= '<script type="text/javascript">
            var base ="' . $baseUrl . '";
            function get_api_token()    
            {
                    var client_id = "";
                     var client_secret ="";
                        var elements = document.forms["modules"].elements;
                        for (i=0; i<elements.length; i++){
                            if(elements[i].name=="configuration[MODULE_PAYMENT_GOCOIN_MERCHANT_ID]"){
                                client_id = elements[i].value;
                            }
                            if(elements[i].name=="configuration[MODULE_PAYMENT_GOCOIN_ACCESS_KEY]"){
                                client_secret =  elements[i].value;
                            }

                        }

                    if (!client_id) {
                        //alert("Please input "+mer_id+" !");
                        alert("Please input Client Id !");
                        return false;
                    }
                    if (!client_secret) {
                       // alert("Please input "+access_key+" !");
                        alert("Please input Client Secret Key !");
                        return false;
                    }
                    

                    var cid = document.getElementById("cid").value;
                    var csec = document.getElementById("csec").value;
                    if (client_id != cid || client_secret != csec) {
                       alert("Please save changed Client Id and Client Secret Key first!");
                       return;
                    }

                    var currentUrl =  base+ "gocointoken.php";
                    var url = "https://dashboard.gocoin.com/auth?response_type=code"
                                + "&client_id=" + client_id
                                + "&redirect_uri=" + currentUrl
                                + "&scope=user_read+invoice_read_write";
                                

                    var strWindowFeatures = "location=yes,height=570,width=520,scrollbars=yes,status=yes";
                    var win = window.open(url, "_blank", strWindowFeatures);
                    return false;
                }</script>';
    return $str;
}

