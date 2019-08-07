<?php

/**
 * ECSHOP 订单自动确认收货
 * ===========================================================
 * * 版权所有 2005-2018 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ==========================================================
 * $Author: liubo $
 * $Id: auto_confirm.php 17217 2019-05-28 liubo $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}
$cron_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/cron/auto_confirm.php';
if (file_exists($cron_lang))
{
    global $_LANG;

    include_once($cron_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'auto_confirm_desc';

    /* 作者 */
    $modules[$i]['author']  = 'ECSHOP TEAM';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.ecshop.com';

    /* 版本号 */
    $modules[$i]['version'] = '1.0.0';

    /* 配置信息 */
    $modules[$i]['config']  = array(
        array('name' => 'auto_confirm_day', 'type' => 'select', 'value' => '7'),
    );

    return;
}
confirm_log("begin");
empty($cron['auto_confirm_day']) && $cron['auto_confirm_day'] = 7;
include_once(ROOT_PATH . 'includes/lib_transaction.php');
$confirmtime = gmtime() - $cron['auto_confirm_day'] * 3600 * 24;
$order_status = [OS_SPLITED];
$pay_status = [PS_UNPAYED,PS_PAYED];
$shipping_status = [SS_SHIPPED];
$where = "order_status in (".implode(',', $order_status).") AND pay_status in (".implode(',', $pay_status).") AND shipping_status in (".implode(',', $shipping_status).") AND shipping_time < ".$confirmtime;
$sql = "SELECT count(*) FROM ".$ecs->table('order_info') ." WHERE ".$where;
$count = $db->GetOne($sql);
confirm_log("select_count:".$count);
$page_size = 1;
$total = ceil($count/$page_size);
$i = 1;
while ( $i<= $total) {
    $sql = "SELECT order_id FROM ".$ecs->table('order_info') ." WHERE ".$where." limit 0,".$page_size;
    confirm_log("select_sql:".$sql);
    $rows = $db->getAll($sql);
    confirm_log("select_rows:",$rows);
    foreach ($rows as $key => $order) {
        $order_id = isset($order['order_id']) ? intval($order['order_id']) : 0;
        affirm_received_auto($order_id,$msg);
        confirm_log("finish_order_id:".$order_id."|msg:".$msg);
    }
    $i++;
}

function confirm_log($msg,$data=null){
    error_log(date("c")."\t".$msg."\t".stripslashes(json_encode($data))."\t\n",3,LOG_DIR."/auto_confirm_".date("Y-m-d").".log");
}

?>