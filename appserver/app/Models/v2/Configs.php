<?php

namespace App\Models\v2;

use App\Models\BaseModel;

use App\Helper\Token;
use App\Helper\XXTEA;
use App\Services\Cloud\Client;
use App\Services\QiNiu\QiNiu;
use App\Services\Other\JSSDK;
use App\Services\Shopex\Authorize;
use Cache;
use Log;

class Configs extends BaseModel
{
    protected $connection = 'shop';

    protected $table = 'config';

    protected $guarded = [];

    public $timestamps   = true;

    public static function getList(array $attributes)
    {
        extract($attributes);
        $url = isset($url) ? $url : null;
        
        $data = self::where('status', 1)->get();

        $config = ['config' => self::formatConfig($data, $url), 'feature' => Features::getList(), 'platform' => self::getApplicationPlatform()];

        return self::formatBody(['data' => base64_encode(XXTEA::encrypt($config, 'getprogname()'))]);
    }

    public static function getWeChat(array $attributes)
    {
        extract($attributes);
        $url = isset($url) ? $url : null;
        $data = self::where('status', 1)->where('code', 'wechat.web')->get();
        $config = ['config' => self::formatConfig($data, $url), 'feature' => Features::getList(), 'platform' => self::getApplicationPlatform()];
        return self::formatBody(['data' => base64_encode(XXTEA::encrypt($config, 'getprogname()'))]);
    }

    private static function getApplicationPlatform()
    {
        return [
            'type'      => self::B2C,
            'vendor'    => self::ECSHOP,
            'version'   => '3.5.0'
        ];
    }

    public static function checkConfig($code)
    {
        if (!$license = Token::decode_license()) {
            return self::formatError(4444, trans('message.license.invalid'));
        }

        switch ($code) {
            case 'sms':
                if ($license['permissions']['sms'] !== true) {
                    return self::formatError(4445, trans('message.license.unauthorized'));
                }
                return self::initLeanCloud();
                break;
        }

        return true;
    }

    public static function verifyConfig(array $params, $config)
    {
        if (!isset($config->config)) {
            return false;
        }

        $data = json_decode($config->config, true);

        foreach ($params as $key => $value) {
            if (!isset($data[$value])) {
                return false;
            }
        }

        return $data;
    }

    private static function initLeanCloud()
    {
        if (!$cloud = Configs::where('code', 'leancloud')->first()) {
            return self::formatError(3001, trans('message.cloud.config'));
        }

        if (!$cloud->status) {
            return self::formatError(3002, trans('message.cloud.notopen'));
        }

        $cloud_config = json_decode($cloud->config);
        if ($cloud_config && isset($cloud_config->app_id) && isset($cloud_config->app_key)) {
            Client::initialize($cloud_config->app_id, $cloud_config->app_key);
            return true;
        } else {
            return self::formatError(3001, trans('message.cloud.config'));
        }
    }


    public static function getWxQrcode()
    {
        $uid = Token::authorization();

        // 小程序二维码 缓存一个月
        $res = Cache::remember('wx_qrcpde' . $uid, 43200, function () use ($uid) {
            $wxa_config = Configs::where('code', 'wechat.wxa')->value('config');

            $wxa_config = json_decode($wxa_config, true);

            Log::info('生成二维码时小程序的配置' . json_encode($wxa_config));

            $jssdk = new JSSDK($wxa_config['app_id'], $wxa_config['app_secret']);

            $access_token = $jssdk->getAccessToken();

            if (empty($access_token)) {
                Log::info('生成二维码时access token获取出错');
                return self::formatError(self::UNKNOWN_ERROR);
            }

            // 开发参数和地址https://developers.weixin.qq.com/miniprogram/dev/api/qrcode.html
            $qrcode_url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token;

            $params = [
                'scene' => 'spread&u=' . $uid,
            ];

            $params = json_encode($params);

            $img = curl_request($qrcode_url, 'POST', $params, [], false);

            if (is_array($img)) {
                Log::error('二维码生成错误' . json_encode($img));
                return;
            }

            return $img;
        });


        if (strpos($res, 'errcode')) {
            Log::error('二维码生成错误' . json_encode($res));
            Cache::forget('wx_qrcpde' . $uid);

            // 如果出错 主动清除access token
            Cache::forget('access_token');
            return false;
        }

        return  $res;
    }

    private static function formatConfig($data, $url)
    {
        $body = null;
        foreach ($data as $value) {
            $arr = json_decode($value->config, true);

            //qiniu格式化
            if ($value->code == 'qiniu') {
                if (!empty($value->config)) {
                    $qiniu = new QiNiu($arr['app_key'], $arr['secret_key']);
                    unset($arr['app_key']);
                    unset($arr['secret_key']);
                    $arr['token'] = $qiniu->uploadToken(array('scope' => $arr['bucket']));
                }
            }

            //wxpay.web jssdk
            if ($value->code == 'wechat.web' && $value->status) {
                if (!empty($value->config)) {
                    $jssdk = new JSSDK($arr['app_id'], $arr['app_secret']);
                    $arr = $jssdk->GetSignPackage($url);
                }
            }

            if ($value->code == 'wxpay.web' && $value->status) {
                if (!empty($value->config)) {
                    $jssdk = new JSSDK($arr['app_id'], $arr['app_secret']);
                    $arr = $jssdk->GetSignPackage($url);
                }
            }

            if (is_array($arr)) {
                $body[$value->code] = $arr;
            }
        }

        $body['authorize'] = false;

        $response = Authorize::info();
        if ($response['result'] == 'success') {
            // 旗舰版授权...
            if ($response['info']['authorize_code'] == 'NDE') {
                $body['authorize'] = true;
            }
        }

        //安全处理
        unset($body['alipay.app']);
        unset($body['wxpay.app']);
        unset($body['unionpay.app']);
        unset($body['leancloud']['master_key']);

        return $body;
    }

   /**
    * 二进制流生成文件
    * $_POST 无法解释二进制流，需要用到 $GLOBALS['HTTP_RAW_POST_DATA'] 或 php://input
    * $GLOBALS['HTTP_RAW_POST_DATA'] 和 php://input 都不能用于 enctype=multipart/form-data
    * @param    String  $file   要生成的文件路径
    * @return   boolean
    */
    public function binary_to_file($src, $file)
    {
        $ret = file_put_contents($file, $src);
        return $ret;
    }

    /**
    * 获取H5转发设置
    */
    public static function getShare()
    {
        $data = [];
        if ($rows = ShopConfig::whereIn('code', ['share_title','share_desc'])->get()) {
            foreach ($rows as $share) {
                $data[$share['code']] = $share['value'];
            }
        }
        return self::formatBody($data);
    }
}
