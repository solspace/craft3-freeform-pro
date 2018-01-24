<?php

namespace Solspace\FreeformPro\Bundles;

use Solspace\Commons\Resources\CpAssetBundle;

abstract class AbstractFreeformProAssetBundle extends CpAssetBundle
{
    /**
     * @inheritDoc
     */
    protected function getSourcePath(): string
    {
        return '@Solspace/FreeformPro/Resources';
    }
}
