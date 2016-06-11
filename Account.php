<?php

namespace lubaogui\account;

use Yii;
use yii\helpers\ArrayHelper;
use lubaogui\account\BaseAccount;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\Trans;
use lubaogui\account\models\TransLog;
use lubaogui\account\models\Freeze;
use lubaogui\payment\Payment;
use lubaogui\payment\models\Payable;
use lubaogui\payment\models\Receivable;
use common\models\UserWithdraw;
use yii\base\Exception;

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
            $this->addError(__METHOD__, '非普通帐号不能参与支付交易');
            return false;
        }

        //不论哪种交易模式，首先从用户账户扣款，扣款成功才有后续动作
        if (!$this->minus($buyerAccount->uid, $trans->total_money, $trans->id, '产品担保交易扣款')) {
            $this->addError(__METHOD__, '用户账户扣款失败');
            return false;
        }


        //根据不同的交易模式，执行不同的业务逻辑,直接支付在此完成付款逻辑，担保交易再确认时
        //完成剩余的付款逻辑
        switch ($trans->pay_mode) {
        case Trans::PAY_MODE_DIRECTPAY: {
            if ($this->finishPayTrans($trans)) {
                return true;
            }
            else {
                $this->addError(__METHOD__, '结算付款时出错');
                return false;
            }
            break;
        }
        case Trans::PAY_MODE_VOUCHPAY: {
            $vouchAccount = UserAccount::getVouchAccount();
            if (!$this->plus($vouchAccount->uid, $trans->total_money, $trans->id, '担保账号交易收款')) {
                $this->addError(__METHOD__, '担保账号收款失败');
                return false;
            }

            //变更交易状态
            $trans->status = Trans::PAY_STATUS_SUCCEEDED;
            if ($trans->save()) {
                return true;
            }
            else {
                $this->addError(__METHOD__, '保存交易状态时出错');
                $this->addErrors($trans->getErrors());
                return false;
            }

            break;
        }
        default: break;
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
        Yii::warning('进入担保交易结算环节', __METHOD__);
        $trans = Trans::findOne($transId);
        if ($trans->pay_mode != Trans::PAY_MODE_VOUCHPAY) {
            Yii::warning('非担保交易模式不能走担保确认环节', __METHOD__);
            $this->addError(__METHOD__, '交易并非担保交易，此操作无效');
            return false;
        }
        Yii::warning('成功获取交易信息', __METHOD__);

        //金额从担保账号转出
        Yii::warning('开始从中间担保帐号扣款', __METHOD__);
        $vouchAccount = UserAccount::getVouchAccount();;
        if (!$this->minus($vouchAccount->uid, $trans->total_money, $trans->id, '担保交易账号转出完成购买')) {
            $this->addError(__METHOD__, '担保帐号转出失败');
            Yii::warning('担保交易帐号扣款失败', __METHOD__);
            return false;
        }
        Yii::warning('担保交易帐号扣款成功', __METHOD__);

        //完成交易的后续操作,是在扣款成功之后或者从担保账号中转出款项之后进行的操作
        if (!$this->finishPayTrans($trans)) {
            $this->addError(__METHOD__, '结算失败');
            Yii::warning('交易结算失败', __METHOD__);
            return false;
        }

        //变更交易状态
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if ($trans->save()) {
            Yii::warning('交易信息保存成功', __METHOD__);
            return true;
        }
        else {
            Yii::warning('交易信息保存成功', __METHOD__);
            Yii::warning($trans->getErrors(), __METHOD__);
            $this->addError(__METHOD__, '保存交易信息失败');
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
    public function refundTrans($transId, $refundMoney) {

        $trans = Trans::findOne($transId);
        if (empty($trans)) {
            //此处可记录错误日志,或者写错误信息
            Yii::$app->warning('没有此交易记录');
            $this->addError(__METHOD__, '不存在transId指定的交易');
            return false;
        }

        //仅用户之间的交易支持退款
        if (! $trans->transType->refundable) {
            Yii::$app->warning('此种交易不支持退款');
            $this->addError(__METHOD__, '该类型的交易不支持退款,仅用户之间的担保交易支持退款');
            return false;
        }

        //如果已经在走退款流程，则直接抛出异常
        if ($trans->status === Trans::PAY_STATUS_REFUNDED) {
            Yii::$app->warning('退款已在进行中，或者已完成退款');
            $this->addError(__METHOD__, '退款已在进行中，请勿重复退款');
            return false;
        }

        //退款金额大小判断:退款金额需要大于0，同时退款金额不能超过交易总金额
        if ($refundMoney >= 0) {
            if ($refundMoney > $trans->total_money) {
                $this->addError(__METHOD__, '退款金额不能大于交易总金额');
                return false;
            }
            else {
                $trans->earnest_money = $trans->total_money - $refundMoney;
                $trans->refunded_money =  $refundMoney;
            }
        }
        else {
            $this->addError('display-error', '退款金额需要大于0，并且不能超过订单总金额!');
            return false;
        }

        //根据交易的不同状态进行退款
        switch ($trans->status) {
        case Trans::PAY_STATUS_SUCCEEDED : {
            //从担保账号中退款,由于交易没有达成，分润也没有做，直接退款即可
            $vouchAccount = UserAccount::getVouchAccount();
            if (!$this->minus($vouchAccount->uid, $trans->total_money, $trans->id, '担保交易担保账号退款')) {
                $this->addError(__METHOD__, '担保账号退款失败');
                return false;
            }
            //如果交易存在保证金，则直接在退款同时将保证金打款给hug
            if ($trans->earnest_money > 0) {
                $sellerAccount = $this->getUserAccount($trans->to_uid);
                if (!$this->plus($sellerAccount->uid, $trans->earnest_money, $trans->id, '产品退款订金收入')) {
                    $this->addError(__METHOD__, '给卖家打款保证金时出错');
                    return false;
                }
            }
            break;
        }
        case Trans::PAY_STATUS_FINISHED : {
            //TODO
            //获取卖家账号，并退款,如果卖方收款但是余额不足，无法退款。后期可以采用保证金方式，目前不支持此种退款
            return false;
            $sellerAccount = $this->getUserAccount($trans->to_uid);
            if (!$sellerAccount->minus($trans->money, $trans, '产品交易退款')) {
                return false;
            }
            break;
        }
        default: {
            $this->addError('display-error', '此种支付状态下的交易无法申请退款');
            return false;
        }
        }

        //对于finishRefundTrans,需要区分交易状态，SUCCEEDED状态只返还用户钱款即可，FINISHED状态需要退利润等
        if ($this->finishRefundTrans($trans)) {
            $transLog = new TransLog();
            $transLog->action = '交易退款';
            $transLog->money = $refundMoney;
            $transLog->currency = $trans->currency;
            $transLog->trans_id = $trans->id;
            if ($transLog->save()) {
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
     * @brief 冻结资金,在提现申请的时候，会冻结资金, 冻结操作失败，则提现申请无法继续
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 20:00:36
    **/
    public function freezeForWithdraw($uid, $money, $withdrawId) {
        $withdraw = UserWithdraw::findOne($withdrawId);
        if (empty($withdraw)) {
            $this->addError('display-error', '提现申请记录不存在');
            return false;
        }
        $freezeType = Freeze::FREEZE_TYPE_WITHDRAW;

        if ($this->freeze($uid, $money, $freezeType, $withdrawId, '用户提现冻结')) {
            return true;
        }
        else {
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
    public function unfreezeForWithdraw($withdrawId) {
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
     * @author 吕宝贵
     * @date 2015/12/06 17:44:54
     **/
    public function processWithdraw($withdrawId) {
        $withdraw = UserWithdraw::findOne($withdrawId);
        if (empty($withdraw)) {
            $this->addError(__METHOD__, '提现申请记录不存在');
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
        $trans->to_uid = $withdraw->uid;
        $trans->currency = 1;

        if (! $trans->save()) {
            $this->addError('display-error', '处理提现时保存交易信息出错');
            $this->addErrors($trans->getErrors());
            return false;
        }

        $freeze = Freeze::findOne(['source_id'=>$withdraw->id]);
        if (! $freeze) {
            $this->addError('display-error', '找不到冻结记录');
            return false;
        }

        //设置freeze记录关联的transId
        $freeze->trans_id = $trans->id;
        if (! $freeze->save()) {
            $this->addError('display-error', '冻结记录回写交易信息失败');
            $this->addErrors($freeze->getErrors());
            return false;
        }

        //生成打款记录
        $payable = null;
        if (! $payable = Payable::findOne(['trans_id'=>$trans->id])) {
            $payable = new  Payable();
        }
        $payable->trans_id = $trans->id;
        $payable->pay_uid = 0;
        $payable->receive_uid = $trans->to_uid;
        $payable->currency = 1;
        $payable->money = $trans->total_money;
        $payable->status = Payable::PAY_STATUS_WAITPAY;
        $payable->pay_method = Payable::PAY_METHOD_DIRECTPAY;

        //保存打款记录，供财务下载打款处理
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
     * @return  bool 是否回掉成功 
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
    public function processWithdrawPaySuccess($payId, $paySuccessCallback) {

        //处理交易信息
        $payable = Payable::findOne($payId); 
        if (!$payable) {
            $this->addError(__METHOD__, '获取付款记录信息失败');
            return false;
        }
        $trans = $payable->trans;
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if (!$trans->save()) {
            $this->addError(__METHOD__, '交易信息保存失败');
            return false;
        }

        //完成冻结，用户的冻结金额进行操作
        $withdrawUser = $this->getUserAccount($payable->receive_uid);
        if (! $this->finishFreeze($withdrawUser->uid, $trans->id)) {
            $this->addError(__METHOD__, '减除用户冻结余额时出错!');
            return false;
        }

        //用户设定的回调操作
        $callbackData['id'] = $withdrawId ;
        if (call_user_func($paySuccessCallback, $callbackData)) {
            return true;
        }
        else {
            $this->addError(__METHOD__, '处理提现支付成功回掉函数时出错');
            return false;
        }
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
        $receivable->from_uid = $trans->from_uid;
        $receivable->status = Receivable::PAY_STATUS_WAITPAY;
        $receivable->description = 'Mr-Hug产品购买充值';
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
        if (! $transCharge = Trans::findOne(['trans_id_ext'=>$trans->id, 'trans_type_id'=>Trans::TRANS_TYPE_CHARGE])) {
            $transCharge = new Trans();
        }

        $transCharge->trans_id_ext = $trans->id;
        $transCharge->trans_type_id = Trans::TRANS_TYPE_CHARGE;
        $transCharge->from_uid = $trans->from_uid;
        $transCharge->to_uid = $trans->to_uid;
        //所有充值交易都为直接交易
        $transCharge->pay_mode = Trans::PAY_MODE_DIRECTPAY;
        $transCharge->money = $trans->total_money;
        $transCharge->total_money = $trans->total_money - $userAccount->balance;

        if (! $transCharge->save()) {
            $this->addError(__METHOD__, '保存充值交易单时发生错误');
            $this->addErrors($transCharge->getErrors());
            return false;
        }
 
        //生成收款单，并返回
        return $this->generateReceivable($transCharge);

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
    public function processChargePaySuccess($transId) {

        //获取交易记录
        $trans = Trans::findOne($transId);
        if (!$trans) {
            $this->addError(__METHOD__, '对应的交易记录不存在');
            return false;
        }

        //充值型trans，直接成功,设置交易信息
        $trans->status = Trans::PAY_STATUS_FINISHED;
        if (!$trans->save()) {
            return false;
        }

        //为用户账户充值,充值成功即为一个事物，后续的购买判断在controller里面完成
        $userAccount = $this->getUserAccount($trans->from_uid);
        if (!$this->plus($userAccount->uid, $trans->total_money, $trans->id, '用户账户充值')) {
            $this->addError(__METHOD__, '为账户充值时发生错误');
            return false;
        }

        return $trans;

    }

}
