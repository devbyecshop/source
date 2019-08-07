<?php

/**
 * ECSHOP 银联在线支付
 * ============================================================================
 * 版权所有 2005-2018 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: maxiaochen $
 * $Id: chinapay.php 2018-12-04 20:18:00Z maxiaochen $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

// 包含配置文件
$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/chinapay.php';

if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'chinapay_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'ECSHOP TEAM';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.ecshop.com';

    /* 版本号 */
    $modules[$i]['version'] = '20140728';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('name' => 'MerId', 'type' => 'text', 'value' => ''), // 这个的顺序尽量不要修改 admin/payment.php里 证书文件是否处理的时候简单的判断第一个的value
        array('name' => 'chinapay_pfx', 'type' => 'file', 'value' => ''),
        array('name' => 'chinapay_pfx_pwd', 'type' => 'text', 'value' => ''),
        array('name' => 'chinapay_cer', 'type' => 'file', 'value' => ''),
    );

    return;
}

/**
 * 类
 * chinapay 20140728
 * signMethod 默认使用01 证书方式
 * 如果需要用sha256方式的签名密钥 signMethod配置11（配置业务邮件发送的密钥 ，测试环境固定88888888）
 * 由于银联的version、signMethod多种搭配 ecshop银联支付的后台配置暂时不做太多的配置选择 否则客户配置时可能会十分凌乱 有需要可以单独修改当前页的配置 
 */
class CHINAPAY
{
    private $version = '20140728';  // 报文版本号
    private $payment; // 支付方式配置信息
    private $separator = '9876543'; // 分隔订单order_sn和log_id用

    /**
     * 生成支付代码
     * @param   array   $order  订单信息
     * @param   array   $payment    支付方式信息
     */

    function get_code($order, $payment){
        $params = array(
            'MerId'             =>  $payment['MerId'], //商户号
            'MerOrderNo'        =>  substr(time(),-5).$order['order_sn'].$this->separator.$order['log_id'], //订单号
            'OrderAmt'          =>  $order['order_amount'] * 100, //订单金额 ,单位：分
            'TranDate'          =>  date('Ymd'), //交易日期 8位数字，为订单提交日期
            'TranTime'          =>  date('His'), //交易时间 6位数字，为订单提交时间
            'TranType'          =>  '', //交易类型 4位数字，网银支付交易为0001，如果商户不填写，ChinaPay会在持卡人页面显示商户已开通的交易类型供持卡人选择，完成支付
            'BusiType'          =>  '0001', //业务类型 4位数字，固定值：0001
            'Version'           =>  $this->version, //版本号
            // 'SplitType'         =>  '', //分账类型 4位数字,不分账不填写此域；填写规则[0001:实时分账;0002:延时分账]
            // 'SplitMethod'       =>  '', //分账方式 1位数字,不分账不填写此域；填写规则[0:按金额分账;1:按比例分账]
            // 'MerSplitMsg'       =>  '', //分账公式 不分账不填写此域；填写规则[商户号或者费用类型^金额或者占用比例;商户号或者费用^金额或者占用比例]

            // 'BankInstNo'        =>  '', //支付机构号 15位，可以为空    ????
            // 'PayTimeOut'        =>  '', // 支付超时时间 5位，可以为空 ，单位：分钟   ??????
            // 'TimeStamp'         =>  '', // 商户系统时间戳 13位，可以为空 ，单位：毫秒   ?????
            'RemoteAddr'        =>  $_SERVER['REMOTE_ADDR'], //商户客户端IP 可以为空 ，商户开通防钓鱼时必填，单位：分钟
            'CurryNo'           =>  'CNY', //交易币种 3位，默认为CNY 人民币
            'AccessType'        =>  '0', //接入类型 1位数字，默认:0,表示接入类型[0:以商户身份接入；1:以机构身份接入]
            'AcqCode'           =>  '', //收单机构号 15位数字     ????
            'CommodityMsg'      =>  '', //商品信息描述 购买商品的信息的描述    ???
            'MerPageUrl'        =>  return_url(basename(__FILE__, '.php')), //页面应答接收URL 不超过80字节，商户系统前台应答接受地址
            'MerBgUrl'          =>  return_url(basename(__FILE__, '.php')), //后台应答接收URL 不超过80字节，商户系统后台应答接受地址
            // 'MerResv'           =>  '', //商户私有域 英文或数字，不超过60字节，ChinaPay将原样返回给商户系统该字段填入的数据 ?????
            // 'trans_BusiId'      =>  '', //业务ID 可以为空,需要在chinapay开通业务Id编号  ?????
            // 'trans_P1'          =>  '', //参数1
            // 'trans_P2'          =>  '', //参数2
        );
        $params = $this->sign($params);
        // echo "<pre>";print_r($params);exit();
        // echo "<pre>";print_r($payment);print_R($order);exit();

        require_once('chinapay_util/Settings_INI.php');
        $settings = new Settings_INI();
        $settings->load(ROOT_PATH ."cert/path.properties");
        $front_pay_url = $settings->get("pay_url");
        
        $button = "<input type='submit' value='" . $GLOBALS['_LANG']['chinapay_button'] . "' />";
        $html = $this->create_html($params,$front_pay_url,$button);
        return $html;
    }

