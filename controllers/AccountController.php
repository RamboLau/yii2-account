<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;                                
use yii\web\Controller;
use yii\filters\VerbFilter;                                
use yii\filters\AccessControl;                                
use lubaogui\payment\models\PayChannel; 
use common\models\Booking; 
use lubaogui\account\models\UserAccount; 
use frontend\models\PayForm;

/**
 * Account controller
 */
class AccountController extends Controller
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

        $userAccount = Yii::$app->account->getUserAccount(Yii::$app->user->identity['uid']);
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
        $transaction = Yii::$app->beginTransaction();
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
            //返回需要跳转到页面url或者二维码图片
            $payment->gotoPay($receivable, $returnType);
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
        if ($payForm->load(Yii::$app->request->post())) {

            //根据form产生trans,trans处于未支付状态
            $transaction = Yii::$app->beginTransaction();
            $userAccount = Yii::$app->account->getUserAccount(Yii::$app->user->uid);
            $trans = $payForm->getTrans();

            //如果账户余额大于交易的总金额，则直接支付
            if ($userAccount->balance >= $trans->total_money) {
                if (Yii::$app->account->pay($trans)) {
                    $transaction->commit();
                    //设置通知消息
                    Yii::$app->success('订单支付成功');
                    //跳转到订单支付成功页面
                    $this->redirect();
                }
                else {
                    $transaction->rollback();
                    Yii::$app->error('订单支付失败');
                }
            }
            else {

                if (! $receivable = Yii::$app->account->generateReceivableAndChargeTrans($trans, $userAccount)) {
                    $transaction->rollback();
                    throw new Exception('生成充值订单出错');
                }
                else {
                    $transaction->commit();
                }
                
                //下面的操作将会引起用户端页面的跳转或者是微信支付页面弹出
                $payment = new Payment();
                $payChannel = PayChannel::findOne($payForm->channel_id);

                //跳转到支付页面,如果是微信扫码支付，返回的是图片生成的url地址，如果是支付宝，返回的是html
                $payment = new Payment($payChannel->alias); 
                $returnType = null;
                if ($payChannel->alias == 'wechatpay') {
                    $returnType = 'QRCodeUrl';
                }

                //跳转到支付或者返回支付二维码地址
                return $payment->gotoPay($receivable, $returnType);
            }
        }
        else {
            //如果用户没有提交支付，则认为是需要渲染页面
            return $this->render('pay',[
                'model' => $payForm,
                'channels'=>$channels,
            ]);
        } 

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
    public function actionPayNotify() {

        $channels = PayChannel::find()->select(['id', 'name', 'alias'])->indexBy('id')->all();
        //支付方式通过支付的时候设置notify_url的channel_id参数来进行分辨
        $payChannelId = Yii::$app->request->get('channel_id');

        $payment = new Payment($channels[$payChannelId]['alias']);
        //设置支付的成功和失败回调函数
        $handlers = [
            'paySuccessHandler'=>[Yii::$app->account, 'processChargePaySuccess'],
            'payFailHandler'=>[Yii::$app->account, 'processPayFailure'],
            ];

        $transaction = $this->beginTransaction();
        $payment->setHandlers($handlers);

        //业务逻辑都在handlers中实现
        try {
            $trans = null;
            if ($trans = $payment->processNotify()) {
                $transaction->commit();
                //上面是用户充值成功逻辑，如果交易存在关联的交易，则查询关联交易的信息，并尝试支付
                if ($trans->trans_id_ext) {
                    $transOrder = Trans::findOne($trans->trans_id_ext);
                    if (!$transOrder) {
                        return false;
                    }
                    $transaction = $this->beginTransaction();
                    if (Yii::$app->pay($transOrder)) {
                        $transaction->commit();
                        //页面跳转逻辑
                    }
                    else {
                        $transaction->rollback();
                        //页面跳转逻辑
                    }
                }
                else {
                    //成功页面跳转逻辑
                }
            }
            else {
                $transaction->rollback();
            }
        } 
        catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        
    }

}
