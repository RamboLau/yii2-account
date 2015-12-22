<?php

namespace lubaogui\account;

use yii\helpers\ArrayHelper;
use lubaogui\account\BaseAccount;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\Trans;
use lubaogui\account\models\Bill;
use lubaogui\payment\Payment;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等,账户操作中包含利润分账，但是分账最多支持2个用户分润
 */
class Account extends BaseAccount 
{

    /**
     * @brief 用户直接购买另外一个用户的产品,controller用于构建trans, account完成最终的支付业务逻辑
     *
     * @return  public function 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:38
     **/
    public function pay($trans) {

        //判断trans是否已经完成，如果已经完成，则不允许第二次支付
        $buyerAccount = $this->getUserAccount($trans->from_uid);
        if ($buyerAccount->type != UserAccount::ACCOUNT_TYPE_NORMAL) {
            //throw new Exception('非普通账号不支持直接交易!请联系管理员');
            return false;
        }

        //不论哪种交易模式，首先从用户账户扣款，扣款成功才有后续动作
        if (!$buyerAccount->minus($trans->total_money)) {
            return false;
        }

        //直接支付交易,从购买者账号中直接扣款
        if ($trans->pay_mode == Trans::PAY_MODE_DIRECTPAY) {

            //finishPayTrans主要完成交易的后续操作，如打款给卖家，收取手续费等
            if ($this->finishPayTrans($trans)) {
                return true;
            }
            else {
                return false;
            }
        }

        //担保支付情况，需要将款项在用户扣款成功之后支付给中间账号
        if ($trans->pay_mode == Trans::PAY_MODE_VOUCHPAY) {
            $vouchAccount = $this->getUserAccount($vouchAccountId);
            if (!$vouchAccount->plus($money)) {
                return false;
            }

            //变更交易状态
            $trans->status = Trans::PAY_STATUS_SUCCEEDED;
            if ($trans->save()) {
                return true;
            }
            else {
                return false;
            }
        }

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
    public function confirmVouchPay($transId) {
        $trans = Trans::findOne($transId);
        if ($trans->pay_mode != Trans::PAY_MODE_VOUCHPAY) {
            return false;
        }

        //金额从担保账号转出
        $vouchAccount = UserAccount::findOne($vouchAccountId);
        if (!$vouchAccount->minus($trans->total_money)) {
            return false;
        }

        //完成交易的后续操作,是在扣款成功之后或者从担保账号中转出款项之后进行的操作
        if (!$this->finishPayTrans($trans)) {
            return false;
        }

        //变更交易状态
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if ($trans->save()) {
            return true;
        }
        else {
            return false;
        }
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
    public function refundTrans($transId) {

        $trans = Trans::findOne($transId);
        if (empty($trans)) {
            //此处可记录错误日志,或者写错误信息
            Yii::$app->warning('没有此交易记录');
            return false;
        }

        //仅用户之间的交易支持退款
        if ($trans->type != Trans::TRANS_TYPE_TRADE) {
            Yii::$app->warning('仅用户之间的交易支持退款');
            return false;
        }

        //如果已经在走退款流程，则直接抛出异常
        if ($trans->status === Trans::PAY_STATUS_REFUNDED) {
            Yii::$app->warning('退款已在进行中，或者已完成退款');
        }

        //根据交易的不同状态进行退款
        switch ($trans->status) {
        case Trans::PAY_STATUS_SUCCEEDED : {
            //从担保账号中退款,由于交易没有达成，分润也没有做，直接退款即可
            $vouchAccount = UserAccount::findOne($vouchAccountId);
            if (!$vouchAccount->minus($money)) {
                return false;
            }
            break;
        }
        case Trans::PAY_STATUS_FINISHED : {
            //获取卖家账号，并退款
            $sellerAccount = UserAccount::findOne($vouchAccountId);
            if (!$sellerAccount->minus($money)) {
                return false;
            }
            break;
        }
        default: {
            return false;
        }
        }

        //对于finishRefundTrans,需要区分交易状态，SUCCEEDED状态只返还用户钱款即可，FINISHED状态需要退利润等
        if ($this->finishRefundTrans($trans)) {
            return true;
        }
        else {
            return false;
        }

    }

    /**
     * @brief 转账操作,转账操作属于直接支付给对方金额,扩展时使用
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
     **/
    public function transfer($transParams) {


    }

    /**
     * @brief 用户提现，提现只能提取balance中的额度，保证金无法提取,该方法完成实际的提现操作,实际需要WithdrawForm
     * 来产生trans
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:44:54
     **/
    public function withdraw($trans) {

        $withdrawUser = $this->getUserAccount($trans->from_uid);
        if ($money > $withdrawUser->balance) {
            return false;
        }

        //创建付款记录
        //Todo: 补全相关信息
        $payable = new Payable();
        $payable->money = $trans->money;
        $payable->status = 1;
        if (!$payable->save()) {
            return false;
        }

        //创建冻结记录，冻结的意思是该笔款项在途，无法使用,提现申请审核中可以取消提现，提现中状态无法取消
        if (!$withdrawUser->freeze($trans->money)) {
            return false;
        }
        return true;

    }

    /**
     * @brief 提现付款给用户账号成功
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 20:31:42
     **/
    public function withdrawPaySucceeded($payId, $callback) {

        $payable = Payable::findOne($payId); 
        $withdrawUser = UserAccount::findOne($payable->to_uid);
        //完成冻结，将款项从冻结中减除
        $withdrawUser->finishFreeze();
        $payable->status = Payable::PAY_STATUS_SUCCEEDED;
        if (!$payable->save()) {
            return false;
        }
        $trans = Trans::findOne($payable->trans_id);
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if ($trans->save()) {
            //回调函数，主要在action层填写，记录订单的信息更新等,如果是SOA服务，此操作会触发发送一条成功消息
            call_user_func($callback, $data);  
            return true;
        }

    }


    /**
     * @brief 根据交易记录产生收款记录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/23 00:07:28
    **/
    public function generateReceivable($trans) {
        //新建收款记录
        $receivable = new  Receivable();
        $receivable->money = $trans->total_money;
        $receivable->trans_id = $trans->id;
        $receivable->status = Receivable::PAY_STATUS_WAITPAY;
        //返回收款记录,用以跳转到第三方进行支付
        if ($receivable->save()) {
            return $receivable;
        }
        else {
            return false;
        }
    }
 
    /**
     * @brief 用于产生用户充值的充值请求记录,在action中判断是否需要充值，如果需要，在此计算充值请求的具体内容
     *
     * @param array 交易的相关信息，以及订单支付的总金额, 充值的金额都需要在此列出
     * @return Receivable 代收款记录信息
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:49:22
     **/
    public function generateReceivableAndChargeTrans($trans, $userAccount) {

        $transCharge = new Trans();
        $transCharge->trans_id_ext = $trans->id;
        $transCharge->type = Trans::CHARGE;
        $transCharge->total_money = $trans->total_money - $userAccount->balance;

        if (! $transCharge->save()) {
            return false;
        }
 
        return $this->generateReceivable($transCharge);

    }

    /**
     * @brief 支付成功处理,在支持成功之后的业务逻辑处理，支付宝和微信的所有回告都应该是充值接口
     *
     * @param string $callback 回调函数，仅仅对需要对订单进行逻辑处理的操作有效，需要添加订单id为参数
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 21:14:32
     **/
    public function chargePaySucceeded($trans) {

        $receivable = Receivable::findOne($receivableId);
        $receivable->status = Receivable::PAY_STATUS_FINISHED;
        $receivable->save();
        $trans = Trans::findOne($receivable->trans_id);
        //充值型trans，直接成功
        $trans->status = Trans::PAY_STATUS_FINISHED;
        $userAccount = UserAccount::findOne($trans->uid);
        //为用户账户充值
        $userAccount->plus($trans->total_money, '账户充值');

        //交易成功之后，需要根据trans是否有关联的trans_id来判断是否进行订单交易处理
        if ($trans->trans_id_ext) {
            $transOrder = Trans::findOne($trans->trans_id_ext);
            switch  ($transOrder->type) {
            case Trans::PAY_MODE_DIRECTPAY: {
                if (!$this->directPay($transOrder)) {
                    return false;
                }
                break;
            }
            case Trans::PAY_MODE_VOUCHPAY: {
                if (!$this->vouchPay($transOrder)) {
                    return false;
                }
                break;
            }
            default:break;
            }
            //回调订单处理函数
            call_user_func([$callback, $transOrder->trans_ext_id]);
        }

        return true;
    }

}
