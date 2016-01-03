<?php

namespace lubaogui\account;

use yii\helpers\ArrayHelper;
use lubaogui\account\BaseAccount;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\Trans;
use lubaogui\account\models\Freeze;
use lubaogui\payment\Payment;
use lubaogui\payment\models\Payable;
use lubaogui\payment\models\Receivable;
use common\models\UserWithdraw;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等,账户操作中包含利润分账，但是分账最多支持2个用户分润
 */
class Account extends BaseAccount 
{


    private $vouchAccountId = 1;

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
        if (!$buyerAccount->minus($trans->total_money, $trans->id, $trans->trans_type_id, '交易扣款', '产品担保交易扣款')) {
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
            $vouchAccount = $this->getUserAccount($this->vouchAccountId);
            if (!$vouchAccount->plus($trans->total_money, $trans->id, $trans->trans_type_id, '交易', '担保交易')) {
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
        if (!$vouchAccount->minus($trans->total_money, $trans->id, $trans->trans_type_id, '担保交易', '担保交易账号转出完成购买')) {
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
     * @brief 转账操作,转账操作属于直接支付给对方金额,扩展时使用 TODO
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
     * @brief 冻结资金,在提现申请的时候，会冻结资金,如果用户取消，会取消冻结资金
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 20:00:36
    **/
    public function freeze($withdrawId) {
        $withdraw = UserWithdraw::findOne($withdrawId);
        if (empty($withdraw)) {
            $this->addError('display-error', '提现申请记录不存在');
            return false;
        }

        //产生freeze记录
        $freeze = new Freeze();
        $freeze->source_id = $withdrawId;
        $freeze->uid = $withdraw->uid;
        $freeze->type = Freeze::FREEZE_TYPE_WITHDRAW;
        $freeze->status = Freeze::FREEZE_STATUS_FREEZING;
        $freeze->currency = 1;
        $freeze->money = $withdraw->money;
        $freeze->description = '提现';
        
        if ($freeze->save()) {
            $userAccount = $this->getUserAccount($withdraw->uid);
            if ($userAccount->freeze($withdraw->money)) {
                return true;
            }
            else {
                $this->addError('display-error', '冻结用户金额失败');
                return false;
            }
        }
        else {

            $this->addError('display-error', '冻结记录保存失败');
            return false;
        }
    }

    /**
     * @brief 解除锁定,只有用户主动取消提现或者冻结操作的时候会接触freeze
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 21:51:16
    **/
    public function unfreeze($withdrawId) {
        $withdraw = UserWithdraw::findOne($withdrawId);
        if (empty($withdraw)) {
            $this->addError('display-error', '提现申请记录不存在');
            return false;
        }

        $freeze = Freeze::findOne(['source_id'=>$withdraw->id, 'type' => Freeze::FREEZE_TYPE_WITHDRAW]);

        if ($freeze->unfreeze()) {
            $userAccount = $this->getUserAccount($withdraw->uid);
            if ($userAccount->unfreeze($withdraw->money)) {
                return true;
            }
            else {
                $this->addError('display-error', '冻结用户金额失败');
                return false;
            }
        }
        else {

            $this->addError('display-error', '冻结记录保存失败');
            return false;
        }

    }

    /**
     * @brief 处理实际的提现流程，只有在提现审核通过之后才会调用此方法 
     * 来产生trans
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:44:54
     **/
    public function processWithdraw($withdrawId) {
        $withdraw = UserWithdraw::findOne($withdrawId);
        if (empty($withdraw)) {
            $this->addError('display-error', '提现申请记录不存在');
            return false;
        }

        $withdrawUser = $this->getUserAccount($withdraw->uid);

        //生成trans
        $trans = new Trans();
        $trans->trans_id_ext = $withdrawId;
        $trans->trans_type_id = Trans::TRANS_TYPE_WITHDRAW;
        $trans->status = Trans::PAY_STATUS_WAITPAY;
        $trans->pay_mode = Trans::PAY_MODE_DIRECTPAY;;
        $trans->total_money = $withdraw->money;
        $trans->from_uid = $withdraw->uid;
        $trans->currency = 1;

        if (! $trans->save()) {
            $this->addError('display-error', '处理提现时保存交易信息出错');
            $this->addErrors($trans->getErrors());
            return false;
        }

        if (! $trans->freeze->saveTransId($trans->id)) {
            $this->addError('display-error', '冻结记录回写交易信息失败');
            $this->addErrors($trans->freeze->getErrors());
            return false;
        }

        $payable = null;
        if (! $payable = Payable::findOne(['trans_id'=>$trans->id])) {
            $payable = new  Payable();
        }

        $payable->trans_id = $trans->id;
        $payable->pay_uid = $trans->from_uid;
        $payable->receive_uid = $trans->to_uid;
        $payable->currency = 1;
        $payable->money = $trans->total_money;
        $payable->status = Payable::PAY_STATUS_WAITPAY;
        $payable->pay_method = Payable::PAY_METHOD_DIRECTPAY;

        //返回收款记录,用以跳转到第三方进行支付
        if ($payable->save()) {
            return true;
        }
        else {
            $this->addErrors($payable->getErrors());
            return false;
        }
 
    }

    /**
     * @brief 处理提现下载支付列表到银行成功的动作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 16:30:03
    **/
    public function processWithdrawPaying($payable, $withdrawPayingCallback) {

        $payable->status = Payable::PAY_STATUS_PAYING;

        if (!$payable->save()) {
            $this->addError('display-error', '付款记录状态保存失败');
            return false;
        }
        
        $trans = $payable->trans;
        $withdrawId = $trans->trans_id_ext;

        $callbackData['id'] = $withdrawId ;
        return call_user_func($withdrawPayingCallback, $callbackData);

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
    public function processWithdrawPaySuccess($payId, $paySuccesscallback) {

        $payable = Payable::findOne($payId); 

        if (!$payable) {
            $this->addError('display-error', '获取付款记录信息失败');
            return false;
        }

        //处理交易信息
        $trans = $payable->trans;
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if (!$trans->save()) {
            $this->addError('display-error', '交易信息保存失败');
            return false;
        }

        $withdrawUser = $this->getUserAccount($payable->receive_uid);
        //完成冻结，将款项从冻结中减除
        if ($withdrawUser->finishFreeze($trans->total_money)) {
            return true;
        }
        else {
            $this->addError('display-error', '减除用户冻结余额时出错!');
            $this->addErrors($withdrawUser->getErrors());
            return false;
        }

        //冻结记录完成
        $freeze = Freeze::fineOne(['trans_id'=>$trans->id, 'type'=>Freeze::FREEZE_TYPE_WITHDRAW]);

        if (!$freeze) {
            $this->addError('display-error', '冻结记录不存在');
            return false;
        }

        if (!$freeze->finishFreeze()) {
            $this->addError('display-error', '冻结记录无法更改状态');
            return false;
        }

        //用户设定的回调操作
        $callbackData['id'] = $withdrawId ;
        return call_user_func($paySuccesscallback, $callbackData);
    }

    /**
     * @brief 根据交易记录产生收款记录,用户充值时使用
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

        $receivable = null;

        if (! $receivable = Receivable::findOne(['trans_id'=>$trans->id])) {
            $receivable = new  Receivable();
        }

        $receivable->money = $trans->total_money;
        $receivable->trans_id = $trans->id;
        $receivable->uid = $trans->from_uid;
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


        $transCharge = null;

        //如果充值的交易已存在，则不再多余生成，复用充值交易
        if (! $transCharge = Trans::find()->where(['trans_id_ext'=>$trans->id, 'trans_type_id'=>TRANS_TYPE_CHARGE])->one()) {
            $transCharge = new Trans();
        }

        $transCharge->trans_id_ext = $trans->id;
        $transCharge->trans_type_id = Trans::TRANS_TYPE_CHARGE;
        $transCharge->from_uid = $trans->from_uid;
        $transCharge->to_uid = $trans->to_uid;
        //所有充值交易都为直接交易
        $transCharge->pay_mode = Trans::PAY_MODE_DIRECTPAY;
        $transCharge->total_money = $trans->total_money - $userAccount->balance;

        if (! $transCharge->save()) {
            return false;
        }
 
        return $this->generateReceivable($transCharge);

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
    public function generatePayable($trans) {
        //新建收款记录

    }

    /**
     * @brief 充值成功处理,第三方的只有充值成功通知,在支持成功之后的业务逻辑处理，支付宝和微信的所有回告都应该是充值接口
     *
     * @param string $callback 回调函数，仅仅对需要对订单进行逻辑处理的操作有效，需要添加订单id为参数
     * @return Trans object 返回充值支付的交易记录 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 21:14:32
     **/
    public function processChargePaySuccess($receivable) {

        $receivable->status = Receivable::PAY_STATUS_FINISHED;
        if (!$receivable->save()) {
            return false;
        }

        //获取交易记录
        $trans = Trans::findOne($receivable->trans_id);
        if (!$trans) {
            return false;
        }

        //充值型trans，直接成功,设置交易信息
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if (!$trans->save()) {
            return false;
        }

        //为用户账户充值,充值成功即为一个事物，后续的购买判断在controller里面完成
        $userAccount = $this->getUserAccount($trans->to_uid);
        if (!$userAccount->plus($trans->total_money, $trans->id, $trans->trans_type_id, '充值', '账户充值')) {
            return false;
        }

        return $trans;

    }

}
