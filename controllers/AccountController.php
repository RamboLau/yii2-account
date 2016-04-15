<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\base\Exception;
use common\controllers\WebController;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use lubaogui\payment\Payment;
use lubaogui\payment\models\PayChannel;
use common\models\Booking;
use lubaogui\account\models\UserAccount;
use lubaogui\account\models\Trans;
use lubaogui\account\exceptions\LBUserException;
use frontend\models\PayForm;

/**
 * Account controller
 */
class AccountController extends WebController
{
    public $enableCsrfValidation = true;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['detail' ,'pay', 'charge'],
                'rules' => [
                     [
                        'actions' => ['detail' ,'pay', 'charge'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'charge' => ['post', 'get'],
                    'pay' => ['post', 'get'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'common\actions\ApiErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }


    /**
     * @brief 返回当前登录账户详细信息，该操作用户必须登录
     *
     * @return  public function
     * @retval
     * @see
     * @note
     * @author 吕宝贵
     * @date 2015/12/20 19:53:21
    **/
    public function actionDetail() {

        $userAccount = Yii::$app->account->getUserAccount(Yii::$app->user->identity->uid);
        return $userAccount;

    }

    /**
     * @brief 用户直接充值页面操作
     *
     * @return  public function
     * @retval
     * @see
     * @note
     * @author 吕宝贵
     * @date 2015/12/08 22:35:12
    **/
    public function actionCharge()
    {
        $channels = PayChannel::find()->select(['id', 'name', 'alias'])->indexBy('id')->all();
        $chargeForm = new $ChargeForm();
        $postParams = Yii::$app->request->post();
        $transaction = Yii::$app->db->beginTransaction();
        if ($chargeForm->load($postParams, '') && $chargeForm->generateTrans()) {

            $trans = $chargeForm->getTrans();
            //生成提交给支付模块的待付款记录
            if (!$receivable = Yii::$app->account->generateReceivable($trans)) {
                $transaction->rollback();
                throw new Exception('产生收款记录失败');
            }
            $transaction->commit();

            //跳转到支付页面,如果是微信扫码支付，返回的是图片生成的url地址，如果是支付宝，返回的是html
            $payChannel = PayChannel::findOne($chargeForm->channel_id);
            $payment = new Payment($payChannel->alias);
            $returnType = null;
            if ($payChannel->alias == 'wechatpay') {
                $returnType = 'QRCodeUrl';
            }
            //返回需要跳转到页面url或者二维码图片, 如果返回flase，则报错
            if (! $payment->generatePayRequestParams($receivable)) {
                throw new Exception('无法产生支付信息，订单或已支付');
            }
        }
        else {
            return $this->render('charge',[
                'model' => $chargeForm,
                'channels'=>$channels,
            ]);
        }
    }

    /**
     * @brief 担保交易支付,支付的时候，需要判断是否需要用户充值完成支付
     *
     * @return  public function
     * @retval
     * @see
     * @note
     * @author 吕宝贵
     * @date 2015/12/08 22:35:00
    **/
    public function actionPay()
    {
        $channels = PayChannel::find()->select(['id', 'name', 'alias'])->indexBy('id')->all();
        $payForm = new PayForm();
        $requestParams = array_merge(Yii::$app->request->get(), Yii::$app->request->post());

        if (!$payForm->load($requestParams, '')) {
            //如果用户没有提交支付，则认为是需要渲染页面
            return $this->render('pay',[
                'model' => $payForm,
                'channels'=>$channels,
                ]);
        }

        //根据form产生trans,trans处于未支付状态
        $transaction = Yii::$app->db->beginTransaction();
        $userAccount = Yii::$app->account->getUserAccount(Yii::$app->user->identity->uid);

        $trans = null;
        if (! $trans = $payForm->getTrans()) {
            $transaction->rollback();
            throw new Exception(json_encode($payForm->getErrors()));
        }

        //如果账户余额大于交易的总金额，则直接支付
        $callbackData = ['bid'=>$payForm->booking_id, 'trans_id'=>$trans->id];
        if ($userAccount->balance >= $trans->total_money) {
            if (Yii::$app->account->pay($trans)) {
                //回调预订中的成功支付函数
                if (call_user_func([Booking::className(), 'processPaySuccess'], $callbackData)) {
                    $transaction->commit();
                    //支付成功
                    $this->data = [
                        'pay_url'=>'',
                        'need_pay'=>0,
                        'channel_id'=>0,
                        ];
                    return;
                }
                else {
                    $transaction->rollback();
                    //此处需要设置错误信息
                    return ;
                }
            }
            else {
                $transaction->rollback();
                return;
            }
        }

        if (! $receivable = Yii::$app->account->generateReceivableAndChargeTrans($trans, $userAccount)) {
            $transaction->rollback();
            throw new Exception('生成充值订单出错');
        }
        else {
            if (call_user_func([Booking::className(), 'processGenTransSuccess'], $callbackData)) {
                $transaction->commit();
            }
            else {
                $transaction->rollback();
                throw new Exception('生成支付信息出错');
            }
        }

        //下面的操作将会引起用户端页面的跳转或者是微信支付页面弹出
        $payChannel = PayChannel::findOne($payForm->channel_id);

        //根据是否是移动端支付，决定传给payment的options参数
        $payOptions['app_id'] = 'wechatpay-native';
        if ($payForm->is_mobile) {
            $payOptions['app_id'] = 'wechatpay-app';
        }

        //跳转到支付页面,如果是微信扫码支付，返回的是图片生成的url地址，如果是支付宝，返回的是html
        $payment = new Payment($payChannel->alias, $payOptions);

        //跳转到支付或者返回支付二维码地址
        $returnData = $payment->generatePayRequestParams($receivable);

        if ($returnData) {
            $this->data = [
                'pay_url'=>$returnData,
                'need_pay'=>1,
                'channel_id'=>$payForm->channel_id,
                ];
        }
        else {
            throw new Exception('支付失败');
        }

    }

    /**
     * @brief 支付宝回调处理action
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 00:35:46
    **/
    public function actionAlipayNotify() {
        //支付方式通过支付的时候设置notify_url的channel_id参数来进行分辨
        //此方法不妥，换为使用其他方法来判断支付channel_id
        $payChannelId = 1;
        if ($this->processPayNotify($payChannelId)) {
            echo 'success';
            exit;
        }
    }

    /**
     * @brief 微信支付回调处理
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/01 00:37:07
    **/
    public function actionWechatPayNotify() {
        //支付方式通过支付的时候设置notify_url的channel_id参数来进行分辨
        Yii::info('进入微信支付回调', 'account-pay-notify');
        $payChannelId = 2;
        $this->processPayNotify($payChannelId); 
        
    }

    /**
     * @brief 移动端支付成功回调
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/02/27 22:22:32
    **/
    public function actionWechatAppPayNotify() {
        //支付方式通过支付的时候设置notify_url的channel_id参数来进行分辨
        Yii::info('进入微信支付回调', 'account-pay-notify');
        $payChannelId = 2;
        $this->processPayNotify($payChannelId); 
    }

    /**
     * @brief 处理充值消息通知的action,对于notify来讲，不需要做页面跳转，只需要针对不同的支付方式返回对应的状态
     *
     * @return  public function
     * @retval
     * @see
     * @note
     * @author 吕宝贵
     * @date 2015/12/09 23:30:24
    **/
    protected function processPayNotify($payChannelId) {

        $channels = PayChannel::find()->select(['id', 'name', 'alias'])->indexBy('id')->all();
        //回调的时候，appId根据返回的参数来确定
        $payment = new Payment($channels[$payChannelId]['alias']);


        //判断订单是否支付成功, 如果成功则进入成功处理逻辑
        if ($payment->checkPayStatus() === false) {
            throw new LBUserException('支付结果检查失败!', 1);
            exit;
        }

        //如果checkPayStatus返回成功，代表支付成功，此时进行实际的业务处理
        $transaction = Yii::$app->db->beginTransaction();

        //设置支付的成功和失败回调函数,此处处理的主要是账户体系的逻辑
        $handlers = [
            'paySuccessHandler'=>[Yii::$app->account, 'processChargePaySuccess'],
            'payFailHandler'=>[Yii::$app->account, 'processPayFailure'],
        ];

        $trans = null;
        //业务逻辑处理，此处应当判断订单是否成功
        if ($transId = $payment->processNotify($handlers)) {
            $trans = 
            $transaction->commit();
            Yii::info('成功处理用户充值逻辑', 'account-pay-notify');

            //如果交易存在关联的交易，则查询关联交易的信息，并尝试支付相应的关联订单，需要提供订单的回调函数
            if ($trans->trans_id_ext) {
                //如果存在关联订单，需要开启新事务处理相关订单
                Yii::info('存在关联交易，处理关联交易逻辑', 'account-pay-notify');
                $transaction = Yii::$app->db->beginTransaction();
                $transOrder = Trans::findOne($trans->trans_id_ext);
                if (!$transOrder) {
                    $transaction->rollback();
                    Yii::warning('关联交易查询失败,并不存在该关联交易订单， 退出购买逻辑', 'account-pay-notify');
                    throw new LBUserException('关联交易查询失败，退出购买逻辑', 2);
                    exit;
                }

                if (Yii::$app->account->pay($transOrder)) {
                    //如果关联交易为产品购买
                    if ($transOrder->trans_type_id == Trans::TRANS_TYPE_TRADE) {
                        $booking = Booking::findOne(['bid'=>$transOrder->trans_id_ext]);
                        if (! $booking) {
                            $transaction->rollback();
                            Yii::warning('查询关联预订失败', 'account-pay-notify');
                            throw new LBUserException('查询关联订单信息失败', 2);
                            exit;
                        }
                        Yii::info('关联预订信息获取成功', 'account-pay-notify');
                        $callbackData = [
                            'bid' =>$booking->bid ,
                            'trans_id' => $booking->trans_id,
                        ];
                        //此处的回调为业务上的订单处理回调
                        if (! Booking::processPaySuccess($callbackData)) {
                            $transaction->rollback();
                            Yii::warning('关联预订处理操作失败', 'account-pay-notify');
                            throw new LBUserException('订单支付成功回调处理失败', 3);
                            exit;
                        }
                        Yii::info('关联预订相关回调处理成功', 'account-pay-notify');
                    }
                    $transaction->commit();
                    Yii::info('提交事务...', 'account-pay-notify');
                    //页面跳转逻辑
                }
                else {
                    $transaction->rollback();
                    throw new LBUserException('订单付款失败', 4, Yii::$app->account->getErrors());
                    exit;
                }
            }
        }
        else {
            $transaction->rollback();
            Yii::warning('处理支付成功失败...', 'account-pay-notify');
            throw new LBUserException('订单付款失败', 4, $payment->getErrors());
            exit;
        }
        Yii::info('交易成功处理...', 'account-pay-notify');
    }

}
