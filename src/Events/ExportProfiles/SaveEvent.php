<?php

namespace Solspace\FreeformPro\Events\ExportProfiles;

use craft\events\CancelableEvent;
use Solspace\FreeformPro\Models\ExportProfileModel;

class SaveEvent extends CancelableEvent
{
    /** @var ExportProfileModel */
    private $model;

    /** @var bool */
    private $new;

    /**
     * @param ExportProfileModel $model
     * @param bool       $new
     */
    public function __construct(ExportProfileModel $model, bool $new = false)
    {
        $this->new   = $new;
        $this->model = $model;

        parent::__construct();
    }

    /**
     * @return ExportProfileModel
     */
    public function getModel(): ExportProfileModel
    {
        return $this->model;
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->new;
    }
}
