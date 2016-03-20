<?php
namespace common\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\UploadedFile;
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
    const REQUEST_PARAMS_MISSING = 1001;
    const REQUEST_UUID_MISSING = 1002;
    const REQUEST_SIGN_ERROR = 2001;
    const OPERATION_FAILED = 3001;
    const DB_SAVE_FAILED = 3002;

    //api接口的返回状态，code代表错误码，message代表错误信息
    public $code = 0;
    public $message = '';
    protected $meta = [];
    protected $links = [];
    protected $requestParams = [];
    protected $headers = [];
    protected $uid = 0;
    protected $uuid = false;
    protected $user = [];
    protected $webroot = '';

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
                $this->headers = Yii::$app->request->getHeaders()->toArray();
                $this->webroot = Yii::getAlias('@webroot');
                $this->uid =isset($this->requestParams['uid'])?$this->requestParams['uid']:$this->headers['uid'];
                $this->uuid =isset($this->requestParams['uuid'])?$this->requestParams['uuid']:$this->headers['uuid'];
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
     * @添加新的响应header头中的变量
     */
    protected function addResponseHeader($name, $value)
    {
        Yii::$app->getResponse()->getHeaders()->add($name,$value);
        return true;
    }

    /**
     * @保存上传文件
     * @param $field string  POST的file字段
     * @param $path string   存储的目录相对路径
     * @return string 返回存储的额文件相对路径
     */

    protected function saveUploadFile($field, $path)
    {
        //处理头像
        $uploadFile = UploadedFile::getInstanceByName($field);
        if ($uploadFile)
        {
            return $this->saveUploadFileInstance($uploadFile, $path);
        }
        else
        {
            Yii::warning("Failed to save the upload file!");
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    protected function saveMultipleUploadFile($field, $path)
    {
        //处理多个图片上传
        $urls = [];
        $uploadFiles = UploadedFile::getInstancesByName($field);
        if (empty($uploadFiles))
        {
            return [];
        }
        foreach ($uploadFiles as $uploadFile) {
            $url = $this->saveUploadFileInstance($uploadFile, $path);
            array_push($urls, $url);
        }
        return $urls;
    }

    //保存图片实例方法
    protected function saveUploadFileInstance(&$uploadFile, $path)
    {
        $name = $uploadFile->name;
        $arrFileName = explode('.',$name);
        $fileType = end($arrFileName);
        $relativePath = $path;          //图片保存的相对路径
        $filePath = $this->webroot.'/'.$relativePath;   //图片保存的绝对路径
        $fileName = time().Yii::$app->security->generateRandomString(6).".$fileType";  //图片名称
        $targetFilename = $filePath.'/'.$fileName;

        //不存在路径则创建
        if (!is_dir($filePath))
        {
            @mkdir($filePath, 0777, true);
        }
        $size = getimagesize($uploadFile->tempName);
        $uploadFile->saveAs($targetFilename, true);
        //获取图片大小;

        return [
            'image'=>$relativePath.'/'.$fileName,
            'image_width'=>$size[0],
            'image_height'=>$size[1],
        ];
    }

    /**
     *
     * setError 设置错误信息
     */
    protected function triggerError($errorMsg, $errorNo = 1, $forceExit = true)
    {
        $this->code = $errorNo;
        $this->message = is_array($errorMsg)?json_encode($errorMsg):$errorMsg;
        if ($forceExit)
        {
            throw new Exception($this->message, $this->code);
        }
        // return json_encode(['err_no'=>$errorNo,'err_msg'=>$errorMsg]);
    }


}
