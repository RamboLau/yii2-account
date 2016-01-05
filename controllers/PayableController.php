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
use yii\web\UploadedFile;

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
    public function actionDownload() {
        
        $transaction = Yii::$app->db->beginTransaction();
        $payableConds = ['status'=>Payable::PAY_STATUS_WAITPAY];
        $payables = Payable::find()->where($payableConds)->indexBy('id')->all();

        $dataArray = [];
        foreach ($payables as $payable) {
            $item = [];
            $item['money'] = $payable->money;
            $userAccount = Yii::$app->account->getUserAccount($payable->uid);
            if ($userAccount->processWithdrawPaySuccess()) {

            }
        }

        $excelConverter = new Excel();
        if ($excelConverter->exportToExcel($dataArray)) {
            $processBatch = new PayableProcessBatch();
            $processBatch->total_money = $totalMoney;
            $processBatch->count = $payableCount;
            $processBatch->download_time = time();
            if ($processBatch->save()) {
                Yii::$app->db->createCommand->update(Payable::tableName(), ['status'=>Payable::PAY_STATUS_PAYING], $payableConds);
                $transaction->commit();
                return true;
            }
            else {
                $transaction->rollback();
                return true;
            }
        }
        else {
            $transaction->rollback();
            throw new Exception('写入excel文件出错');
        }

    }


    /**
     * @brief 确认批量付款成功
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/05 15:35:21
    **/
    public function actionConfirmBatchPay() {
        $batchProcessNo = Yii::$app->request->post('process_batch_no');
        if (Yii::$app->db->createCommand()->update(Payable::tableName(), ['status'=>Payable::PAY_STATUS_FINISHED], ['process_batch_no'=>$batchProcessNo])) {
            return true;
        }
        else {
            return false;
        }
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
        //获取文件
        $excelFile = UploadedFile::getInstanceByName('payedfile');
        if (! $excelFile) {
            throw new Exception('获取上传文件失败');
        }
        $excelConverter = new Excel();
        $payedItems = $excelConverter->importFromFile();

        //循环处理支付结果，对有问题的结果写入文件，导出给用户,一天提取的款项默认不多，因此不需要做异步处理
        foreach ($payedItems as $payedItem) {
            if ($payedItem['status']) {
                $payable = Payable::findOne($payedItem['id']);
                $userAccount = Yii::$app->account->getUserAccount($payable->uid);
                $callbackFunc = [UserWithdraw::className(),'processFinishPayNotify'];
                if (!$userAccount->processWithdrawPaySuccess($payable->id,$callbackFunc)) {
                    //将错误处理的信息记录下来

                }
            }
        }
    }

}
