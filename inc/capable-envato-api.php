<?php

// required core files
require_once('config.inc.php');


class Capable_Envato_API {
    
    /**
     * API Key
     */
    
    protected $api_key;
    

    /**
     * Instantiates the class
     */
    
    public function __construct( $api_key = NULL ) {
        
        if( !$api_key ) {
            return;
        }
        
        $this->api_key = $api_key;
        
    }
    
    /**
     * Attach API key. 
     *
     * @param string
     *
     */   
    
    public function set_api_key( $api_key ) {

        $this->api_key = $api_key;

    }
    
    
    /*
	 * Envato API Request URLs
	 *
	 * @access public
     * @param string $username 
	 * @return array
	*/
	public function api_request_urls( $type = 'base', $username = '' ) {
		
        $api_request_urls = array(
			'token'           => 'https://api.envato.com/token', 
            'authorize'       => 'https://api.envato.com/authorization',  
            'base'            => 'https://api.envato.com/v3/market',
            'email'           => 'https://api.envato.com/v1/market/private/user/email.json',
			'username'        => 'https://api.envato.com/v1/market/private/user/username.json',
			'account_details' => 'https://api.envato.com/v1/market/private/user/account.json',
			'purchases'       => 'https://api.envato.com/v2/market/buyer/purchases',
            'list_purchases'  => 'https://api.envato.com/v3/market/buyer/list-purchases',
			'user_details'    => 'https://api.envato.com/v1/market/user:' . $username . '.json',
			'user_badges'     => 'https://api.envato.com/v1/market/user-badges:' . $username . '.json',
			'author_sales'    => 'https://api.envato.com/v2/market/author/sales',
            'author_sale'     => 'https://api.envato.com/v3/market/author/sale',
            'user_items'      => 'https://api.envato.com/v1/market/user-items-by-site:' . $username . '.json'
		);
		
		return $api_request_urls[$type];
        
	}
    
    
    /**
	 * General purpose function to query the Envato API for Tokens.
	 *
	 * @param string $url The url to access, via curl.
	 * @return false The results of the curl request.
	 */
    
	protected function curl_token( $url ) {
    
		if( empty( $url) ) return false;

		$ch = curl_init( $url );
        
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Envato API Wrapper PHP)' );
        
        // Curl Header
		$header = array();
		$header[] = 'Content-length: 0';
		$header[] = 'Content-type: application/json';
		$header[] = 'Authorization: Bearer '. $this->api_key;

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

		$data = curl_exec( $ch );
		curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

