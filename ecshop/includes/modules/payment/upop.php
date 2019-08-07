<?php

/**
 * ECSHOP 中国银联支付
 * ============================================================================
 * 版权所有 2005-2018 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: douqinghua $
 * $Id: upop.php 2018-11-15 11:04:00Z pangxp $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

// 包含配置文件
$payment_lang = ROOT_PATH . 'languages/' .$GLOBALS['_CFG']['lang']. '/payment/upop.php';

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
    $modules[$i]['desc']    = 'upop_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'ECSHOP TEAM';

    /* 网址 */
    $modules[$i]['website'] = 'http://www.ecshop.com';

    /* 版本号 */
    $modules[$i]['version'] = '5.1.0';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('name' => 'upop_sign_method', 'type' => 'select', 'value' => ''), // 这个的顺序尽量不要修改 admin/payment.php里 证书文件是否处理的时候简单的判断第一个的value
        // array('name' => 'upop_merAbbr', 'type' => 'text', 'value' => '商户名称'),
        array('name' => 'upop_account', 'type' => 'text', 'value' => ''),
        array('name' => 'upop_cert_pwd', 'type' => 'text', 'value' => ''),
        array('name' => 'upop_cert', 'type' => 'file', 'value' => ''),
    );

    return;
}

/**
 * 类
 * upop 5.1.0
 * signMethod 默认使用01 证书方式
 * 如果需要用sha256方式的签名密钥 signMethod配置11（配置业务邮件发送的密钥 ，测试环境固定88888888）
 * 由于银联的version、signMethod多种搭配 ecshop银联支付的后台配置暂时不做太多的配置选择 否则客户配置时可能会十分凌乱 有需要可以单独修改当前页的配置 
 */
class UPOP
{
    private $version = '5.1.0';  // 报文版本号
    private $signMethod = '01';  // 签名方式
    private $ifValidateCNName = false; //是否验证验签证书的CN，测试环境请设置false，生产环境请设置true。非false的值默认都当true处理。
    private $company = "中国银联股份有限公司";
    private $payment; // 支付方式配置信息
    private static $verifyCerts510 = array(); 

    /**
     * 生成支付代码
     * @param   array   $order  订单信息
     * @param   array   $payment    支付方式信息
     */

    function get_code($order, $payment)
    {
        $this->payment = $payment;
        // 如果设置的是密钥方式
        if ($this->payment['upop_sign_method'] == '1') {
            $this->signMethod = '11';
        }
        // 初始化变量
        if (!defined('EC_CHARSET'))
        {
            $charset = 'UTF-8';
        }
        else
        {
            $charset = strtoupper(EC_CHARSET);
        }

        // $front_pay_url         = 'https://gateway.test.95516.com/gateway/api/frontTransReq.do'; // 测试使用
        $front_pay_url         = 'https://gateway.95516.com/gateway/api/frontTransReq.do'; // 正式地址
        $security_key          = $payment['upop_security_key'];
        $merId                 = $payment['upop_account'];
        $orderNumber           = $order['order_sn'] . 'op' . $this->_formatSN($order['log_id']);	 //银联限制 8-32位数字字母，不能含“-”或“_”  所以改用$order_sn的$pay_id两个变量的缩写op作为分割符;
        $frontEndUrl           = return_url(basename(__FILE__, '.php'));
        $backEndUrl            = return_url(basename(__FILE__, '.php'));
        
        // $merAbbr               = $payment['upop_merAbbr'];

        $params = array(
                "version"            =>  $this->version,                      //接口版本
                "signMethod"         =>  $this->signMethod,                   //签名方法
                "encoding"           =>  $charset,                     //编码
                'txnType'            => '01',                         //交易类型
                'txnSubType'         => '01',                                  //交易子类
                'bizType'            => '000201',                                 //业务类型
                'channelType'        => '08',                //渠道类型，07-PC，08-手机
                'accessType'         => '0',                  //接入类型
                'currencyCode'       => '156',              //交易币种，境内商户固定156
                "merId"              =>  $merId,                       //收款账号
                "orderId"            =>  $orderNumber,                 //订单号，必须唯一，8-32位数字字母，不能含“-”或“_”
                'txnTime'            =>  date('YmdHis'), //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
                "txnAmt"             =>  $order['order_amount'] * 100, //交易金额 转化为分
                "frontUrl"           =>  $frontEndUrl,                 // 前台回调URL
                "backUrl"            =>  $backEndUrl,                  // 后台回调URL
                // 订单超时时间。
                // 超过此时间后，除网银交易外，其他交易银联系统会拒绝受理，提示超时。 跳转银行网银交易如果超时后交易成功，会自动退款，大约5个工作日金额返还到持卡人账户。
                // 此时间建议取支付时的北京时间加15分钟。
                // 超过超时时间调查询接口应答origRespCode不是A6或者00的就可以判断为失败。
                'payTimeout' => date('YmdHis', strtotime('+15 minutes')), 
                
        );
        $this->sign($params);
        
        $button = "<input type='submit' value='" . $GLOBALS['_LANG']['upop_button'] . "' />";
        $html = $this->create_html($params,$front_pay_url,$button);

        return $html;
    }

