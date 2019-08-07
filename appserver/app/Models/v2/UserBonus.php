<?php

namespace App\Models\v2;

use App\Models\BaseModel;
use App\Helper\Token;
use Log;

class UserBonus extends BaseModel
{
    protected $connection = 'shop';
    protected $table      = 'user_bonus';
    public $timestamps = false;
    protected $primaryKey = 'bonus_id';

    /* 红包发放的方式 */
    const SEND_BY_USER               = 0; // 按用户发放
    const SEND_BY_GOODS              = 1; // 按商品发放
    const SEND_BY_ORDER              = 2; // 按订单发放
    const SEND_BY_PRINT              = 3; // 线下发放


    /**
     * 设置红包为已使用
     * @param   int     $bonus_id   红包id
     * @param   int     $order_id   订单id
     * @return  bool
     */
    public static function useBonus($bonus_id, $order_id)
    {
        if ($model = self::where('bonus_id', $bonus_id)->first()) {
            $model->order_id  = $order_id;
            $model->used_time = time();
            if ($model->save()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 设置红包为未使用
     * @param   int     $bonus_id   红包id
     * @return  bool
     */
    public static function unuseBonus($bonus_id)
    {
        if ($model = self::where('bonus_id', $bonus_id)->first()) {
            $model->order_id  = 0;
            $model->used_time = 0;
            if ($model->save()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 添加红包
     * @param   int     $bonus_sn   红包序列号
     * @return  bool
     */
    public static function addBonus(array $attribute)
    {
        extract($attribute);
        $uid = Token::authorization();
        Log::info($bonus_sn);
        if(!$model = self::where('bonus_sn',$bonus_sn)->first() ){
            return self::formatError(self::BAD_REQUEST, trans('message.member.bonus.not_exists'));
        }
        Log::info("bonus_info",$model->toArray());
        if($model->user_id != 0){
            if ($model->user_id == $uid){
                //红包已经添加过了。
                return self::formatError(self::BAD_REQUEST, trans('message.member.bonus.is_used'));
            }else{
                //红包被其他人使用过了。
                return self::formatError(self::BAD_REQUEST, trans('message.member.bonus.is_used_by_other'));
            }
            return self::formatError(self::UNKNOWN_ERROR);
        }
        //红包没有被使用
        $bonus_type = BonusType::where('type_id',$model->bonus_type_id)->select('send_end_date','use_end_date')->first();
        if(time() > $bonus_type->use_end_date){
            return self::formatError(self::BAD_REQUEST, trans('message.member.bonus.use_expire'));
        }
        $data = [
            'user_id' => $uid,
        ];
        self::where('bonus_id',$model->bonus_id)->update($data);
        return self::formatBody([]);
    }

}
