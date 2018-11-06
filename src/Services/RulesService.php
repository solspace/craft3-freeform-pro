<?php

namespace Solspace\FreeformPro\Services;

use craft\base\Component;
use craft\web\View;
use Solspace\Freeform\Events\Assets\RegisterEvent;
use Solspace\Freeform\Events\Forms\AttachFormAttributesEvent;
use Solspace\Freeform\Events\Forms\FormRenderEvent;
use Solspace\Freeform\Events\Forms\PageJumpEvent;
use Solspace\Freeform\Freeform;
use Solspace\FreeformPro\Bundles\SubmissionEditRulesBundle;
use yii\base\Event;

class RulesService extends Component
{
    /**
     * @param FormRenderEvent $event
     */
    public function addJavascriptToForm(FormRenderEvent $event)
    {
        $form           = $event->getForm();
        $ruleProperties = $form->getRuleProperties();

        if (null !== $ruleProperties && $ruleProperties->hasActiveFieldRules($form->getCurrentPage()->getIndex())) {
            static $scriptLoaded;

            if (null === $scriptLoaded) {
                $scriptJs = file_get_contents(\Yii::getAlias('@freeform-pro') . '/Resources/js/src/form/rules.js');

                if (Freeform::getInstance()->settings->isFooterScripts()) {
                    \Craft::$app->view->registerJs($scriptJs, View::POS_END);
                } else {
                    $event->appendJsToOutput($scriptJs);
                }
            }
        }
    }

    /**
     * @param AttachFormAttributesEvent $event
     */
    public function addAttributesToFormTag(AttachFormAttributesEvent $event)
    {
        $form           = $event->getForm();
        $ruleProperties = $form->getRuleProperties();

        if (null !== $ruleProperties && $ruleProperties->hasActiveFieldRules($form->getCurrentPage()->getIndex())) {
            $event->attachAttribute('data-ff-rules-enabled', true);
        }
    }

    /**
     * @param PageJumpEvent $event
     */
    public function handleFormPageJump(PageJumpEvent $event)
    {
        $form = $event->getForm();
        $ruleProperties = $form->getRuleProperties();

        if (null !== $ruleProperties && $ruleProperties->hasActiveGotoRules($form->getCurrentPage()->getIndex())) {
            $event->setJumpToIndex($ruleProperties->getPageJumpIndex($form));
        }
    }

    /**
     * @param RegisterEvent $event
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function registerRulesJsAsAssets(RegisterEvent $event)
    {
        $event->getView()->registerAssetBundle(SubmissionEditRulesBundle::class);
    }
}
