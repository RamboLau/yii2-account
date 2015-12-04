<?php

namespace lubaogui\account;


use lubaogui\account\models\Trans;

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
        $buyerAccount = UserAccount::findOne($trans->uid);

        if ($buyerAccount->type != UserAccount::ACCOUNT_TYPE_NORMAL) {
            throw new Exception('非普通账号不支持直接交易!请联系管理员');
        }

        //提交给账户进行扣款，如果失败则返回false
        if (!$buyerAccount->minus($trans->total_money)) {
            return false;
        }

        $sellerAccount = UserAccount::findOne($trans->to_uid);

        //收款账户处理逻辑
        if (!$sellerAccount->plus($money)) {
            return false;
        }

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


    }

    /**
     * @brief 退款操作,会根据交易的具体形态来判断退款方法
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
    **/
    public function refundForTrans($trans) {

        //如果已经在走退款流程，则直接抛出异常
        if ($trans->status >= Trans::PAY_STATUS_REFUND_AUDITING) {
            throw new Exception('退款已在进行中，或者已完成退款');
        }

        //如果交易双方都已经收到款项，则通过直接退款方式完成退款
        if ($trans->status === Trans::PAY_STATUS_FINISHED) {
            throw new Exception('先完成退款');
        }

        //成功状态只有担保交易存在，直接支付状态直接是支付完成FINISHED
        if ($trans->status === Trans::PAY_STATUS_SUCCEEDED) {

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
     * @date 2015/12/03 09:17:23
    **/
    protected function refundDirectPayTrans() {

    }

    /**
     * @brief 
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/03 09:17:29
    **/
    protected function refundVouchPayTrans() {


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
     * @brief 确认某个交易完成，对于担保交易需要执行此操作(大部分交易属于担保交易)，对于直接支付交易无此操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 16:22:19
    **/
    public function confirmTrans($trans) {

    }
}
