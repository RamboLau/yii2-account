<?php
namespace common\models;

use Yii;
use yii\base\Model;
use lubaogui\account\models\Trans;

/**
 * Pay form 该form从用户端获取交易信息，需要从订单表，产品表中获取分润信息，交易方式等
 */
class ChargeForm extends Model
{
    //订单id
    public $order_id;

    //发起用户uid,一般为购买方
    public $from_uid;

    //收款id
    public $to_uid;

    //支付模式,只有在用户余额不足的时候才会启用
    public $provider_id;

    //支付模式，支付模式一般需要从订单表中获取，或者采用回调的方式获取
    private $payMode;

    //由用户提交信息产生的trans,用户提交的支付额度等信息不可信，实际信息需要从订单表和产品表中获取生成trans
    private $trans;

    //由用户提交信息产出订单
    private $order;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['trans_id_ext', 'from_uid', 'to_uid', 'money'], 'required'],
            ['pay_mode'], 'integer'],
        ];
    }

    /**
     * based on the pay form ,generate Trans, this method can be rewrite by the child class
     *
     * @return Trans 返回trans实例
     */
    public function getTrans($order, $product)
    {
        $trans = null;
        if ($order->trans_id) {
            $trans = Trans::findOne(['id'=>'trans_id']);
        }
        else {
            $trans = $this->generateTrans();
        }
        return $trans;
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
