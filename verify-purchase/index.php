<?php

require_once('../inc/capable-envato-api.php');

class Capable_Verification {
    
    /**
     * Code to Verify
     */

    public $purchase_code;

    
    /**
     * Database Verification Table
     */

    protected $verification_table;


    /**
     * Database Installation Table
     */

    protected $installation_table;
    
    
    /**
     * Database Customer Table
     */

    protected $customers_table;
    

    /**
     * Database Settings
     */

    private $database;


    /**
     * Envato
     */

    private $envato;
    
    
    /**
     * Purchase
     */

    private $purchase;
    
    
    /**
     * Customer ID
     */

    private $customer_id;


    /**
     * Installation Max Count
     */

    private $max_count;
    
    
    /**
     * Sanitized $_GET
     */

    private $domain_data = array();

    
    /**
     * Instantiates the class
     */

    function __construct() {

        if( !isset( $_GET['purchase_code'] ) ) {
            return;
        }

        global $db;

        // Database Connection
        $this->database = $db;

        // Database Verification Table
        $this->verification_table = 'verification';

        // Database Installation Count
        $this->installation_table = 'installation_count';
        
        // Database Customer Table
        $this->customers_table = 'envato_customers';
        $this->max_count = 2;

        try{

            // Purchase Code to Verify
            $this->purchase_code = $this->database->escape( $_GET['purchase_code'] );

            // Domain related Data
            $this->domain_data['domain'] = isset($_GET['domain']) ? $this->database->escape( $_GET['domain'] ) : '';
            $this->domain_data['admin_email'] = isset($_GET['admin_email']) ? $this->database->escape( $_GET['admin_email'] ) : '';
            $this->domain_data['revoke_domain'] = isset($_GET['revoke_domain']) ? $this->database->escape( $_GET['revoke_domain'] ) : '';

        } catch (Exception $e) {

            header('Content-Type: application/json');
            echo '{"result" : "access_denied", "reason" : "Something went wrong!"}';

            $this->database->disconnect();
            die();

        }

        // Start Verification
        $this->start_verification();
        
    }


    /**
     * Start Verification
     * @throws Exception
     */

    function start_verification() {

        // Connect to Envato
        $this->envato = new Capable_Envato_API();
        $this->envato->set_api_key( EN_TOKEN );
        
        // Verify Purchase
        $this->purchase = ( object ) $this->envato->verify_purchase( $this->purchase_code );

        // Wrong Code
        if( !$this->purchase ) {
            
            // Close connection
            $this->database->disconnect();
            
            header('Content-Type: application/json');
            echo '{"result" : "access_denied", "reason" : "' . INVALID . '"}';
            die();
            
        } else {

            if( !valid_product_ids( $this->purchase->product_id ) ) {

                header('Content-Type: application/json');
                echo '{"result" : "access_denied", "reason" : "' . INVALID . '"}';
                die();

            } else {

                $this->db_report();

            }
            
        }
        
    }
    
    
    /**
     * Database Report
     */

    function db_report() {

        try {

            $this->database->where( 'purchase_code', $this->purchase_code );
            $this->database->where( 'revoke_domain', '0' );

            $verification = $this->database->get( $this->verification_table );

            // @todo remove when token introduced
            if( license_white_list( $this->purchase_code ) && $this->domain_data['revoke_domain'] == 0 ) {

                header('Content-Type: application/json');
                echo '{"result" : "access_success", "user" : "' . $this->purchase->buyer . '", "supported" : "' . $this->purchase->supported_until . '"}';

                // Close connection
                $this->database->disconnect();
                die();

            }

            // @todo remove when token introduced
            if( license_white_list( $this->purchase_code ) && $this->domain_data['revoke_domain'] == 1 ) {

                $this->database->where( 'purchase_code', $this->purchase_code );
                $verification = $this->database->get( $this->verification_table );

                $this->revoke_license( $verification, true );

                // Close connection
                $this->database->disconnect();
                die();

            }

            // Purchase Code has not been used before
            if( !$verification ) {

                $this->insert_license();

            // Purchase Code already in use
            } else {

                if( $this->domain_data['revoke_domain'] == 0 ) {

                    $match = false;

                    // loop through current verifications
                    foreach( $verification as $key => $single_verification ) {

                        // match found skip rest
                        if( $match ) {
                            continue;
                        }

                        // registration domain and current domain match
                        if( $single_verification['domain'] == $this->domain_data['domain'] && check_ip_range( $_SERVER['REMOTE_ADDR'], $single_verification['server_real_ip'] ) ) {

                            header('Content-Type: application/json');
                            echo '{"result" : "access_success", "user" : "' . $this->purchase->buyer . '", "supported" : "' . $this->purchase->supported_until . '"}';

                            // set match flag
                            $match = true;

                        }

                    }

                    // allow a second / third installation
                    if( !$match && $this->database->count <= $this->max_count ) {

                        $this->insert_license();

                    } elseif( !$match ) {

                        header('Content-Type: application/json');
                        echo '{"result" : "access_denied", "reason" : "' . INVALID_WRONG_SERVER . '"}';

                    }

                } else {

                    $this->revoke_license( $verification );

                }

            }

        } catch (Exception $e) {

            echo $e->getMessage();

        }
        
        // Close connection
        $this->database->disconnect();
        die();        
        
    }
    

