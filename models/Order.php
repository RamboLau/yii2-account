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

    //支付状态，等待支付，支付成功，支付完成（确认支付给对方,交易完成), 退款审核, 退款中， 退款完成 
    const PAY_STATUS_WAITPAY = 1;
    const PAY_STATUS_SUCCEEDED = 2;
    const PAY_STATUS_FINISHED = 3;
    const PAY_STATUS_REFUNDED = 4;

    //交易的类型，包含充值，提现和用户之间的交易（商家也属于用户中的一种），trans_type为单独一个表描述,初期可写死
    const TRANS_TYPE_CHARGE = 1;
    const TRANS_TYPE_WITHDRAW = 2;
    const TRANS_TYPE_TRADE = 3;


    public function rules() {
        return [
            ];
    }

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
     * @brief 处理支付成功逻辑
     *
     * @param array 回调时传入的参数数组，应该包含订单id, 交易id
     * @return  public static function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/27 13:15:09
    **/
    public static function processPaySuccess($data) {

        $order = static::findOne($data['bid']);
        if (! $order) {
            return false;
        }
        $order->trans_id = $data['trans_id'];
        //根据交易类型选择支付状态，如果是担保交易，则是支付成功，如果是直接支付，则直接是支付完成
        $order->status = self::PAY_STATUS_SUCCEEDED;
        if ($order->save()) {
            return true;
        }
        else {
            return false;
        }

    }

    /**
     * @brief 处理交易产生完成，此函数一般在支付需要第三方充值时调用
     *
     * @param array $data 回调时传入的参数数组，包含交易id, 订单id
     * @return  public static function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/27 13:18:25
    **/
    public static function processGenTransSuccess($data) {

        $order = static::findOne($data['bid']);
        if (! $order) {
            return false;
        }
        $order->trans_id = $data['trans_id'];
        //根据交易类型选择支付状态，如果是担保交易，则是支付成功，如果是直接支付，则直接是支付完成
        if ($order->save()) {
            return true;
        }
        else {
            return false;
        }

    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
