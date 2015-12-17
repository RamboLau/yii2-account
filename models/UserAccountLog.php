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
 * @file UserAccountLog.php
 * @author 吕宝贵(lbaogui@lubanr.com)
 * @date 2015/11/29 11:26:09
 * @version $Revision$
 * @brief
 *
 **/


class UserAccountLog extends ActiveRecord 
{

    /**
     * @brief 获取表名称，{{%}} 会自动将表名之前加前缀，前缀在db中定义
     *
     * @retval string 表名称  
     * @author 吕宝贵
     * @date 2015/11/29 11:48:52
    **/
    public static function tableName() {
        return '{{%user_account_log}}';
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
     * @brief 获取关联的交易记录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/17 20:31:11
    **/
    public function getTrans() {
        return $this->hasOne(Trans::className(), ['id'=>'trans_id']);
    }

    /**
     * @brief 获取关联的交易类型信息
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/17 20:38:48
    **/
    public function getTransType() {
        return $this->hasOne(TransType::className(), ['id'=>'trans_type_id'];
    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
