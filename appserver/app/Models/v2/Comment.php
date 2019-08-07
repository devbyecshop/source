<?php

namespace App\Models\v2;

use App\Models\BaseModel;

use App\Helper\Token;
use DB;

class Comment extends BaseModel
{
    protected $connection = 'shop';
    protected $table      = 'comment';
    public $timestamps = false;

    protected $appends = ['id','username','grade','content', 'is_anonymous', 'created_at','updated_at'];

    protected $visible = ['id','username','grade','content', 'is_anonymous', 'created_at','updated_at'];

    protected $primaryKey = 'comment_id';

    protected $guarded = [];


    const GOODS = 0;
    const ARTICLE = 1;

    const BAD     = 1;            // 差评
    const MEDIUM  = 2;            // 中评
    const GOOD    = 3;            // 好评

    /**
    * 获取商品评论总数
    *
    * @access public
    * @param integer $goods_id
    * @return integer
    */
    public static function getCommentCountById($goods_id)
    {
        return self::where(['id_value' => $goods_id,'status' => 1])->where(['comment_type' => self::GOODS])->count();
    }

    /**
    * 获取商品评论好评率
    *
    * @access public
    * @param integer $goods_id
    * @return integer
    */
    public static function getCommentRateById($goods_id)
    {
        $rate = self::select('*', DB::raw('concat( sum(comment_rank)/(count(id_value) * 5)) AS goods_rank_rate'))->where('id_value', $goods_id)->where('comment_type', self::GOODS)->where('user_id', '<>', 0)->value('goods_rank_rate');
        return round($rate * 100);
    }

    public static function getReview(array $attributes)
    {
        extract($attributes);
        $model = self::where(['comment_type' => self::GOODS, 'id_value' => $product, 'status' => 1])->orderBy('add_time', 'DESC');
        if (isset($grade) && is_numeric($grade)) {
            if ($grade == self::BAD) {
                $model->where(function ($query) {
                    $query->where('comment_rank', '<', '3')->where('comment_rank', '>', 0);
                });
            } elseif ($grade == self::MEDIUM) {
                $model->where('comment_rank', '=', '3');
            } elseif ($grade == self::GOOD) {
                $model->where(function ($query) {
                    $query->where('comment_rank', '>', '3')->orWhere('comment_rank', 0);
                });
            }
        }

        $total = $model->count();

        $data = $model
            ->paginate($per_page)->toArray();

        $_data = $ids = [];
        foreach ($data['data'] as $review) {
            $_data[$review['id']] = $review;
            $ids[] = $review['id'];
        }
        $replys = self::whereIn('parent_id',$ids)->get();
        if($replys){
            foreach ($replys as $reply) {
                $_data[$reply['parent_id']]['reply'] = $reply;
            }
        }
        $_data = array_values($_data);
        return self::formatBody(['reviews' => $_data, 'paged' => self::formatPaged($page, $per_page, $total)]);
    }

    public static function getSubtotal(array $attributes)
    {
        extract($attributes);

        $bad = self::where(['comment_type' => self::GOODS, 'id_value' => $product])
                    ->where('status',1)
                    ->where(function ($query) {
                        $query->where('comment_rank', '<', '3')->where('comment_rank', '>', 0);
                    })
                    ->count();

        $medium = self::where(['comment_type' => self::GOODS, 'id_value' => $product])
                ->where('status',1)
                ->where('comment_rank', '=', 3)->count();

        $good = self::where(['comment_type' => self::GOODS, 'id_value' => $product])
                ->where('status',1)
                ->where(function ($query) {
                        $query->where('comment_rank', '>', 3)->orWhere('comment_rank', 0);
                    })
                ->count();

        $total = self::where(['comment_type' => self::GOODS, 'id_value' => $product])->where('status',1)->count();

        return self::formatBody(['subtotal' => ['total' => $total, 'bad' => $bad, 'medium' => $medium, 'good' => $good]]);
    }

    public static function toCreate($uid, array $attributes, $is_anonymous, $comment_status)
    {
        extract($attributes);

        if ($member = Member::where('user_id', $uid)->first()) {
            return self::create([
                'comment_type' => 0,
                'id_value' => $goods,
                'email' => $member->email,
                //匿名时 用户名默认为ecshop
                'user_name' => ($is_anonymous == 0) ? $member->user_name : '匿名用户',
                'content' => $content,
                'comment_rank' => ($grade == 2) ? 3 : (($grade == 3) ? 5 : 1),
                'add_time' => time(),
                'ip_address' => app('request')->ip(),
                'status' => $comment_status,
                'parent_id' => 0,
                'user_id' => $uid,
            ]);
        }

        return false;
    }

    public function author()
    {
        return $this->belongsTo('App\Models\v2\Member', 'user_id', 'user_id');
    }


    public function getIdAttribute()
    {
        return $this->attributes['comment_id'];
    }


    public function getGradeAttribute()
    {
        $rank = $this->attributes['comment_rank'];
        if ($rank > 0 && $rank < 3) {
            return self::BAD;
        }
        if ($rank == 3) {
            return self::MEDIUM;
        }
        if ($rank > 3 && $rank < 6) {
            return self::GOOD;
        }
    }

    public function getContentAttribute()
    {
        return $this->attributes['content'];
    }

    public function getIsAnonymousAttribute()
    {
        if ($this->attributes['user_name'] == '匿名用户' || $this->attributes['user_name'] == '' || $this->attributes['user_id'] == 0) {
            return 1;
        }
        return 0;
    }

    public function getUsernameAttribute()
    {
        return $this->attributes['user_name'];
    }


    public function getCreatedatAttribute()
    {
        return $this->attributes['add_time'];
    }

    public function getUpdatedatAttribute()
    {
        return $this->attributes['add_time'];
    }
}