    /**
     * 响应操作
     */
    function respond()
    {
        if ( @constant('DEBUG_API') ) {
            error_log(date("c")."\t".var_export($_POST, 1)."\t\n", 3, LOG_DIR."/upop_".date("Y-m-d",time()).".log");
        }

        $this->payment        = get_payment('upop');

        $arr_ret = $_POST;

        // 验证签名
        if (!$this->validate($arr_ret)) {
            return false;
        }

        if ($arr_ret['respCode'] != '00') 
        {
            return false;
        }
        if(!strpos($arr_ret['orderId'], 'op')) 
        {
            return false;
        }
        $order_sn_arr = explode('op', $arr_ret['orderId']);
        
        $order_sn    = $order_sn_arr['0'];
        $pay_id = intval($order_sn_arr['1']);
        $payment_amount = intval($arr_ret['settleAmt']);
      
        // 检查商户账号是否一致。
        if ($this->payment['upop_account'] != $arr_ret['merId'])
        {
            $this->logInfo( 'upop_account' . $this->payment['upop_account'] . ', merId:' . $arr_ret['merId'] );
            return false;
        }
        // 检查价格是否一致
        if (!check_money($pay_id, $payment_amount/100))
        {
            $this->logInfo('pay_id:' . $pay_id . ' 金额不一致, ' .  $payment_amount/100);
            return false;
        }
        
        $action_note = $arr_ret['respCode'] . ':' 
                . $arr_ret['respMsg'] 
                . $GLOBALS['_LANG']['upop_txn_id'] . ':' 
                . $arr_ret['queryId'];

        // 完成订单。
        order_paid($pay_id, PS_PAYED, $action_note);

        //告诉用户交易完成
        return true;

    }
    /**
    * 格式订单号
    */
    function _formatSN($sn)
    {
        return str_repeat('0', 9 - strlen($sn)) . $sn;
    }
    function create_html($params,$front_pay_url,$button)
    {
        $html = <<<eot
    <br />
    <form style="text-align:center;" id="pay_form" name="pay_form" action="{$front_pay_url}" method="post" target="_blank">
eot;
        foreach ($params as $key => $value) 
        {
            $html .= " <input type=\"hidden\" name=\"{$key}\" id=\"{$key}\" value=\"{$value}\" />\n";
        }
        $html .= $button . "</form><br />";
        return $html;
    }

    /**
     * 签名
     * @param  array $params
     * @return 
     */
    function sign(&$params)
    {
        if($params['signMethod']=='01') {
            return $this->signByCertInfo($params);
        } else {
            return $this->signBySecureKey($params);
        }
    }

