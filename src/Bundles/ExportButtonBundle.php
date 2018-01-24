<?php

namespace Solspace\FreeformPro\Bundles;

class ExportButtonBundle extends AbstractFreeformProAssetBundle
{
    /**
     * @inheritDoc
     */
    public function getScripts(): array
    {
        return ['js/export-button.js'];
    }
}
