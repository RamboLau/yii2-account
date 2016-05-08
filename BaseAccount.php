<?php

namespace lubaogui\account;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\base\Model;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\UserAccountLog;
use lubaogui\account\models\Trans;
use lubaogui\account\models\Bill;
use lubaogui\payment\Payment;
use lubaogui\account\behaviors\ErrorBehavior;;
use yii\base\Exception;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等,账户操作中包含利润分账，但是分账最多支持2个用户分润
 */
class BaseAccount extends Model 
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
        $this->config = ArrayHelper::merge(
            require(__DIR__ . '/config/main.php'),
            require(__DIR__ . '/config/main-local.php')
        );
    }

    /**
     * @brief 默认的错误behaviors列表，此处主要是追加错误处理behavior
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 16:55:03
     **/
    public function behaviors() {
        return [
            ErrorBehavior::className(),
        ];
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
    public function getUserAccount($uid, $accountType = UserAccount::ACCOUNT_TYPE_NORMAL) {
        if (empty($uid)) {
            $this->addError('display-error', '提交的用户id为空');
            return false;
        }
        $userAccount = UserAccount::findOne($uid);
        if (!$userAccount) {
            $userAccount = UserAccount::createAccount($uid, $accountType);
            if (! $userAccount) {
                $this->addError('display-error', '为用户开户失败');
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
        if (!$sellerAccount->plus($trans->money, $trans, '产品售卖收入')) {
            return false;
        }

        //分润账号处理逻辑
        if ($trans->profit > 0) {
            if (!$this->processProfit('pay', $trans)) {
                $this->addError('display-error', '处理利润收入失败');
                return false;
            }
        }

        //手续费逻辑处理
        if ($trans->fee > 0) {
            return $this->processFee('pay', $trans);
        }

        return true;

    }

    /**
     * @brief 退款的后续处理操作，完成交易和付款成功有不同的退款逻辑, 此方法主要完成退款后买方和手续费等处理操作 
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 18:02:59
     **/
    protected function finishRefundTrans($trans) {

        //退款是否收取手续费,可以在这里做逻辑判断,此处退款退给用户多少钱，需要确定
        $buyerAccount = UserAccount::findOne($trans->from_uid); 
        if (!$this->plus($buyerAccount->uid, $trans->total_money - $trans->earnest_money, $trans->id, '订单退款')) {
            $this->addError('display-error', '为用户退款时发生错误');
            return false;
        }

        //对于支付交易已经完成的订单，需要退款手续费,利润，还有保证金等操作，一期先不做。
        if ($trans->status === Trans::PAY_STATUS_FINISHED) {
            $this->addError('display-error', '交易已经确认完成，目前不支持此种退款, 如果需要申诉，请联系客服处理');
            return false;
        }

        //保存交易状态
        $trans->status = Trans::PAY_STATUS_REFUNDED;
        if ($trans->save()) {
            return true;
        }
        else {
            $this->addError(__METHOD__, '保存交易状态变更时出错');
            return false;
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
     * @date 2015/12/05 12:45:03
     **/
    protected function processProfit($action, $trans) {
        $profitAccount = UserAccount::getProfitAccount();
        switch ($action) {
        case 'pay': {
            return $this->plus($profitAccount->uid, $trans->profit, $trans->id, '利润收入');
            break;
        }
        case 'refund': {
            return $this->minus($profitAccount->uid, $trans->profit, $trans->id, '利润退款');
            break;
        }
        default:break;
        }
        return false;
    }

    /**
     * @brief 
     *
     * @return  
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    protected function processFee($action, $trans) {

        $feeAccount = UserAccount::getFeeAccount();
        switch ($action) {
        case 'pay': {
            $this->plus($feeAccount->uid, $trans->fee, $trans->id, '手续费收入');
            break;
        }
        case 'refund': {
            $this->minus($feeAccount->uid, $trans->fee, $trans->id, '手续费退款');
            break;
        }
        default:break;
        }

    }

    /**
     * @brief 用户帐户增加描述为description的$money,关联交易为transId
     *
     * @return  
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function plus($uid, $money, $transId, $description, $currency = 1 ) {
        return $this->balance($uid, UserAccount::BALANCE_TYPE_PLUS, $money, $transId, $description, $currency);
    }

    /**
     * @brief 用户帐户减少描述为description的$money
     *
     * @return  protected function 
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function minus($uid, $money, $transId, $description, $currency = 1) {
        return $this->balance($uid, UserAccount::BALANCE_TYPE_MINUS, $money, $transId, $description, $currency);
    }

    /**
     * @brief 用户账户需要冻结$money的金额,UserAccount类中也应该有freeze方法
     *
     * @return  protected function 
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function freeze($uid, $money, $type, $sourceId, $description, $currency = 1) {
        //产生冻结记录
        $freeze = new Freeze();
        $freeze->uid = $uid;
        $freeze->type = $type;
        $freeze->money = $money;
        $freeze->currency = $currency;
        $freeze->source_id = $sourceId ? $sourceId : 0;
        $freeze->status = Freeze::FREEZE_STATUS_FREEZING;
        $freeze->description = $description;

        if ($freeze->save()) {
            //账户结算
            return $this->balance($uid, UserAccount::BALANCE_TYPE_FREEZE, $money, $freeze->id, $description, $currency);
        }
        else {
            $this->addErrors($freeze->getErrors);
            return false;
        }

    }

    /**
     * @brief 用户帐户需要解除交易id为$transId的冻结记录
     *
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function unFreeze($uid, $freezeId, $description, $currency) {
        //获取trans相对应的freeze记录,并解除冻结
        $freeze = Freeze::findOne(['uid'=>$uid, 'trans_id'=>$transId]);
        if (empty($freeze)) {
            $this->addError(__METHOD__, '该交易并没有关联的冻结记录');
            return false;
        }

        //如果冻结操作成功，则对账户进行操作
        if ($freeze->unFreeze()) {
            return $this->balance($uid, $freeze->money, $transId, $description, $currency);
        }
        else {
            $this->addErrors($freeze->getErrors());
            return false;
        }
    }

    /**
     * @brief 用户帐户需要完成交易id为$transId的冻结记录
     *
     * @return  protected function 
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function finishFreeze($uid, $transId) {
        //获取trans相对应的freeze记录
        $freeze = Freeze::findOne(['uid'=>$uid, 'trans_id'=>$transId]);
        if (empty($freeze)) {
            $this->addError(__METHOD__, '该交易并没有关联的冻结记录');
            return false;
        }

        if ($freeze->finish()) {
            return $this->balance($uid, UserAccount::BALANCE_TYPE_FINISH_FREEZE, $money, $transId, $description, $currency);
        }
        else {
            $this->addErrors($freeze->getErrors());
            return false;
        }
    }

    /**
     * @brief 用户帐户变动的核心函数,所有的变动都需要通过此函数来记录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 14:39:00
     **/
    protected function balance($uid, $balanceType, $money, $transId, $description, $currency) {

        $freezeCat = false;
        $freeze = null;
        $trans = null;
        if (in_array($balanceType, [UserAccount::BALANCE_TYPE_FREEZE, UserAccount::BALANCE_TYPE_UNFREEZE, UserAccount::BALANCE_TYPE_FINISH_FREEZE])) {
            $freezeCat = true;
            $freeze = Freeze::findOne($transId);
            if (! $freeze) {
                $this->addError('trans', '不存在该交易订单');
                return false;
            }
        }
        else {
            //根据交易号查找对应的交易
            $trans = Trans::findOne($transId);
            if (! $trans) {
                $this->addError('trans', '不存在该交易订单');
                return false;
            }
        }


        Yii::warning('获取用户帐户', __METHOD__);
        $userAccount = $this->getUserAccount($uid);

        Yii::warning('判断账户变更类型', __METHOD__);
        switch ($balanceType) {
        case UserAccount::BALANCE_TYPE_PLUS : {
            $userAccount->plus($money);
            break;
        }
        case UserAccount::BALANCE_TYPE_MINUS : {
            $userAccount->minus($money);
            break;
        }
        case UserAccount::BALANCE_TYPE_FREEZE : {
            $userAccount->freeze($money);
            break;
        }
        case UserAccount::BALANCE_TYPE_UNFREEZE : {
            $userAccount->unFreeze($money);
            break;
        }
        case UserAccount::BALANCE_TYPE_FINISH_FREEZE : {
            $userAccount->finishFreeze($money);
            break;
        }
        default: {
            $this->addError(__METHOD__, '不支持提交的账户操作类型');
            return false;
        }
        }

        if ($userAccount->save()) {
            //记录账单
            Yii::warning('产生用户账单', __METHOD__);
            $bill = new Bill();
            $bill->uid = $userAccount->uid;
            if (! $freezeCat) {
                $bill->trans_id = $trans->id;
                $bill->trans_type_id = $trans->trans_type_id;
                $bill->trans_type_name = $trans->transType->name;
            }
            else {
                $bill->trans_id = $freeze->id;
            }
            $bill->money = $money;
            $bill->balance_type = $balanceType;
            $bill->currency = $currency;
            $bill->description = $description;
            if (! $bill->save()) {
                $this->addErrors($bill->getErrors());
                return false;
            }

            Yii::warning('产生账户快照', __METHOD__);
            //账户快照产生
            $accountLog = new UserAccountLog();
            $accountLog->uid = $userAccount->uid;
            $accountLog->account_type = $userAccount->type;
            $accountLog->currency = $currency;
            if (! $freezeCat) {
                $accountLog->trans_id = $trans->id;
            }
            else {
                $accountLog->trans_id = $freeze->id;
            }
            $accountLog->balance = $userAccount->balance;
            $accountLog->deposit = $userAccount->deposit;
            $accountLog->frozen_money = $userAccount->frozen_money;
            $accountLog->balance_type = $balanceType;
            $accountLog->trans_money = $money;
            $accountLog->trans_desc = $description;

            Yii::warning('保存账户信息', __METHOD__);
            if (! $accountLog->save()) {
                //$this->addErrors($accountLog->getErrors());
                Yii::warning('保存账户快照失败', __METHOD__);
                Yii::warning($accountLog->getErrors(), __METHOD__);
                return false;
            }
            else {
                Yii::warning('保存账户快照成功', __METHOD__);
                return true;
            }

        }    
        else {
            return false;
        }

    }

}