    function signByCertInfo(&$params) 
    {
        $this->logInfo( '=====signByCertInfo签名报文开始======' );
        if(isset($params['signature'])){
            unset($params['signature']);
        }
        $result = false;
        if($params['signMethod']=='01') {
            //证书ID
            $params ['certId'] = $this->getCertInfoFromPfx('certId'); //certId 证书文件里的序列号
            $private_key = $this->getCertInfoFromPfx('pkey');
            // 转换成key=val&串
            $params_str = $this->createLinkString ($params);
            $this->logInfo( "签名key=val&...串 >" . $params_str );
            if($params['version']=='5.0.0'){
                $params_sha1x16 = sha1 ( $params_str, FALSE );
                $this->logInfo( "摘要sha1x16 >" . $params_sha1x16 );
                // 签名
                $result = openssl_sign ( $params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1);
        
                if ($result) {
                    $signature_base64 = base64_encode ( $signature );
                    $this->logInfo( "签名串为 >" . $signature_base64 );
                    $params ['signature'] = $signature_base64;
                } else {
                    $this->logInfo( ">>>>>签名失败<<<<<<<" );
                }
            } else if ($params['version']=='5.1.0') {
                //sha256签名摘要
                $params_sha256x16 = hash( 'sha256',$params_str);
                $this->logInfo( "摘要sha256x16 >" . $params_sha256x16 );
                // 签名
                $result = openssl_sign ( $params_sha256x16, $signature, $private_key, 'sha256');
                if ($result) {
                    $signature_base64 = base64_encode ( $signature );
                    $this->logInfo( "签名串为 >" . $signature_base64 );
                    $params ['signature'] = $signature_base64;
                } else {
                    $this->logInfo( ">>>>>签名失败<<<<<<<" );
                }
            }
        } else {
            $this->logInfo( "signMethod不正确");
            $result = false;
        }
        $this->logInfo( '=====signByCertInfo签名报文结束======' );
        return $result;
    }

    function signBySecureKey(&$params) 
    {
        // $this->payment['upop_cert_pwd'] = '88888888';
        $this->logInfo( '=====signBySecureKey签名报文开始======' );
        
        if($params['signMethod']=='11') {
            // 转换成key=val&串
            $params_str = $this->createLinkString ($params);
            $this->logInfo( "签名key=val&...串 >" . $params_str );
            $params_before_sha256 = hash('sha256', $this->payment['upop_cert_pwd']);
            $params_before_sha256 = $params_str.'&'.$params_before_sha256;
            $this->LogInfo( "before final sha256: " . $params_before_sha256);
            $params_after_sha256 = hash('sha256',$params_before_sha256);
            $this->logInfo( "签名串为 >" . $params_after_sha256 );
            $params ['signature'] = $params_after_sha256;
            $result = true;
        } else if($params['signMethod']=='12') {
            //TODO SM3
            $this->logInfo( "signMethod=12未实现");
            $result = false;
        } else {
            $this->logInfo( "signMethod不正确");
            $result = false;
        }
        $this->logInfo( '=====signBySecureKey签名报文结束======' );
        return $result;
    }

    /**
     * 验签
     * @param   array   $params  应答数组
     * @return  boolean
     */
    function validate($params) 
    {
        $isSuccess = false;
        if($params['signMethod'] == '01')
        {
            $signature_str = $params['signature'];
            unset($params['signature']);
            $params_str = $this->createLinkString($params);

            $this->logInfo( 'validate报文去[signature] key=val&串>' . $params_str );
            $this->logInfo( '签名原文>' . $signature_str );
            if($params['version']=='5.0.0'){
                // 公钥
                $public_key = $this->getVerifyCertByCertId ( $params ['certId'] );
                $signature = base64_decode ( $signature_str );
                $params_sha1x16 = sha1 ( $params_str, FALSE );
                $this->logInfo( 'sha1>' . $params_sha1x16 );
                $isSuccess = openssl_verify ( $params_sha1x16, $signature, $public_key, OPENSSL_ALGO_SHA1 );
                $this->logInfo( $isSuccess ? '验签成功' : '验签失败' );
            } elseif ($params['version']=='5.1.0') {
                $strCert = $params['signPubKeyCert'];
                $strCert = $this->verifyAndGetVerifyCert($strCert);
                if($strCert == null){
                    $this->logInfo("validate cert err: " + $params["signPubKeyCert"]);
                    $isSuccess = false;
                } else {
                    $params_sha256x16 = hash('sha256', $params_str);
                    $this->logInfo( 'sha256>' . $params_sha256x16 );
                    $signature = base64_decode ( $signature_str );
                    $isSuccess = openssl_verify ( $params_sha256x16, $signature,$strCert, "sha256" );
                    $this->logInfo( $isSuccess ? '验签成功' : '验签失败' );
                }
            } else {
                $this->logInfo( "wrong version: " + $params['version'] );
                $isSuccess = false;
            }
        } else {
            $isSuccess = $this->validateBySecureKey($params);
        }
        return $isSuccess;
    }