        return json_decode( $data );
        
	}
    
    /*
	 * Call Envato API Bearer
	 *
	 * @access public
	 * @return null
	*/
    
    public function curl_bearer( $initial_data, $request_url = '' ) {
	
		$bearer = 'bearer ' . $initial_data['access_token'];
        
		$header = array();
		$header[] = 'Content-length: 0';
		$header[] = 'Content-type: application/json';
		$header[] = 'Authorization: ' . $bearer;

		$request_url = !empty( $request_url ) ? $request_url : $this->api_request_urls( 'username' );
		
		$ch = curl_init( $request_url );

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_USERAGENT, EN_CLIENT );

		$data     = curl_exec( $ch );
		$response = json_decode($data, true);

		curl_close($ch);
		
		return $data;	
	}
    
    
    /*
	 * Call Envato API
	 *
	 * @access public
	 * @return null
	*/
	public function call_envato_api( $custom_fields = array() ) {
        
		$fields = !empty( $custom_fields ) ? $custom_fields : array(
			'grant_type'    => urlencode('authorization_code'),
			'client_id'     => EN_CLIENT,
			'client_secret' => EN_SECRET
		);
		
        $fields_string = http_build_query( $fields, '', '&' );
        
		$ch = curl_init( $this->api_request_urls('token') );
	
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, count( $fields) );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, EN_CLIENT );
	
		$cinit_data = curl_exec( $ch );
	
		$initial_data = json_decode( $cinit_data, true );
	
		curl_close( $ch );
        
		return $initial_data;
        
	}
    
    
    /*
	 * Load Envato API Access Token
	 *
	 * @access public
	 * @return array
	*/
    
	public function load_envato_access_token() {
        
        if( isset( $_GET['envato_registration'] ) && isset( $_GET['code'] ) ) {
			
            $fields = array(
				'grant_type'    => urlencode( 'authorization_code' ),
				'code'          => urlencode( $_GET['code'] ),
				'client_id'     => urlencode( EN_CLIENT ),
				'client_secret' => urlencode( EN_SECRET )
			);
            
            return $this->call_envato_api( $fields );
            
            
		} elseif( isset( $_GET['envato_user_data'] ) && isset( $_GET['code'] ) ) {
			
            $fields = array(
				'grant_type'    => urlencode( 'refresh_token' ),
				'refresh_token' => urlencode( $_GET['code'] ),
				'client_id'     => urlencode( EN_CLIENT ),
				'client_secret' => urlencode( EN_SECRET )
			);            
            
            return $this->call_envato_api( $fields );
			
		}
        
        return false;        
        
    }
    
    
    /*
	 * Return Envato API Data
	 *
	 * @access public
	 * @return array
	*/
    
	public function return_envato_data( $initial_data, $request_url = '' ) {
        
        $errors = array();
        
		if( empty( $initial_data['access_token'] ) ) { 
            
            $errors[] = 'Could not get bearer, please contact admin'; 
            
        }
			
		if( !$errors ) {
			
            $data = $this->curl_bearer( $initial_data, $request_url );
			return json_decode( $data, true );
            
		} else {	
            
            print_r( $errors );
            
		}
        
	}
    
    /*
	 * Load Envato API User Data
	 *
	 * @access public
	 * @return array
	*/
	public function load_envato_user_data( $refresh_code, $request_url = '') {
		
		$fields = array(
			'grant_type'    => urlencode( 'refresh_token' ),
			'refresh_token' => urlencode( $refresh_code ),
			'client_id'     => urlencode( EN_CLIENT ),
			'client_secret' => urlencode( EN_SECRET )
		);
		
        $initial_data = $this->call_envato_api( $fields );
		
		return $this->return_envato_data( $initial_data, $request_url );
        
	}
    
    
    /*
	 * Envato API User Purchases
	 *
	 * @access public
	 * @return array
	 */
    
	public function get_user_purchases( $custom_args ) {
        
        global $db;
        
        $purchases = array();
        
        $default_args = array(
			'user_id'       => 0,
			'username'      => '',
			'refresh_code'  => ''
		);
        
		$args = array_merge( $default_args, $custom_args );

        $username     = $args['username'];
        $refresh_code = $args['refresh_code'];

        if( !empty( $username ) && !empty( $refresh_code ) ) {
			
			$data = $this->load_envato_user_data( $refresh_code, $this->api_request_urls('purchases') );
            
			foreach( $data as $item ) {
				
                foreach( $item as $single_item ) {
                    
					if( is_array( $single_item ) ) {
                        
						if( !empty( $single_item['item']['id'] ) ) {
							
                            $purchases[] = array(
								'id'                => $single_item['item']['id'], 
								'name'              => $single_item['item']['name'], 
								'date'              => $single_item['sold_at'], 
								'license'           => $single_item['license'], 
								'code'              => $single_item['code'],
								'support_amount'    => $single_item['support_amount'],
                                'supported_until'   => $single_item['supported_until']
							);
							
                            $db->connection('default')->setPrefix();
                            $db->connection('default')->insert( 'envato_purchase_codes', array(
                                'purchase_code'     => $single_item['code'],
                                'product_name'      => $single_item['item']['name'],
                                'product_id'        => $single_item['item']['id'],
                                'buyer'             => $username,
                                'license'           => $single_item['license'],
                                'supported_until'   => $single_item['supported_until']                                
                            ) );
                            
						}
                        
					}	
                    
				}
                
			}
            
		}
		
		return $purchases;
        
    }
    
    
    /*
	 * Envato API User Information
	 *
	 * @access public
	 * @return array
	 */
    
	public function get_user_info( $custom_args ) {
        
        $purchases = array();
        
        $default_args = array(
			'user_id'       => 0,
			'username'      => '',
			'refresh_code'  => ''
		);
        
		$args = array_merge( $default_args, $custom_args );

        $username     = $args['username'];
        $refresh_code = $args['refresh_code'];

        if( !empty( $username ) && !empty( $refresh_code ) ) {
            
            $profile = $this->load_envato_user_data( $refresh_code, $this->api_request_urls('account_details') );
            $email   = $this->load_envato_user_data( $refresh_code, $this->api_request_urls('email') );
            
            return array_merge( $profile, $email );
            
        }
        
    }

    /*
	 * Get Support Expired Date
	 *
	 * @return 	array
	 */
    
    public function get_support_data( $purchases ) {
        
        $last_purchase = array();        
        
        foreach( $purchases as $purchase ) {
            
            if( empty( $last_purchase ) ) {
                
                $last_purchase = $purchase;    
                
            } else {
                
                if( new DateTime( $purchase['supported_until'] ) > new DateTime( $last_purchase['supported_until'] ) ) {
                    
                    $last_purchase = $purchase;
                    
                }
                
            }
            
        }
        
        return $last_purchase;
        
    }
    
    
    /*
	 * Verify Purchase Code
	 *
	 * @return 	array
	 */

    /**
     * @throws Exception
     */
    public function verify_purchase($purchase_code = '', $force_refresh = false ) {

		if( empty( $purchase_code ) ) {
			return false;
		}

        global $db;        
        
        if( !$force_refresh ) {
            
            $db->connection('default')->setPrefix();
            $db->connection('default')->where( "purchase_code", $purchase_code );

            $response = $db->connection('default')->getOne("envato_purchase_codes");
            
        } else {
            
            $response = false;
            
        }
        
        // check response status
        if( $response ) {
            
            return $response;
            
        } else {
            
            $response = $this->curl_token( $this->api_request_urls( 'author_sale' ) . '?code=' . $purchase_code );

            if ( isset( $response->error ) && $response->error == '404' ) {

                return false;

            } else {
                
                $response = array(
                    'purchase_code'     => $purchase_code,
                    'product_name'      => $response->item->name,
                    'product_id'        => $response->item->id,
                    'buyer'             => $response->buyer,
                    'license'           => $response->license,
                    'supported_until'   => $response->supported_until                
                );
                
                // check if purchase code is already in database
                $db->connection('default')->setPrefix();
                $db->connection('default')->where( "purchase_code", $purchase_code );

                $purchase_code_data = $db->connection('default')->getOne("envato_purchase_codes");
                
                if( $purchase_code_data ) {
                    
                    $db->connection('default')->where( 'id', $purchase_code_data['id'] );
                    $db->connection('default')->update( 'envato_purchase_codes', $response );                    
                    
                } else {
                    
                    $db->connection('default')->insert('envato_purchase_codes', $response );    
                    
                }
                
                return $response;

            }
            
        }    

	}

    /*
     * Check value to find if it was serialized.
     *
     * If $data is not a string, then returned value will always be false.
     * Serialized data is always a string.
     *
     * @param string $data   Value to check to see if was serialized.
     * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
     * @return bool False if not serialized and true if it was.
     */
    
    function is_serialized( $data, $strict = true ) {
        
        // if it isn't a string, it isn't serialized.
        if ( ! is_string( $data ) ) {
            return false;
        }
        
        $data = trim( $data );
        
        if ( 'N;' == $data ) {
            return true;
        }
        
        if ( strlen( $data ) < 4 ) {
            return false;
        }
        
        if ( ':' !== $data[1] ) {
            return false;
        }
        
        if ( $strict ) {
            
            $lastc = substr( $data, -1 );
            if ( ';' !== $lastc && '}' !== $lastc ) {
                return false;
            }
            
        } else {
            
            $semicolon = strpos( $data, ';' );
            $brace     = strpos( $data, '}' );
            // Either ; or } must exist.
            if ( false === $semicolon && false === $brace )
                return false;
            // But neither must be in the first X characters.
            if ( false !== $semicolon && $semicolon < 3 )
                return false;
            if ( false !== $brace && $brace < 4 )
                return false;
        }
        
        $token = $data[0];
        
        switch ( $token ) {
            case 's' :
                if ( $strict ) {
                    if ( '"' !== substr( $data, -2, 1 ) ) {
                        return false;
                    }
                } elseif ( false === strpos( $data, '"' ) ) {
                    return false;
                }
                // or else fall through
            case 'a' :
            case 'O' :
                return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
            case 'b' :
            case 'i' :
            case 'd' :
                $end = $strict ? '$' : '';
                return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
        }
        
        return false;
    }
    
    /**
     * Check whether serialized data is of string type.
     *
     * @param string $data Serialized data.
     * @return bool False if not a serialized string, true if it is.
     */
    
    function is_serialized_string( $data ) {
        
        if ( ! is_string( $data ) ) {
            return false;
        }
        
        $data = trim( $data );
        
        if ( strlen( $data ) < 4 ) {
            
            return false;
            
        } elseif ( ':' !== $data[1] ) {
            
            return false;
            
        } elseif ( ';' !== substr( $data, -1 ) ) {
            
            return false;
            
        } elseif ( $data[0] !== 's' ) {
            
            return false;
            
        } elseif ( '"' !== substr( $data, -2, 1 ) ) {
            
            return false;
            
        } else {
            
            return true;
            
        }
    }
    
    /**
     * Unserialize value only if it was serialized.
     *
     * @param string $original Maybe unserialized original, if is needed.
     * @return mixed Unserialized data can be any type.
     */
    
    function maybe_unserialize( $original ) {
        
        if ( $this->is_serialized( $original ) ) {
            
            return @unserialize( $original );
            
        }
            
        return $original;
        
    }
    
    /**
     * Serialize data, if needed.
     *
     * @param string|array|object $data Data that might be serialized.
     * @return mixed A scalar data
     */
    
    function maybe_serialize( $data ) {
        
        if( is_array( $data ) || is_object( $data ) ) {
            
            return serialize( $data );
            
        }
        
        if( $this->is_serialized( $data, false ) ) {
            
            return serialize( $data );
            
        }
        
        return $data;
        
    }
    
    
    /**
     * A simple convenience function to save a few seconds during development.
     *
     * @param $data The array or object to display on the page, for testing.
     */
    
    public function prettyPrint( $data ) {
      
        echo "<pre>";
            print_r( $data );
        echo "</pre>";
      
    }
    
}