<?php
namespace common\controllers;

use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\validators\SignValidator;
use yii\base\Exception;
use common\models\User;
use common\models\Person;
use common\models\Message;
use common\models\SceneMatches;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * Site controller
 */
class WebController extends Controller
{

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
        //Yii::$app->user->setReturnUrl(Yii::$app->request->referrer);
        if (parent::beforeAction($action)) {
            $this->requestParams =
                array_merge(Yii::$app->request->get(),Yii::$app->request->post());
            $this->headers = Yii::$app->request->getHeaders()->toArray();
            $this->user = Yii::$app->user->identity;
            $this->uid = Yii::$app->user->identity['id'];
            $this->webroot = Yii::getAlias('@webroot');
            return true;
        }
        else 
        {
            return false;
        }
    }

    public function render($view, $param = []){
        return parent::render($view,$param);
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $data)
    {
        if(is_array($data) ||!$data){
            if (!$data) {
                $data = [
                    'code' => $this->code,
                    'message' => $this->message,
                    'data' => $this->data,
                    'meta' => $this->meta,
                ];
            }
            if (isset($data['data']['message'])) {
                $data['message'] = $data['data']['message'];
                unset($data['data']['message']);
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        }
        $result = parent::afterAction($action, $data);
        //自定义自己的返回后期处理代码
        return $result;
    }

}
