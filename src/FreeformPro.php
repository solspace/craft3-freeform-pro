<?php
/**
 * Created by PhpStorm.
 * User: gustavs
 * Date: 31/08/2017
 * Time: 12:01
 */

namespace Solspace\FreeformPro;

use craft\base\Plugin;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Dashboard;
use craft\services\Plugins;
use craft\web\UrlManager;
use Solspace\Freeform\Events\Fields\FetchFieldTypes;
use Solspace\Freeform\Events\Freeform\RegisterCpSubnavItemsEvent;
use Solspace\Freeform\Events\Integrations\FetchCrmTypesEvent;
use Solspace\Freeform\Events\Integrations\FetchMailingListTypesEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Services\CrmService;
use Solspace\Freeform\Services\FieldsService;
use Solspace\Freeform\Services\MailingListsService;
use Solspace\FreeformPro\Controllers\ExportProfilesController;
use Solspace\FreeformPro\Controllers\QuickExportController;
use Solspace\FreeformPro\Services\ExportProfilesService;
use Solspace\FreeformPro\Services\WidgetsService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use yii\base\Event;

/**
 * Class FreeformPro
 *
 * @property WidgetsService        $widgets
 * @property ExportProfilesService $exportProfiles
 */
class FreeformPro extends Plugin
{
    const TRANSLATION_CATEGORY = 'freeform';

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
     * Add events
     */
    public function init()
    {
        parent::init();

        $this->controllerMap = [
            'quick-export'    => QuickExportController::class,
            'export-profiles' => ExportProfilesController::class,
        ];

        $this->setComponents(
            [
                'widgets'        => WidgetsService::class,
                'exportProfiles' => ExportProfilesService::class,
            ]
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $routes       = include __DIR__ . '/routes.php';
                $event->rules = array_merge($event->rules, $routes);
            }
        );

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

        Event::on(
            Freeform::class,
            Freeform::EVENT_REGISTER_SUBNAV_ITEMS,
            function (RegisterCpSubnavItemsEvent $event) {
                $event->addSubnavItem(
                    'exportProfiles',
                    self::t('Export'),
                    'freeform/export-profiles',
                    'notifications'
                );
            }
        );

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
}
