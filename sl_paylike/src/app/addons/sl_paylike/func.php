<?php
/**
 * @copyright 2019 Paylike.io.
 * @author Panos <panos@Paylike.com>
 * Date: 16/9/2019
 * Time: 10:10 πμ
 */

function sl_paylike_currencies() {
    return \Paylike\Currency::getCurrenciesList();
}

function sl_paylike_get_order_statuses_list() {
    $statuses = fn_get_statuses();
    $data = array();
    foreach ($statuses as $k => $status) {
        $data[$k] = $status['description'];
    }
    return $data;
}

function fn_sl_paylike_change_order_status($status_to, $status_from, &$order_info, $force_notification, $order_statuses, $place_order) {
    $doCapture = false;
    $doVoid = false;
    $txnId = false;
    if($order_info['payment_method']['processor']=='Paylike') {
        if($order_info['payment_method']['processor_params']['checkout_mode']=='delayed') {
            if($order_info['payment_method']['processor_params']['capture_status']==$status_to && $order_info['payment_method']['processor_params']['delayed_status']==$status_from) {
                $captured = !empty($order_info['payment_info']['captured']) ? $order_info['payment_info']['captured'] : 'Y';
                $txnId = !empty($order_info['payment_info']['transaction_id']) ? $order_info['payment_info']['transaction_id'] : '';
                if ($captured == 'N' && !empty($txnId)) {
                    $doCapture = true;
                }
            }
            elseif($order_info['payment_method']['processor_params']['void_status']==$status_to && $order_info['payment_method']['processor_params']['delayed_status']==$status_from) {
                $captured = !empty($order_info['payment_info']['captured']) ? $order_info['payment_info']['captured'] : 'Y';
                $txnId = !empty($order_info['payment_info']['transaction_id']) ? $order_info['payment_info']['transaction_id'] : '';
                if ($captured == 'N' && !empty($txnId)) {
                    $doVoid = true;
                }
            }
        }
    }
    if($doCapture) {
        $cc = new \Paylike\CaptureDelayed();
        $cc->capture($order_info, $txnId);
    }
    elseif($doVoid) {
        $cc = new \Paylike\CaptureDelayed();
        $cc->void($order_info, $txnId);
    }
}

function sl_paylike_can_refund_order($order_info) {
    $out = false;
    if($order_info['payment_method']['processor']=='Paylike') {
        $captured = !empty($order_info['payment_info']['captured']) ? $order_info['payment_info']['captured'] : 'Y';
        $refunded = !empty($order_info['payment_info']['refunded']) ? $order_info['payment_info']['refunded'] : 'N';
        $txnId = !empty($order_info['payment_info']['transaction_id']) ? $order_info['payment_info']['transaction_id'] : '';
        $captured_amount = !empty($order_info['payment_info']['amount_capt']) ? floatval($order_info['payment_info']['amount_capt']) : 0;
        $refunded_amount = !empty($order_info['payment_info']['amount_refu']) ? floatval($order_info['payment_info']['amount_refu']) : 0;
        if ($captured == 'Y' && !empty($txnId) && $captured_amount>0 && ($refunded=='N' || ($refunded=='Y' && $refunded_amount<$captured_amount))) {
            $out = true;
        }
    }
    return $out;
}

function sl_paylike_delete_payment_processors()
{
    db_query("UPDATE ?:payments SET processor_id = 0, processor_params='', status='D' WHERE processor_id IN (SELECT processor_id FROM ?:payment_processors WHERE processor_script IN ('paylike.php'))");
    db_query("DELETE FROM ?:payment_processors WHERE processor_script IN ('paylike.php')");
}
