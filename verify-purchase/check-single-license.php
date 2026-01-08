<?php

require_once('../inc/capable-envato-api.php');

if( isset( $_GET['purchase_code'] ) ) {
    
    $api    = new Capable_Envato_API( EN_TOKEN );
    $verify = $api->verify_purchase( $_GET['purchase_code'], true );
    
    if( !empty( $verify['buyer'] ) ) {
        
        echo 'OK',
        die();
        
    } else {
        
        die();
        
    }
    
}