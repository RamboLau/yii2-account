<?php

namespace lubaogui\account;

use yii\helpers\ArrayHelper;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\Trans;
use lubaogui\account\models\Bill;
use lubaogui\payment\Payment;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等,账户操作中包含利润分账，但是分账最多支持2个用户分润
 */
class BaseAccount extends Component 
{

    private $config;  

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 10:12:27
     **/
    public function init() {
        $this->config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/config/main.php'),
            require(__DIR__ . '/config/main-local.php')
        );
    }

    /**
     * @brief 获取某个账户, 如果用户账户不存在，则自动为用户开通一个账号,账户信息不允许被缓存，当开启一个事务时，必须
     * 重新加载账号
     *
     * @return  object 用户账户对象 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:59:27
     **/
    protected function getUserAccount($uid) {
        $userAccount = UserAccount::findOne($uid);
        if (!$userAccount) {
            $userAccount = new UserAccount();
            $userAccount->uid = $uid;
            $userAccount->balance = 0;
            $userAccount->frozen_meony = 0;
            $userAccount->deposit = 0;
            $userAccount->currency = 1; //默认只支持人民币
            if (!$userAccount->save()) {
                return false; 
            }
        }
        return  $userAccount;
    }

    /**
     * @brief 每个交易最后确认交易完成时所进行的操作，主要为打款给目的用户，分润，收取管理费等
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:31:52
     **/
    protected function finishPayTrans($trans) {

        //收款账户处理逻辑
        $sellerAccount = UserAccount::findOne($trans->to_uid);
        if (!$sellerAccount->plus($money)) {
            return false;
        }

        //分润账号处理逻辑
        if ($trans->profit > 0) {
            $this->profit('pay', $trans_profit, '账号利润');
        }

        //手续费逻辑处理
        if ($trans->fee > 0) {
            $this->fee('pay', $fee, '支付手续费');
        }

    }

    /**
     * @brief 完成交易退款,该函数主要完成多种退款流程最后的利润退款，手续费退款等,退款不区分交易模式
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 18:02:59
     **/
    protected function finishRefundTrans($trans) {

        //退款是否收取手续费,可以在这里做逻辑判断
        $buyerAccount = UserAccount::findOne($trans->from_uid); 
        if (!$buyerAccount->plus($trans->money)) {
            return false;
        }

        //此处需要沟通确定如何退款

        //退手续费，退利润等等
        $trans->status = Trans::PAY_STATUS_REFUNDED;
        $trans->save();

        return true;
    }

    /**
     * @brief 
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/05 12:45:03
     **/
    protected function processProfit($action, $money, $reason) {
        $profitAccount = UserAccount::findOne($globalProfitAccountId);
        switch $action {
        case 'pay': {
            $profitAccount->plus($money, $reason);
            break;
        }
        case 'refund': {
            $profitAccount->minus($money, $reason);
            break;
        }
        default:break;
        }
    }

    /**
     * @brief 
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    protected function processFee($action, $money, $reason) {

    }

}