    function validateBySecureKey($params) 
    {
        // 测试待删除
        // $this->payment['upop_cert_pwd'] = '88888888';
        $isSuccess = false;
        
        $signature_str = $params['signature'];
        unset( $params['signature'] );
        $params_str = $this->createLinkString($params);
        $this->logInfo( 'validateBySecureKey报文去[signature] key=val&串>' . $params_str );
        $this->logInfo( '签名原文>' . $signature_str );
        
        if($params['signMethod']=='11') {

            $params_before_sha256 = hash('sha256', $this->payment['upop_cert_pwd']);
            $params_before_sha256 = $params_str.'&'.$params_before_sha256;
            $params_after_sha256 = hash('sha256',$params_before_sha256);
            $isSuccess = $params_after_sha256 == $signature_str;
            $this->logInfo( $isSuccess ? '验签成功' : '验签失败' );
        } else if($params['signMethod']=='12') {
            //TODO SM3
            $this->logInfo( "sm3没实现");
            $isSuccess = false;
        } else {
            $this->logInfo( "signMethod不正确");
            $isSuccess = false;
        }
        return $isSuccess;
    }

    /**
     * 通过certId获取证书
     * @param   string    $certId  证书序列号
     * @return  string|boolean
     */
    function getVerifyCertByCertId($certId)
    {
        $x509data = file_get_contents(ROOT_PATH . 'cert/acp_prod_verify_sign.cer');
        if($x509data === false ){
            $this->logInfo($filePath . " file_get_contents fail。");
        }
        if(!openssl_x509_read($x509data)){
            $this->logInfo($certPath . " openssl_x509_read fail。");
        }

        $certdata = openssl_x509_parse($x509data);
        $this->LogInfo($certdata);
        if ($certId == $certdata['serialNumber']) {
            return $x509data;
        } else {
            $this->logInfo("未匹配到序列号为[" . $certId . "]的证书");
            return false;
        }
    }

    function verifyAndGetVerifyCert($certBase64String){

        
        if (array_key_exists($certBase64String, $this->verifyCerts510)){
            return $this->verifyCerts510[$certBase64String];
        }
        
        $middleCertPath = ROOT_PATH . 'cert/acp_prod_middle.cer';
        $rootCertPath = ROOT_PATH . 'cert/acp_prod_root.cer';
        $certInfo = openssl_x509_parse($certBase64String);
        
        $cn = $this->getIdentitiesFromCertficate($certInfo);
        if(strtolower($this->ifValidateCNName) == "true"){
            if ($this->company != $cn){
                $this->logInfo("cer owner is not CUP:" . $cn);
                return null;
            }
        } else if ($this->company != $cn && "00040000:SIGN" != $cn){
            $this->logInfo("cer owner is not CUP:" . $cn);
            return null;
        }
        
        $from = date_create ( '@' . $certInfo ['validFrom_time_t'] );
        $to = date_create ( '@' . $certInfo ['validTo_time_t'] );
        $now = date_create ( date ( 'Ymd' ) );
        $interval1 = $from->diff ( $now );
        $interval2 = $now->diff ( $to );
        if ($interval1->invert || $interval2->invert) {
            $this->logInfo("signPubKeyCert has expired");
            return null;
        }
         
        $result = openssl_x509_checkpurpose($certBase64String, X509_PURPOSE_ANY, array($rootCertPath, $middleCertPath));
        if($result === FALSE){
            $this->logInfo("validate signPubKeyCert by rootCert failed");
            return null;
        } else if($result === TRUE){
            $this->verifyCerts510[$certBase64String] = $certBase64String;
            return $this->verifyCerts510[$certBase64String];
        } else {
            $this->logInfo("validate signPubKeyCert by rootCert failed with error");
            return null;
        }
    }

