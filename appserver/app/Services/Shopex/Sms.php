<?php 
namespace App\Services\Shopex;

use Log;
use Cache;
use App\Models\v2\ShopConfig;
use App\Models\v2\Order;

class Sms
{
    const ORDER_PLACED_SMS = '您有新订单.收货人:%s 电话:%s';
    const SMS_PAID = '已付款';

    const ORDER_PAYED_SMS = '订单 %s 已付款。收货人：%s；电话：%s。';
    const ORDER_PAYED_TO_CUSTOMER_SMS = '订单 %s 付款了，付款金额：%s，我们将立即配货发货。';
    public static $action    = ['sms_order_placed', 'sms_order_payed', 'sms_order_payed_to_customer'];

    public static function requestSmsCode($mobile)
    {
        $certificate_info = ShopConfig::findByCode('certificate');
        
        if (!$certificate_info) {
            return false;
        }

        $certificate = unserialize($certificate_info);

        if (empty($certificate['token'])) {
            return false;
        }

        $token = $certificate['token'];

        $code = self::generate_verify_code(6);

        $template = env('SMS_TEMPLATE', '#CODE# 短信验证码有效期30分钟，请尽快进行验证。');

        //发送短信参数
        $param = array(
           'act' => 'ecmobile_send_sms',//固定方法
           'phone' => $mobile,//电话号码
           'content' => str_replace('#CODE#', $code, $template), //短信内容
           'return_data' => 'json',//返回类型
        );

        $ac = self::get_ac($param, $token);//验证签名

        $param['ac'] = $ac;//签名值放入参数中

        $sms_test = env('SMS_TEST', false);
        $sms_test_host = env('SMS_TEST_HOST', '');

        if ($sms_test) {
            $api = $sms_test_host;
            $param = json_encode([
                'msgtype' => 'text',
                'text' => [
                    'content' => "手机号：{$mobile} 验证码 {$code} 有效期 30分钟，请尽快验证。"
                ]
            ]);
            $response = curl_request($api, 'POST', $param, ['Content-Type: application/json']);
            if (isset($response['errcode']) && !$response['errcode']) {
                Cache::put('smscode:'.$mobile, $code, 30);
                return true;
            }
        } else {
            $api = config('app.shop_url') . '/api.php';
            $response = curl_request($api, 'POST', $param);
            if (isset($response['result']) && $response['result'] == 'success') {
                Cache::put('smscode:'.$mobile, $code, 30);
                return true;
            }
        }


        Log::error('验证码发送失败', array_merge($response, ['mobile' => $mobile, 'code' => $code]));

        return false;
    }

    public static function verifySmsCode($mobile, $code)
    {
        if (Cache::get('smscode:'.$mobile) == $code) {
            Cache::forget('smscode:'.$mobile);
            return true;
        }

        return false;
    }

    public static function sendSms($action,$params,$mobile=null){
        $_data = self::checkSendSms($action,$params,$mobile);
        if(!$_data) return false;
        //发送短信参数
        $param = array(
           'act' => 'ecmobile_send_sms',//固定方法
           'phone' => $_data['mobile'],//电话号码
           'content' => $_data['content'], //短信内容
           'return_data' => 'json',//返回类型
        );
        Log::info("发送短信",$param);

        $ac = self::get_ac($param, $_data['token']);//验证签名

        $param['ac'] = $ac;//签名值放入参数中

        $api = config('app.shop_url') . '/api.php';
        $response = curl_request($api, 'POST', $param);
        if (isset($response['result']) && $response['result'] == 'success') {
            return true;
        }

        Log::error('验证码发送失败', array_merge($response, $_data));
        return false;
    }

    public static function checkSendSms($action,$params,$mobile=null){
        $error = ['action'=>$action,'mobile' => $mobile, 'msg' => ''];
        if(!in_array($action, self::$action)){
            $error['msg'] = "短信类型错误";
            Log::error('短信发送失败',$error);
            return false;
        }
        // 检查是否开启
        $status = ShopConfig::findByCode($action);
        if($status != 1){
            return false;
        }
        // 获取token
        if(!$token = self::getToken()){
            $error['msg'] = "获取token失败";
            Log::error('短信发送失败',$error);
            return false;
        }

        if(in_array($action, ['sms_order_placed','sms_order_payed'])){
            if(!$mobile = self::getShopMobile()){
                $error['msg'] = "获取商家手机号失败";
                Log::error('短信发送失败',$error);
                return false;
            }
        }

        if(!$mobile){
            $error['msg'] = "手机号为空";
            Log::error('短信发送失败',$error);
            return false;
        }

        $content = self::getConent($action,$params);
        if($content == ""){
            $error['msg'] = "内容为空";
            Log::error('短信发送失败',$error);
            return false;
        }
        return ['token'=>$token,'mobile'=>$mobile,'content'=>$content];
    }

    public static function getToken(){
        $certificate_info = ShopConfig::findByCode('certificate');
        
        if (!$certificate_info) {
            return false;
        }

        $certificate = unserialize($certificate_info);

        if (empty($certificate['token'])) {
            return false;
        }

        return $certificate['token'];
    }

    public static function getConent($action,$params){
        Log::info("params===",$params);
        $content = "";
        switch ($action) {
            case 'sms_order_placed'://商家接收新订单
                $template = $params['pay_status'] == Order::PS_UNPAYED ? self::ORDER_PLACED_SMS : self::ORDER_PLACED_SMS . '[' . self::SMS_PAID . ']';
                $content = sprintf($template, $params['consignee'], $params['tel']);
                break;
            case 'sms_order_payed'://消费者支付订单时发商家
                $template = self::ORDER_PAYED_SMS;
                $content = sprintf($template, $params['order_sn'], $params['consignee'], $params['tel']);
                break;
            case 'sms_order_payed_to_customer'://消费者支付订单时发消费者
                $template = self::ORDER_PAYED_TO_CUSTOMER_SMS;
                $content = sprintf($template, $params['order_sn'], $params['money_paid']);
                break;
            
            default:
                # code...
                break;
        }
        return $content;
        
    }

    public static function getShopMobile(){
        // 检查是否设置了商家手机号 shop_config sms_shop_mobile
        if(!$shop_mobile = ShopConfig::findByCode('sms_shop_mobile')){
            return false;
        }
        return $shop_mobile;
    }

    //验证方法
    public static function get_ac($params, $token)
    {
        ksort($params);
        $tmp_verfy='';
        foreach ($params as $key=>$value) {
            $params[$key]=stripslashes($value);
            $tmp_verfy.=$params[$key];
        }
        return strtolower(md5(trim($tmp_verfy.$token)));
    }

    public static function generate_verify_code($num = 4)
    {
        if (!$num) {
            return false;
        }
        
        $num = intval($num);

        $pool = '0123456789';
        $shuffled = str_shuffle($pool);

        $code = substr($shuffled, 0, $num);

        return $code;
    }
}
