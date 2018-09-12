<?php

namespace Solspace\FreeformPro\Controllers;

use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Commons\Helpers\StringHelper;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Resources\Bundles\CodepackBundle;
use Solspace\FreeformPro\FreeformPro;
use yii\web\Response;

class SettingsController extends BaseProController
{
    /**
     * @return Response|null
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionSaveSettings()
    {
        PermissionHelper::requirePermission(Freeform::PERMISSION_SETTINGS_ACCESS);

        $this->requirePostRequest();
        $postData = \Craft::$app->request->post('settings', []);

        $plugin = Freeform::getInstance();
        $plugin->setSettings($postData);

        if (\Craft::$app->plugins->savePluginSettings($plugin, $postData)) {
            \Craft::$app->session->setNotice(FreeformPro::t('Settings Saved'));

            return $this->redirectToPostedUrl();
        }

        $errors = $plugin->getSettings()->getErrors();
        \Craft::$app->session->setError(
            implode("\n", StringHelper::flattenArrayValues($errors))
        );
    }

    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function actionProvideSetting(): Response
    {
        PermissionHelper::requirePermission(Freeform::PERMISSION_SETTINGS_ACCESS);

        $this->view->registerAssetBundle(CodepackBundle::class);
        $template = \Craft::$app->request->getSegment(3);

        return $this->renderTemplate(
            'freeform-pro/_settings/' . (string) $template,
            [
                'settings' => Freeform::getInstance()->getSettings(),
            ]
        );
    }
}
