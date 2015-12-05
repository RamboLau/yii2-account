<?php

namespace lubaogui\account;

use lubaogui\account\models\Trans;
use lubaogui\account\models\Bill;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等,账户操作中包含利润分账，但是分账最多支持2个用户分润
 */
class Account extends Component 
{
    /**
     * @brief 用户直接购买另外一个用户的产品
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:38
    **/
    public function directPayForTrans($trans) {

        $buyerAccount = UserAccount::findOne($trans->from_uid);
        if ($buyerAccount->type != UserAccount::ACCOUNT_TYPE_NORMAL) {
            throw new Exception('非普通账号不支持直接交易!请联系管理员');
        }

        //提交给账户进行扣款,账单，账户变化都进行更新
        if (!$buyerAccount->minus($trans->total_money)) {
            return false;
        }

        //收款账户处理逻辑
        $sellerAccount = UserAccount::findOne($trans->to_uid);
        if (!$sellerAccount->plus($money)) {
            return false;
        }

        //分润账号处理逻辑
        $this->profit('pay', $money, '账号利润');

        //手续费逻辑处理
        $this->fee('pay', $fee, '支付手续费');

        return true;
    }


    /**
     * @brief 担保交易支付,担保交易支付先将资金从购买者手中扣除，支付给担保人账号，确认收入之后将资金划给目标用户账号
     *
     * @return  public 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/03 06:53:03
    **/
    public vouchPayForTrans($trans) {

        $buyerAccount = UserAccount::findOne($trans->from_uid);

        if ($buyerAccount->type != UserAccount::ACCOUNT_TYPE_NORMAL) {
            throw new Exception('非普通账号不支持直接交易!请联系管理员');
        }
         
        //提交给账户进行扣款,账单，账户变化都进行更新,扣款逻辑中会检查用户的余额是否充足
        if (!$buyerAccount->minus($trans->total_money)) {
            return false;
        }

        //资金打入到担保账号
        $vouchAccount = UserAccount::findOne($vouchAccountId);
        if (!$vouchAccount->plus($money)) {
            return false;
        }

        //变更交易状态
        $trans->status = Trans::PAY_STATUS_SUCCEEDED;
        $trans->save();

        return true;
    }

    /**
     * @brief 确认某个交易完成，对于担保交易需要执行此操作(大部分交易属于担保交易)，对于直接支付交易无此操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 16:22:19
    **/
    public function confirmVouchTrans($trans) {
        if ($tans->pay_mode != Trans::PAY_MODE_VOUCHPAY) {
            throw new Exception('非担保交易无须确认');
        }

        //获取担保账号
        $vouchAccount = UserAccount::findOne($vouchAccountId);

        //金额从担保账号转出
        if (!$vouchAccount->minus($money)) {
            return false;
        }

        //收款人获取收入
        $sellerAccount = UserAccount::findOne($trans->to_uid);
        if (!$sellerAccount->plus($trans->money)) {
            return false;
        }

        //利润账号打入收益
        $profitAccount = UserAccount::findOne($globalProfitAccountId);
        $sellerAccount = UserAccount::findOne($trans->to_uid);
        if (!$sellerAccount->plus($trans->money)) {
            return false;
        }

        //变更交易状态
        $trans->status = Trans::PAY_STATUS_FINISHED;
        $trans->save();

        return true;
    }

    /**
     * @brief 退款操作,会根据交易的具体形态来判断退款方法，仅用户之间的交易支持退款，充值和提现不支持退款
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
    **/
    public function refundTrans($trans) {

        //仅用户之间的交易支持退款
        if ($trans->type != Trans::TRANS_TYPE_TRADE) {
            throw new Exception('仅用户之间的交易支持退款');
        }

        //如果已经在走退款流程，则直接抛出异常
        if ($trans->status === Trans::PAY_STATUS_REFUNDED) {
            throw new Exception('退款已在进行中，或者已完成退款');
        }

        //担保交易未确定付款的交易，从中间账号返回给购买用户
        if ($trans->status === Trans::PAY_STATUS_SUCCEEDED) {
            //获取担保账号
            $vouchAccount = UserAccount::findOne($vouchAccountId);

            //担保账号将款项退给用户
            if (!$vouchAccount->minus($money)) {
                return false;
            }
        }

        //如果交易双方都已经收到款项，则从付款方账户中扣减
        if ($trans->status === Trans::PAY_STATUS_FINISHED) {
            //获取担保账号
            $sellerAccount = UserAccount::findOne($vouchAccountId);

            //担保账号退款
            if (!$sellerAccount->minus($money)) {
                return false;
            }

            //利润账号退款


            //手续费账号退款,手续费是否可以退款，需要由产品流程来判断


            //分润账号退款


        }

        //退款是否收取手续费,可以在这里做逻辑判断
        $buyerAccount = UserAccount::findOne($trans->from_uid); 
        if (!$buyerAccount->plus($trans->money)) {
            return false;
        }

        $trans->status = Trans::PAY_STATUS_REFUNDED;
        $trans->save();

        return true;
    }

    /**
     * @brief 转账操作,转账操作属于直接支付给对方金额
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
    **/
    public function transferToAccount($trans) {

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
    protected function profit($action, $money, $reason) {
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
    protected function fee($action, $money, $reason) {

    }

}
