<?php

class Capable_Demo_Server {

    /**
     * Database Verification Table
     */

    protected $verification_table;

    /**
     * Database XML Record Table
     */

    protected $record_xml_table;

    /**
     * Database Installation Record Table
     */

    protected $record_installation_table;

    /**
     * Allowed File Names
     */

    protected $allowed_request_file_names;

    /**
     * Allowed File Types
     */

    protected $allowed_request_file_extensions;

    /**
     * Request File
     */

    protected $request_file;

    /**
     * Request File
     */

    protected $request_slider;


    /**
     * Request File Extension
     */

    protected $request_file_extension;


    /**
     * Instantiates the class
     */

    public function __construct() {

        // Database Verification Table
        $this->verification_table = 'verification';

        // Statistics
        $this->record_xml_table = 'website_downloads';
        $this->record_installation_table = 'website_installations';

        // Current Allowed File Names
        $this->allowed_request_file_names = array(
            ''
        );

        $this->allowed_request_file_extensions = array(
            'txt', 'zip', 'xml', 'json', 'dat'
        );

        $this->sanitize_request_data();

    }


    /**
     * Sanitize Request Data
     */

    protected function sanitize_request_data() {

        global $license;

        // show global stats
        if( isset( $_GET['request_file_stats'] ) ) {

            $this->global_stats();

        }

        // record if installation is processed
        if( isset( $_GET['action'] ) && ( $_GET['action'] == 'record_demo_installation' ) ) {

            $this->record_installation();

        }

        // sanitize file name first
        if( isset( $_GET['file'] ) && in_array( $_GET['file'], $this->allowed_request_file_names ) ) {

            $this->request_file = $_GET['file'];

        } else {

            header('Content-Type: application/json');
            echo '{"result" : "access_denied", "reason" : "' . FILE_NOT_FOUND . '"}';
            die();

        }

        //  XML Request
        if( isset( $_GET['action'] ) && (
            $_GET['action'] == 'request_demo_xml' ||
            $_GET['action'] == 'request_demo_widgets' ||
            $_GET['action'] == 'request_demo_options' ||
            $_GET['action'] == 'request_demo_sliders' ||
            $_GET['action'] == 'request_demo_templates' ||
            $_GET['action'] == 'request_premium_templates'
        ) ) {

            if( !empty( $_GET['purchase_code'] ) ) {

                $license->set_purchase_code( $_GET['purchase_code'], $_GET['domain'] );
                $license->set_license_status();

                if( $license->license_valid ) {

                    if( $_GET['action'] == 'request_demo_xml' ) {

                        $this->process_xml();

                    }

                    if( $_GET['action'] == 'request_demo_templates' ) {

                        $this->process_templates();

                    }

                    if( $_GET['action'] == 'request_demo_options' ) {

                        $this->process_txt();

                    }

                    if( $_GET['action'] == 'request_demo_widgets' ) {

                        $this->process_widgets();

                    }

                    if( $_GET['action'] == 'request_demo_sliders' && !empty( $_GET['slider'] )  ) {

                        $this->process_sliders( $_GET['slider'] );

                    }

                } else {

                    header('Content-Type: application/json');
                    echo '{"result" : "access_denied", "reason" : "' . $license->license_info . '"}';
                    die();

                }

            } else {

                header('Content-Type: application/json');
                echo '{"result" : "access_denied", "reason" : "' . PURCHASE_CODE_EMPTY . '"}';
                die();

            }

        }

        // sanitize file name extension
        if( in_array( $_GET['kind'], $this->allowed_request_file_extensions ) ) {

            $this->request_file_extension = $_GET['kind'];

        } else {

            die('Direct Downloads are disabled. You need a valid license.');

        }





        $_GET['purchase_code'];
        $_GET['domain'];



    }


    /**
     * Get stored license data
     */

    protected function get_license_data() {






    }

    /**
     * Verify Request
     */

    protected function verify_request() {

        global $db;



    }






    /**
     * Global Statistics
     */

    protected function global_stats() {

        $templates = new FilesystemIterator( BASE_PATH . 'demo-server/templates/', FilesystemIterator::SKIP_DOTS );
        $demos = new FilesystemIterator( BASE_PATH . 'demo-server/xml/', FilesystemIterator::SKIP_DOTS );
        $options = new FilesystemIterator( BASE_PATH . 'demo-server/options/', FilesystemIterator::SKIP_DOTS );
        $slider = new FilesystemIterator( BASE_PATH . 'demo-server/revslider/', FilesystemIterator::SKIP_DOTS );

        header('Content-Type: application/json');
        echo json_encode( array(
            'templates' => iterator_count( $templates ) -1,
            'demos' => iterator_count( $demos ) -1,
            'options' => iterator_count( $options ) -1,
            'slider' => iterator_count( $slider ) -1
        ) );

        die();

    }


