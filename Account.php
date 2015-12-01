<?php

namespace lubaogui\account;

/**
 * 该类属于对账户所有对外接口操作的一个封装，账户的功能有充值，提现，担保交易，直付款交易等
 */
class Account extends Component 
{

    /**
     * @brief 用户购买另外一个用户的产品
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:38
    **/
    public function buy($trans) {

    }

    /**
     * @brief 退款操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
    **/
    public function refund($trans) {

    }

    /**
     * @brief 转账操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/30 10:32:54
    **/
    public function transfer($trans) {

    }

    /**
     * @brief 确认某个交易完成，对于担保交易需要执行此操作，对于直接支付交易无此操作
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
