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
     * @brief 直接充值页面操作
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
        $model = new ChargeForm();
        if ($model->load(Yii::$app->request->post())) {

            //处理逻辑
            $this->redirect();
        }
        else {
            return $this->render('charge',[
                'model' => $model
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
                
                //如果账户余额不足，则根据$receivable的金额去充值,下面的操作将会引起用户端页面的跳转或者是微信支付页面弹出
                Yii::$app->payment->generateUserRequest($receivable);
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
