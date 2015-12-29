<?php
/*************************************************************************** *
 * Copyright (c) 2015 Lubanr.com All Rights Reserved
 *
 **************************************************************************/
 
namespace lubaogui\account\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use lubaogui\account\models\Bill;
use lubaogui\account\models\UserAccountLog;
 
 
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

    //账户类型,三种，普通账户，公司账户，银行账户,默认时普通用户账户
    const ACCOUNT_TYPE_NORMAL = 1;              //个人普通账号
    const ACCOUNT_TYPE_COMPANY = 2;            //公司类型账号，非自有公司
    const ACCOUNT_TYPE_BANK = 3;               //银行账号
    const ACCOUNT_TYPE_SELFCOMPANY_FEE = 4;    //公司手续费收费账号
    const ACCOUNT_TYPE_SELFCOMPANY_PROFIT = 5; //利润账号
    const ACCOUNT_TYPE_SELFCOMPANY_VOUCH = 6; //担保账号

    //支出类型

    const BALANCE_TYPE_PLUS = 1;
    const BALANCE_TYPE_MINUS = 2;

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
     * @param int $uid 用户id
     * @param int $type 用户账号类型
     * @param int $currency 货币类型，默认为１, 人民币
     * @return  bool 是否创建成功 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/17 11:15:12
    **/
    public function createAccount($uid, $type = ACCOUNT_TYPE_NORMAL, $currency = 1) {

        $account = new static();
        $account->uid = $uid;
        $account->currency = $currency;
        $account->type = $type;

        if ($account->save()) {
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
     * @date 2015/12/01 16:26:45
    **/
    public function plus($money, $transId, $transTypeId, $transTypeName,  $description, $currency = 1) {

        $this->balance += $money;
        if ($this->save()) {
            //记录账单
            $bill = new Bill();
            $bill->uid = $this->uid;
            $bill->trans_id = $transId;
            $bill->trans_type_id = $transTypeId;
            $bill->trans_type_name = $transTypeName;
            $bill->money = $money;
            $bill->balance_type = static::BALANCE_TYPE_PLUS;
            $bill->currency = $currency;
            $bill->description = $description;
            if (! $bill->save()) {
                return false;
            }

            //账户快照产生
            $accountLog = new UserAccountLog();
            $accountLog->uid = $this->uid;
            $accountLog->account_type = $this->account_type;
            $accountLog->currency = $currency;
            $accountLog->balance = $this->balance;
            $accountLog->deposit = $this->deposit;
            $accountLog->frozen_money = $this->frozen_money;
            $accountLog->descrption = $this->dsctcrption;
            $accountLog->balance_type = static::BALANCE_TYPE_PLUS;
            $accountLog->trans_money = $money;
            $accountLog->trans_desc = $description;

            if (! $accountLog->save()) {
                return false;
            }
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
    public function minus($money, $transId, $transTypeId, $transTypeName,  $description, $currency = 1) {

        $this->balance = $this->balance - $money;
        if ($this->save()) {
            //记录账单
            $bill = new Bill();
            $bill->uid = $this->uid;
            $bill->trans_id = $transId;
            $bill->trans_type_id = $transId;
            $bill->trans_type_name = $transTypeName;
            $bill->money = $money;
            $bill->balance_type = static::BALANCE_TYPE_MINUS;
            $bill->currency = $currency;
            $bill->description = $description;
            if ($bill->save()) {
                return true;
            }
            else {
                return false;
            }

            //账户快照产生
            $accountLog = new UserAccountLog();
            $accountLog->uid = $this->uid;
            $accountLog->account_type = $this->account_type;
            $accountLog->currency = $currency;
            $accountLog->balance = $this->balance;
            $accountLog->deposit = $this->deposit;
            $accountLog->frozen_money = $this->frozen_money;
            $accountLog->descrption = $this->dsctcrption;
            $accountLog->balance_type = static::BALANCE_TYPE_MINUS;
            $accountLog->trans_money = $money;
            $accountLog->trans_desc = $description;

            if ($accountLog->save()) {
                return true;
            }
            else {
                return false;
            }
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
