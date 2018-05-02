<?php

return [
    // Settings
    'freeform/settings/recaptcha'                            => 'freeform-pro/settings/provide-setting',
    // Export
    'freeform/export/export-dialogue'                        => 'freeform-pro/quick-export/export-dialogue',
    'freeform/export'                                        => 'freeform-pro/quick-export/index',
    // Export Profiles
    'freeform/export-profiles'                               => 'freeform-pro/export-profiles/index',
    'freeform/export-profiles/delete'                        => 'freeform-pro/export-profiles/delete',
    'freeform/export-profiles/new/<formHandle:[a-zA-Z_\-]+>' => 'freeform-pro/export-profiles/create',
    'freeform/export-profiles/<id:\d+>'                      => 'freeform-pro/export-profiles/edit',
];
