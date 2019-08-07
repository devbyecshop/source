<?php

namespace App\Models\v2;

use App\Models\BaseModel;
use App\Helper\Token;
use App\Helper\Header;
use App\Services\Payment\Alipay\AlipayRSA;
use App\Services\Payment\Alipay\AlipayNotify;
use App\Services\Payment\wxpay\WxPay;
use App\Services\Payment\wxpay\WxResponse;
use App\Services\Payment\Unionpay\Union;
use Log;
use App\Services\Shopex\Erp;
use App\Services\Shopex\Sms;
use App\Services\Shopex\Authorize;
use App\Services\Payment\Teegon\TeegonService;
use App\Services\Payment\AlipayWap\AlipayWapSubmit;
use App\Services\Payment\AlipayWap\AlipayWapNotify;
use App\Services\Payment\Unionpaynew\sdk\AcpService;
use App\Services\Payment\Unionpaynew\sdk\SDKConfig;

class Payment extends BaseModel
{
    protected $connection = 'shop';
    protected $table      = 'config';
    public $timestamps = false;

    protected $appends = ['desc'];
    protected $visible = ['code', 'name', 'desc'];

    public static function getList(array $attributes)
    {
        extract($attributes);
        $userAgent = Header::getUserAgent();
        
        $model = array();
        $response = Authorize::info();
        if ($response['result'] == 'success') {
            if (true) {
                //余额支付
                if ($arr = Pay::where(['enabled' => 1, 'pay_code' => 'balance'])->select('pay_code as code','pay_name as name','pay_desc as desc')->first()) {
                    $arr = $arr->toArray();
                    array_push($model, $arr);
                }
                // 货到付款
                if ($arr = Pay::where(['enabled' => 1, 'pay_code' => 'cod'])->select('pay_code as code','pay_name as name','pay_desc as desc')->first()) {
                    $orderinfo = Order::where(['order_id'=>$order])->first();
                    $support_cod = Shipping::where(['shipping_id'=>$orderinfo['shipping']['id'],'support_cod'=>1])->first();
                    if($support_cod){
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                    
                }
                if (isset($userAgent['Platform']) && strtolower($userAgent['Platform']) == 'wechat') {
                    // 旗舰版授权...
                    if ($response['info']['authorize_code'] == 'NDE') {
                        if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'wxpay.web'])->first()) {
                            $arr = $arr->toArray();
                            array_push($model, $arr);
                        }
                        // $model = self::where(['type' => 'payment', 'status' => 1, 'code' => 'wxpay.web'])->get()->toArray();
                    }
                } elseif (isset($userAgent['Platform']) && strtolower($userAgent['Platform']) == 'mozilla') {
                    // 支付宝wap支付
                    if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'alipay.wap'])->first()) {
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                    // 微信H5支付
                    if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'wxpay.h5'])->first()) {
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                } else {
                    //支付宝app支付
                    if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'alipay.app'])->first()) {
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                    //微信app支付
                    if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'wxpay.app'])->first()) {
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                    //银联app支付
                    if ($arr = self::where(['type' => 'payment', 'status' => 1, 'code' => 'unionpay.app'])->first()) {
                        $arr = $arr->toArray();
                        array_push($model, $arr);
                    }
                }
            }
        }

        return self::formatBody(['payment_types' => $model]);
    }

    public static function pay(array $attributes)
    {
        extract($attributes);
        $uid = Token::authorization();

        $order = Order::where(['user_id' => $uid, 'order_id' => $order, 'pay_status' => Order::PS_UNPAYED])->with('goods')->first();
        if (!$order) {
            return self::formatError(self::NOT_FOUND);
        }

        $shop_name = ShopConfig::findByCode('shop_name');
        // 查询支付方式id pay_id
        $paymentModel = Pay::where('pay_code',$code)->select('pay_id','pay_code','pay_name','enabled','type')->first();
        $pay_id = $paymentModel?$paymentModel->pay_id:65535;

        if ($code == "wxpay.h5") {

            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config) {
                return self::formatError(self::UNKNOWN_ERROR);
            }
            $pay_log = [
                'order_id' => $order->order_id,
                'order_amount' => $order->order_amount,
            ];
            $new_log_id = PayLog::insertGetId($pay_log);
            Log::info("new_log_id:".$new_log_id);
            $wxpay = new WxPay();
            $wxpay->init($config['app_id'], $config['app_secret'], $config['mch_key']);
            $data =array(
                'appid' => $config['app_id'],
                'mch_id' => $config['mch_id'],
                'nonce_str' => str_random(32),
                'body' => "微信支付-微信H5支付",
                'attach' => $order->sn,
                'out_trade_no' => $order->sn.$new_log_id,
                'total_fee' => $order->order_amount * 100,
                'spbill_create_ip' => self::get_client_ip(),
                //'spbill_create_ip' => app('request')->ip(),
                'notify_url' => url('/v2/order.notify.wxpay.h5'),
                'trade_type' => "MWEB",
                'scene_info' => '{"h5_info": {"type":"Wap","wap_url": "https://pay.qq.com","wap_name": "腾讯充值"}}'
                );
            $data['sign'] = $wxpay->createMd5Sign($data);

            Log::debug('请求参数' . json_encode($data));

            $xml = self::arraytoxml($data);      
            $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
            $dataxml = self::http_post($url,$xml);
            $objectxml = (array)simplexml_load_string($dataxml, 'SimpleXMLElement', LIBXML_NOCDATA);
            Log::debug('返回结果' . json_encode($objectxml));
            if ($objectxml['return_code'] == 'SUCCESS') {
                if ($objectxml['result_code'] == 'SUCCESS') {
                    return self::formatBody(['result' => $objectxml]);
                } else {
                    return self::formatBody(['message' => $objectxml['err_code_des']]);
                }
            }else{
                $msg = array_get($objectxml,'return_msg','请求失败');
                return self::formatBody(['message' => $msg]);
            }
            return false;
        }
        
        //-------------  天工收银  -----------
        if ($code == 'teegon.wap') {
            if (!isset($channel) || empty($channel)) {
                return self::formatError(self::BAD_REQUEST, trans('message.teegon.channel')); // 选择支付方式
            }

            $result = Pay::checkConfig('yunqi');

            $config = ShopConfig::where('code', 'yunqi_account')->first();

            if (empty($config)) {
                return self::formatError(self::NOT_FOUND);
            }

            $config = unserialize($config['value']);

            if (!$result || empty($config['appkey']) || empty($config['appsecret'])) {
                return self::formatError(self::UNKNOWN_ERROR);
            }

            $data['order_no'] = $order->order_sn; //订单号
            $data['channel'] = $channel;
            $data['return_url'] = urldecode($referer);
            $data['amount'] = number_format($order->order_amount, 2, '.', '');
            $data['subject'] = $shop_name; // $shop_name
            $data['metadata'] = ""; // 可选
            $data['notify_url'] = url("/v2/order.notify.teegon.wap");//支付成功后天工支付网关通知
            $data['client_ip'] = '127.0.0.1';

            $srv = new TeegonService('https://api.teegon.com/', $config['appkey'], $config['appsecret']);

            $sign = $srv->sign($data);
            $data['sign'] = $sign;
            Log::info('data:' . json_encode($data));
            $res = $srv->pay($data, true);

            if (isset($res['error'])) {
                return self::formatError(self::BAD_REQUEST, $res['error']);
            }

            Log::error('userAgent: '.var_export($res, true));

            $url = $html = null;

            if ($channel == 'wxpay_jsapi') { // 微信支付
                $url = str_replace(['window.location=', '"'], '', $res['result']['action']['params']);
            }
            if ($channel == 'chinapay') { // 银联支付
                $html = $srv->buildRequestForm($data, 'post', '确认');
            }
            return self::formatBody([
                'order' => $order, 'teegon' => ['url' => $url, 'html' => $html],
            ]);
        }

        // ----------------------------
        //-----------  支付宝手机网站支付  -----------//
        if ($code == 'alipay.wap') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            
            $config = self::checkConfig(['partner_id', 'seller_id', 'private_key'], $payment);

            if (!$config) {
                return self::formatError(self::UNKNOWN_ERROR);
            }
            
            $parameter = array(
                "service"         => 'alipay.wap.create.direct.pay.by.user',
                "partner"         => $config['partner_id'],
                "seller_id"       => $config['seller_id'],
                "payment_type"    => '1',
                "notify_url"      => url("/v2/order.notify.alipay.wap"),
                "return_url"      => $referer,
                "_input_charset"  => 'utf-8',
                "out_trade_no"    => $order->order_sn,
                "subject"         => $shop_name,
                "total_fee"       => number_format($order->order_amount, 2, '.', ''),
                // "show_url"        => $show_url,
                "app_pay"         => "Y",//启用此参数能唤起钱包APP支付宝
                "body"            => $shop_name,
            );

            //建立请求
            $alipaySubmit = new AlipayWapSubmit($config['private_key']);
            $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "确认");

            return self::formatBody([
                'order' => $order,
                'alipay' => ['html' => $html_text],
            ]);
            // echo $html_text;
        }

        if ($code == 'cod.app' || $code == 'cod') {
            $order->pay_time = time();
            $order->order_status = Order::OS_UNCONFIRMED;
            $order->pay_status = Order::PS_UNPAYED;
            $order->pay_id = $pay_id;
            $order->pay_name = "货到付款";
            $order->money_paid += $order->order_amount;
            $order->order_amount = 0;
            $order->save();
            OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '货到付款');
            //发送短信
            $params = [
                'order_sn' => $order->order_sn,
                'consignee' => $order->consignee['name'],//收货人姓名
                'tel' => $order->tel,//收货人手机号
            ];
            Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
            $params = [
                'order_sn' => $order->order_sn,
                'money_paid' => $order->money_paid,//支付金额
            ];
            Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
            return self::formatBody(['order' => $order]);
        }

        if ($code == 'balance') {
            $user_info = Member::user_info($uid);

            /* 用户帐户余额是否足够 */
            if ($order->order_amount > $user_info['user_money'] + $user_info['credit_line']) {
                return self::formatError(self::BAD_REQUEST, trans('message.payment.balance'));
            }

            $order->surplus = min($order->order_amount, $user_info['user_money'] + $user_info['credit_line']);

            $order->pay_time = time();
            $order->order_status = Order::OS_CONFIRMED;
            $order->pay_status = Order::PS_PAYED;
            $order->pay_id = $pay_id;
            $order->pay_name = "余额支付";
            $order->order_amount = 0;
            $order->save();

            $pay_order = '支付订单 %s';

            Member::logAccountChange($uid, $order->surplus * (-1), 0, 0, 0, sprintf($pay_order, $order->sn));
            OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '余额支付');
            //发送短信
            $params = [
                'order_sn' => $order->order_sn,
                'consignee' => $order->consignee['name'],//收货人姓名
                'tel' => $order->tel,//收货人手机号
            ];
            Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
            $params = [
                'order_sn' => $order->order_sn,
                'money_paid' => $order->surplus,//支付金额
            ];
            Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
            return self::formatBody(['order' => $order]);
        }
        if ($code == 'alipay.app') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }

            $config = self::checkConfig(['partner_id', 'seller_id', 'private_key'], $payment);
            if (!$config) {
                return self::formatError(self::UNKNOWN_ERROR);
            }

            $data = [
                "notify_url"     => url('/v2/order.notify.alipay.app'),
                "partner"        => $config['partner_id'],
                "seller_id"      => $config['seller_id'],
                "out_trade_no"   => $order->order_sn,
                "subject"        => $shop_name,
                "body"           => $shop_name,
                "total_fee"      => number_format($order->order_amount, 2, '.', ''),
                "service"        => "mobile.securitypay.pay",
                "payment_type"   => "1",
                "_input_charset" => "utf-8",
                "it_b_pay"       => "30m",
                "show_url"       => "m.alipay.com"
            ];

            $sign = AlipayRSA::rsaSign(AlipayRSA::getSignContent($data), keyToPem($config['private_key'], true));
            $data['sign'] = $sign;
            $data['sign_type'] = 'RSA';

            return self::formatBody(['order' => $order, 'alipay' => ['order_string' => http_build_query($data)]]);
        }

        if ($code == 'wxpay.app') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config) {
                return self::formatError(self::UNKNOWN_ERROR);
            }

            $wxpay = new WxPay();
            $wxpay->init($config['app_id'], $config['app_secret'], $config['mch_key']);
            $nonce_str = str_random(32);
            $time_stamp = time();
            $pack = 'Sign=WXPay';

            $inputParams = [

                //公众账号ID
                'appid' => $config['app_id'],

                //商户号
                'mch_id' => $config['mch_id'],

                'device_info' => '1000',

                //随机字符串
                'nonce_str' => $nonce_str,

                //商品描述
                'body' => $shop_name,

                'attach' => $shop_name,

                //商户订单号
                'out_trade_no' => $order->order_sn,

                //总金额
                'total_fee' => $order->order_amount * 100,
                // 'total_fee' => 1,

                //终端IP
                'spbill_create_ip' => app('request')->ip(),

                //接受微信支付异步通知回调地址
                'notify_url' => url('/v2/order.notify.wxpay.app'),

                //交易类型:JSAPI,NATIVE,APP
                'trade_type' => 'APP'
            ];

            $inputParams['sign'] = $wxpay->createMd5Sign($inputParams);

            //获取prepayid
            $prepayid = $wxpay->sendPrepay($inputParams);

            $prePayParams = [
                'appid' => $config['app_id'],
                'partnerid' => $config['mch_id'],
                'prepayid' => $prepayid,
                'package' => $pack,
                'noncestr' => $nonce_str,
                'timestamp' => $time_stamp,
            ];

            //生成签名
            $sign = $wxpay->createMd5Sign($prePayParams);

            $body = [
                'appid' => $config['app_id'],
                'mch_id' => $config['mch_id'],
                'prepay_id' => $prepayid,
                'nonce_str' => $nonce_str,
                'timestamp' => $time_stamp,
                'packages' => $pack,
                'sign' => $sign,
            ];
            return self::formatBody(['order' => $order, 'wxpay' => $body]);
        }

        if ($code == 'wxpay.web' || $code == 'wxpay.wxa') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config) {
                return self::formatError(self::UNKNOWN_ERROR);
            }
            $pay_log = [
                'order_id' => $order->order_id,
                'order_amount' => $order->order_amount,
            ];
            $new_log_id = PayLog::insertGetId($pay_log);
            Log::info("new_log_id:".$new_log_id);
            $wxpay = new WxPay();
            $wxpay->init($config['app_id'], $config['app_secret'], $config['mch_key']);
            $nonce_str = str_random(32);
            $time_stamp = (string)time();
            if ($code == 'wxpay.wxa') {
                $notify_url = url('/v2/order.notify.wxpay.wxa');
            } else {
                $notify_url = url('/v2/order.notify.wxpay.web');
            }
            

            $inputParams = [

                //公众账号ID
                'appid' => $config['app_id'],

                //商户号
                'mch_id' => $config['mch_id'],

                //商户号
                'openid' => $openid,

                'device_info' => '1000',

                //随机字符串
                'nonce_str' => $nonce_str,

                //商品描述
                'body' => $shop_name,

                'attach' => $order->order_sn,

                //商户订单号
                'out_trade_no' => $order->order_sn.$new_log_id,

                //总金额
                'total_fee' => $order->order_amount * 100,
                // 'total_fee' => 1,

                //终端IP
                'spbill_create_ip' => app('request')->ip(),

                //接受微信支付异步通知回调地址
                'notify_url' => $notify_url,

                //交易类型:JSAPI,NATIVE,APP
                'trade_type' => 'JSAPI'
            ];

            $inputParams['sign'] = $wxpay->createMd5Sign($inputParams);

            //获取prepayid
            $prepayid = $wxpay->sendPrepay($inputParams);

            $pack = 'prepay_id='.$prepayid;

            $prePayParams = [
                'appId' => $config['app_id'],
                'timeStamp' => $time_stamp,
                'package' => $pack,
                'nonceStr' => $nonce_str,
                'signType' => 'MD5'
            ];

            //生成签名
            $sign = $wxpay->createMd5Sign($prePayParams);

            $body = [
                'appid' => $config['app_id'],
                'mch_id' => $config['mch_id'],
                'prepay_id' => $prepayid,
                'nonce_str' => $nonce_str,
                'timestamp' => $time_stamp,
                'packages' => $pack,
                'sign' => $sign,
            ];

            return self::formatBody(['order' => $order, 'wxpay' => $body]);
        }

        if ($code == 'unionpayold.app') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            $config = self::checkConfig(['mer_id', 'cert_pwd'], $payment);
            $signCert = Cert::where('config_id', $payment->id)->value('file');

            if (!$config || !$signCert) {
                return self::formatError(self::UNKNOWN_ERROR);
            }
            
            $unionpay = new Union;
            $unionpay->config = [
                'appUrl' => 'https://gateway.95516.com/gateway/api/appTransReq.do', //App请求交易地址
                'frontUrl' => 'https://gateway.95516.com/gateway/api/frontTransReq.do', //前台交易请求地址
                'singleQueryUrl' => 'https://gateway.95516.com/gateway/api/queryTrans.do', //单笔查询请求地址

                // 'appUrl' => 'https://gateway.test.95516.com/gateway/api/appTransReq.do', //App请求交易地址
                // 'frontUrl' => 'https://gateway.test.95516.com/gateway/api/frontTransReq.do', //前台交易请求地址
                // 'singleQueryUrl' => 'https://gateway.test.95516.com/gateway/api/queryTrans.do', //单笔查询请求地址

                'signCertPath' => $signCert, //签名证书路径
                'verifyCertPath' => app()->basePath() . '/app/Services/Payment/Unionpay/UpopRsaCert.cer', //生产 验签证书路径
                'merId' => $config['mer_id'],
                'signCertPwd' => $config['cert_pwd'], //签名证书密码
            ]; //上面给出的配置参数
            $unionpay->params = [
                'version' => '5.0.0', //版本号
                'encoding' => 'UTF-8', //编码方式
                'certId' => $unionpay->getSignCertId(), //证书ID
                'signature' => '', //签名
                'signMethod' => '01', //签名方式
                'txnType' => '01', //交易类型
                'txnSubType' => '01', //交易子类
                'bizType' => '000201', //产品类型
                'channelType' => '08',//渠道类型
                'backUrl' => url('/v2/order.notify.unionpay.app'), //后台通知地址

                'frontUrl' => 'https://gateway.95516.com/gateway/api/frontTransReq.do', //前台通知地址
                // 'frontUrl' => 'https://gateway.test.95516.com/gateway/api/frontTransReq.do',

                'accessType' => '0', //接入类型
                'merId' => $config['mer_id'], //商户代码
                'orderId' => $order->order_sn, //商户订单号
                'txnTime' => date('YmdHis'), //订单发送时间
                'txnAmt' => $order->order_amount * 100, //交易金额，单位分
                'currencyCode' => '156', //交易币种
            ];
            $tn = $unionpay->getTn(); //手机控件支付的所需的tn参数。
            Log::debug('tn参数' . $tn);
            return self::formatBody(['order' => $order, 'unionpay' => ['tn' => $tn]]);
        }

        if ($code == 'unionpay.app') {
            $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

            if (!$payment) {
                return self::formatError(self::NOT_FOUND);
            }
            $config = self::checkConfig(['mer_id', 'cert_pwd'], $payment);
            $signCert = Cert::where('config_id', $payment->id)->value('file');

            if (!$config || !$signCert) {
                return self::formatError(self::UNKNOWN_ERROR);
            }
            
            $params = array(
    
                'version' => '5.1.0',         //版本号
                'encoding' => 'utf-8',        //编码方式
                'signMethod' => '01',         //签名方法
                'txnType' => '01',            //交易类型    
                'txnSubType' => '01',         //交易子类
                'bizType' => '000201',        //业务类型
                'accessType' => '0',          //接入类型
                'channelType' => '08',        //渠道类型
                'orderId' => $order->order_sn,  //订单号
                'merId' => $config['mer_id'], //商户代码
                'txnTime' => date('YmdHis'),  //订单发送时间
                'txnAmt' => $order->order_amount * 100, //交易金额，单位分
                'currencyCode' => '156', //交易币种
                'backUrl' => url('/v2/order.notify.unionpay.app'), //后台通知地址
                'signature' => '', //签名
                'frontUrl' => SDKConfig::getSDKConfig()->frontTransUrl, //前台通知地址  
                // 'certId' => 77917653140,
                );
            //私钥证书
            // $cert_path = app()->basePath() . '/app/Services/Payment/Unionpaynew/sdk/dzz.pfx';
            $cert_path = $signCert;
            $cert_pwd = $config['cert_pwd'];

            AcpService::signByCertInfo( $params, $cert_path, $cert_pwd ); // 签名

            //消费接口地址
            $url = SDKConfig::getSDKConfig()->appTransUrl;
            $result_arr = AcpService::post ( $params, $url);

            if (!isset($result_arr['tn'])) {
                Log::debug('获取银联受理订单号失败，错误码：' . $result_arr['respMsg']);
                return '获取银联受理订单号失败';
            }

            return self::formatBody(['order' => $order, 'unionpay' => ['tn' => $result_arr['tn'] ]]);
        }
    }

    public static function notify($code)
    {
        Log::info('支付开始回调');
        // 查询支付方式id pay_id
        $paymentModel = Pay::where('pay_code',$code)->select('pay_id','pay_code','pay_name','enabled','type')->first();
        $pay_id = $paymentModel?$paymentModel->pay_id:65535;

        //--------- 天工收银 notify ----------
        if ($code == 'teegon.wap') {
            Log::info('notify:'. json_encode($_POST));

            if (isset($_POST['charge_id'])) {
                if ($_POST['is_success'] == true) {

                    /* 修改订单状态 */
                    $order = Order::findUnpayedBySN($_POST['order_no']);

                    $order->pay_time = time();
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "天工收银支付宝手机支付";
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();

                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '天工收银支付宝手机支付');
                    AffiliateLog::affiliate($order->order_id);
                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                    Log::info('notify_order:'. json_encode($order));

                    Log::info('notify_is_success:'. $_POST['is_success']);
                    return true;
                } else {
                    Log::info('notify:'. json_encode($_POST));
                    return false;
                }
            } else {
                Log::info('notify_not_post:'. json_encode($_POST));
                return false;
            }
        }
        //----------------------

        $payment = self::where(['type' => 'payment', 'status' => 1, 'code' => $code])->first();

        if (!$payment) {
            return false;
        }

        if ($code == 'alipay.app' || $code == 'alipay.wap') {
            $out_trade_no = isset($_POST['out_trade_no']) ? $_POST['out_trade_no'] : 0;

            $order = Order::findUnpayedBySN($out_trade_no);

            $config = self::checkConfig(['partner_id', 'seller_id', 'private_key'], $payment);
            if (!$config || !$order) {
                return false;
            }
            $alipay_config = array(
                "partner"           => $config['partner_id'],
                "alipay_public_key" => keyToPem($config['public_key']),
                "sign_type"         => strtoupper('RSA'),
                "input_charset"     => strtolower('utf-8'),
                "cacert"            => app()->basePath() . '/app/Services/Payment/Alipay/cacert.pem',
                "transport"         => "http",
                "notify_url"        => url("/v2.order.notify.alipay.app"),
            );

            $alipayNotify = new AlipayNotify($alipay_config);
            $verify_result = $alipayNotify->verifyNotify();

            if ($verify_result) {//验证成功

                if (empty($_POST['out_trade_no']) && !empty($_POST['notify_data'])) {
                    $_POST = json_decode(json_encode(simplexml_load_string($_POST['notify_data'])), true);
                }

                $trade_status = $_POST['trade_status'];
                $trade_no = $_POST['trade_no'];

                if ($_POST['trade_status'] == 'TRADE_FINISHED') {
                    //修改订单状态
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "支付宝手机支付";
                    //插入付款时间
                    $order->pay_time = time();
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();

                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '支付宝手机支付');
                    AffiliateLog::affiliate($order->order_id);
                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                } elseif ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                    //修改订单状态
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "支付宝手机支付";
                    //插入付款时间
                    $order->pay_time = time();
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();
                                        
                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '支付宝手机支付');
                    AffiliateLog::affiliate($order->order_id);
                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                } else {
                    Log::error('订单支付回调处理异常: '.$out_trade_no);
                    Log::error('TRADE_STATUS:'.$_POST['trade_status']);
                }

                echo "success";
            } else {
                Log::info('订单支付回调故障: '.$out_trade_no);
                echo "fail";
            }

            return true;
        }

        if ($code == 'wxpay.app') {
            if (version_compare(PHP_VERSION, '5.6.0', '<')) {
                if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                    $postStr = $GLOBALS['HTTP_RAW_POST_DATA'];
                } else {
                    $postStr = file_get_contents('php://input');
                }
            } else {
                $postStr = file_get_contents('php://input');
            }

            if (empty($postStr)) {
                return false;
            }

            /* 创建支付应答对象 */
            $resHandler = new WxResponse();

            $inputParams = $resHandler->xmlToArray($postStr);

            foreach ($inputParams as $k => $v) {
                $resHandler->setParameter($k, $v);
            }

            $out_trade_no = $resHandler->getParameter("out_trade_no");

            $order = Order::findUnpayedBySN($out_trade_no);

            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config || !$order) {
                return false;
            }

            $resHandler->setKey($config['mch_key']);

            //判断签名
            if ($resHandler->isTenpaySign() == true) {

                //支付结果
                $return_code = $resHandler->getParameter("return_code");

                //判断签名及结果
                if ("SUCCESS"==$return_code) {

                    //商户在收到后台通知后根据通知ID向财付通发起验证确认，采用后台系统调用交互模式
                    //商户交易单号
                    $out_trade_no = $resHandler->getParameter("out_trade_no");

                    //财付通订单号
                    $transaction_id = $resHandler->getParameter("transaction_id");
                    $order->pay_time = time();
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "微信App支付";
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();

                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '微信App支付');
                    AffiliateLog::affiliate($order->order_id);
                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                } else {
                    Log::error('后台通知失败');
                }
                //回复服务器处理成功
                echo $resHandler->getSucessXml();
            } else {
                echo $resHandler->getFailXml();
                Log::error("验证签名失败");
            }

            return true;
        }

        if ($code == 'wxpay.web'|| $code == 'wxpay.wxa') {
            if (version_compare(PHP_VERSION, '5.6.0', '<')) {
                if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                    $postStr = $GLOBALS['HTTP_RAW_POST_DATA'];
                } else {
                    $postStr = file_get_contents('php://input');
                }
            } else {
                $postStr = file_get_contents('php://input');
            }

            if (empty($postStr)) {
                return false;
            }

            /* 创建支付应答对象 */
            $resHandler = new WxResponse();

            $inputParams = $resHandler->xmlToArray($postStr);

            foreach ($inputParams as $k => $v) {
                $resHandler->setParameter($k, $v);
            }

            $attach = $resHandler->getParameter("attach");

            $order = Order::findUnpayedBySN($attach);

            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config || !$order) {
                return false;
            }

            $resHandler->setKey($config['mch_key']);

            //判断签名
            if ($resHandler->isTenpaySign() == true) {

                //支付结果
                $return_code = $resHandler->getParameter("return_code");

                //判断签名及结果
                if ("SUCCESS"==$return_code) {

                    //商户在收到后台通知后根据通知ID向财付通发起验证确认，采用后台系统调用交互模式
                    //商户交易单号
                    // $out_trade_no = $resHandler->getParameter("out_trade_no");

                    //财付通订单号
                    // $transaction_id = $resHandler->getParameter("transaction_id");
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "微信公众号支付";
                    //插入付款时间
                    $order->pay_time = time();
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();
                                        
                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '微信公众号支付');
                    AffiliateLog::affiliate($order->order_id);
                    // 修改pay_log状态
                    PayLog::where('order_id', $order->order_id)->update(['is_paid' => 1]);

                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                    Log::error('微信公众号支付成功');
                } else {
                    Log::error('后台通知失败');
                }
                //回复服务器处理成功
                echo $resHandler->getSucessXml();
            } else {
                echo $resHandler->getFailXml();
                Log::error("验证签名失败");
            }

            return true;
        }

        if ($code == 'unionpay.app') {
            $out_trade_no = isset($_POST['orderId']) ? $_POST['orderId'] : 0;

            $order = Order::findUnpayedBySN($out_trade_no);

            $config = self::checkConfig(['mer_id', 'cert_pwd'], $payment);
            if (!$config || !$order) {
                return false;
            }

            $unionpay = new Union;

            $unionpay->config = [
                'appUrl' => 'https://101.231.204.80:5000/gateway/api/appTransReq.do', //App请求交易地址
                'frontUrl' => 'https://101.231.204.80:5000/gateway/api/frontTransReq.do', //前台交易请求地址
                'singleQueryUrl' => 'https://101.231.204.80:5000/gateway/api/queryTrans.do', //单笔查询请求地址
                
                'signCertPath' => app()->basePath() . '/app/Services/Payment/Unionpay/cert.pfx', //签名证书路径

                // 'appUrl' => 'https://gateway.test.95516.com/gateway/api/appTransReq.do', //App请求交易地址
                // 'frontUrl' => 'https://gateway.test.95516.com/gateway/api/frontTransReq.do', //前台交易请求地址
                // 'singleQueryUrl' => 'https://gateway.test.95516.com/gateway/api/queryTrans.do', //单笔查询请求地址

                'verifyCertPath' => app()->basePath() . '/app/Services/Payment/Unionpay/UpopRsaCert.cer', //生产 验签证书路径
                'merId' => $config['mer_id'],
                'signCertPwd' => $config['cert_pwd'], //签名证书密码
            ]; //上面给出的配置参数

            $postStr = $_POST;
            $unionpay->params = $_POST;
            // if (!$unionpay->verifySign()) {
            //     echo 'fail';
            //     return false;
            // }
            
            if (!$sign = AcpService::validate($postStr)) {
                echo 'fail';
                return false;
            }
            if ($unionpay->params['respCode'] == '00') {
                $out_trade_no = $unionpay->params['queryId'];
                $order_sn = $unionpay->params['orderId'];

                //业务代码
                $order->order_status = Order::OS_CONFIRMED;
                $order->pay_status = Order::PS_PAYED;
                $order->pay_id = $pay_id;
                $order->pay_name = "银联手机支付";
                //插入付款时间
                $order->pay_time = time();
                $order->money_paid += $order->order_amount;
                $order->order_amount = 0;
                $order->save();
                OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '银联手机支付');
                AffiliateLog::affiliate($order->order_id);
                Erp::order($order->order_sn);
                //发送短信
                $params = [
                    'order_sn' => $order->order_sn,
                    'consignee' => $order->consignee['name'],//收货人姓名
                    'tel' => $order->tel,//收货人手机号
                ];
                Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                $params = [
                    'order_sn' => $order->order_sn,
                    'money_paid' => $order->money_paid,//支付金额
                ];
                Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
            }
            echo 'success';
            return true;
        }

        if ($code == 'wxpay.h5') {
            if (version_compare(PHP_VERSION, '5.6.0', '<')) {
                if (!empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                    $postStr = $GLOBALS['HTTP_RAW_POST_DATA'];
                } else {
                    $postStr = file_get_contents('php://input');
                }
            } else {
                $postStr = file_get_contents('php://input');
            }

            if (empty($postStr)) {
                return false;
            }

            /* 创建支付应答对象 */
            $resHandler = new WxResponse();

            $inputParams = $resHandler->xmlToArray($postStr);

            foreach ($inputParams as $k => $v) {
                $resHandler->setParameter($k, $v);
            }

            $attach = $resHandler->getParameter("attach");

            $order = Order::findUnpayedBySN($attach);

            $config = self::checkConfig(['app_id', 'app_secret', 'mch_id', 'mch_key'], $payment);
            if (!$config || !$order) {
                return false;
            }

            $resHandler->setKey($config['mch_key']);

            //判断签名
            if ($resHandler->isTenpaySign() == true) {

                //支付结果
                $return_code = $resHandler->getParameter("return_code");

                //判断签名及结果
                if ("SUCCESS"==$return_code) {

                    //商户在收到后台通知后根据通知ID向财付通发起验证确认，采用后台系统调用交互模式
                    //商户交易单号
                    // $out_trade_no = $resHandler->getParameter("out_trade_no");

                    //财付通订单号
                    // $transaction_id = $resHandler->getParameter("transaction_id");
                    $order->pay_time = time();
                    $order->order_status = Order::OS_CONFIRMED;
                    $order->pay_status = Order::PS_PAYED;
                    $order->pay_id = $pay_id;
                    $order->pay_name = "微信H5支付";
                    $order->money_paid += $order->order_amount;
                    $order->order_amount = 0;
                    $order->save();

                    OrderAction::toCreateOrUpdate($order->order_id, $order->order_status, $order->shipping_status, $order->pay_status, '微信H5支付');
                    AffiliateLog::affiliate($order->order_id);
                    // 修改pay_log状态
                    PayLog::where('order_id', $order->order_id)->update(['is_paid' => 1]);
                    Erp::order($order->order_sn);
                    //发送短信
                    $params = [
                        'order_sn' => $order->order_sn,
                        'consignee' => $order->consignee['name'],//收货人姓名
                        'tel' => $order->tel,//收货人手机号
                    ];
                    Sms::sendSms('sms_order_payed',$params,null);//消费者支付订单时发商家
                    $params = [
                        'order_sn' => $order->order_sn,
                        'money_paid' => $order->money_paid,//支付金额
                    ];
                    Sms::sendSms('sms_order_payed_to_customer',$params,$order->tel);//消费者支付订单时发消费者
                } else {
                    Log::error('后台通知失败');
                }
                //回复服务器处理成功
                echo $resHandler->getSucessXml();
            } else {
                echo $resHandler->getFailXml();
                Log::error("验证签名失败");
            }

            return true;
        }
    }

    private static function checkConfig(array $params, $payment)
    {
        $config = json_decode($payment->config, true);

        foreach ($params as $key => $value) {
            if (!isset($config[$value])) {
                return false;
            }
        }

        return $config;
    }

    public function getDescAttribute()
    {
        return $this->attributes['description'];
    }

    public static function http_post($url, $data) 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public static function arraytoxml($data)
    {
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }

    public static function get_client_ip() 
    {
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    }
}