    /**
     * 响应操作
     */
    function respond(){
        $this->logInfo('respond:'.json_encode($_POST));
        if (isset($_POST['code'])) unset($_POST['code']);

        $this->payment = get_payment('chinapay');

        $arr_ret = $_POST;

        // 验证签名
        if (!$this->validate($arr_ret)) {
            return false;
        }

        if ($arr_ret['OrderStatus'] != '0000') {
            $this->logInfo('pay false. OrderStatus:'.$arr_ret['OrderStatus']);
            return false;
        }
        $order_sn_arr = explode($this->separator, $arr_ret['MerOrderNo']);
        $order_sn    = substr($order_sn_arr['0'],5,13);
        $pay_id = intval($order_sn_arr['1']);
        $payment_amount = intval($arr_ret['OrderAmt']);
      
        // 检查商户账号是否一致。
        if ($this->payment['MerId'] != $arr_ret['MerId'])
        {
            $this->logInfo( 'local_MerId:' . $this->payment['MerId'] . ', merId:' . $arr_ret['MerId'] );
            return false;
        }
        // 检查价格是否一致
        if (!check_money($pay_id, $payment_amount/100))
        {
            $this->logInfo('pay_id:' . $pay_id . ' 金额不一致, ' .  $payment_amount/100);
            return false;
        }
        
        $action_note = 'pay_status:' 
                . $arr_ret['OrderStatus'].';'
                . $GLOBALS['_LANG']['chinapay_txn_id'] . ':' 
                . $arr_ret['MerOrderNo'];

        // 完成订单。
        order_paid($pay_id, PS_PAYED, $action_note);

        //告诉用户交易完成
        return true;

    }

    function create_html($params,$front_pay_url,$button){
        $html = <<<eot
    <br />
    <form style="text-align:center;" id="pay_form" name="pay_form" action="{$front_pay_url}" method="post" target="_blank">
eot;

        $params_info = "TranReserved;MerId;MerOrderNo;OrderAmt;CurryNo;TranDate;SplitMethod;BusiType;MerPageUrl;MerBgUrl;SplitType;MerSplitMsg;PayTimeOut;MerResv;Version;BankInstNo;CommodityMsg;Signature;AccessType;AcqCode;OrderExpiryTime;TranType;RemoteAddr;Referred;TranTime;TimeStamp;CardTranData";
        foreach ($params as $k => $v) {
            if (strstr($params_info, $k)) {
                $html .=  "<input type='hidden' name = '" . $k . "' value ='" . $v . "'/>";
            }
        }

        $html .= $button . "</form><br />";
        return $html;
    }

