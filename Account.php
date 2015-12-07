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
class Account extends Component 
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
     * @brief 获取账户状态
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:59:27
     **/
    public function getBalance($uid) {
        $userAccount = UserAccount::findOne($uid);
        return  $userAccount->balance;
    }

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
    public function directPay($transParams) {

        $trans = null;
        if (is_array($transParams)) {
            $trans = new Trans();
            $trans->load($transParams, '');
            $trans->save();
        }
        else {
            $trans = $transParams;
        }

        $buyerAccount = UserAccount::findOne($trans->from_uid);
        if ($buyerAccount->type != UserAccount::ACCOUNT_TYPE_NORMAL) {
            //throw new Exception('非普通账号不支持直接交易!请联系管理员');
            return false;
        }

        //提交给账户进行扣款,账单，账户变化都进行更新,对于买家，扣除total_money
        if (!$buyerAccount->minus($trans->total_money)) {
            return false;
        }

        //完成交易的后续操作，如扣费等
        if (!$this->finishPayTrans($trans)) {
            return false;
        }

        return $trans->id;
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
    public vouchPay($transParams) {

        $trans = null;
        if (is_array($transParams)) {
            $trans = new Trans();
            $trans->load($transParams);
            $trans->save();
        }
        else {
            $trans = $transParams;
        }

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

        return $trans->id;

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
            throw new Exception('非担保交易无须确认');
            return false;
        }

        //金额从担保账号转出
        $vouchAccount = UserAccount::findOne($vouchAccountId);
        if (!$vouchAccount->minus($trans->total_money)) {
            return false;
        }

        //完成交易的后续操作
        if (!$this->finishPay($trans)) {
            return false;
        }

        //变更交易状态
        $trans->status = Trans::PAY_STATUS_FINISHED;
        $trans->save();

        return true;
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
    protected function finishPay($trans) {

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
     * @brief 完成交易退款,该函数主要完成多种退款流程最后的利润退款，手续费退款等
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 18:02:59
     **/
    protected function finishRefund($transId) {

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
     * @brief 转账操作,转账操作属于直接支付给对方金额,扩展时使用
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
     **/
    public function transferToAccount($transParams) {


    }

    /**
     * @brief 用户提现，提现只能提取balance中的额度，保证金无法提取,该方法完成实际的提现操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:44:54
     **/
    public function withdraw($params) {

        $trans = new Trans();
        $trans->load($params, '');
        $trans->save();


        $withdrawUser = UserAccount::findOne($trans->from_uid);
        if ($money > $withdrawUser->balance) {
            return false;
        }

        //创建付款记录
        $payable = new Payable();
        $payable->money = $trans->money;
        $payable->status = 1;
        $payable->save();

        $withdrawUser->freeze($trans->money);

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
        $payable->save();
        $trans = Trans::findOne($payable->trans_id);
        $trans->status = Trans::PAY_STATUS_FINISHED;
        $trans->save();

        return true;

    }

    /**
     * @brief 用户充值,该操作仅生成充值记录,具体的支付需要通过payment完成,充值有两种模式，用户直接充值和订单产生
     * 的充值，对于订单产生的充值，充值完成之后需要完成订单的业务逻辑
     *
     * @return  返回用户信息 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:49:22
     **/
    public function directCharge($params) {

        //新建交易
        $trans = new Trans();
        $trans->load($params, '');
        $trans->status = Trans::PAY_STATUS_WAITPAY;
        $trans->save();

        //新建收款记录
        $receivable = new  Receivable();
        $receivable->money = $trans->total_money;
        $receivable->status = Receivable::PAY_STATUS_WAITPAY;
        $receivable->save();

        return $receivable;

    }

    /**
     * @brief 用户由于购买订单而产生充值行为，这种行为需要有两个
     * 的充值，对于订单产生的充值，充值完成之后需要完成订单的业务逻辑
     *
     * @param array 交易的相关信息，以及订单支付的总金额, 充值的金额都需要在此列出
     * @return  返回用户信息 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/06 17:49:22
     **/
    public function chargeForTrans($paramsOrder,$paramsCharge) {

        //新建交易,应当包含交易类型
        $transOrder = new Trans();
        $transOrder->load($paramsOrder, '');
        $transOrder->status = Trans::PAY_STATUS_WAITPAY;
        $transOrder->save();

        $transCharge = new Trans();
        $transCharge->load($paramsCharge, '')
            //充值是否为了某一个交易，如果是，则充值完成之后自动尝试完成该交易
            $transCharge->trans_id_ext = $transOrder->id;

        //新建收款记录
        $receivable = new  Receivable();
        $receivable->money = $transCharge->total_money;
        $receivable->trans_id = $transCharge->id;
        $receivable->status = Receivable::PAY_STATUS_WAITPAY;
        $receivable->save();

        return $receivable;

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
    public function chargePaySucceeded($receivableId, $callback) {

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
