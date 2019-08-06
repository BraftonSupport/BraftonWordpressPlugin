<?php
function checkRadioVal($val, $check, $return=NULL){
    if($val == $check){
        if($return == NULL){
            echo 'checked';
        }
        else{
            echo $return;
        }
    }
}
function switchCase($brand){
    switch ($brand){
        case 'api.brafton.com':
        return 'Brafton';
        break;
        case 'api.contentlead.com':
        return 'ContentLEAD';
        break;
        case 'api.castleford.com.au' || 'api.castlefordmedia.com.au':
        return 'Castleford';
        break;
    }
}
include_once BRAFTON_DIR . 'classes/BraftonError.php';
include_once BRAFTON_DIR . 'classes/BraftonOptions.php';
include_once BRAFTON_DIR . 'classes/BraftonFeedLoader.php';
include_once BRAFTON_DIR . 'classes/BraftonArticleLoader.php';
include_once BRAFTON_DIR . 'classes/BraftonVideoLoader.php';
include_once BRAFTON_DIR . 'classes/BraftonMarpro.php';
include_once BRAFTON_DIR . 'classes/BraftonCustomType.php';
include_once 'admin/_BraftonAdminFunctions.php';
include_once BRAFTON_DIR . 'BraftonXML.php';
include_once BRAFTON_DIR . 'classes/BraftonUpdate.php';
include_once BRAFTON_DIR . 'classes/BraftonWidgets.php';
?>