    /**
     * 签名
     * @param  array $params
     * @return 
     */
    function sign(&$params){
        include_once('chinapay_util/common.php');
        include_once('chinapay_util/SecssUtil.class.php');
        define(cardResveredKey, 'cardResvered'); //银行的demo里没有给出定义（调用常量但是没有定义）

        $transResvedJson = array();
        $cardInfoJson = array();
        $sendMap = array();
        foreach ($params as $key => $value) {
            if (isEmpty($value)) {
                continue;
            }
            if (startWith($key, 'trans_')) {
                $key = substr($key, strlen('trans_'));
                $transResvedJson[$key] = $value;
            } elseif (startWith($key, 'card_')) {
                $key = substr($key, strlen('card_'));
                $cardInfoJson[$key] = $value;
            } else {
                $sendMap[$key] = $value;
            }
        }
        $transResvedStr = null;
        $cardResvedStr = null;
        if (count($transResvedJson) > 0) {
            $transResvedStr = json_encode($transResvedJson);
        }
        if (count($cardInfoJson) > 0) {
            $cardResvedStr = json_encode($cardInfoJson);
        }

        $secssUtil = new SecssUtil();
        
        if (!isEmpty($transResvedStr)) {
            $transResvedStr = $secssUtil->decryptData($transResvedStr);
            $sendMap['TranReserved'] = $transResvedStr;
        }
        if (!isEmpty($cardResvedStr)) {
            $cardResvedStr = $secssUtil->decryptData($cardResvedStr);
            $sendMap[cardResveredKey] = $cardResvedStr;
        }
        
        $securityPropFile = ROOT_PATH."cert/security.properties";
        // print_r($securityPropFile);exit();
        $secssUtil->init($securityPropFile);
        $secssUtil->sign($sendMap);
        
        $sendMap['Signature'] = $secssUtil->getSign();
        // echo "<pre>";print_r($sendMap);exit();
        return $sendMap;
    }


    /**
     * 验签
     * @param   array   $params  应答数组
     * @return  boolean
     */
    function validate($params) {
        if (strpos($params['Signature'], '%2F') !== false) {
            $params['Signature'] = urldecode($params['Signature']);
        }
        include('chinapay_util/common.php');
        include('chinapay_util/SecssUtil.class.php');

        $secssUtil = new SecssUtil();
        $securityPropFile = ROOT_PATH . "cert/security.properties";
        $secssUtil->init($securityPropFile);
        if ($secssUtil->verify($params)) {
            $isSuccess = true;
        } else {
            $this->logInfo('validate is false');
            $isSuccess = false;
        }
        return $isSuccess;
    }


    function logInfo($data = ''){
        error_log(date("c")."\t".print_r($data, 1)."\t\n", 3, LOG_DIR."/chinapay_".date("Y-m-d",time()).".log");
    }

/** respond 
Array
(
    [TranType] => 0001
    [OrderStatus] => 0000
    [TranDate] => 20181206
    [AcqDate] => 20181206
    [RemoteAddr] => 180.169.8.10
    [CurryNo] => CNY
    [MerOrderNo] => 955672018120604254987654360
    [OrderAmt] => 1
    [Signature] => CA2GdllDoNJiQeIg5wM95z7y4sjtw6rQ2DhKVE5aoWPEwzUNbFK9+Z5nUlle//uXlk/EP8XVPsc71B5JCKyfcLyxhpw74EqL5jHvT7P81P9E3nVUFfWYrU2cAqWmuIjEnY2znnFfE9zVO1UwrWj0H8yardnKN7WMSm90EJtt7Ck=
    [BusiType] => 0001
    [CompleteTime] => 192559
    [BankInstNo] => 700000000000017
    [AcqSeqId] => 0000001129186911
    [TranTime] => 192607
    [MerId] => 739411805290003
    [Version] => 20140728
    [CompleteDate] => 20181206
)
 */



}
?>