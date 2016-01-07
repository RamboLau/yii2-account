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
use lubaogui\account\models\Freeze;
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
    const ACCOUNT_TYPE_NORMAL = 10;              //个人普通账号
    const ACCOUNT_TYPE_COMPANY = 20;            //公司类型账号，非自有公司
    const ACCOUNT_TYPE_SELFCOMPANY_PAY = 30;    //公司现金支付账号
    const ACCOUNT_TYPE_SELFCOMPANY_VOUCH = 40; //担保账号
    const ACCOUNT_TYPE_SELFCOMPANY_PROFIT = 50; //利润账号
    const ACCOUNT_TYPE_SELFCOMPANY_FEE = 60;    //公司手续费收费账号

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
    public static function createAccount($uid, $type = ACCOUNT_TYPE_NORMAL, $currency = 1) {

        $account = new static();
        $account->uid = $uid;
        $account->currency = $currency;
        $account->type = $type;
        $account->is_enabled = 1;

        if ($account->save()) {
            return $account;
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
    public function plus($money, $trans, $description, $currency = 1) {
        return $this->balance(static::BALANCE_TYPE_PLUS, $money, $trans, $description, $currency =1);
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
    public function minus($money, $trans, $description, $currency = 1) {
        if ($this->balance - $money < 0) {
            $this->addError('balance', '余额不足，无法支持操作');
            return false;
        }
        return $this->balance(static::BALANCE_TYPE_MINUS, $money, $trans, $description, $currency =1);
    }

    /**
     * @brief 冻结用户的金额,该操作仅操作用户的金额，不填写freeze记录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/04 23:50:06
    **/
    public function freeze($money) {
        if ($this->balance < $money) {
            $this->addError('balance', '余额不足');
            return false;
        }
        $this->balance = $this->balance - $money;
        $this->frozen_money = $this->frozen_money + $money;

        if ($this->save()) {
            return true;
        }
        else {
            return false;
        }
    }


    /**
     * @brief 解锁金额
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 22:03:21
    **/
    public function unfreeze($money) {
        $this->balance = $this->balance + $money;
        $this->frozen_money = $this->frozen_money - $money;

        if ($this->save()) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @brief 完成金额冻结，从冻结余额中扣除冻结记录对应的金额
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 14:53:04
    **/
    public function finishFreeze($money) {

        $this->frozen_money = $this->frozen_money - $money;
        if ($this->save()) {
            return true;
        }
        else {
            return false;
        }

    }


    /**
     * @brief 账户减除或者增加金额
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 14:39:00
    **/
    public function balance($balanceType, $money, $trans, $description) {

        if ($balanceType === static::BALANCE_TYPE_PLUS) {
            $this->balance += $money;
        }
        else if ($balanceType === static::BALANCE_TYPE_MINUS) {
            $this->balance -= $money;
        }
        else {
            $this->addError('uid', '不支持提交的账户操作类型');
            return false;
        }

        if ($this->save()) {
            //记录账单
            $bill = new Bill();
            $bill->uid = $this->uid;
            $bill->trans_id = $trans->id;
            $bill->trans_type_id = $trans->trans_type_id;
            $bill->trans_type_name = $trans->transType->name;
            $bill->money = $money;
            $bill->balance_type = $balanceType;
            $bill->currency = $trans->currency;
            $bill->description = $description;
            if (! $bill->save()) {
                $this->addErrors($bill->getErrors());
                return false;
            }

            //账户快照产生
            $accountLog = new UserAccountLog();
            $accountLog->uid = $this->uid;
            $accountLog->account_type = $this->type;
            $accountLog->currency = $trans->currency;
            $accountLog->balance = $this->balance;
            $accountLog->deposit = $this->deposit;
            $accountLog->frozen_money = $this->frozen_money;
            $accountLog->balance_type = $balanceType;
            $accountLog->trans_money = $money;
            $accountLog->trans_desc = $description;

            if (! $accountLog->save()) {
                $this->addErrors($accountLog->getErrors());
                return false;
            }
            return true;

        }    
        else {
            return false;
        }

    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
