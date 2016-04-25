<?php

namespace lubaogui\account;

use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\base\Model;
use lubaogui\account\models\UserAccount;
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
     * @brief 获取公司付款账号
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/07 10:57:17
     **/
    public function getCompanyPayAccount() {
        $companyAccount = UserAccount::find()->where(['type'=>UserAccount::ACCOUNT_TYPE_SELFCOMPANY_PAY])->one();
        if (! $companyAccount) {
            throw new Exception('必须设置公司付款账号');
        }
        return $companyAccount;

    }

    /**
     * @brief 担保交易中间账号
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/07 11:06:18
     **/
    public function getVouchAccount() {
        $vouchPayAccount = UserAccount::find()->where(['type'=>UserAccount::ACCOUNT_TYPE_SELFCOMPANY_VOUCH])->one();
        if (! $vouchPayAccount) {
            throw new Exception('必须设置担保交易账号');
        }
        return $vouchPayAccount;
    }

    /**
     * @brief 利润账号
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/07 12:07:15
     **/
    public function getProfitAccount() {
        $profitAccount = UserAccount::find()->where(['type'=>UserAccount::ACCOUNT_TYPE_SELFCOMPANY_PROFIT])->one();
        if (! $profitAccount) {
            throw new Exception('必须设置利润账号');
        }
        return $profitAccount;
    }

    /**
     * @brief 手续费账号
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/07 12:07:47
     **/
    public function getFeeAccount() {
        $feeAccount = UserAccount::find()->where(['type'=>UserAccount::ACCOUNT_TYPE_SELFCOMPANY_FEE])->one();
        if (! $feeAccount) {
            throw new Exception('必须设置担保交易账号');
        }
        return $feeAccount;
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
        if (!$buyerAccount->plus($trans->total_money - $trans->earnest_money, $trans, '产品退款')) {
            $this->addError('display-error', '为用户退款时发生错误');
            return false;
        }

        //对于支付交易已经完成的订单，需要退款手续费,利润，还有保证金等操作，一期先不做。
        if ($trans->status === Trans::PAY_STATUS_FINISHED) {
            $this->addError('display-error', '交易已经完成，目前不支持此种退款');
            return false;
        }

        //保存交易状态
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
    protected function processProfit($action, $trans) {
        $profitAccount = $this->getProfitAccount();
        switch ($action) {
        case 'pay': {
            return $profitAccount->plus($trans->profit, $trans, '利润收入');
            break;
        }
        case 'refund': {
            return $profitAccount->minus($trans->profit, $trans, '利润退款');
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

        $profitAccount = $this->getProfitAccount();
        switch ($action) {
        case 'pay': {
            $profitAccount->plus($trans->fee, $trans, '手续费收入');
            break;
        }
        case 'refund': {
            $profitAccount->minus($trans->fee, $trans, '手续费退款');
            break;
        }
        default:break;
        }

    }

    /**
     * @brief 用户帐户增加描述为description的$money
     *
     * @return  
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function plus($uid, $money, $transId, $description, $currency = 1 ) {

        $this->balance();
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
        $this->balance();

    }

    /**
     * @brief 用户账户需要冻结$money的金额,UserAccount类中也应该有freeze方法
     *
     * @return  protected function 
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function freeze($uid, $money, $transId, $description, $currency = 1) {

    }

    /**
     * @brief 用户帐户需要解除交易id为$transId的冻结记录
     *
     * @retval   
     * @author 吕宝贵
     * @date 2015/12/05 12:45:52
     **/
    public function unFreeze($uid, $transId) {

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

        $this->balance($uid, UserAccount::BALANCE_TYPE_FREEZE, $money, )

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

        //根据交易号查找对应的交易
        $trans = Trans::findOne($transId);
        if (! $trans) {
            $this->addError('trans', '不存在该交易订单');
            return false;
        }

        $userAccount = $this->getUserAccount($uid);

        switch $balanceType {
        case UserAccount::BALANCE_TYPE_PLUS : {
            $userAccount->balance += $money;
            break;
        }
        case UserAccount::BALANCE_TYPE_MINUS : {
            $userAccount->balance -= $money;
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
        default {
            $userAccount->addError('uid', '不支持提交的账户操作类型');
            return false;
        }
        }

        if ($userAccount->save()) {
            //记录账单
            $bill = new Bill();
            $bill->uid = $userAccount->uid;
            $bill->trans_id = $trans->id;
            $bill->trans_type_id = $trans->trans_type_id;
            $bill->trans_type_name = $trans->transType->name;
            $bill->money = $money;
            $bill->balance_type = $balanceType;
            $bill->currency = $trans->currency;
            $bill->description = $description;
            if (! $bill->save()) {
                $userAccount->addErrors($bill->getErrors());
                return false;
            }

            //账户快照产生
            $accountLog = new UserAccountLog();
            $accountLog->uid = $userAccount->uid;
            $accountLog->account_type = $userAccount->type;
            $accountLog->currency = $trans->currency;
            $accountLog->trans_id = $trans->id;
            $accountLog->balance = $userAccount->balance;
            $accountLog->deposit = $userAccount->deposit;
            $accountLog->frozen_money = $userAccount->frozen_money;
            $accountLog->balance_type = $balanceType;
            $accountLog->trans_money = $money;
            $accountLog->trans_desc = $description;

            if (! $accountLog->save()) {
                $userAccount->addErrors($accountLog->getErrors());
                return false;
            }
            return true;

        }    
        else {
            return false;
        }

    }

}
