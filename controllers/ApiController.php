<?php
namespace common\controllers;

use Yii;
use yii\rest\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\validators\SignValidator;
use yii\base\Exception;
use common\models\User;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * Site controller
 */
class ApiController extends Controller
{

    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'data',
    ];

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['contentNegotiator']['formats']['application/xml']);
        return $behaviors;
    }

   /**
     * @inheritdoc
     */
    public function verbs()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $requestValidator = new SignValidator();
            if ($requestValidator->load()->validate())
            {
                $this->requestParams = array_merge(Yii::$app->request->get(),Yii::$app->request->post());
                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $data)
    {
        $data = parent::afterAction($action, $data);
        // your custom code here
        $result = [
            'code'=>$this->code,
            'message'=>$this->message,
            '_meta'=>$this->meta,
            '_links'=>$this->links,
            'data'=>$data,
            ];
        return $result;
    }

    /**
     *
     * setError 设置错误信息
     */
    protected function triggerError($errorCode, $errorDescription, $errors = null)
    {
        $this->code = $errorNo;
        $this->message = is_array($errorMsg)?json_encode($errorMsg):$errorMsg;
        if ($forceExit)
        {
            throw new Exception($this->message, $this->code);
        }
    }

}
