<?php

namespace mazpaijo\attachmentsAws\models;

use mazpaijo\attachmentsAws\ModuleTrait;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * This is the model class for table "attach_file".
 *
 * @property integer $id
 * @property string $name
 * @property string $model
 * @property integer $itemId
 * @property integer $userId
 * @property integer $terminal_id
 * @property integer $created_by
 * @property string $hash
 * @property integer $size
 * @property string $type
 * @property string $mime
 */
class File extends ActiveRecord
{
    use ModuleTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return Yii::$app->getModule('attachments')->tableName;
    }
    
    /**
     * @inheritDoc
     */
    public function fields()
    {
        return [
            'url'
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'model', 'itemId','userId','terminal_id','created_by', 'hash', 'size', 'type', 'mime'], 'required'],
            [['itemId','userId','terminal_id','created_by', 'size'], 'integer'],
            [['name', 'model', 'hash', 'type', 'mime'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'model' => 'Model',
            'itemId' => 'Item ID',
            'userId' => 'User ID',
            'terminal_id' => 'Terminal ID',
            'created_by' => 'Created By',
            'hash' => 'Hash',
            'size' => 'Size',
            'type' => 'Type',
            'mime' => 'Mime'
        ];
    }

    public function getUrl()
    {
        return Url::to(['/attachments-aws/file/download', 'id' => $this->id]);
    }

    public function getPath()
    {
        return $this->getModule()->getFilesDirPath($this->hash) . DIRECTORY_SEPARATOR . $this->hash . '.' . $this->type;
    }
}
