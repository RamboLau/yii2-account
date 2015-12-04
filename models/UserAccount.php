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
 * @file UserAccount.php
 * @author 吕宝贵(lbaogui@lubanr.com)
 * @date 2015/11/29 11:26:09
 * @version $Revision$
 * @brief
 *
 **/


class UserAccount extends ActiveRecord 
{

    //账户类型,三种，普通账户，公司账户，银行账户
    const ACCOUNT_TYPE_NORMAL = 1;
    const ACCOUNT_TYPE_COMPANY = 2;
    const ACCOUNT_TYPE_BANK = 3;

    /**
     * @brief 获取表名称，{{%}} 会自动将表名之前加前缀，前缀在db中定义
     *
     * @retval string 表名称  
     * @author 吕宝贵
     * @date 2015/11/29 11:48:52
    **/
    public static function tableName() {
        return '{{%user_account}}';
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
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/01 16:26:45
    **/
    public function plus($money) {

        $this->balance += $money;
        if ($this->save()) {
            $bill = new Bill();
            $bill->save();
            $accountLog = new UserAccountLog();
            $accountLog->save();
            return true;
        }    
        else {
            return false;
        }

    }

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/04 22:57:20
    **/
    public function minus($money) {

        $this->balance = $this->balance -  $money;
        if ($this->save()) {
            $bill = new Bill();
            $bill->save();
            $accountLog = new UserAccountLog();
            $accountLog->save();
            return true;
        } 
        else {
            return false;
        }
    }

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/04 23:50:06
    **/
    public function freeze() {

    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
