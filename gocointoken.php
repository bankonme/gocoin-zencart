<?php

require('includes/application_top.php');
if (!defined('MODULE_PAYMENT_GOCOIN_STATUS') || (MODULE_PAYMENT_GOCOIN_STATUS != 'True')) {
    exit;
}
include DIR_WS_INCLUDES . 'gocoinlib/src/GoCoin.php';

function shoGocoinToken() {
    $code = isset($_REQUEST['code']) && !empty($_REQUEST['code'])? $_REQUEST['code']:'';
    $client_id = MODULE_PAYMENT_GOCOIN_MERCHANT_ID;
    $client_secret = MODULE_PAYMENT_GOCOIN_ACCESS_KEY;

    try {
        $token = GoCoin::requestAccessToken($client_id, $client_secret, $code, null);
        echo "<b>Copy this Access Token into your GoCoin Module: </b><br>" . $token;
    } catch (Exception $e) {
        echo "Problem in getting Token: " . $e->getMessage();
    }
    die();
}

shoGocoinToken();
require('includes/application_bottom.php');
?>