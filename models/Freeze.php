<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Lubanr.com All Rights Reserved
 *
 **************************************************************************/
 
namespace lubaogui\account\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
 
 
/**
 * @file Freeze.php
 * @author 吕宝贵(lbaogui@lubanr.com)
 * @date 2015/11/29 11:26:09
 * @version $Revision$
 * @brief
 *
 **/


class Freeze extends ActiveRecord 
{

    const FREEZE_STATUS_FREEZING = 1;
    const FREEZE_STATUS_THAWED = 2;
    const FREEZE_STATUS_FINISHED = 3;

    const FREEZE_TYPE_WITHDRAW = 1;

    /**
     * @brief 获取表名称，{{%}} 会自动将表名之前加前缀，前缀在db中定义
     *
     * @retval string 表名称  
     * @author 吕宝贵
     * @date 2015/11/29 11:48:52
    **/
    public static function tableName() {
        return '{{%freeze}}';
    }

    /**
     * @brief 自动设置 created_at和updated_at
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2015/11/29 16:19:03
    **/
    public function behaviors() {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * @brief 
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 11:22:10
    **/
    public function unFreeze() {

        if ($freeze->status === FREEZE_STATUS_THAWED) {
            $this->addError('display-error', '该记录已经解锁');
            return false;
        }
        if ($freeze->status === FREEZE_STATUS_FINISHED) {
            $this->addError('display-error', '冻结已完成，无法被解除');
            return false;

        }

        $freeze->thawed_at = time();
        $freeze->status = Freeze::FREEZE_STATUS_THAWED;
        if ($freeze->save()) {
            return true;
        }
        else {
            return false;
        }

    }

    /**
     * @brief 完成冻结操作
     *
     * @return  public function 
     * @retval   
     * @see 
     * @note 
     * @author 吕宝贵
     * @date 2016/01/02 11:11:21
    **/
    public function finishFreeze() {

        if ($this->status === self::FREEZE_STATUS_THAWED) {
            $this->addError(__METHOD__, '操作无法完成，记录已经解冻';
            return false;
        }
        if ($this->status === self::FREEZE_STATUS_FINISHED) {
            return true;
        }

        $this->status = self::FREEZE_STATUS_FINISHED;
        $this->finished_at = time(); 
        if ($this->save()) {
            return true;
        }
        else {
            $this->addError(__METHOD__, '该记录已经解锁');
            return false;
        }

    }

}

/* vim: set et ts=4 sw=4 sts=4 tw=100: */
