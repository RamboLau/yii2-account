<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace lubaogui\account\behaviors;

use yii\base\Behavior;
use yii\base\InvalidCallException;

/**
 * TimestampBehavior automatically fills the specified attributes with the current timestamp.
 *
 * To use TimestampBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use yii\behaviors\TimestampBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         TimestampBehavior::className(),
 *     ];
 * }
 * ```
 *
 * By default, TimestampBehavior will fill the `created_at` and `updated_at` attributes with the current timestamp
 * when the associated AR object is being inserted; it will fill the `updated_at` attribute
 * with the timestamp when the AR object is being updated. The timestamp value is obtained by `time()`.
 *
 * If your attribute names are different or you want to use a different way of calculating the timestamp,
 * you may configure the [[createdAtAttribute]], [[updatedAtAttribute]] and [[value]] properties like the following:
 *
 * ```php
 * use yii\db\Expression;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => TimestampBehavior::className(),
 *             'createdAtAttribute' => 'create_time',
 *             'updatedAtAttribute' => 'update_time',
 *             'value' => new Expression('NOW()'),
 *         ],
 *     ];
 * }
 * ```
 *
 * In case you use an [[Expression]] object as in the example above, the attribute will not hold the timestamp value, but
 * the Expression object itself after the record has been saved. If you need the value from DB afterwards you should call
 * the [[\yii\db\ActiveRecord::refresh()|refresh()]] method of the record.
 *
 * TimestampBehavior also provides a method named [[touch()]] that allows you to assign the current
 * timestamp to the specified attribute(s) and save them to the database. For example,
 *
 * ```php
 * $model->touch('creation_time');
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 */
class ErrorBehavior extends Behavior 
{

    private $errors;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @brief 添加错误信息
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 16:28:29
    **/
    public function addError($name,  $message = '') {
        $this->errors[$name][] = $message;
    }

    /**
     * @brief 添加错误信息
     *
     * @param [in/out] $errors : array
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 16:29:14
    **/
    public function addErrors(array $items) {
        foreach ($items as $name => $errors) {
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->addError($name, $error);
                }
            }
            else {
                $this->addError($name, $errors);
            }
        }
    }

    /**
     * @brief 获取错误信息列表
     *
     * @return  public function 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 16:29:19
    **/
    public function getErrors($name = null) {
        if ($name === null) {
            return $this->errors === null ? [] : $this->errors;
        }
        else {
            return isset($this->errors[$name]) ? $this->errors[$name] : [];
        }
    }

    /**
     * @brief 获取错误信息列表中的第一个错误信息
     *
     * @return  public function 
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/12/30 16:29:24
    **/
    public function getFirstError() {
        if (empty($this->errors)) {
            return [];
        }
        else {
            $errors = [];
            foreach ($this->errors as $name=>$es) {
                if (!empty($es)) {
                    $errors[$name] = reset($es);
                }
            }
            return $errors;
        }
    }

}
