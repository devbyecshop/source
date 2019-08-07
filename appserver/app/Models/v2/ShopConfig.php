<?php

namespace App\Models\v2;

use App\Models\BaseModel;

use App\Helper\Header;

use Log;

class ShopConfig extends BaseModel
{
    protected $connection = 'shop';
    protected $table      = 'shop_config';
    public $timestamps = true;

    public static function findByCode($code)
    {
        return self::where(['code' => $code])->value('value');
    }

    public static function getSiteInfo()
    {
        return[
            'site_info'=> [
                'name' => self::findByCode('shop_name'),
                'title' => self::findByCode('shop_title'),
                'desc' => self::findByCode('shop_desc'),
                'logo' => formatPhoto(self::findByCode('shop_logo')),
                'opening' => (bool)!self::findByCode('shop_closed'),
                'telephone' => self::findByCode('service_phone'),
                'terms_url' => env('TERMS_URL'),
                'about_url' => env('ABOUT_URL'),
            ]
        ];
    }

    public static function getConfigs(array $attributes){
        extract($attributes);
        $key = json_decode($key,1);
        $all_key = ['can_invoice','use_integral'];
        foreach ($key as $name) {
            if(in_array($name, $all_key)){
                $data[$name] = self::findByCode($name);
            }
        }
        return $data;

    }

    private static function getConfigure($configure)
    {
        $data = [];
        $configure = unserialize($configure);
        foreach ($configure as $key => $val) {
            $data[$val['name']] = $val['value'];
        }

        return $data;
    }
}
