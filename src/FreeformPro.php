<?php
/**
 * Created by PhpStorm.
 * User: gustavs
 * Date: 31/08/2017
 * Time: 12:01
 */

namespace Solspace\FreeformPro;

use craft\base\Plugin;
use craft\db\Query;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Freeform\Controllers\SubmissionsController;
use Solspace\Freeform\Events\Fields\FetchFieldTypes;
use Solspace\Freeform\Events\Freeform\RegisterCpSubnavItemsEvent;
use Solspace\Freeform\Events\Freeform\RegisterSettingsNavigationEvent;
use Solspace\Freeform\Events\Integrations\FetchCrmTypesEvent;
use Solspace\Freeform\Events\Integrations\FetchMailingListTypesEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Services\CrmService;
use Solspace\Freeform\Services\FieldsService;
use Solspace\Freeform\Services\FormsService;
use Solspace\Freeform\Services\MailingListsService;
use Solspace\Freeform\Services\SettingsService;
use Solspace\FreeformPro\Controllers\ExportProfilesController;
use Solspace\FreeformPro\Controllers\QuickExportController;
use Solspace\FreeformPro\Controllers\SettingsController;
use Solspace\FreeformPro\Models\Settings;
use Solspace\FreeformPro\Services\ExportProfilesService;
use Solspace\FreeformPro\Services\ProFormsService;
use Solspace\FreeformPro\Services\RecaptchaService;
use Solspace\FreeformPro\Services\RulesService;
use Solspace\FreeformPro\Services\WidgetsService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use yii\base\Event;

/**
 * Class FreeformPro
 *
 * @property WidgetsService        $widgets
 * @property ExportProfilesService $exportProfiles
 * @property RecaptchaService      $recaptcha
 * @property RulesService          $rules
 * @property ProFormsService       $proForms
 */
class FreeformPro extends Plugin
{
    const TRANSLATION_CATEGORY = 'freeform';

    const VIEW_EXPORT_PROFILES = 'export-profiles';

    const PERMISSION_EXPORT_PROFILES_ACCESS = 'freeform-pro-exportProfilesAccess';
    const PERMISSION_EXPORT_PROFILES_MANAGE = 'freeform-pro-exportProfilesManage';

    /**
     * @return FreeformPro|Plugin
     */
    public static function getInstance(): FreeformPro
    {
        return parent::getInstance();
    }

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Add events
     */
    public function init()
    {
        parent::init();
        \Yii::setAlias('@freeform-pro', __DIR__);

        $this->initControllers();
        $this->initServices();

        if (!class_exists(Freeform::class)) {
            return;
        }

        $this->initRoutes();
        $this->initIntegrations();
        $this->initWidgets();
        $this->initPermissions();
        $this->initNavigation();
        $this->initEventListeners();
        $this->handlePluginChanges();
    }

    /**
     * @param string $message
     * @param array  $params
     * @param string $language
     *
     * @return string
     */
    public static function t(string $message, array $params = [], string $language = null): string
    {
        return \Craft::t(self::TRANSLATION_CATEGORY, $message, $params, $language);
    }

