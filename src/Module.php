<?php
namespace mazpaijo\attachmentsAws;

use mazpaijo\attachmentsAws\models\File;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\i18n\PhpMessageSource;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'mazpaijo\attachmentsAws\controllers';

    public $storePath = '@app/uploads/store';

    public $tempPath = '@app/uploads/temp';

    public $rules = [];

    public $tableName = 'attach_file';

    public function init()
    {
        parent::init();

        if (empty($this->storePath) || empty($this->tempPath)) {
            throw new Exception('Setup {storePath} and {tempPath} in module properties');
        }

        $this->rules = ArrayHelper::merge(['maxFiles' => 3], $this->rules);
        $this->defaultRoute = 'file';
        $this->registerTranslations();
    }

    public function registerTranslations()
    {
        \Yii::$app->i18n->translations['mazpaijo/*'] = [
            'class' => PhpMessageSource::className(),
            'sourceLanguage' => 'en',
            'basePath' => '@vendor/mazpaijo/yii2-attachments-aws/src/messages',
            'fileMap' => [
                'mazpaijo/attachments-aws' => 'attachments-aws.php'
            ],
        ];
    }

    public static function t($category, $message, $params = [], $language = null)
    {
        return \Yii::t('mazpaijo/' . $category, $message, $params, $language);
    }

    public function getStorePath()
    {
        return \Yii::getAlias($this->storePath);
    }

    public function getTempPath()
    {
        return \Yii::getAlias($this->tempPath);
    }

    /**
     * @param $fileHash
     * @return string
     */
    public function getFilesDirPath($fileHash)
    {
        $path = $this->getStorePath() . DIRECTORY_SEPARATOR . $this->getSubDirs($fileHash);

        FileHelper::createDirectory($path);

        return $path;
    }
    public function getFilesDirPathAws($fileHash)
    {
        $path = $this->getSubDirs($fileHash);
        //FileHelper::createDirectory($path);

        return $path;
    }

    public function getSubDirs($fileHash, $depth = 3)
    {
        $depth = min($depth, 9);
        $path = '';

        for ($i = 0; $i < $depth; $i++) {
            $folder = substr($fileHash, $i * 3, 2);
            $path .= $folder;
            if ($i != $depth - 1) $path .= DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    public function getUserDirPath()
    {
        \Yii::$app->session->open();

        $userDirPath = $this->getTempPath() . DIRECTORY_SEPARATOR . \Yii::$app->session->id;
        FileHelper::createDirectory($userDirPath);

        \Yii::$app->session->close();

        return $userDirPath . DIRECTORY_SEPARATOR;
    }

    public function getShortClass($obj)
    {
        $className = get_class($obj);
        if (preg_match('@\\\\([\w]+)$@', $className, $matches)) {
            $className = $matches[1];
        }
        return $className;
    }

    /**
     * @param $filePath string
     * @param $owner
     * @return bool|File
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function attachFile($filePath, $owner)
    {
        if (empty($owner->id)) {
            throw new Exception('Parent model must have ID when you attaching a file');
        }
        if (!file_exists($filePath)) {
            throw new Exception("File $filePath not exists");
        }

        $fileHash = md5(microtime(true) . $filePath);
        $fileType = pathinfo($filePath, PATHINFO_EXTENSION);
        $newFileName = "$fileHash.$fileType";
        $fileDirPath = $this->getFilesDirPath($fileHash);
        $newFilePath = $fileDirPath . DIRECTORY_SEPARATOR . $newFileName;

        if (!copy($filePath, $newFilePath)) {
            throw new Exception("Cannot copy file! $filePath  to $newFilePath");
        }


        $file = new File();
        $file->name = pathinfo($filePath, PATHINFO_FILENAME);
        $file->model = $this->getShortClass($owner);
        $file->itemId = $owner->id;
        $file->terminal_id = $owner->terminal_id;
        $file->userId = (\Yii::$app->user->isGuest) ? "" : \Yii::$app->user->identity->id;
        $file->created_by = (\Yii::$app->user->isGuest) ? "" : \Yii::$app->user->identity->id;
        $file->hash = $fileHash;
        $file->size = filesize($filePath);
        $file->type = $fileType;
        $file->mime = FileHelper::getMimeType($filePath);

        if ($file->save()) {
            unlink($filePath);
            return $file;
        } else {
            return false;
        }
    }

    public function attachFileAws($filePath, $owner)
    {
        if (empty($owner->id)) {
            throw new Exception('Parent model must have ID when you attaching a file');
        }

        $fileHash = md5(microtime(true) . $filePath);
        $fileType = pathinfo($filePath, PATHINFO_EXTENSION);
        $newFileName = "$fileHash.$fileType";
        $fileDirPath = $this->getFilesDirPathAws($fileHash);
        $newFilePath = $fileDirPath . DIRECTORY_SEPARATOR . $newFileName;
        //\Yii::$app->awss3Fs->createDir($fileDirPath);
        $stream = fopen($filePath, 'r+');
        \Yii::$app->awss3Fs->write("attachments".DIRECTORY_SEPARATOR.$newFilePath,$stream);
        fclose($stream);
        $file = new File();
        $file->name = pathinfo($filePath, PATHINFO_FILENAME);
        $file->model = $this->getShortClass($owner);
        $file->itemId = $owner->id;
        $file->terminal_id = $owner->terminal_id;
        $file->userId = (\Yii::$app->user->isGuest) ? "" : \Yii::$app->user->identity->id;
        $file->created_by = (\Yii::$app->user->isGuest) ? "" : \Yii::$app->user->identity->id;
        $file->hash = $fileHash;
        $file->size = filesize($filePath);
        $file->type = $fileType;
        $file->mime = FileHelper::getMimeType($filePath);

        if ($file->save()) {
            unlink($filePath);
            return $file;
        } else {
            return false;
        }
    }

    public function detachFile($id)
    {
        /** @var File $file */
        $file = File::findOne(['id' => $id]);
        if (empty($file)) return false;
        $filePath = $this->getFilesDirPath($file->hash) . DIRECTORY_SEPARATOR . $file->hash . '.' . $file->type;
        
        // this is the important part of the override.
        // the original methods doesn't check for file_exists to be 
        return file_exists($filePath) ? unlink($filePath) && $file->delete() : $file->delete();
    }

    public function detachFileAws($id){
        /** @var File $file */
        $file = File::findOne(['id' => $id]);
        if (empty($file)) return false;
        $filePath = $this->getFilesDirPathAws($file->hash) . DIRECTORY_SEPARATOR . $file->hash . '.' . $file->type;
        $exists = \Yii::$app->awss3Fs->has("attachments".DIRECTORY_SEPARATOR.$filePath);
        $file->delete();
        if ($exists){
            \Yii::$app->awss3Fs->delete("attachments".DIRECTORY_SEPARATOR.$filePath);
        }

        return true;

    }
}
