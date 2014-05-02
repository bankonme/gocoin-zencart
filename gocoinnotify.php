<?php
require('includes/application_top.php');
if (!defined('MODULE_PAYMENT_GOCOIN_STATUS') || (MODULE_PAYMENT_GOCOIN_STATUS != 'True')) {
    exit;
}
//error_log('/******************************************************/\n'.date('h:i:s A').file_get_contents("php://input"),3,'tester.log');
function callback() {
    global $db;
    _paymentStandard();
}

function getNotifyData() {
    $post_data = file_get_contents("php://input");
    //error_log('\n'.$post_data,3,'tester.log');
    if (!$post_data) {
        $response = new stdClass();
        $response->error = 'Post Data Error';
        return $response;
    }
    $response = json_decode($post_data);
    return $response;
}

function _paymentStandard() {
    global $db;
    $sts_default = MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID; // Default
    $sts_processing = MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID; // Processing

    $module_display = 'gocoin';
    $response = getNotifyData();

    $error = 0;
    if (!$response) {
        $error = $error + 1;
        $error_msg[] = ' NotifyData Blank';
        
        //======================Error=============================     
    }
    if (isset($response->error) && $response->error != '') {
        $error = $error + 1;
        $error_msg[] = $response->error;
        
    }
    if (isset($response->payload)) {

        //======================IF Response Get=============================     
        $event = $response->event;
        $order_id               = (int) $response->payload->order_id;
        $redirect_url           = $response->payload->redirect_url;
        $transction_id          = $response->payload->id;
        $total                  = $response->payload->base_price;
        $status                 = $response->payload->status;
        $currency_id            = $response->payload->user_defined_1;
        $secure_key             = $response->payload->user_defined_2;
        $currency               = $response->payload->base_price_currency;
        $currency_type          = $response->payload->price_currency;
        $invoice_time           = $response->payload->created_at;
        $expiration_time        = $response->payload->expires_at;
        $updated_time           = $response->payload->updated_at;
        $merchant_id            = $response->payload->merchant_id;
        $btc_price              = $response->payload->price;
        $price                  = $response->payload->base_price;
        $url                    = "https://gateway.gocoin.com/merchant/" . $merchant_id . "/invoices/" . $transction_id;
        $fprint                 = $response->payload->user_defined_8;

        if ($order_id > 0) {
            $iArray = array(
                'order_id' => $order_id,
                'invoice_id' => $transction_id,
                'url' => $url,
                'status' => $event,
                'btc_price' => $btc_price,
                'price' => $price,
                'currency' => $currency,
                'currency_type' => $currency_type,
                'invoice_time' => $invoice_time,
                'expiration_time' => $expiration_time,
                'updated_time' => $updated_time,
                'fingerprint'       => $fprint);
                        
              $i_id =  getFPStatus($iArray);
               if(!empty($i_id) && $i_id==$transction_id){
                    updateTransaction('payment', $iArray);

                    switch ($event) {
                        case 'invoice_created':
                        case 'invoice_payment_received':
                            break;
                        case 'invoice_ready_to_ship':
                            if (isset($order_id) && is_numeric($order_id) && ($order_id > 0)) {
                                $cur_sts = $sts_processing;
                                $order_query = $db->Execute("select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" .(int) $order_id . "'");

                                if ($order_query->RecordCount() > 0) {
                                    if ($order_query->fields['orders_status'] == MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID) {
                                        if (($status == 'paid') || ($status == 'ready_to_ship')) {
                                            $sts1 = (MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID);
                                            $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $db->prepare_input($sts1) . "', last_modified = now() where orders_id = '" . (int) $order_id . "'");
                                            $comment_status = $status;

                                            $sql_his = "insert into " . TABLE_ORDERS_STATUS_HISTORY . "(orders_id,orders_status_id,date_added,customer_notified,comments)values('" . $db->prepare_input($order_id) . "','" . $db->prepare_input($sts1) . "',now(),'0','" . $db->prepare_input($comment_status) . "' )";
                                            $db->Execute($sql_his);
                                        }
                                    }
                                }
                            }
                            break;
                    }
               }
            
        } else {
           
        }

        //=================== Set To Array=====================================//
        //Used for adding in db
    }

    if ($error > 0) {
        $email_body = @implode('<br>', $error_msg);
    }
}

function getFPStatus($details){
    global $db;
            $query = $db->Execute($sql="SELECT invoice_id FROM  gocoin_ipn where
            invoice_id = '".$db->prepare_input($details['invoice_id'])."' and   
            fingerprint = '".$db->prepare_input($details['fingerprint'])."'  ");
            if($query->RecordCount() > 0){
                    return $query->fields['invoice_id'];
            }
 }  
 
function updateTransaction($type = 'payment', $details) {
    global $db;
    return $db->Execute("
        update  gocoin_ipn set 
            status           = '" . $db->prepare_input($details['status']) . "',   
            updated_time     = '" . $db->prepare_input($details['updated_time']) . "'   where
            invoice_id       = '" . $db->prepare_input($details['invoice_id']) . "' and   
            order_id         = '" . $db->prepare_input($details['order_id']) . "'       
         ");
}

callback();
require('includes/application_bottom.php');
?>
