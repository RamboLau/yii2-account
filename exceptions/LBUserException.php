<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace lubaogui\account\exceptions;

use yii\base\UserException;

/**
 * UserException is the base class for exceptions that are meant to be shown to end users.
 * Such exceptions are often caused by mistakes of end users.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class LBUserException extends UserException
{
    private $errors;
    private $responseFormat;

    /**
     * @brief 异常实例初始化
     *
     * @param [in/out] $errors : array
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 11:53:01
    **/
    public function __construct($displayErrMsg, $errorCode, array $errors = [])  {

        if (! empty($errors)) {
            $this->errors = $errors;
        }

    }  

    /**
     * @brief 设置错误信息，该错误信息
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 12:40:24
    **/
    public function getErrors() {
        return $this->errors;
    }
}
