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
    //支付模式，包含直接支付和担保支付
    const PAY_MODE_DIRECTPAY = 1;
    const PAY_MODE_VOUCHPAY = 2;

    //支付状态，等待支付，支付成功，支付完成（确认支付给对方,交易完成）,退款中， 退款完成 
    const PAY_STATUS_WAITPAY = 1;
    const PAY_STATUS_SUCCEEDED = 2;
    const PAY_STATUS_FINISHED = 3;
    const PAY_STATUS_REFUND_AUDITING = 4;
    const PAY_STATUS_REFUNDING = 5;
    const PAY_STATUS_REFUNDED = 6;

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
