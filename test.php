<?php

composer install paypal/merchant-sdk-php
require( 'vendor/autoload.php');
require( 'db.php' );
$db = new CQDashboardDB();
if($db) {
    maybe_create_database_table( $db );
    
    save_balance_to_db( 
            'my-account-name',  // this is just for internal usage in case you are using multiple accounts
            "payment_apiX.xxxxxx.com",  // api user name
            "XXXXXXXXXXXX", // api password
            "XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX", // signature
            $db
        );    
    $db->close();
}


function save_balance_to_db( $account, $user, $pwd, $signature, $db ){
    $API_Endpoint = "https://api-3t.paypal.com/nvp";
    $version = "124";
    $resArray = CallGetBalance ( $API_Endpoint, $version, $user, $pwd, $signature );
    $ack = strtoupper ( $resArray ["ACK"] );

    if ($ack == "SUCCESS") {
        for( $i = 0; $i<10; $i++ ){
            if( array_key_exists( 'L_AMT' . $i, $resArray ) && array_key_exists( 'L_CURRENCYCODE' . $i, $resArray ) ){
                $balance = urldecode ( $resArray[ 'L_AMT' . $i ] );
                $currency = urldecode ( $resArray[ 'L_CURRENCYCODE' . $i ] );

                $sql = "
                    INSERT INTO paypal( ACCOUNT, CURRENCY, BALANCE )
                    VALUES( '$account', '$currency', $balance );
                ";
                $db->exec($sql);
            }
        }
    }
}


function maybe_create_database_table( $db ){
    $sql = "
            CREATE TABLE IF NOT EXISTS paypal
            (ID INTEGER PRIMARY KEY AUTOINCREMENT,
            ACCOUNT     CHAR(10)    NOT NULL,
            CURRENCY    CHAR(3),
            BALANCE     REAL,
            TIMESTAMP DATETIME DEFAULT CURRENT_TIMESTAMP);
        ";
    $db->exec($sql);
}

function CallGetBalance($API_Endpoint, $version, $user, $pwd, $signature) {
    // setting the curl parameters.
    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, $API_Endpoint );
    curl_setopt ( $ch, CURLOPT_VERBOSE, 1 );
    curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
    curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_POST, 1 );

    // NVPRequest for submitting to server
    $nvpreq = "METHOD=GetBalance" . "&RETURNALLCURRENCIES=1" . "&VERSION=" . $version . "&PWD=" . $pwd . "&USER=" . $user . "&SIGNATURE=" . $signature;
    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $nvpreq );
    $response = curl_exec ( $ch );

    $nvpResArray = deformatNVP ( $response );

    curl_close ( $ch );

    return $nvpResArray;
}

/*
 * This function will take NVPString and convert it to an Associative Array and it will decode the response. It is usefull to search for a particular key and displaying arrays. @nvpstr is NVPString. @nvpArray is Associative Array.
 */
function deformatNVP($nvpstr) {
    $intial = 0;
    $nvpArray = array ();

    while ( strlen ( $nvpstr ) ) {
        // postion of Key
        $keypos = strpos ( $nvpstr, '=' );
        // position of value
        $valuepos = strpos ( $nvpstr, '&' ) ? strpos ( $nvpstr, '&' ) : strlen ( $nvpstr );

        /* getting the Key and Value values and storing in a Associative Array */
        $keyval = substr ( $nvpstr, $intial, $keypos );
        $valval = substr ( $nvpstr, $keypos + 1, $valuepos - $keypos - 1 );
        // decoding the respose
        $nvpArray [urldecode ( $keyval )] = urldecode ( $valval );
        $nvpstr = substr ( $nvpstr, $valuepos + 1, strlen ( $nvpstr ) );
    }
    return $nvpArray;
}
USE MULTIPLE ACCOUNTS
If you want to monitor multiple PayPal account balances just duplicate the call to save_balance_to_db. Each account is identified by the first parameter which is just an internal name for you. Multiple currencies are already included.

save_balance_to_db( 
            'my-first-account-name',  
            "payment_apiX.xxxxxx.com",  
            "XXXXXXXXXXXX", 
            "XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX", 
            $db
        );   

save_balance_to_db( 
            'my-second-account-name',  
            "payment_apiX.xxxxxx.com",  
            "XXXXXXXXXXXX", 
            "XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX.XXXXXXXXXXXXXXXX", 
            $db
        );   