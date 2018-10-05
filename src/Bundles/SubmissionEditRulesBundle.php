<?php
/**
 * Created by PhpStorm.
 * User: gustavs
 * Date: 06/09/2017
 * Time: 15:14
 */

namespace Solspace\FreeformPro\Bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use Solspace\Commons\Resources\CpAssetBundle;

class SubmissionEditRulesBundle extends AbstractFreeformProAssetBundle
{
    /**
     * @inheritDoc
     */
    public function getScripts(): array
    {
        return [
            'js/src/submission-edit.js',
            'js/src/form/rules.js',
        ];
    }
}
