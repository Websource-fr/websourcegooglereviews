<?php
/**
 * 1.3.0 : badge de note affiché aussi sous Classic / Hummingbird via
 * displayProductAdditionalInfo (ces thèmes n'appellent pas displayProductRating).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    return $module->isRegisteredInHook('displayProductAdditionalInfo')
        || $module->registerHook('displayProductAdditionalInfo');
}
