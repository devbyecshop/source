<?php

namespace App\Http\Controllers\v2;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\v2\Configs;
use App\Models\v2\AffiliateLog;

class ConfigController extends Controller
{
    public function index()
    {
        $rules = [
            'url' => 'string',
        ];
        if ($error = $this->validateInput($rules)) {
            return $error;
        }
        $data = Configs::getList($this->validated);
        return $this->json($data);
    }
    public function wechat()
    {
        $rules = [
            'url' => 'string',
        ];
        if ($error = $this->validateInput($rules)) {
            return $error;
        }
        $data = Configs::getWeChat($this->validated);
        return $this->json($data);
    }

    /**
    * 获取推荐设置
    */
    public function affiliateExpire()
    {
        $data = AffiliateLog::getAffiliateExpire();
        return $this->json($data);
    }

    /**
    * 获取H5转发设置
    */
    public function share()
    {
        $data = Configs::getShare();
        return $this->json($data);
    }
}