    /**
     * @inheritDoc
     */
    protected function afterInstall()
    {
        parent::afterInstall();

        \Craft::$app->getCache()->multiSet(
            [
                Freeform::VERSION_CACHE_KEY           => Freeform::VERSION_PRO,
                Freeform::VERSION_CACHE_TIMESTAMP_KEY => time(),
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function afterUninstall()
    {
        parent::afterUninstall();

        \Craft::$app->getCache()->delete(Freeform::VERSION_CACHE_KEY);
        \Craft::$app->getCache()->delete(Freeform::VERSION_CACHE_TIMESTAMP_KEY);
    }

    /**
     * Install only if Freeform Lite is installed
     *
     * @return bool
     */
    protected function beforeInstall(): bool
    {
        $isLiteInstalled = (bool) (new Query())
            ->select('id')
            ->from('{{%plugins}}')
            ->where(['handle' => 'freeform'])
            ->one();

        if (!$isLiteInstalled) {
            \Craft::$app->session->setNotice(
                \Craft::t('app', 'You must install Freeform Lite before you can install Freeform Pro')
            );

            return false;
        }

        return true;
    }

    private function initControllers()
    {
        if (!\Craft::$app->request->isConsoleRequest) {
            $this->controllerMap = [
                'quick-export'    => QuickExportController::class,
                'export-profiles' => ExportProfilesController::class,
                'settings'        => SettingsController::class,
            ];
        }
    }

    private function initServices()
    {
        $this->setComponents(
            [
                'widgets'        => WidgetsService::class,
                'exportProfiles' => ExportProfilesService::class,
                'recaptcha'      => RecaptchaService::class,
                'rules'          => RulesService::class,
                'proForms'       => ProFormsService::class,
            ]
        );
    }

    private function initRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $routes       = include __DIR__ . '/routes.php';
                $event->rules = array_merge($event->rules, $routes);
            }
        );
    }

    private function initIntegrations()
    {
        Event::on(
            CrmService::class,
            CrmService::EVENT_FETCH_TYPES,
            function (FetchCrmTypesEvent $event) {
                $finder = new Finder();

                $namespace = 'Solspace\FreeformPro\Integrations\CRM';

                /** @var SplFileInfo[] $files */
                $files = $finder
                    ->name('*.php')
                    ->files()
                    ->ignoreDotFiles(true)
                    ->in(__DIR__ . '/Integrations/CRM/');

                foreach ($files as $file) {
                    $className = str_replace('.' . $file->getExtension(), '', $file->getBasename());
                    $className = $namespace . '\\' . $className;
                    $event->addType($className);
                }
            }
        );

        Event::on(
            MailingListsService::class,
            MailingListsService::EVENT_FETCH_TYPES,
            function (FetchMailingListTypesEvent $event) {
                $finder = new Finder();

                $namespace = 'Solspace\FreeformPro\Integrations\MailingLists';

                /** @var SplFileInfo[] $files */
                $files = $finder
                    ->name('*.php')
                    ->files()
                    ->ignoreDotFiles(true)
                    ->in(__DIR__ . '/Integrations/MailingLists/');

                foreach ($files as $file) {
                    $className = str_replace('.' . $file->getExtension(), '', $file->getBasename());
                    $className = $namespace . '\\' . $className;
                    $event->addType($className);
                }
            }
        );

        Event::on(
            FieldsService::class,
            FieldsService::EVENT_FETCH_TYPES,
            function (FetchFieldTypes $event) {
                $finder = new Finder();

                $namespace = 'Solspace\FreeformPro\Fields';

                /** @var SplFileInfo[] $files */
                $files = $finder
                    ->name('*.php')
                    ->files()
                    ->ignoreDotFiles(true)
                    ->in(__DIR__ . '/Fields/');

                foreach ($files as $file) {
                    $className = str_replace('.' . $file->getExtension(), '', $file->getBasename());
                    $className = $namespace . '\\' . $className;
                    $event->addType($className);
                }
            }
        );
    }

    private function initWidgets()
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $finder = new Finder();

                $namespace = 'Solspace\FreeformPro\Widgets';

                /** @var SplFileInfo[] $files */
                $files = $finder
                    ->name('*Widget.php')
                    ->files()
                    ->ignoreDotFiles(true)
                    ->notName('Abstract*.php')
                    ->in(__DIR__ . '/Widgets/');

