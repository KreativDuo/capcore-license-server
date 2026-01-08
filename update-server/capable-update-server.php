<?php

/**
 * Extend WP Update Server
 */

class Capable_UpdateServer extends Wpup_UpdateServer {
    
        
    /**
     * Database Settings
     */

    protected $database_connection;    
    
    
    /**
     * Database Verification Table
     */

    protected $verification_table;
    
    
    /**
     * License Status
     */
    
    protected $license_valid = false;


    /**
     * License Info
     */

    protected $license_info = 'Your license is not valid.';

    
    /**
     * Instantiates the class
     */
    
    public function __construct() {
        
        parent::__construct();
        
        // Database Verification Table
        $this->verification_table = 'verification';
        
        // Open Database Connection
        $this->database_connection = new mysqli( LS_DB_HOST, LS_DB_USER, LS_DB_PASSWORD, LS_DB_NAME );
        
    }


    /**
     * Adjust Meta Data
     *
     * @param $meta
     * @param $request
     *
     * @return $meta
     *
     */
    
    protected function filterMetadata( $meta, $request ) {

        global $license;

        $meta = parent::filterMetadata( $meta, $request );

        if( plugin_white_list( $request->query['slug'] ) ) {
            return $meta;
        }

        if( !empty( $request->query['purchase_code'] ) ) {

            $license->set_purchase_code( $request->query['purchase_code'], $request->query['domain'] );

        }

        $license->set_license_status();

        if( license_white_list( $license->get_purchase_code() ) ) {

            $license->license_valid = true;

        }

        // valid license
        if( $license->license_valid ) {

            $meta['download_url'] = self::addQueryArg( array( 'purchase_code' => $request->param('purchase_code'), 'domain' => $request->query['domain'] ), $meta['download_url'] );

        } else {

            // invalid license
            unset( $meta['download_url'] );

        }

        $meta['sections'] = array(
            // 'intro'     => 'test',
            'changelog' => 'Please visit <a target="_blank" href="https://capable-themes.com/changelog/">capable-themes.com</a> for the changelog information.'
        );

		return $meta;
        
	}


    /**
     * Check Download
     */
    protected function checkAuthorization( $request ) {

        global $license;

        parent::checkAuthorization( $request );

        if( !plugin_white_list( $request->query['slug'] ) ) {

            if (!empty($request->query['purchase_code'])) {

                $license->set_purchase_code($request->query['purchase_code'], $request->query['domain']);

            }

            $license->set_license_status();

            if ($request->action === 'download') {

                if (!$license->license_valid) {

                    header('Content-Type: application/json');
                    echo '{"result" : "access_denied", "reason" : "' . $license->license_info . '"}';
                    die();

                }

            }

        }

    }

}