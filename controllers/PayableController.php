<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;                                
use yii\web\Controller;
use yii\filters\VerbFilter;                                
use yii\filters\AccessControl;                                
use lubaogui\payment\models\PayChannel; 
use common\models\Booking; 
use lubaogui\account\models\Payable; 
use frontend\models\PayableForm;

/**
 * Payable controller
 */
class PayableController extends Controller
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
     * @brief 返回支付的详细信息
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/20 19:53:21
    **/
    public function actionDetail() {

        $userPayable = UserPayable::find()->select(['uid', 'currency', 'balance', 'frozon_money' ])->where(['uid'=>Yii::$app->user->identity['uid']]);
        return $userPayable;

    }

    /**
     * @brief 获取审核通过的需要支付给用户的列表 
     *
     * @return  public function 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/08 22:35:12
    **/
    public function actionIndex()
    {
        $payables = Payable::find()->where()->indexBy('id')->all();

    }

    /**
     * @brief 将需要支付给用户的款项列表导出到excel
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/22 18:13:36
    **/
    public function actionExportToExcel() {
        $payables = Payable::find()->where()->indexBy('id')->all();

        return Yii::$app->excel->exportToExcel($payables);
    }

    /**
     * @brief 从银行返回的excel列表总导出支付成功和失败信息，用于后续处理,解冻，结算等。
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/22 18:14:17
    **/
    public function actionImportFromExcel() {
        $excelFile = Yii::$app->request->post('payables');
        $payables = $excelFile->parseToArray();

        //循环处理支付结果，对有问题的结果写入文件，导出给用户,一天提取的款项默认不多，因此不需要做异步处理
        foreach ($payables as $payable) {

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
            $userPayable = Yii::$app->account->getUserPayable(Yii::$app->user->uid);
            $trans = $payForm->getTrans();

            //如果账户余额大于交易的总金额，则直接支付
            if ($userPayable->balance >= $trans->total_money) {
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
        //支付方式通过支付的时候设置notify_url的channel_id参数来进行分辨
        $payChannelId = Yii::$app->request->get('channel_id');

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
                return false;
            }
        }
    }

}
