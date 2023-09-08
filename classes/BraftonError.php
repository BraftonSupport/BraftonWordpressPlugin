<?php 
/*
 * Brafton Error Class
 * rewrite for seperate function to get the erros currently logged, add new error function
 */
//for debugging.  Displays 
class BraftonErrorReport {
    
    /*
     *$url Current url location
     */
    private $url;
    /*
     *$e_key Encryption key for verification for the error logging api
     */
    private $e_key;
    /*
     *$post_url Url location for error reporting with $e_key as [GET] Parameter
     */
    private $post_url;
    /*
     *$section Current sectoin reporting the error set by passing variable to the set_section method
     */
    private $section;
    /*
     *$level current brafton level of severity set by passing int variable to the set_level method
     */
    public $level;
    
    public $debug;
    
    private $domain;
    
    private static $Errors = null;
    
    public static $instance = null;
    
    //Construct our error reporting functions
    private function __construct($api, $brand, $debug){
        $this->debug = $debug;
        $this->url = $_SERVER['REQUEST_URI'];
        $this->domain = $_SERVER['HTTP_HOST'];
        $this->api = $api;
        $this->brand = $brand;
        //$this->e_key = 'ucocfukkuineaxf2lzl3x6h9';
        $this->post_url = BRAFTON_BASE_URL.'errorlog/wordpress/error/'.BRAFTON_ERROR_KEY;
        $this->level = 1;
        $this->section = 'error initialize';
        register_shutdown_function(array($this,  'check_for_fatal'));
        set_error_handler(array($this, 'log_error') );
        set_exception_handler(array($this, 'log_exception'));
        ini_set( "display_errors", 0 );
        error_reporting( E_ALL );
    }
    public static function getInstance($api, $brand, $debug){
        if(self::$instance == null){
            self::$instance = new BraftonErrorReport($api, $brand, $debug);
        }
        return self::$instance;
    }
    //handles the error log page
    static function errorPage(){
        if(isset($_POST['braftonClearLog']) && $_POST['braftonClearLog'] == 1){
            delete_option('brafton_e_log');
        }
        
        $_POST['braftonClearLog'] = 0;
        //$save = BraftonOptions::saveAllOptions();
    }
    //Sets the current section reporting the error periodically set by the article and video loops themselves
    public function set_section($sec){
        $this->section = $sec;   
    }
    public function get_section(){
        return $this->section;   
    }
    //sets the current level of error reporting used to determine if remote sending is enabled periodically upgraded during article and video loops from 1 (critical error script stopped running) -> 5 (minor error script continued but something happened.)
    public function set_level($level){
        $this->level = $level;
    }
    //upon error being thrown log_error fires off to throw an exception erro
    public function log_error( $num, $str, $file, $line, $context = null )
    {   
        if($str == 'Call to a member function getAttribute() on a non-object'){
            $str = $str. " Couldn't retrieve either (news, comments, categoryDefinition)";
        }
        $this->log_exception( new ErrorException( $str, 0, $num, $file, $line ) );
    }
    //retrieves the current error log from the db returns an array of current logs
    private function b_e_log(){
        if(self::$Errors != null){
            return self::$Errors;
        }
        if(!$brafton_error = get_option('brafton_e_log')){
            add_option('brafton_e_log');
            $brafton = null;
        }
        else{
            $brafton_error = get_option('brafton_e_log');
            $jsonErrors = json_encode($brafton_error);
            $brafton = $brafton_error;
            
            if( mb_strlen($jsonErrors) >= 50000 ){
                $filename = "Brafton_Errors_".date('Y-M-d-(h.m.s)')."-".$this->domain.".txt";
                if(is_writable(dirname(__FILE__))){
                    $folder = BRAFTON_DIR;
                    if(!is_dir($folder.'/admin/logs')){
                        mkdir($folder.'/admin/logs');
                    }
                    $file = fopen($folder.'admin/logs/'.$filename, 'w');
                    fwrite($file, $jsonErrors);
                    fclose($file);
                }
                $client = site_url();
                $url = BRAFTON_BASE_URL.'logs.php?client='.$this->domain;
                //$url = 'http://staging.updater.brafton.com/logs.php?client='.$this->domain;
                $post_args = array(
                    'body' => array(
                        'errors' => $jsonErrors,
                        'filename'  => $filename
                    )
                );
                $response = wp_remote_post($url, $post_args);
                delete_option('brafton_e_log');
                add_option('brafton_e_log');
                return null;

            }
        }
        return self::$Errors = $brafton;
        
        //return $brafton; 
    }
    //Known minor Errors occuring from normal operation.
    public function check_known_errors($e){
        switch(basename($e->getFile())){
            case 'link-template.php':
            return false;
            break;
            case 'post.php':
            return false;
            break;
            case 'class-wp-image-editor-imagick.php':
            return false;
            break;
            case 'translation-management.class.php':
            return false;
            break;
            default:
            return true;
        }
    }
    public function forceVital($msg){
        $vitals = array(
            'Article Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time',
            'Video Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time'
        );
        if(in_array($msg, $vitals)){
            return true;
        }
        return false;
    }
    //workhorse of the error reporting.  This function does the heavy lifting of logging the error and sending an error report
    public function log_exception( Throwable $e ){
        $errorLevel = method_exists($e,'getseverity')? $e->getseverity(): 2;
        $errorLevel = $this->forceVital( $e->getMessage() )? 1 : $errorLevel;

        if ( ($errorLevel == 1) || ($this->debug) && ($this->check_known_errors($e)) ){

            $errorlog = $this->make_local_report($e, $errorLevel);
            
            if($errorLevel == 1){ 
                $append = '&b_error=vital';
                $turn_on_debug = new BraftonOptions();
                $turn_on_debug->saveOption('braftonDebugger', 1);
                if($this->domain != 'localhost'){
                    $this->send_remote_report($errorlog);
                }
                if(isset($_GET['b_error']) && $_GET['b_error'] == 'vital'){ $append = ''; }
                header("LOCATION:$this->url{$append}");
            }else {
                return;
            }
        }
        return;
    }

