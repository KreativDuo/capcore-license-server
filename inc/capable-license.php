<?php

class Capable_License {

    /**
     * Database Verification Table
     */

    protected $verification_table;


    /**
     * License Status
     */

    public $purchase_code = false;


    /**
     * License Status
     */

    public $domain_license = false;


    /**
     * License Status
     */

    public $license_valid = false;


    /**
     * License Info
     */

    public $license_info = 'Your license is not valid.';


    /**
     * Instantiates the class
     */

    public function __construct() {

        // Database Verification Table
        $this->verification_table = 'verification';


    }

    /**
     * @param mixed $purchase_code
     * @param mixed $domain
     */

    public function set_purchase_code( $purchase_code, $domain ) {

        $this->purchase_code  = $purchase_code;
        $this->domain_license = $domain;

    }

    public function get_purchase_code() {

        return $this->purchase_code;

    }


    /**
     * Get License Status
     */

    public function set_license_status() {

        global $db;

        try {

            $db->where( 'purchase_code', $db->escape( $this->purchase_code ) );
            $db->where( 'revoke_domain', '0' );

            $verification = $db->get( $this->verification_table, null );

            $match = false;

            // @todo remove when token introduced
            if( license_white_list( $this->purchase_code ) ) {

                $this->license_valid = true;
                $this->license_info = VALID_SERVER;

                // Close connection
                $db->disconnect();
                $match = true;

            }

            if( license_black_list( $this->purchase_code ) ) {

                $this->license_valid = false;
                $this->license_info = INVALID;

                // Close connection
                $db->disconnect();
                $match = true;

            }


            if( $verification ) {

                foreach( $verification as $row ) {

                    if( $match ) {
                        continue;
                    }

                    if( check_ip_range( $_SERVER['REMOTE_ADDR'], $row['server_real_ip'] ) && $row['domain'] == $this->domain_license ) {

                        $this->license_valid = true;
                        $this->license_info = VALID_SERVER;

                        $match = true;

                    }

                }

                if( !$match ) {

                    $this->license_valid = false;
                    $this->license_info = INVALID_WRONG_SERVER;

                }

            } else {

                $this->license_valid = false;
                $this->license_info = VALID_NO_SERVER;

            }

        } catch (Exception $e) {

            echo $e->getMessage();

        }

        $db->disconnect();

    }

}