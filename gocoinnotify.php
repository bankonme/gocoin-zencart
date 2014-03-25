<?php
    require('includes/application_top.php');
    if (!defined('MODULE_PAYMENT_GOCOIN_STATUS') || (MODULE_PAYMENT_GOCOIN_STATUS != 'True')) {
        exit;
    }

    function callback() {
        global $db;
        _paymentStandard();
    }

    function getNotifyData() {
        $post_data = file_get_contents("php://input");

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
            die('error');
            //======================Error=============================     
        }
        if (isset($response->error) && $response->error != '') {
            $error = $error + 1;
            $error_msg[] = $response->error;
            die('error');
        }
        if (isset($response->payload)) {

            //======================IF Response Get=============================     
            $event = $response->event;
            $order_id1 = (int) $response->payload->order_id;
            $redirect_url = $response->payload->redirect_url;
            $transction_id = $response->payload->id;
            $total = $response->payload->base_price;
            $status = $response->payload->status;
            $currency_id = $response->payload->user_defined_1;
            $secure_key = $response->payload->user_defined_2;
            $currency = $response->payload->base_price_currency;
            $currency_type = $response->payload->price_currency;
            $invoice_time = $response->payload->created_at;
            $expiration_time = $response->payload->expires_at;
            $updated_time = $response->payload->updated_at;
            $merchant_id = $response->payload->merchant_id;
            $btc_price = $response->payload->price;
            $price = $response->payload->base_price;
            $url = "https://gateway.gocoin.com/merchant/" . $merchant_id . "/invoices/" . $transction_id;


            switch ($status) {
                case 'paid':
                    $cur_sts = $sts_processing;

                    break;

                default:
                    $cur_sts = $sts_default;
                    break;
            }
            $sql_1 = "select * from " . DB_PREFIX . 'gocoin_session' . " where id = '" . $order_id1 . "' ";

            $gocoin_ses = $db->Execute($sql_1);
            $num = $gocoin_ses->RecordCount();
            if ($num > 0) {
                $order_id = $gocoin_ses->fields['order_id'];
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
                        'updated_time' => $updated_time);
                    if ($event == 'invoice_created') {
                        if (isset($order_id) && is_numeric($order_id) && ($order_id > 0)) {
                           
                            $order_query = $db->Execute("select orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $order_id ."'");
                           
                            if ($order_query->RecordCount() > 0) {
                                if ($order_query->fields['orders_status'] == MODULE_PAYMENT_GOCOIN_DEFAULT_ORDER_STATUS_ID) {

                                    if ($status == 'paid') {
                                        $sts1= (MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_GOCOIN_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID);
                                        $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" .$sts1."', last_modified = now() where orders_id = '" . (int) $order_id . "'");
                                        $comment_status = $status;
                                        
                                        $sql_his = "insert into ".TABLE_ORDERS_STATUS_HISTORY."(orders_id,orders_status_id,date_added,customer_notified,comments)values('".$order_id."','".$sts1."',now(),'0','".$comment_status."' )";
                                        $db->Execute($sql_his);
                                    }
                                }



                            }
                        }
                        addTransaction('payment', $iArray);
                    } else {

                    }
                } else {
                    die('error');
                }
            } else {
                die('error');
            }
            //=================== Set To Array=====================================//
            //Used for adding in db
        }

        if ($error > 0) {
            $email_body = @implode('<br>', $error_msg);
        }
    }

    function addTransaction($type = 'payment', $details) {
        global $db;
        return $db->Execute("
          INSERT INTO ".DB_PREFIX."gocoin_ipn (order_id, invoice_id, url, status, btc_price,
          price, currency, currency_type, invoice_time, expiration_time, updated_time)
          VALUES ( 
              '" . $details['order_id'] . "',
              '" . $details['invoice_id'] . "',
              '" . $details['url'] . "',
              '" . $details['status'] . "',
              '" . $details['btc_price'] . "',
              '" . $details['price'] . "',
              '" . $details['currency'] . "',
              '" . $details['currency_type'] . "',
              '" . $details['invoice_time'] . "',
              '" . $details['expiration_time'] . "',
              '" . $details['updated_time'] . "' )");
    }

    callback();
    require('includes/application_bottom.php');
    ?>