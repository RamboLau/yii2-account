<?php
namespace frontend\controllers;

use Yii;
use yii\base\Exception; 
use yii\base\InvalidParamException; 
use yii\web\Controller;
use yii\filters\VerbFilter;                                
use yii\filters\AccessControl;                                
use lubaogui\payment\models\PayChannel; 
use common\models\Booking; 
use lubaogui\payment\models\Payable; 
use lubaogui\payment\models\PayableProcessBatch;
use lubaogui\payment\models\PayableProcessBatchSearch;
use yii\web\UploadedFile;
use lubaogui\excel\Excel;
use common\models\UserWithdraw;

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

        $datasRet = $this->generateDatas($payables);
        if (empty($datasRet)) {
            $transaction->rollback();
            throw new Exception('没有可供下载的记录');
        }
        $transaction->commit();
        $datas = $datasRet['datas'];
        $meta = $datasRet['meta'];

        $excelConverter = new Excel();
        if ($excelConverter->exportToExcel($datas, $meta)) {
            return true;
        }
        return false;
    }

    /**
     * @brief 重新下载对应批次的Excel表格文件
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/11 14:21:51
     **/
    public function actionRedownload() {
        $processBatchNo = Yii::$app->request->get('process_batch_no');
        $processBatch = PayableProcessBatch::findOne($processBatchNo);
        if (!$processBatchNo || !$processBatch) {
            throw new Exception('批次处理序号必须提供或者必须存在,process_batch_no', 1000);
        }
        $payables = Payable::find()->where(['process_batch_no'=>$processBatchNo])->indexBy('id')->all();

        $transaction = Yii::$app->db->beginTransaction();
        $datasRet = $this->generateDatas($payables, $processBatch);
        if (empty($datasRet)) {
            $transaction->rollback();
            throw new Exception('没有可供下载的记录');
        }
        $transaction->commit();
        $datas = $datasRet['datas'];
        $meta = $datasRet['meta'];

        $excelConverter = new Excel();
        if ($excelConverter->exportToExcel($datas, $meta)) {
            return true;
        }
        return false;
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
        $transaction = Yii::$app->db->beginTransaction();
        if (Yii::$app->db->
            createCommand()->
            update(Payable::tableName(), ['status'=>Payable::PAY_STATUS_FINISHED], ['process_batch_no'=>$batchProcessNo])->
            execute() ) {
                $transaction->commit();
                return true;
            }
        else {
            $transaction->rollback();
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


    /**
     * @brief 所有的付款批次列表,同步渲染页面还是异步接口？貌似都可以
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/11 20:33:40
     **/
    public function actionBatchList() {
        $searchModel = new PayableProcessBatchSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $this->render(
            'batch-list',
            [
                'searchModel'=>$searchModel,
                'dataProvider'=>$dataProvider,
            ]
        );
    }

    /**
     * @brief 产生Excel文件
     *
     * @return  protected function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/11 14:34:57
     **/
    protected function generateDatas($payables, $processBatch = null) {

        if (empty($payables)) {
            throw new Exception('没有可供下载的记录');
        }

        $meta = [
            'filename'=>'付款明细' . date('Y-m-d-H'),
                'author'=>'Mr-Hug',
                'modify_user'=>'Mr-Hug',
                'title'=>'Mr-Hug付款明细',
                'subject'=>'Mr-Hug付款明细',
                'description'=>'Mr-Hug应付账款明细，用户提现明细',
                'keywords'=>'Mr-Hug, 付款，银行转账',
                'category'=>'银行转账'
            ];

        $headerLables = ['企业参考号', '收款人编号', '收款人账号', '收款人名称', '收方开户支行', '收款人所在省', 
            '收款人所在市', '收方邮件地址', '收方移动电话', '币种', '付款分行', '结算方式', '业务种类', '付方账号',
            '期往日', '期望时间', '用途', '金额', '收方行号', '收方开户银行', '业务摘要'
        ];
        $datas = [];

        //第一行为title信息
        $datas[] = $headerLables;

        $totalMoney = 0;
        $payableCount = 0;
        $payableIds = [];
        foreach ($payables as $payable) {

            $totalMoney += $payable->money;
            $payableCount += 1;

            $data = [
                $payable->id, //企业参考号
                $payable->receive_uid, //收款人编号
                $payable->receiverBankAccount->account_no, //收款人银行卡号，
                $payable->receiverBankAccount->account_name, //收款人姓名，
                '', //开户支行可以为空
                $payable->receiverBankAccount->province, //收款人所在省，
                $payable->receiverBankAccount->city,    //收款人所在市
                '', //邮件地址可为空
                '', //收款人移动电话可为空
                '', //币种可为空,
                '', //付款分行
                '普通', //结算方式
                '', //业务种类
                '', //付方账号
                date('Ymd', $payable->updated_at+86400*2), //期望日
                '', //期望时间
                'Mr-Hug服务费', //用途
                $payable->money, //金额
                '', //收方行号
                $payable->receiverBankAccount->bank_name, //收方开户银行
                $payable->memo, //业务摘要
            ];

            if (!$processBatch) {
                $callbackFunc = [UserWithdraw::className(),'processPayingNotify'];
                if (!Yii::$app->account->processWithdrawPaying($payable, $callbackFunc)) {
                    return false;
                }
            }
            $datas[] = $data;
            $payableIds[] = $payable->id;
        }

        if (!$processBatch) {
            $processBatch = new PayableProcessBatch(); 
            $processBatch->total_money = $totalMoney;
            $processBatch->count = $payableCount;
            $processBatch->download_time = time();
            if (!$processBatch->save()) { 
                return false;
            }
        }


        //处理相关的应付记录状态 
        if (! Yii::$app->db->createCommand()->
            update(Payable::tableName(),['process_batch_no'=>$processBatch->id], ['id' => $payableIds])
            ->execute()) {
                return false;
            }

        return ['datas'=>$datas, 'meta'=>$meta];

    }

}
