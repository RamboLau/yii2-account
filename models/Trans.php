<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Lubanr.com All Rights Reserved
 *
 **************************************************************************/
 
namespace lubaogui\account\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
 
 
/**
 * @file Trans.php
 * @author 吕宝贵(lbaogui@lubanr.com)
 * @date 2015/11/29 11:26:09
 * @version $Revision$
 * @brief
 *
 **/


class Trans extends ActiveRecord 
{
    //交易的几种状态，需要和数据库保持一致
    const TRANS_STATUS_WAITPAY = 0;
    const TRANS_STATUS_PAYSUCCEEDED = 1;
    const TRANS_STATUS_SUCCEEDED = 2;
    const TRANS_STATUS_REFUNDING = 3;
    const TRANS_STATUS_REFUNDED = 4;

    //结算的几种状态,目前仅支持实时结算
    const TRANS_SETTLE_TYPE_IMMEDIATE = 1;

    //支付的两种方法，这两种方法仅仅对于网站账户之间的交易有限，对充值和支付不适用
    const TRANS_PAY_MODE_VOUCH = 1;
    const TRANS_PAY_MODE_DIRECTPAY = 2;

    /**
     * @brief 获取表名称，{{%}} 会自动将表名之前加前缀，前缀在db中定义
     *
     * @retval string 表名称  
     * @author 吕宝贵
     * @date 2015/11/29 11:48:52
    **/
    public static function tableName() {
        return '{{%trans}}';
    }

    /**
     * @brief 自动设置 created_at和updated_at
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/29 16:19:03
    **/
    public function behaviors() {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @brief 获取一个trans_id对应的所有账单
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 16:50:53
    **/
    public function getBills() {
        return $this->hasMany(Bills::className(), ['trans_id'=>'id']);
    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