    /**
     * Insert License
     */

    function insert_license() {

        try {

            $verification_data = array (
                'customer_id' => $this->customer_id, // @todo
                'product_id' => $this->purchase->product_id,
                'purchase_code' => $this->purchase_code,
                'purchase_code_origin' => 'envato',
                'domain' => $this->domain_data['domain'],
                'server_real_ip' => $_SERVER['REMOTE_ADDR'],
                'domain_admin_email' => $this->domain_data['admin_email'],
                'supported_until' => $this->purchase->supported_until,
                'revoke_domain' => $this->domain_data['revoke_domain'],
            );

            $this->database->where( 'purchase_code', $this->purchase_code );
            $this->database->where( 'revoke_domain', '1' );

            $verification = $this->database->get( $this->verification_table );

            if( $verification ) {

                $match = false;

                // loop through current verification
                foreach( $verification as $key => $single_verification ) {

                    // match found skip rest
                    if( $match ) {
                        continue;
                    }

                    if( $single_verification['domain'] == $this->domain_data['domain'] && check_ip_range( $_SERVER['REMOTE_ADDR'], $single_verification['server_real_ip'] )  ) {

                        $this->database->where('id', $single_verification['id'] );

                        if( $this->database->update( $this->verification_table, array( 'revoke_domain' => 0 ) ) ) {

                            header('Content-Type: application/json');
                            echo '{"result" : "access_success", "user" : "' . $this->purchase->buyer . '", "supported" : "' . $this->purchase->supported_until . '"}';

                            $match = true;

                        }

                    }

                }

                if( !$match ) {

                    if( $this->database->insert( $this->verification_table, $verification_data ) ) {

                        header('Content-Type: application/json');
                        echo '{"result" : "access_success", "user" : "' . $this->purchase->buyer . '", "supported" : "' . $this->purchase->supported_until . '"}';

                    } else {

                        header('Content-Type: application/json');
                        echo '{"result" : "access_denied", "reason" : "Could not create License!"}';

                    }

                }

            } else {

                if( $this->database->insert( $this->verification_table, $verification_data ) ) {

                    header('Content-Type: application/json');
                    echo '{"result" : "access_success", "user" : "' . $this->purchase->buyer . '", "supported" : "' . $this->purchase->supported_until . '"}';

                } else {

                    header('Content-Type: application/json');
                    echo '{"result" : "access_denied", "reason" : "Could not create License!"}';

                }

            }


        } catch ( Exception $e ) {

            header('Content-Type: application/json');
            echo '{"result" : "access_denied", "reason" : "' . $e->getMessage() . '"}';

        }
        
    }

    /**
     * Revoke License
     *
     * @param $verification array
     * @param $trusted boolean
     *
     */

    function revoke_license(array $verification, bool $trusted = false ) {

        $match = false;

        // loop through current verifications
        foreach( $verification as $key => $single_verification ) {

            // match found skip rest
            if( $match ) {
                continue;
            }

            // registration domain and current domain match
            if( $single_verification['domain'] == $this->domain_data['domain'] &&
                check_ip_range( $_SERVER['REMOTE_ADDR'], $single_verification['server_real_ip'] ) ||
                $single_verification['domain'] == $this->domain_data['domain'] && $trusted ) {

                $match = true;
                $this->database->where('id', $single_verification['id'] );

                try {

                    if( $this->database->update( $this->verification_table, array( 'revoke_domain' => 1 ) ) ) {

                        header('Content-Type: application/json');
                        echo '{"result" : "revoke_success"}';

                    }

                } catch (Exception $e) {

                    header('Content-Type: application/json');
                    echo '{"result" : "revoke_denied", "reason" : "INVALID DOMAIN"}';

                }

            }

        }

        if( !$match ) {

            header('Content-Type: application/json');
            echo '{"result" : "revoke_denied", "reason" : "' . INVALID_WRONG_SERVER . '"}';

        }


    }
    
    
}

new Capable_Verification();