    function getIdentitiesFromCertficate($certInfo)
    {
        $cn = $certInfo['subject'];
        $cn = $cn['CN'];    
        $company = explode('@',$cn);
        
        if(count($company) < 3) {
            $this->logInfo($company);
            return null;
        } 
        return $company[2];
    }

    function getCertInfoFromPfx($key)
    {
        $pkcs12certdata = file_get_contents(ROOT_PATH . $this->payment['upop_cert']);
        if ($pkcs12certdata === false) {
            $this->logInfo($certPath.' file_get_contents fail');
            return;
        }
        if (openssl_pkcs12_read($pkcs12certdata, $certs, $this->payment['upop_cert_pwd']) === false) {
            $this->logInfo($certPath . ", pwd[" . $certPwd . "] openssl_pkcs12_read fail。");
            return;
        }
        // $this->logInfo($certs);
        if ($key == 'pkey') {
            return $certs['pkey'];
        } elseif ($key == 'certId') {
            $cert = openssl_x509_read($certs['cert']);
            if (!$cert) {
                $this->logInfo($certPath . " openssl_x509_read fail。");
            }
            $certData = openssl_x509_parse($cert);
            openssl_x509_free($cert);
            return $certData['serialNumber'];
        } else {
            return $certs[$key];
        }

    }

    /**
     * 将数组转换为string
     *
     * @param  array  $param          
     * @return string
     */
    function createLinkString($param) {
        if($param == NULL || !is_array($param))
            return "";
        
        $linkString = "";
        ksort($param);
        foreach ($param as $key => $value) {
            $linkString .= $key . "=" . $value . "&";
        }
        // 去掉最后一个&字符
        $linkString = substr ( $linkString, 0, count ( $linkString ) - 2 );
        
        return $linkString;
    }

