<?php

include ("../../../inc/includes.php");

Session::checkRight("config", UPDATE);

Html::header(__('Masive', 'masive'), $_SERVER['PHP_SELF'], "projects", "PluginMasiveConfig");

if (isset($_POST['submit']) && csrf_check()) {
    $result = PluginMasiveConfig::uploadFile($_FILES['uploaded_file']);
    if ($result['status'] == 'success') {
        Html::displayMessageOk($result['message']);
    } else {
        Html::displayErrorAndDie($result['message']);
    }
}

PluginMasiveConfig::displayForm();

Html::footer();

