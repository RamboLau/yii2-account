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
        $payables = Payable::find()->where(['status'=>Payable::PAY_STATUS_WAITPAY])->indexBy('id')->all();
        $bankFields = [
            ''=>

        ];

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

}