    function logInfo($data = '')
    {
        if ( @constant('DEBUG_API') ) {
            error_log(date("c")."\t".print_r($data, 1)."\t\n", 3, LOG_DIR."/upop_".date("Y-m-d",time()).".log");
        }
    }

/** respond 测试验签可以使用
$_POST = array (
  'accNo' => '6216261000000000018',
  'accessType' => '0',
  'bizType' => '000201',
  'currencyCode' => '156',
  'encoding' => 'UTF-8',
  'merId' => '777290058164592',
  'orderId' => '2018112099609op000000033',
  'queryId' => '801811201752082273768',
  'respCode' => '00',
  'respMsg' => '成功[0000000]',
  'settleAmt' => '203800',
  'settleCurrencyCode' => '156',
  'settleDate' => '1120',
  'signMethod' => '01',
  'signPubKeyCert' => '-----BEGIN CERTIFICATE-----
MIIEQzCCAyugAwIBAgIFEBJJZVgwDQYJKoZIhvcNAQEFBQAwWDELMAkGA1UEBhMC
Q04xMDAuBgNVBAoTJ0NoaW5hIEZpbmFuY2lhbCBDZXJ0aWZpY2F0aW9uIEF1dGhv
cml0eTEXMBUGA1UEAxMOQ0ZDQSBURVNUIE9DQTEwHhcNMTcxMTAxMDcyNDA4WhcN
MjAxMTAxMDcyNDA4WjB3MQswCQYDVQQGEwJjbjESMBAGA1UEChMJQ0ZDQSBPQ0Ex
MQ4wDAYDVQQLEwVDVVBSQTEUMBIGA1UECxMLRW50ZXJwcmlzZXMxLjAsBgNVBAMU
JTA0MUBaMjAxNy0xMS0xQDAwMDQwMDAwOlNJR05AMDAwMDAwMDEwggEiMA0GCSqG
SIb3DQEBAQUAA4IBDwAwggEKAoIBAQDDIWO6AESrg+34HgbU9mSpgef0sl6avr1d
bD/IjjZYM63SoQi3CZHZUyoyzBKodRzowJrwXmd+hCmdcIfavdvfwi6x+ptJNp9d
EtpfEAnJk+4quriQFj1dNiv6uP8ARgn07UMhgdYB7D8aA1j77Yk1ROx7+LFeo7rZ
Ddde2U1opPxjIqOPqiPno78JMXpFn7LiGPXu75bwY2rYIGEEImnypgiYuW1vo9UO
G47NMWTnsIdy68FquPSw5FKp5foL825GNX3oJSZui8d2UDkMLBasf06Jz0JKz5AV
blaI+s24/iCfo8r+6WaCs8e6BDkaijJkR/bvRCQeQpbX3V8WoTLVAgMBAAGjgfQw
gfEwHwYDVR0jBBgwFoAUz3CdYeudfC6498sCQPcJnf4zdIAwSAYDVR0gBEEwPzA9
BghggRyG7yoBATAxMC8GCCsGAQUFBwIBFiNodHRwOi8vd3d3LmNmY2EuY29tLmNu
L3VzL3VzLTE0Lmh0bTA5BgNVHR8EMjAwMC6gLKAqhihodHRwOi8vdWNybC5jZmNh
LmNvbS5jbi9SU0EvY3JsMjQ4NzIuY3JsMAsGA1UdDwQEAwID6DAdBgNVHQ4EFgQU
mQQLyuqYjES7qKO+zOkzEbvdFwgwHQYDVR0lBBYwFAYIKwYBBQUHAwIGCCsGAQUF
BwMEMA0GCSqGSIb3DQEBBQUAA4IBAQAujhBuOcuxA+VzoUH84uoFt5aaBM3vGlpW
KVMz6BUsLbIpp1ho5h+LaMnxMs6jdXXDh/du8X5SKMaIddiLw7ujZy1LibKy2jYi
YYfs3tbZ0ffCKQtv78vCgC+IxUUurALY4w58fRLLdu8u8p9jyRFHsQEwSq+W5+bP
MTh2w7cDd9h+6KoCN6AMI1Ly7MxRIhCbNBL9bzaxF9B5GK86ARY7ixkuDCEl4XCF
JGxeoye9R46NqZ6AA/k97mJun//gmUjStmb9PUXA59fR5suAB5o/5lBySZ8UXkrI
pp/iLT8vIl1hNgLh0Ghs7DBSx99I+S3VuUzjHNxL6fGRhlix7Rb8
-----END CERTIFICATE-----',
  'traceNo' => '227376',
  'traceTime' => '1120175208',
  'txnAmt' => '203800',
  'txnSubType' => '01',
  'txnTime' => '20181120175208',
  'txnType' => '01',
  'version' => '5.1.0',
  'signature' => 'l9mMoIt2jiLFAgk5sNoC4VEPNLLiqSloObTL0EgTEBG9Ob+J5NksDpvIRyLAwz6+We+pyhKA5SwrOZPOQioCW+qJLD/J57Esg0XS7Ikg6QyUiIFn33ZRTJ1arPUm0lQXq0rrXMo5smjsSTOq6ogxygXiuHohNyIOp3x5NSySuAol8bTLjoNp6pC0KB3hqDaNX5kcnjMq/Eg1ZT1TOtKnxYoWPKccSlQiw36yWJvFyNYg2TOo6gxZoFCVIelV4PpPnozu6AwE4bcR+/D/HLpsqAKuYr0KcmRyOJpL/gRcNaMIhNRuWK8rF8IkTMNWcloOoN+lRHUrMmxfo5kLhI/tHA==',
);


$_POST = array (
  'accNo' => '6216261000000000018',
  'accessType' => '0',
  'bizType' => '000201',
  'currencyCode' => '156',
  'encoding' => 'UTF-8',
  'merId' => '777290058164592',
  'orderId' => '2018112090136op000000032',
  'queryId' => '151811201749152872508',
  'respCode' => '00',
  'respMsg' => '成功[0000000]',
  'settleAmt' => '203800',
  'settleCurrencyCode' => '156',
  'settleDate' => '1120',
  'signMethod' => '11',
  'traceNo' => '287250',
  'traceTime' => '1120174915',
  'txnAmt' => '203800',
  'txnSubType' => '01',
  'txnTime' => '20181120174915',
  'txnType' => '01',
  'version' => '5.1.0',
  'signature' => 'f961488732cbd376429b59d300879611983ec24d2ad636fbf916746c6ccc616e',
);
 */



}
?>