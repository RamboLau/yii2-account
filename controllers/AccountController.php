<?php
namespace frontend\controllers;

use Yii;

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
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
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
        $channels = PayChannel::find()->select(['id', 'name', 'alias', 'description'])->indexBy('id')->all();
        $chargeForm = new ChargeForm();
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
            $payment = new lubaogui\payment\Payment($payChannel->alias); 
            $payment->gotoPay($receivable);

        }
        else {
            return $this->render('charge',[
                'model' => $chargeForm,
                'channels'=>$channels,
            ]);
        } 
    }

    /**
     * @brief 担保交易支付
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
        $payForm = new PayForm();

        if ($payForm->load(Yii::$app->request->post())) {

            //根据form产生trans,trans处于未支付状态
            $userAccount = Yii::$app->account->getUserAccount(Yii::$app->user->uid);
            $trans = $payForm->generateTrans();

            //如果账户余额大于交易的总金额，则直接支付
            if ($userAccount->balance >= $trans->total_money) {
                $transaction = $this->beginTransaction();
                if (Yii::$app->account->pay($trans)) {
                    $transaction->commit();
                    //设置通知消息
                    Yii::$app->success('订单支付成功');
                    //跳转到订单页面
                    $this->redirect();
                }
                else {
                    $transaction->rollback();
                    Yii::$app->error('订单支付失败');
                }
            }
            else {
                $receivable = Yii::$app->account->chargeForTrans($trans);
                //如果账户余额不足，则根据$receivable的金额去充值,
                //下面的操作将会引起用户端页面的跳转或者是微信支付页面弹出
                die(Yii::$app->payment->generateUserRequest($receivable));
            }
        }
        else {
            //如果用户没有提交支付，则认为是需要渲染页面
            return $this->render('pay',[
                'model' => $payForm
            ]);
        } 

    }

    /**
     * @brief 处理充值消息通知的action
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/09 23:30:24
    **/
    public function actionPayNotify() {

        //验证返回通知的真实性
        if (Yii::$app->payment->verifyReturn()) {
            //处理逻辑

        }
    }

}
