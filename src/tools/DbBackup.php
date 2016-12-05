<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\tools;

use Craft;
use craft\base\Tool;
use craft\helpers\FileHelper;
use yii\base\ErrorException;
use yii\web\ServerErrorHttpException;
use ZipArchive;

/**
 * DbBackup represents a Backup Database tool.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class DbBackup extends Tool
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName()
    {
        return Craft::t('app', 'Backup Database');
    }

    /**
     * @inheritdoc
     */
    public static function iconValue()
    {
        return 'database';
    }

    /**
     * @inheritdoc
     */
    public static function optionsHtml()
    {
        return Craft::$app->getView()->renderTemplate('_includes/forms/checkbox',
            [
                'name' => 'downloadBackup',
                'label' => Craft::t('app', 'Download backup?'),
                'checked' => true,
            ]);
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws ServerErrorHttpException
     */
    public function performAction($params = [])
    {
        if (($backupPath = Craft::$app->getDb()->backup()) === false) {
            return null;
        }

        if (!is_file($backupPath)) {
            throw new ServerErrorHttpException('Could not create backup');
        }

        if (empty($params['downloadBackup'])) {
            return null;
        }

        $zipPath = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.pathinfo($backupPath, PATHINFO_FILENAME).'.zip';

        if (is_file($zipPath)) {
            try {
                FileHelper::removeFile($zipPath);
            } catch (ErrorException $e) {
                Craft::warning("Unable to delete the file \"{$zipPath}\": ".$e->getMessage());
            }
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new ServerErrorHttpException('Cannot create zip at '.$zipPath);
        }

        $filename = pathinfo($backupPath, PATHINFO_BASENAME);
        $zip->addFile($backupPath, $filename);
        $zip->close();

        return [
            'backupFile' => pathinfo($filename, PATHINFO_FILENAME)
        ];
    }
}