<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;                                
use yii\web\Controller;
use yii\filters\VerbFilter;                                
use yii\filters\AccessControl;                                
use lubaogui\payment\models\PayChannel; 
use common\models\Booking;                                                                                                                             
use lubaogui\payment\models\PayChannel;                                                                                                                
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
                    'charge' => ['post'],
                    'pay' => ['post'],
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
     * @brief 返回账户详细信息，该操作用户必须登录
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/20 19:53:21
    **/
    public function actionDetail() {

        $userAccount = UserAccount::find()->select(['uid', 'currency', 'balance', 'frozon_money' ])->where(['uid'=>Yii::$app->user->identity['uid']]);
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
            $receivable = new Receivable();
            $receivable->trans_id = $trans->id;
            $receivable->channel_id = $chargeForm->channel_id;
            $receivable->money = $trans->total_money;
            if ($receivable->save()) {
                $transaction->commit();
            }
            else {
                $transaction->rollback();
                throw new Exception('支付记录创建失败');
            }

            $payChannel = PayChannel::findOne($$chargeForm->channel_id);

            //跳转到支付页面,如果是微信扫码支付，返回的是图片生成的url地址，如果是支付宝，返回的是html
            $payment = new Payment($payChannel->alias); 
            $returnType = null;
            if ($payChannel->alias == 'wechatpay') {
                $returnType = 'QRCodeUrl';
            }

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

                $transaction = $this->beginTransaction();
                $receivable = Yii::$app->account->chargeForTrans($trans);
                //如果账户余额不足，则根据$receivable的金额去充值,
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
        $payChannelId = Yii::$app->request->post('channel_id');

        $payment = new Payment($channels[$payChannelId]['alias']);
        //根据回调地址，确定支付通知来源

        $handlers = [
            'paySuccessHandler'=>[$this, 'processPaySuccess'],
            'payFailHandler'=>[$this, 'processPayFailure'],
            ];

        $transaction = $this->beginTransaction();
        $payment->setHandlers($handlers);

        //业务逻辑都在handlers中实现
        try {
            if ($payment->processNotify()) {
                $transaction->commit();
            }
            else {
                $transaction->rollback();
            }
        } 
        catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        
    }


    /**
     * @brief 支付成功的回调方法
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/20 11:44:17
    **/
    protected function processPaySuccess($data) {
        //用户支付成功
        $receivable = Receivable::findOne(['id'=>$data['receivable_id']]);
        if ($receivable->status === Receivable::PAY_STATUS_FINISHED) {
            return false;
        }
        else {
            //代收款的成功处理逻辑
            if ($receivable->paySuccess()) {
                $trans = Trans::findOne($receivable->trans_id);
                if (empty($trans)) {
                    return false;
                }
                //交易状态需要变更为成功,对于充值型服务，不提供退款，此状态为最终状态
                if ($trans->status !== Trans::PAY_STATUS_FINISHED) {
                    $trans->status = Trans::PAY_STATUS_FINISHED;
                    //如果交易存在关联交易，则出发关联交易的支付过程
                    if ($trans->trans_id_ext) {
                        $transExt = Trans::findOne($trans->trans_id_ext);
                        if (Yii::$app->account->pay($transExt)) {
                            return true;
                        }
                        else {
                            return false;
                        }
                    }
                }
                else {
                    return false;
                }
            }
            else {
                throw new Exception('处理失败，请联系管理员');
            }
        }
    }

    /**
     * @brief 支付失败的回调方法
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/20 11:44:28
    **/
    protected function processPayFailure($data) {

    }

}