    //function for checking if fatal error has occured and trigger the error flow
    public function check_for_fatal(){
        $error = error_get_last();
        if ( $error["type"] == E_ERROR )
            $this->log_error( $error["type"], $error["message"], $error["file"], $error["line"] );
    }
    
    public function debug_trace($msg){    
        if(!$this->debug){
            return;
        }
        if($this->level > 2){ return; }
        $brafton_error = $this->b_e_log();
        $debug_trace = array(
                'client_sys_time'  => date(get_option('date_format')) . " " . date("H:i:s"),
                'error' => 'Debug Tace : '.$msg['message'].' in '. $msg['file'] . ' on line '. $msg['line'] . ' in section ' . $this->section
                );
        $brafton_error[] = $debug_trace;
        self::$Errors = $brafton_error;
        update_option('brafton_e_log', self::$Errors);        
    }
    
    public function make_local_report($e, $errorLevel){
        $brafton_error = $this->b_e_log();
            $errorlog = array(
                'Domain'    => $this->domain,
                'API'       => $this->api,
                'Brand'     => $this->brand,
                'client_sys_time'  => date(get_option('date_format')) . " " . date("H:i:s"),
                'error'     => get_class($e).' : '.$errorLevel.' | '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().' brafton_level '.$this->level.' in section '.$this->section
            );
            $brafton_error[] = $errorlog;
            self::$Errors = $brafton_error;
            update_option('brafton_e_log', $brafton_error);
            return $errorlog;
    }
    
    public function send_remote_report($errorlog){
        $post_args = array(
            'body' => array(
                'error' => json_encode($errorlog)
            )
        );
        wp_remote_post($this->post_url, $post_args);
    }

}
?>