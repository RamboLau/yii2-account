<?php
namespace common\models;

use Yii;
use yii\base\Model;
use lubaogui\account\models\Trans;

/**
 * Pay form 该form从用户端获取交易信息，需要从订单表，产品表中获取分润信息，交易方式等
 */
class PayForm extends Model
{
    //预订id
    public $booking_id;

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
            [['booking_id'], 'integer', 'required'],
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

        $product = Tour::findOne(['id'=>$booking->product_id]);

        $buyerAccount = UserAccount::findOne(Yii::$app->user->identity['uid']);

        //根据booking_id生成交易记录
        $trans = new Trans();
        $trans->pay_mode = $product->pay_mode;
        $trans->trans_type_id = $product->pay_mode;
        $trans->total_money = $booking->total_money;
        $trans->profit = $booking->profit;
        $trans->earnest_money = $booking->earnest_money;
        $trans->trans_id_ext = $this->booking_id;
        $trans->from_uid = Yii::$app->user->identity['uid'];
        $trans->from_uid = $product->uid;
        $trans->status = 1; //1为等待支付状态

        //判断是否需要额外支付

        return true;
    }

    /**
     * based on the pay form ,generate Trans, this method can be rewrite by the child class
     *
     * @return Trans 返回trans实例
     */
    public function getTrans()
    {
        $this->booking = Booking::findOne(['id'=>$this->booking_id, 'uid'=>Yii::$app->user->identity['uid']]);
        if (empty($this->booking)) {
            throw new Exception('并不存在这个预订');
        }

        if (empty($booking->trans_id)) {
            $this->trans = $this->generateTrans();
        }
        return $this->trans;
    }

    /**
     * @brief 跳转到第三方支付页面,如果是微信支付，直接产生微信支付页面并返回,如果不需要支付，直接返回成功
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/14 16:14:50
    **/
    public function gotoPay($receivable) {

    }

}
