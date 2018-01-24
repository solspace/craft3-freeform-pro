<?php

namespace Solspace\FreeformPro\Controllers;

use Solspace\Freeform\Controllers\BaseController;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Services\ExportProfilesService;

class BaseProController extends BaseController
{
    /**
     * @return ExportProfilesService
     */
    protected function getExportProfileService(): ExportProfilesService
    {
        return FreeformPro::getInstance()->exportProfiles;
    }
}