                foreach ($files as $file) {
                    $className = str_replace('.' . $file->getExtension(), '', $file->getBasename());
                    $className = $namespace . '\\' . $className;

                    $event->types[] = $className;
                }
            }
        );
    }

    private function initPermissions()
    {
        if (\Craft::$app->getEdition() >= \Craft::Pro) {
            Event::on(
                UserPermissions::class,
                UserPermissions::EVENT_REGISTER_PERMISSIONS,
                function (RegisterUserPermissionsEvent $event) {
                    if (!isset($event->permissions[Freeform::PERMISSION_NAMESPACE])) {
                        $event->permissions[Freeform::PERMISSION_NAMESPACE] = [];
                    }

                    $permissions        = $event->permissions[Freeform::PERMISSION_NAMESPACE];
                    $orderedPermissions = [];
                    foreach ($permissions as $key => $value) {
                        if ($key === Freeform::PERMISSION_SETTINGS_ACCESS) {
                            $orderedPermissions[self::PERMISSION_EXPORT_PROFILES_ACCESS] = [
                                'label'  => self::t('Access Export Profiles'),
                                'nested' => [
                                    self::PERMISSION_EXPORT_PROFILES_MANAGE => [
                                        'label' => self::t(
                                            'Manage Export Profiles'
                                        ),
                                    ],
                                ],
                            ];
                        }
                        $orderedPermissions[$key] = $value;
                    }

                    $event->permissions[Freeform::PERMISSION_NAMESPACE] = $orderedPermissions;
                }
            );
        }
    }

    private function initNavigation()
    {
        Event::on(
            Freeform::class,
            Freeform::EVENT_REGISTER_SUBNAV_ITEMS,
            function (RegisterCpSubnavItemsEvent $event) {
                if (PermissionHelper::checkPermission(self::PERMISSION_EXPORT_PROFILES_ACCESS)) {
                    $event->addSubnavItem(
                        'exportProfiles',
                        self::t('Export'),
                        'freeform/export-profiles',
                        'notifications'
                    );
                }
            }
        );
    }

    private function initEventListeners()
    {
        Event::on(
            FormsService::class,
            FormsService::EVENT_RENDER_CLOSING_TAG,
            [$this->proForms, 'addDateTimeJavascript']
        );

        Event::on(
            FormsService::class,
            FormsService::EVENT_RENDER_CLOSING_TAG,
            [$this->proForms, 'addPhonePatternJavascript']
        );

        Event::on(
            FieldsService::class,
            FieldsService::EVENT_AFTER_VALIDATE,
            [$this->recaptcha, 'validateRecaptcha']
        );

        Event::on(
            SettingsService::class,
            SettingsService::EVENT_REGISTER_SETTINGS_NAVIGATION,
            function (RegisterSettingsNavigationEvent $event) {
                $event->addNavigationItem('recaptcha', FreeformPro::t('reCAPTCHA'), 'spam');
            }
        );

        Event::on(
            FormsService::class,
            FormsService::EVENT_RENDER_CLOSING_TAG,
            [$this->rules, 'addJavascriptToForm']
        );

        Event::on(
            FormsService::class,
            FormsService::EVENT_RENDER_CLOSING_TAG,
            [$this->recaptcha, 'addRecaptchaJavascriptToForm']
        );

        Event::on(
            FormsService::class,
            FormsService::EVENT_ATTACH_FORM_ATTRIBUTES,
            [$this->rules, 'addAttributesToFormTag']
        );

        Event::on(
            FormsService::class,
            FormsService::EVENT_PAGE_JUMP,
            [$this->rules, 'handleFormPageJump']
        );

        Event::on(
            SubmissionsController::class,
            SubmissionsController::EVENT_REGISTER_EDIT_ASSETS,
            [$this->rules, 'registerRulesJsAsAssets']
        );
    }

    private function handlePluginChanges()
    {
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_ENABLE_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin->getHandle() === 'freeform-pro') {
                    \Craft::$app->getCache()->multiSet(
                        [
                            Freeform::VERSION_CACHE_KEY           => Freeform::VERSION_PRO,
                            Freeform::VERSION_CACHE_TIMESTAMP_KEY => time(),
                        ]
                    );
                }
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_DISABLE_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin->getHandle() === 'freeform-pro') {
                    \Craft::$app->getCache()->delete(Freeform::VERSION_CACHE_KEY);
                    \Craft::$app->getCache()->delete(Freeform::VERSION_CACHE_TIMESTAMP_KEY);
                }
            }
        );
    }
}
