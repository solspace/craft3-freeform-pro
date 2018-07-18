<?php

namespace Solspace\FreeformPro\Controllers;

use craft\helpers\UrlHelper;
use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\MultipleValueInterface;
use Solspace\Freeform\Library\Composer\Components\Form;
use Solspace\Freeform\Library\Exceptions\FreeformException;
use Solspace\FreeformPro\Bundles\ExportProfileBundle;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Models\ExportProfileModel;
use Solspace\FreeformPro\Services\ExportProfilesService;
use yii\web\HttpException;
use yii\web\Response;

class ExportProfilesController extends BaseProController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_ACCESS);

        $exportProfileService = $this->getExportProfilesService();
        $exportProfiles       = $exportProfileService->getAllProfiles();

        return $this->renderTemplate(
            'freeform-pro/export_profiles',
            [
                'exportProfiles' => $exportProfiles,
            ]
        );
    }

    /**
     * @param string $formHandle
     *
     * @return Response
     * @throws HttpException
     */
    public function actionCreate(string $formHandle): Response
    {
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_MANAGE);

        $formModel = $this->getFormsService()->getFormByHandle($formHandle);
        if (!$formModel) {
            throw new HttpException(
                404,
                FreeformPro::t('Form with handle {handle} not found'),
                ['handle' => $formHandle]
            );
        }

        $profile = ExportProfileModel::create($formModel->getForm());

        return $this->renderEditForm($profile, FreeformPro::t('Create a new Export Profile'));
    }

    /**
     * @param int $id
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id): Response
    {
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_MANAGE);

        $profile = $this->getExportProfilesService()->getProfileById($id);

        if (!$profile) {
            throw new HttpException(
                404, FreeformPro::t('Profile with ID {id} not found'), ['id' => $id]
            );
        }

        return $this->renderEditForm($profile, $profile->name);
    }

    /**
     * @return Response
     * @throws HttpException
     */
    public function actionSave(): Response
    {
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_MANAGE);

        $post = \Craft::$app->request->post();

        $formId    = \Craft::$app->request->post('formId');
        $formModel = $this->getFormsService()->getFormById($formId);

        if (!$formModel) {
            throw new HttpException(FreeformPro::t('Form with ID {id} not found', ['id' => $formId]));
        }

        $profileId = \Craft::$app->request->post('profileId');
        $profile   = $this->getNewOrExistingProfile($profileId, $formModel->getForm());

        $profile->setAttributes($post);

        $profile->fields  = $post['fieldSettings'];
        $profile->filters = $post['filters'] ?? [];

        if ($this->getExportProfilesService()->save($profile)) {
            // Return JSON response if the request is an AJAX request
            if (\Craft::$app->request->isAjax) {
                return $this->asJson(['success' => true]);
            }

            \Craft::$app->session->setNotice(FreeformPro::t('Profile saved'));
            \Craft::$app->session->setFlash(FreeformPro::t('Profile saved'), true);

            return $this->redirectToPostedUrl($profile);
        }

        // Return JSON response if the request is an AJAX request
        if (\Craft::$app->request->isAjax) {
            return $this->asJson(['success' => false]);
        }

        \Craft::$app->session->setError(FreeformPro::t('Profile not saved'));

        return $this->renderEditForm($profile, $profile->name);
    }

    /**
     * Deletes a notification
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_MANAGE);

        $profileId = \Craft::$app->request->post('id');

        $this->getExportProfilesService()->deleteById($profileId);

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws HttpException
     */
    public function actionExport()
    {
        PermissionHelper::requirePermission(FreeformPro::PERMISSION_EXPORT_PROFILES_ACCESS);

        $this->requirePostRequest();

        $profileId = \Craft::$app->request->post('profileId');
        $type      = \Craft::$app->request->post('type');

        $profile = $this->getExportProfilesService()->getProfileById($profileId);

        if (!$profile) {
            throw new HttpException(404, FreeformPro::t('Profile with ID {id} not found'), ['id' => $profileId]);
        }

        $form = $profile->getFormModel()->getForm();
        $data = $profile->getSubmissionData();

        switch ($type) {
            case 'json':
                $this->getExportProfilesService()->exportJson($form, $data);
                break;

            case 'xml':
                $this->getExportProfilesService()->exportXml($form, $data);
                break;

            case 'text':
                $this->getExportProfilesService()->exportText($form, $data);
                break;

            case 'csv':
            default:
                $labels = [];
                foreach ($profile->getFieldSettings() as $id => $item) {
                    if (!$item['checked']) {
                        continue;
                    }
                    $labels[$id] = $item['label'];
                }

                $this->getExportProfilesService()->exportCsv($form, $labels, $data);
        }
    }

    /**
     * @param ExportProfileModel $model
     * @param string             $title
     *
     * @return Response
     */
    private function renderEditForm(ExportProfileModel $model, string $title): Response
    {
        $this->view->registerAssetBundle(ExportProfileBundle::class);

        $title .= " ({$model->getFormModel()->name})";

        return $this->renderTemplate(
            'freeform-pro/export_profiles/edit',
            [
                'profile'            => $model,
                'title'              => $title,
                'formOptionList'     => $this->getFormsService()->getAllFormNames(),
                'statusOptionList'   => $this->getStatusesService()->getAllStatusNames(),
                'continueEditingUrl' => 'freeform/export-profiles/{id}',
                'crumbs'             => [
                    ['label' => 'Freeform', 'url' => UrlHelper::cpUrl('freeform')],
                    [
                        'label' => FreeformPro::t('Export Profiles'),
                        'url'   => UrlHelper::cpUrl('freeform/export-profiles'),
                    ],
                    [
                        'label' => $title,
                        'url'   => UrlHelper::cpUrl(
                            'freeform/export-profiles/' . ($model ? $model->id : 'new')
                        ),
                    ],
                ],
            ]
        );
    }

    /**
     * @param int  $id
     * @param Form $form
     *
     * @return ExportProfileModel
     */
    private function getNewOrExistingProfile($id, Form $form): ExportProfileModel
    {
        $profile = $this->getExportProfilesService()->getProfileById((int) $id);

        if (!$profile) {
            $profile = ExportProfileModel::create($form);
        }

        return $profile;
    }

    /**
     * @return ExportProfilesService
     */
    private function getExportProfilesService(): ExportProfilesService
    {
        return FreeformPro::getInstance()->exportProfiles;
    }
}