    /**
     * Process XML File
     */

    protected function process_xml() {

        $this->record_xml();

        header('Content-Type: text/xml; charset=utf-8');
        readfile( BASE_PATH . 'demo-server/xml/' . $this->request_file . '.xml' );
        die();

    }


    /**
     * Record XML File Download
     */

    protected function record_xml() {

        global $db;

        $data = array (
            'website' => $db->escape( $_GET['file'] ),
            'domain'  => $db->escape( $_GET['domain'] )
        );

        $db->insert( $this->record_xml_table, $data );

    }

    /**
     * Record XML File Download
     */

    protected function record_installation() {

        global $db;

        $data = array (
            'website' => $db->escape( $_GET['file'] ),
            'domain'  => $db->escape( $_GET['domain'] )
        );

        $db->insert( $this->record_installation_table, $data );

    }


    /**
     * Process TXT File
     */

    protected function process_txt() {

        header('Content-Type: txt/plain;');
        readfile( BASE_PATH . 'demo-server/options/' . $this->request_file . '.txt' );
        die();

    }


    /**
     * Process Sliders
     */

    protected function process_sliders( $slider ) {

        header('Content-Type: application/zip;');
        readfile( BASE_PATH . 'demo-server/revslider/' . $slider . '.zip' );
        die();

    }

    /**
     * Process Widgets
     */

    protected function process_widgets() {

        header('Content-Type: application/json;');
        readfile( BASE_PATH . 'demo-server/widgets/' . $this->request_file . '_widgets.json' );
        die();

    }


    /*
	 * Create Template Title
	 *
	 * @param $title string
	 * @return $title string
	 *
	 */

    protected function template_title( $title ) {

        $title = str_replace( '.txt', '', $title );

        return ucwords( str_replace( '_', ' ', $title ) );

    }


    /**
     * Process Templates
     *
     */

    protected function process_templates() {

        if( $_REQUEST['type'] == 'template-list' ) {

            $files_total = array();

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( BASE_PATH . 'demo-server/templates/' ));

            foreach( $files as $file ) {

                if ($file->isDir() || basename( $file->getPathname() ) == '.htaccess' ){
                    continue;
                }

                $files_total[str_replace( '.txt', '', $file->getfileName() )] = $this->template_title( $file->getfileName() );

            }

            asort( $files_total );

            header('Content-Type: application/json');
            echo json_encode( $files_total );

            die();


        } else {

            if( !file_exists( BASE_PATH . 'demo-server/templates/' . $_REQUEST['type'] . '.txt' ) ) {

                header('Content-Type: application/json');
                echo json_encode( array(
                    'template' => 'Template does not exist!'
                ) );
                die();

            }

            // process single template
            header('charset=utf-8');
            echo mb_convert_encoding( file_get_contents( BASE_PATH . 'demo-server/templates/' . $_REQUEST['type'] . '.txt' ), 'HTML-ENTITIES', "UTF-8");
            die();

        }

    }





    /**
     * Process a download request.
     *
     * for XML Files only
     *
     */

    protected function actionDownload() {

        $recognized_xml_files = array();





        ob_clean();

        if( isset( $_GET['file_name'] ) ) {

            $file_for_user = $_GET['file_name'];
            $path_for_user = $_GET['file_type'];

            if( $path_for_user == 'xml' || $path_for_user == 'templates' || $path_for_user == 'options' ) {

                $full_path_file = $path_for_user . "/" . $file_for_user;

                header("Content-type: application/octet-stream");
                header('Content-Disposition: attachment; filename="' . $file_for_user . '"');
                readfile( $full_path_file );

                exit();


            }

            exit();


        }

    }


    /**
     * Process a json object such as Options or Templates.
     *
     * for XML Files only
     *
     */

    protected function actionProvide() {





    }


    /**
     * A simple convenience function to save a few seconds during development.
     *
     * @param $data array or object to display on the page, for testing.
     */

    public function pretty_print( $data ) {

        echo "<pre>";
        print_r( $data );
        echo "</pre>";

    }

}