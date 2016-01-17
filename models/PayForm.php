<?php
namespace frontend\models;

use Yii;
use yii\base\Model;
use lubaogui\account\models\Trans;
use lubaogui\account\models\UserAccount;
use common\models\Booking;
use common\models\BookingStatus;
use common\models\Product;   

/**
 * Pay form 该form从用户端获取交易信息，需要从订单表，产品表中获取分润信息，交易方式等
 */
class PayForm extends Model
{
    //预订id
    public $booking_id;
    public $channel_id;

    private $booking;

    //由用户提交信息产生的trans,用户提交的支付额度等信息不可信，实际信息需要从订单表和产品表中获取生成trans
    private $trans;

    /**
     * @brief 返回变量验证规则
     *
     * @return array 验证的规则组合 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/19 20:39:44
    **/
    public function rules()
    {
        return [
            [['booking_id'], 'required'],
            [['booking_id'], 'integer'],
            [['channel_id'], 'safe'],
        ];
    }

    /**
     * @brief 根据用户的输入产生交易记录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/19 20:39:07
    **/
    public function generateTrans() {

        $product = Product::findOne(['pid'=>$this->booking->pid]);
        $buyerAccount = Yii::$app->account->getUserAccount(Yii::$app->user->identity['uid']);

        //根据booking_id生成交易记录
        $trans = new Trans();
        $trans->pay_mode = Trans::PAY_MODE_VOUCHPAY;
        $trans->trans_type_id = Trans::TRANS_TYPE_TRADE;
        $trans->current = 1;
        $trans->total_money = $this->booking->price_final;
        $trans->profit = $this->booking->price_for_platform;
        $trans->money = $trans->total_money - $trans->profit;
        //保证金，目前还没有上
        //$trans->earnest_money = $this->booking->earnest_money;
        $trans->trans_id_ext = $this->booking_id;
        $trans->from_uid = $buyerAccount->uid;;
        $trans->to_uid = $this->booking->hug_uid;
        $trans->status = Trans::PAY_STATUS_WAITPAY; //1为等待支付状态
        if ($trans->save()) {
            return $trans;
        }
        else {
            $this->addErrors($trans->getErrors());
            return false;
        }

    }

    /**
     * based on the pay form ,generate Trans, this method can be rewrite by the child class
     *
     * @return Trans 返回trans实例
     */
    public function getTrans()
    {
        $this->booking = Booking::findOne(['bid'=>$this->booking_id, 'q_uid'=>Yii::$app->user->identity['uid']]);

        if (empty($this->booking)) {
            $this->addError('booking_id', '指定的订单不存在');
            return false;
        }

        //如果预定已经成功支付，则不允许用户进行第二次支付
        if ($this->booking->status > BookingStatus::PAYSUCCESS) {
            $this->addError('booking_id', '订单已支付!');
            return false;
        }

        if (empty($this->booking->trans_id)) {
            $this->trans = $this->generateTrans();
        }
        else {
            $this->trans = Trans::findOne($this->booking->trans_id);
        }

        return $this->trans;
    }

}
