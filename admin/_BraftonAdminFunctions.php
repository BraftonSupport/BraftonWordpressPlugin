<?php
class BraftonAdministration {
    public $options;
    public $plugin_data;
    
    public $curl_init;
    
    public $allow_url_fopen;
    
    public $DOMDocument;
    
    public $post_thumbnail;
    
    public $error;
    
    public $runCheck;
    
    public $current_time;
    
    public $last_run_time;
    
    public $last_run_time_video;
    
    public $last_run;
    
    public $last_run_video;
    
    public $status = "off";
    //true means something along the error chain came back true;  False idicates all clear
    public $statusIndicator = false;
    
    public function __construct(){
        $option = new BraftonOptions();
        $this->options = $option->getAll();
        $this->current_time = date('F d Y h:i:s', time());
        $this->plugin_data  = get_plugin_data(BRAFTON_PLUGIN);
        
        $this->runAllChecks();
        $this->runCheck = $this->CheckRunStatus();
        
        $this->registerSettings();
        
    }
    public static function saveSettings(){
        if(!isset($_POST['braftonSettingsPages'])){
            return;   
        }
        switch ($_POST['submit']){
            case 'Download Error Log':
                $e_log = BraftonOptions::getErrors();
                exit();
                break;
            case 'Save Settings':
                $save = BraftonOptions::saveAllOptions();
                break;
            case 'Upload Archive':
                add_action('admin_init', array('BraftonArticleLoader', 'manualImportArchive'));
                break;
            case 'Clear Error Log':
                $er = BraftonErrorReport::errorPage();
                break;
            case 'Import Articles':
                add_action('admin_init', array('BraftonArticleLoader', 'manualImportArticles'));
                break;
            case 'Import Videos':
                add_action('admin_init', array('BraftonVideoLoader', 'manualImportVideos'));
                break;
            case 'Get Categories':
                add_action('admin_init', array('BraftonArticleLoader', 'manualImportCategories'));
                break;
        }
    }
    public static function adminInitialize(){
        $admin = new BraftonAdministration();
        include BRAFTON_DIR .'admin/BraftonAdminPage.php';
    }
    public static function styleInitialize(){
        $admin = new BraftonAdministration();
        include BRAFTON_DIR .'admin/BraftonStylePage.php';
    }
    public static function health_check(){
        $ch = curl_init();
        $client = site_url();
        $url = BRAFTON_BASE_URL.'wp-remote/remote.php?clientUrl='.$client.'&function=health_check';
        //$url = 'http://localtest.updater.com/wp-remote/remote.php?clientUrl='.$client.'&function=health_check';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        echo $response;
        wp_die();
    }
    public static function getBraftonArticles(){
        $ids = array_map(function($id){
            return trim($id);
        }, isset($_POST['ids'])? $_POST['ids'] : array());
        $options = new BraftonOptions();
        $options = $options->options;
        $postType = $options['braftonArticlePostType']? strtolower(str_replace(' ', '-', preg_replace("/[^a-z0-9 ]/i", "",$options['braftonCustomSlug']) )) : 'post';
        $postType = $options['braftonArticleExistingPostType']? $options['braftonArticleExistingPostType'] : $postType;
        $page = isset($_POST['page'])? $_POST['page'] : 1;
        $args = array(
            'post_type' => $postType,
            'meta_key'  => 'brafton_id',
            'posts_per_page'    => 10,
            'paged' => $page
        );
        $msg = "Last 10 Articles imported by ". BRAFTON_BRAND;
        if($ids){
            $msg = "";
            unset($args['meta_key']);
            $sub_args = array(
                array(
                    'key'   => 'brafton_id',
                    'value' =>  $ids,
                    'compare'   => 'IN'
                    )
                );
            $args['meta_query'] = $sub_args;
        }
        
        $query = new WP_Query($args); ?>
        <section class="brafton_article_list"><?php
        echo $msg.'<br/>';
        if($query->have_posts()) : while($query->have_posts()): $query->the_post();
            $id = get_the_ID(); 
            $meta = get_post_meta($id, 'brafton_id', true); ?>
            <article id="braft-<?php echo $id; ?>" class="single_brafton_article">
                <span><a href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a></span><span style="display:block;margin-left:25px;">DATE: <?php echo get_the_date(); ?></span><span style="display:block;margin-left:25px;">BRAF_ID: <?php echo $meta; ?></span>
            </article>
        <?php   endwhile;
        else:
            echo '<p>Sorry No articles have the id of '. implode(',', $ids).'</p>';
        endif; ?>
            <div class="braf_page">
            <a href="#" onclick="getBraftonArticles(<?php echo $page; ?>)">Previous Page </a> <a href="#" onclick="getBraftonArticles(<?php echo $page + 1; ?>)"> Next page</a>
            </div>
            </section>
        <?php wp_die();
    }
    function tooltip($tip){ 
    ?>
        <img src="<?php echo plugin_dir_url( __FILE__ ); ?>img/tt.png" class="brafton_tt" title="<?php echo $tip; ?>">
    <?php 
    }
    
    private function registerSettings(){
        $this->StatusSetup();
        $this->GeneralSettingsSetup();
        $this->ArticleSettingsSetup();
        $this->VideoSettingsSetup();
        //$this->MarproSettingsSetup();
        $this->AdvancedSettingsSetup();
        $this->ArchiveSettingSetup();
        $this->ErrorSettingsSetup();
        $this->ManualSettingsSetup();
        $this->InstructionsSetup();
        $this->PremiumStylesAtlantisVideoSetup();
        $this->PremiumStylesArticleSetup();   
        $this->ShortCodeSetup();
        $this->SearchSetup();
    }
    function style_page(){
        braftonRegisterSettings();
        include 'BraftonStylePage.php';
    }
    function render_brafton_admin_page(){
        echo '<div id="tab-1" class="tab-1">';
        settings_fields('brafton_status_display');
        do_settings_sections('brafton_status');
        echo '</div>';
        echo '<div id="tab-2" class="tab-1">';
        settings_fields( 'brafton_general_options' );
        do_settings_sections( 'brafton_general' );
        submit_button('Save Settings');
        echo '</div>';
        echo '<div id="tab-3" class="tab-2">';
        settings_fields( 'brafton_article_options');
        do_settings_sections('brafton_article');
        submit_button('Save Settings');
        echo '</div>';
        echo '<div id="tab-4" class="tab-3">';
        settings_fields('brafton_video_options');
        do_settings_sections('brafton_video');
        submit_button('Save Settings');
        echo '</div>';
        
        echo '<div id="tab-5" class="tab-4">';
        settings_fields('brafton_advanced_options');
        do_settings_sections('brafton_advanced');
        submit_button('Save Settings');
        echo '</div>';
        echo '</form>';
        if($this->options['braftonRestyle']){
        echo '<form method="post" action="'. $_SERVER['REQUEST_URI'].'" class="braf_options_form" onsubmit="return settingsValidate()">';
        echo '<div id="tab-10" class="tab-9">';
        settings_fields('brafton_article_style_options');
        do_settings_sections('brafton_article_style');
        submit_button('Save Settings');
        echo '</div>';
        echo '<div id="tab-11" class="tab-10">';
        settings_fields('brafton_atlantis_style_options');
        do_settings_sections('brafton_atlantis');
        submit_button('Save Settings');
        echo '</div>';
        echo '</form>';
        }
        echo '<div id="tab-6" class="tab-5">';
        echo '<form method="post" action="'; echo $_SERVER['REQUEST_URI']; echo '" enctype="multipart/form-data" class="brafton_archive_form">';
        settings_fields('brafton_archive_options');
        do_settings_sections('brafton_archive');
        submit_button('Upload Archive');
        echo '</form>';
        echo '</div>';
        echo '<div id="tab-7" class="tab-6">';
        echo '<form method="post" action="'. $_SERVER['REQUEST_URI'].'" class="brafton_error_form">';
        settings_fields( 'brafton_error_options' );
        do_settings_sections( 'brafton_error' );
        echo '</form>';
        echo '</div>';
        echo '<div id="tab-8" class="tab-7">';
        echo '<form method="post" action="'. $_SERVER['REQUEST_URI'].'" class="brafton_manual_form">';
        settings_fields('brafton_control_options');
        do_settings_sections('brafton_control');
        echo '</div>';
        echo '<div id="tab-9" class="tab-8">';
        settings_fields('brafton_instructions_display');
        do_settings_sections('brafton_instructions');
        echo '</div>';
        echo '<div id="tab-12" class="tab-11">';
        settings_fields('brafton_shortcode_display');
        do_settings_sections('brafton_shortcodes');
        
        settings_fields('shortcodes_arch_display');
        do_settings_sections("shortcodes_arch");
        echo '</div>';
        echo '<div id="tab-13" class="tab-13">';
        settings_fields('brafton_search_options');
        do_settings_sections('brafton_search');
        
        
        
    }
    function runAllChecks(){
        $this->status = ($this->options['braftonStatus'] || $this->options['braftonRemoteOperation']) &&
            ($this->options['braftonArticleStatus'] || $this->options['braftonVideoStatus'])? "pass" : "off";
        
        //Check Dependancies
        $this->curl_init = !function_exists('curl_init');
        
        $this->allow_url_fopen = !ini_get('allow_url_fopen') && $this->options['braftonVideoStatus'];
        
        $this->DOMDocument = !class_exists('DOMDocument');
        
        $this->post_thumbnail = !current_theme_supports( 'post-thumbnails' );
        
        $this->error = isset($_GET['b_error']);
        if($this->status != "off" 
           && ($this->curl_init || $this->allow_url_fopen || $this->DOMDocument || $this->post_thumbnail || $this->error) ){
            $this->status = "fail";   
        }
    }
    /*
    function for displaying errors that relate to the importer only
    */
    function braftonWarnings(){
        $options = $this->options;
        //check if importer settings are valid if they are not throw error // this function should be re-written
        if(isset($saved)){
            echo '<div class="updated">
                    <p>Options Saved Successfully</p>
                    </div>';
        }
        //check if curl is enabled throw warning if it is not
        if($this->curl_init){
            echo '<div class="error">
                    <p>Curl not enabled.</p>
                    </div>';
        }
        if($this->allow_url_fopen){
            echo '<div class="error">
                    <p><b><i>allow_url_fopen</i></b> may not be enabled.  Your videos will not import</p>
                    </div>';
        }
        if($this->DOMDocument){
            echo '<div class="error">
                    <p>DOMDocument not found.  Your content will not import</p>
                    </div>';
        }
        if($this->post_thumbnail) {
            echo '<div class="updated">
                    <p>Thumbnails not enabled for this Theme.</p>
                    </div>';
        }
        //If importer was run manually and had errors throws a warning
        if($this->error){
                echo '<div class="error">
                    <p>The Importer Failed to Run</p>
                    </div>';
        }
        foreach($this->runCheck as $check){
            echo $check;   
        }
        $master = "<div class='updated'>
                    <p>Current Time: $this->current_time </p>";

        if (!$options['braftonStatus'])
        {
            $master .= "</div>";

            if($options['braftonRemoteOperation']){
                echo '<div class="notice notice-info message is-dismissable">
                        <p>Remote Import enabled.</p>
                        </div>';
            }else{
                echo '<div class="error">
                        <p>Importer not enabled.</p>
                        </div>';
            }
        }else{
            $master .= "<p>Next Article Run: $this->last_run</p>
                    <p>Next Video Run: $this->last_run_video</p>
                    </div>";
        }
        echo $master;
    }
    
    function CheckRunStatus(){
        $test = true;
        $this->last_run_time = wp_next_scheduled('braftonSetUpCron');
        $this->last_run_time_video = wp_next_scheduled('braftonSetUpCronVideo');
        $statusArray = array();
        $errorArray = array();
        $this->last_run = 'N/A';
        $this->last_run_video = 'N/A';
        if($this->last_run_time){
            $this->last_run = date('F d Y h:i:s', $this->last_run_time);
        }
        if($this->last_run_time_video){
            $this->last_run_video = date('F d Y h:i:s', $this->last_run_time_video);
        }
        $time = time();
        $current_time = date('F d Y h:i:s', $time);
        if(($this->last_run_time) && $this->last_run_time < $time ){
            $this->status = "fail";
            $statusArray[] = "<div class='error'>
                    <p>The Article Importer Failed to Run at its scheduled time.  If the problem persists try enabling <b><i>&quot;Remote Import&quot;</i></b> on the General Settings Tab.  You may also Contact tech@brafton.com</p>
                    </div>";
            $errorArray[] = 'Article Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time';

        }
        if(($this->last_run_time_video) && $this->last_run_time_video < $time){
            $this->status = "fail";
            $statusArray[] = "<div class='error'>
                    <p>The Video Importer Failed to Run at its scheduled time.  If the problem persists try enabling <b><i>&quot;Remote Import&quot;</i></b> on the General Settings Tab.  You may also Contact tech@brafton.com</p>
                    </div>";
            $errorArray[] = 'Video Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time';
            
        }
        if($this->error){
                $failed_error = BraftonErrorReport::getInstance($this->options['braftonApiKey'],$this->options['braftonApiDomain'], $this->options['braftonDebugger'] );
            foreach($errorArray as $error){
                trigger_error('Article Importer has failed to run.  The cron was scheduled but did not trigger at the appropriate time');
            }
        }
        return $statusArray;
    }
    /*
    function for displaying the sections information
    */
    function print_section_info($args){
        $inst = BRAFTON_ROOT.'docs/ImporterInstructions.pdf';
        switch ($args['id']){
            case 'general':
                echo '<p>This section controls the general settings for your importer.  Features for this plugin may depend on your settings in this section.  If you need help with your settings you may contact your CMS or visit <a href="http://www.brafton.com/support" target="_blank">Our Support Page</a> for assistance.</p><p>You may also view our pdf <a target="_blank" href="'.$inst.'">Instructions</a>';
            break;
            case 'error':
                echo '<p>Provides Error Log support.  Errors resulting in failure to deliver content are directly reported and turn on debug mode capturing all errors for troubleshooting purposes.  Debug Mode has a build in &quot;Tracker&quot; which logs the progress of the importer during operation. You can turn Debug Mode on/off under the &quot;Advanced&quot; tab.</p>';
            break;
            case 'article':
                echo '<p>This section is for setting your article specific settings.  All settings on this page are independant of your video settings.';
            break;
            case 'video':
                echo '<p>This section is for setting your video specific settings.  All settings on this page are independant of your article settings.';
            break;
            case 'marpro':
                echo '<p>This section is for settings related to our Arch Product, which handles lead capture and Call To Action features.</p>';
            break;
            case 'advanced':
                echo '<p>This section is for advanced settings.  We recommend reading the manual if you are making changes here.</p>';
            break;
            case 'archive':
                echo '<p>This is for uploading an archive provided to you by your CMS</p>';
            break;
            case 'control':
                echo '<p>You can manually run the importer at any point by selecting which importer you would like to run.  If you are receiving both Vidoes, and Articles you will have to run the importer for each one seperatly.  The article importer runs each hour, the video importer runs every 12 hours.</p>';
            break;
            case 'atlantis':
                echo '<p>This is for Styling the Atlantis Video Player.  You may use the selection options below or choose to write your own CSS below.</p>';
            break;
            case 'article_style':
                echo '<p>This section can manually adjust the styles for Premium content should they conflict or be overridden by your sites stylesheets.</p>';
            break;
            case 'shortcodes':
                echo '<p>This section displays the shortcodes '.BRAFTON_BRAND.' has implimented to add some great functionality to your site.</p>';
            break;
            case 'arch_forms':
                echo '<p>These shortcodes are used to add ARCH forms to your site.</p><ul class="explain_list"><li>The ID parameter is required and should be replaced with your desired ARCH Form ID. </li><li>Type is your desired Form either "native", "iframe", or "popup".  The Default is "native"</li><li>The POPUP form has an addition feature allowing you to set a custom link for the pop up form.  this can be any valid HTML </li></ul>';
            break;
            case 'b_search':
                echo '<p>Search Brafton Content by Brafton ID that has already been imported.  You can search for multiple articles by entering multiple id\'s seperated by a comma (,) .  example [1223, 3456, 9876]</p><p>If using a custom post type this form will search ONLY that post type for content.</p>';
            break;
        }
    }
    
    function StatusSetup(){
        register_setting(
            'brafton_status_display',
            'brafton_status'
            );
        add_settings_section(
            'status',
            '<h2>'.BRAFTON_BRAND . " Importer Status</h2>",
            array($this, 'displayStatus'),
            'brafton_status'
            );
    }
    
    function displayStatus(){
        ?>
        <?php $this->braftonWarnings(); //braftonWarnings();?>
    <table class="form-table side-info">
        <tr>
            <td>Importer Name</td>
            <td><?php echo $this->plugin_data['Name'];?></td>
        </tr>
        <tr>
            <td>Importer Version</td>
            <td><?php echo $this->plugin_data['Version']; ?></td>
        </tr>
        <tr>
            <td>Author</td>
            <td><?php echo $this->plugin_data['AuthorName']; ?></td>
        </tr>
        <tr>
            <td>Support URL</td>
            <td><a href="<?php echo $this->plugin_data['PluginURI']; ?>">Brafton.com</a></td>
        </tr>
    </table>
    <?php
    }
    function ShortCodeSetup(){
        register_setting(
            'brafton_shortcode_display',
            'brafton_shortcodes'
            );
        add_settings_section(
            'shortcodes',
            BRAFTON_BRAND. ' Shortcodes',
            array($this, 'print_section_info'),
            'brafton_shortcodes'
            );
        
        register_setting(
            'shortcodes_arch_display',
            'shortcodes_arch'
            );
         add_settings_section(
            'arch_forms',
            'ARCH Forms',
            array($this, 'print_section_info'),
            'shortcodes_arch'
            );
        add_settings_field(
            'native_form', // ID
            'Native Form Example', // Title
            array($this, 'nativeFormArch') , // Callback
            'shortcodes_arch', // Page
            'arch_forms' // Section
        );
        add_settings_field(
            'iframe_form', // ID
            'Iframe Form Example', // Title
            array($this, 'iframeFormArch') , // Callback
            'shortcodes_arch', // Page
            'arch_forms' // Section
        );
        add_settings_field(
            'popup_form', // ID
            'PopUp Form Example', // Title
            array($this, 'popupFormArch') , // Callback
            'shortcodes_arch', // Page
            'arch_forms' // Section
        );
    }
    function nativeFormArch(){
        $this->tooltip("This will build the ARCH form into your site.");
       ?><span class="shortcode_example">[arch_form type="native" id="<i>{ID}</i>"/]</span>
    <?php
    }
    function iframeFormArch(){
        $this->tooltip("This will add an IFrame to your site containing your ARCH Form");
       ?><span class="shortcode_example">[arch_form type="iframe" id="<i>{ID}</i>"/]</span>
    <?php
    }
    function popupFormArch(){
        $this->tooltip("This will create a link that once clicked will open the ARCH Form as a PopUp.");
       ?><span class="shortcode_example">[arch_form type="popup" id="<i>{ID}</i>"](Content)[/arch_form]</span>
    <?php
    }
    function InstructionsSetup(){
        register_setting(
            'brafton_instructions_display',
            'brafton_instructions'
            );
        add_settings_section(
            'instructs',
            '',
            array($this, 'display_instructions'),
            'brafton_instructions'
            );
    }
    function display_instructions(){
        ?>
    <iframe src="<?php echo $inst = BRAFTON_ROOT.'docs/ImporterInstructions.pdf#view=FitH'; ?>" width="100%" height="350px"></iframe>
<?php
    }
    /*
     ************************************************************************************************
     *
     * Error Tab Functions Section
     *
     ************************************************************************************************
     */
    function ErrorSettingsSetup(){
        //Error Logs Tab
            register_setting(
                'brafton_error_options', // Option group
                'brafton_error' );
            //sets a section name for the options
            add_settings_section(
                'error', // ID
                'Error Log', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_error' // Page
            );
            /*each one of these adds a field with the options
            add_settings_field(
                'braftonDebugger', // ID
                'Debug Mode', // Title
                array($this, 'braftonDebugger') , // Callback
                'brafton_error', // Page
                'error' // Section
            );*/
            add_settings_field(
                'braftonClearLog', // ID
                'Clear Error Log', // Title
                array($this, 'braftonClearLog') , // Callback
                'brafton_error', // Page
                'error' // Section
            );
            add_settings_field(
                'braftonDisplayLog', // ID
                'Brafton Log <span id="show_hide">(Show Log)</span>', // Title 
                array($this, 'braftonDisplayLog') , // Callback
                'brafton_error', // Page
                'error' // Section
            );
    }

    //Displays the Option for Turning on the Debugger
    function braftonDebugger(){
        $options = $this->options;
        $tip = 'Turns on Debugging Mode which will capture all errors and initiate Debug Trace which tracks the progress of the importer.';
        $this->tooltip($tip); ?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <!--<input type="radio" name="braftonDebugger" value="1" <?php checkRadioVal($options['braftonDebugger'], 1); ?>> ON
        <input type="radio" name="braftonDebugger" value="0" <?php checkRadioVal($options['braftonDebugger'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1" <?php checkRadioVal($options['braftonDebugger'], 1, 'checked'); ?> >
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonDebugger" value="<?php echo $options['braftonDebugger']; ?>">
    <?php
    }

    //Displays the option for Clearing the error Log from the database
    function braftonClearLog(){
        $options = $this->options;
        $this->tooltip('Only set to Clear Log if errors have been corrected.  This log provides a record of errors thrown while the importer is running.');
        ?>
        <!--<input type="radio" name="braftonClearLog" value="1" <?php checkRadioVal($options['braftonClearLog'], 1); ?>> Clear Log
        <input type="radio" name="braftonClearLog" value="0" <?php checkRadioVal($options['braftonClearLog'], 0); ?>> Leave Log Intact-->
            <input type="hidden" name="braftonSettingsPages" value="1">
            <input type="hidden" name="braftonClearLog" value="1" > 
    <?php submit_button('Clear Error Log');
    }

    //Displays any errors currently in the Database from the braftonErrors Option
    function braftonDisplayLog(){
        $tip = 'Displays the actual errors for the importer';
        $this->tooltip($tip); ?>
        <div class="b_e_display">
            <pre style="white-space: nowrap;">

            <?php $errors = get_option('brafton_e_log');
        $length = mb_strlen(json_encode($errors));
            if(!$errors){ echo 'Everythin is fine. You have no errors'; }
            else{
                //convert obj to array
                $errors = $errors;
                if(is_array($errors)){
                    $errors = array_reverse($errors);
                }
                for($i=0;$i<count($errors);++$i){
echo $errors[$i]['client_sys_time'].':<br/>----'.$errors[$i]['error'].'<br>';
                }
                echo 'Total: ' . count($errors);
            }
            ?> 
            </pre>
        </div>
    <?php 
        submit_button('Download Error Log');

    }
    /********************************************************************************************
     *
     * General Settings Tab Functions Section
     *
     ********************************************************************************************
     */

    //Set Up the General Settings Section
    function GeneralSettingsSetup(){
    //General Settings Tab
            register_setting(
                'brafton_general_options',
                'brafton_options' );
            add_settings_section(
                'general', // ID
                'General Importer Settings', // Title
                array($this, 'print_section_info'), // Callbac)k
                'brafton_general' // Page
            );
            //each one of these adds a field with the options
            add_settings_field(
                'braftonImporterOnOff', // ID
                'Automatic Import', // Title 
                array($this, 'braftonStatus') , // Callbac)k
                'brafton_general', // Page
                'general' // Section
            );
            add_settings_field(
                'braftonApiDomain', // ID
                'API Domain', // Title
                array($this, 'braftonApiDomain') , // Callbac)k
                'brafton_general', // Page
                'general' // Section
            );
            if(!is_multisite()){
                add_settings_field(
                    'braftonImporterUser', // ID
                    'Importer User', // Title
                    array($this, 'braftonImporterUser') , // Callbac)k
                    'brafton_general', // Page
                    'general' // Section
                );
            }
            add_settings_field(
                'braftonArticleAuthorDefault',
                'Default Author',
                array($this, 'braftonArticleAuthorDefault'),
                'brafton_general',
                'general'
            );
            add_settings_field(
                'braftonMarproId',
                'Arch ID',
                array($this, 'braftonMarproId'),
                'brafton_general',
                'general'
            );
            add_settings_field(
                'braftonImportJquery',
                'Import JQuery Script',
                array($this, 'braftonImportJquery'),
                'brafton_general',
                'general'
            );
            add_settings_field(
                'braftonDefaultPostStatus',
                'Default Post Status',
                array($this, 'braftonDefaultPostStatus'),
                'brafton_general',
                'general'
            );
            add_settings_field(
                'braftonCategories',
                'Categories',
                array($this, 'braftonCategories'),
                'brafton_general',
                'general'
            );
            
            add_settings_field(
                'braftonSetDate',
                'Publish Date',
                array($this, 'braftonSetDate'),
                'brafton_general',
                'general'
            );
            
            add_settings_field(
                'braftonRemoteOperation',
                '<span style="">Remote Import</span><span style="display:block;color:red;" id="checkFlasher"></span>',
                array($this, 'braftonRemoteOperation'),
                'brafton_general',
                'general'
            );
    }
    //function for setting the marpro id
    function braftonMarproId(){
        $options = $this->options;
        $tip = 'If using our Arch Product you will need your Id.  You can obtain this information from your CMS';
        $this->tooltip($tip); 
    ?>
    <input type="text" name="braftonMarproId" value="<?php
            echo $options['braftonMarproId']; ?>"/>
    <?php
    }
    function braftonRemoteOperation(){
        $options = $this->options;
        $this->tooltip('Some systems can experience a problem with Wordpress Cron.  If your Importer does not trigger automatically you can request &quot;Remote Operation&quot; which causes a request to Brafton servers to trigger an importer run. NOTE: This option should only be used if Automatic Importing does not work.');
        ?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <!--<input type="radio" name="braftonRemoteOperation" value="1" <?php checkRadioVal($options['braftonRemoteOperation'], 1); ?>> ON
        <input type="radio" name="braftonRemoteOperation" value="0" <?php checkRadioVal($options['braftonRemoteOperation'], 0); ?>> OFF--> <input type="hidden" name="braftonRemoteTime" value="<?php echo $options['braftonRemoteTime']; ?>">
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" id="RemoteStatusAutoCheck" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonRemoteOperation'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonRemoteOperation" value="<?php echo $options['braftonRemoteOperation']; ?>">
        <?php $src='';$disp=''; if($options['braftonRemoteOperation']){ $src = '../wp-includes/images/uploader-icons-2x.png'; $disp = 'position:absolute;left-188px;';} ?><span id="remoteCheck" style="display:inline-block;position:absolute;top:10px;padding:5px;width:40px;height:40px;overflow:hidden"><img style="<?php echo $disp; ?>" src="<?php echo $src; ?>"></span>
    <?php

    }
    //Option enables setting up override styles
    //TODO : Will be moving to s seperate section to inself for style
    function braftonRestyle(){
        $options = $this->options;
        $this->tooltip('Premium content embeeded styles can be customized the premium style Tab if they are not appearing as you would like. NOTE: You must have JQuery on your site for this to work.  If you currently do not have JQuery you can add it with the &quot;Import jQuery Script&quot; option on the General Settings Tab.');
        ?>
        <!--<input type="radio" name="braftonRestyle" value="1" <?php checkRadioVal($options['braftonRestyle'], 1); ?>> Add Style Correction
        <input type="radio" name="braftonRestyle" value="0" <?php checkRadioVal($options['braftonRestyle'], 0); ?>> No Style Correction-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonRestyle'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonRestyle" value="<?php echo $options['braftonRestyle']; ?>">
    <?php
    }

    //Importing a copy of JQuery for use if not currently using a copy.  JQuery is required for video playback using AtlantisJS as well as Marpro and syle overrides
    function braftonImportJquery(){
        $options = $this->options;
        $tip = 'Some sites already have jquery, set this to off if additional jquery script included with atlantisjs is causing issues.';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonImportJquery" value="on" <?php	checkRadioval($options['braftonImportJquery'], 'on'); ?> /> On
        <input type="radio" name="braftonImportJquery" value="off" <?php checkRadioval($options['braftonImportJquery'], 'off'); ?>/> Off-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="off" data-on="on" value="1"<?php checkRadioVal($options['braftonImportJquery'], "on", 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonImportJquery" value="<?php echo $options['braftonImportJquery']; ?>">
    <?php
    }

    //Displays the option for enabling open graph tags for single article pages
    function braftonOpenGraphStatus(){
        $options = $this->options;
        $this->tooltip('Adds og: tags to the single.php pages.  These tags are used for social media sites.  Check if you have another SEO plugin currently generating these tags before turning them on. Support for Twitter Cards and Google+ in addition to Facebook.  Note: Twitter requires approval for sharing twitter cards. ');
        ?>
        <!--<input type="radio" name="braftonOpenGraphStatus" value="1" <?php checkRadioVal($options['braftonOpenGraphStatus'], 1); ?>> Add Tags
        <input type="radio" name="braftonOpenGraphStatus" value="0" <?php checkRadioVal($options['braftonOpenGraphStatus'], 0); ?>> No Tags-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonOpenGraphStatus'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonOpenGraphStatus" value="<?php echo $options['braftonOpenGraphStatus']; ?>">
    <?php
    }

    //Display the option for Brafton Categories
    function braftonCategories(){
        $options = $this->options;
        $tip = 'This option is for using categories set by the article when importer.  *RECOMENDATION: Set to Brafton Categories';
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonCategories" value="categories" <?php checkRadioval($options['braftonCategories'], 'categories'); ?> /> Brafton Categories
    <input type="radio" name="braftonCategories" value="none_cat" <?php checkRadioval($options['braftonCategories'], 'none_cat');
    ?> /> None-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="none_cat" data-on="categories" value="1"<?php checkRadioVal($options['braftonCategories'], "categories", 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonCategories" value="<?php echo $options['braftonCategories']; ?>">
    <?php
    }

    //Displays the option for support for Tags
    function braftonTags(){
        $options = $this->options;
        $tip = 'Tags are rarely used and hold no true SEO Value, however we provide you with options if you choose to use them. The Option you select must be included in your XML Feed.';
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonTags" value="tags" <?php checkRadioval($options['braftonTags'], 'tags'); ?> />Tags as tags<br />
    <input type="radio" name="braftonTags" value="keywords" <?php checkRadioval($options['braftonTags'], 'keywords');?> />Keywords as tags<br />
    <input type="radio" name="braftonTags" value="cats" <?php checkRadioval($options['braftonTags'], 'cats');?> />Categories as tags<br />
    <input type="radio" name="braftonTags" value="none_tags" <?php checkRadioval($options['braftonTags'], 'none_tags');?> /> None-->
        <select name="braftonTags">
            <option value="tags" <?php checkRadioval($options['braftonTags'], 'tags', 'selected'); ?> >Tags as tags</option>
            <option value="keywords" <?php checkRadioval($options['braftonTags'], 'keywords', 'selected');?> >Keywords as tags</option>
            <option value="cats" <?php checkRadioval($options['braftonTags'], 'cats', 'selected');?> >Categories as tags</option>
            <option value="none_tags" <?php checkRadioval($options['braftonTags'], 'none_tags', 'selected');?> > None</option>
        </select>
    <?php
    }

    //Displays the option for Custom Tags
    function braftonCustomTags(){
        $options = $this->options;
        $tip = 'Each tag seperated by a comma. I.E. (first,second,third)';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonCustomTags" value="<?php
            echo $options['braftonCustomTags']; ?>"/>
    <?php
    }

    //Displays the option for setting the date for an article when imported
    function braftonSetDate(){
        $options = $this->options;
        $tip = 'Specify which date from your XML Feed to use as the publish date upon import';
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonPublishDate" value="published" <?php checkRadioval($options['braftonPublishDate'], 'published'); ?> /> Published
    <input type="radio" name="braftonPublishDate" value="modified" <?php checkRadioval($options['braftonPublishDate'], 'modified'); ?>/> Last Modified
    <input type="radio" name="braftonPublishDate" value="created" <?php checkRadioval($options['braftonPublishDate'], 'created'); ?>/> Created-->
        <select name="braftonPublishDate">
            <option value="published" <?php checkRadioval($options['braftonPublishDate'], 'published', 'selected'); ?> > Published</option>
            <option value="modified" <?php checkRadioval($options['braftonPublishDate'], 'modified', 'selected'); ?>> Last Modified</option>
            <option value="created" <?php checkRadioval($options['braftonPublishDate'], 'created', 'selected'); ?>> Created</option>
        </select>
    <?php
    }

    //Displays the option for Default Post Status upon import
    function braftonDefaultPostStatus(){
        $options = $this->options;
        $tip = 'Sets the default Post status for articles and video imported.  Draft affords the ability to approve an article before it is made live on the blog';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonPostStatus" value="publish" <?php checkRadioval($options['braftonPostStatus'], 'publish'); ?> /> Published
        <input type="radio" name="braftonPostStatus" value="draft" <?php checkRadioval($options['braftonPostStatus'], 'draft'); ?>/> Draft
        <input type="radio" name="braftonPostStatus" value="private" <?php checkRadioval($options['braftonPostStatus'], 'private'); ?>/> Private-->
        <select name="braftonPostStatus">
            <option value="publish" <?php checkRadioval($options['braftonPostStatus'], 'publish', 'selected'); ?> > Published</option>
            <option value="draft" <?php checkRadioval($options['braftonPostStatus'], 'draft', 'selected'); ?>> Draft</option>
            <option value="private" <?php checkRadioval($options['braftonPostStatus'], 'private', 'selected'); ?>> Private</option>
        </select>
    <?php
    }

    //Displays the option for setting the importer user.  This option used to allow HTMl Tags needed for Premium content including but not limited to script, input, style ect.
    function braftonImporterUser(){
        $options = $this->options;
        $args = array(
            'role'      => 'administrator'
        );
        $admins = get_users($args);
        $tip = 'Designate a User for the Importer. *NOTE: This is different than the Author';
        $this->tooltip($tip); ?>
        <select name="braftonImporterUser">
            <option value="">Select Importer User</option><?php
        foreach($admins as $u){ ?>
            <option value="<?php echo $u->user_login; ?>" <?php checkRadioval($options['braftonImporterUser'], $u->user_login, 'selected'); ?>><?php echo $u->user_login; ?></option><?php
        }
            ?></select>

    <?php
    }

    //Displays the option Turning the Importer itself OFF/ON.
    function braftonStatus(){
        $options = $this->options;
        $tip = 'Turns the Automatic Importer ON/OFF.  Automatic Import utilizes the Wordpress Cron. Articles trigger hourly while videos trigger every 12 hours.';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonStatus" value="1" <?php checkRadioval($options['braftonStatus'], 1); ?>> ON
        <input type="radio" name="braftonStatus" value="0" <?php checkRadioval($options['braftonStatus'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1" <?php checkRadioval($options['braftonStatus'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonStatus" value="<?php echo $options['braftonStatus']; ?>">
    <?php
    }

    //Displays the Options for setting the API Domain
    function braftonApiDomain(){
        $options = $this->options;
        $tip = 'Set the domain your XML Feed originates from.  This information can be obtained from your CMS.';
        $this->tooltip($tip); ?>
            <select name='braftonApiDomain'>
                <option value="api.brafton.com" <?php checkRadioval($options['braftonApiDomain'], 'api.brafton.com', 'selected'); ?>>Brafton</option>
                <option value="api.contentlead.com" <?php checkRadioval($options['braftonApiDomain'], 'api.contentlead.com', 'selected'); ?>>ContentLEAD</option>
                <option value="api.castleford.com.au" <?php checkRadioval($options['braftonApiDomain'], 'api.castleford.com.au', 'selected'); ?>>Castleford</option>
                <option value="api.castlefordmedia.com.au" <?php checkRadioval($options['braftonApiDomain'], 'api.castlefordmedia.com.au', 'selected'); ?>>Castleford Media</option>
            </select>
    <?php
    }

    /********************************************************************************************
     *
     * Article settings Tab functions Section
     *
     ********************************************************************************************
     */

    function ArticleSettingsSetup(){
    //Articles Tab
        $options = $this->options;
        $brand = switchCase($options['braftonApiDomain']);
            register_setting(
                'brafton_article_options', // Option group
                'brafton_article' );
            //sets a section name for the options
            add_settings_section(
                'article', // ID
                'Article Importer Settings', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_article' // Page
            );
            add_settings_field(
                'braftonArticleStatus',
                'Article Importer Status',
                array($this, 'braftonArticleStatus'),
                'brafton_article',
                'article'
            );
            add_settings_field(
                'braftonApiKey', // ID
                'API Key', // Title
                array($this, 'braftonApiKey') , // Callback
                'brafton_article', // Page
                'article' // Section
            );
            add_settings_field(
                'braftonArticleDynamic',
                'Dynamic Author',
                array($this, 'braftonArticleDynamic'),
                'brafton_article',
                'article'
            );
            add_settings_field(
                'braftonCustomArticleCategories',
                'Custom Article Categories',
                array($this, 'braftonCustomArticleCategories'),
                'brafton_article',
                'article'
            );
            
            add_settings_field(
                'braftonArticleLimit',
                '# Articles to Import',
                array($this, 'braftonArticleLimit'),
                'brafton_article',
                'article'
            );
    }

    //Displays the Option for setting the API Key for use with the Artile Importer
    function braftonApiKey(){
        $options = $this->options;
        $tip = 'Enter Your API Key for your XML Feed.  This information can be obtained from your CMS. (Example: 2de93ffd-280f-4d4b-9ace-be55db9ad4b7)';
        $this->tooltip($tip); ?>
        <input type="text" name="braftonApiKey" id="brafton_api_key" value="<?php echo $options['braftonApiKey']; ?>" />

    <?php
    }
    function braftonArticleLimit(){
        $options = $this->options;
        $tip = 'The higher the number here the longer the importer will take to run.  Default is 15';
        $this->tooltip($tip); ?>
        <input type="number" name="braftonArticleLimit" value="<?php echo $options['braftonArticleLimit']; ?>" />
    <?php 
    }
    //Displays the option for allowing overriding of previously imported articles.
    function braftonUpdateContent(){
        $options = $this->options;
        $tip = 'Setting this to ON will override edits made to posts within the last 30 days or using an archive file.  NOTE: This option will completely update the article including downloading the image files associated with them.  When Turned on this option will apply ONLY to the immediately next importer operation.  It will automatically disable itself upon completion.';
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonUpdateContent" value="1" <?php checkRadioval($options['braftonUpdateContent'], 1); ?> /> On
    <input type="radio" name="braftonUpdateContent" value="0" <?php checkRadioval($options['braftonUpdateContent'], 0); ?>/> Off-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonUpdateContent'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonUpdateContent" value="<?php echo $options['braftonUpdateContent']; ?>">
    <?php
    }

    //Displays the options for Using Dynamic Authors
    function braftonArticleDynamic(){
        $options = $this->options;
        $tip = "Sets Author to 'byLine' From the feed. If the Author does not exsist they will be added.
    Default auhor is returned if no author is set int he field or if new author cannot be created.";
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonArticleDynamic" value="y" <?php checkRadioval($options['braftonArticleDynamic'], 'y'); ?> />Enable
    <input type="radio" name="braftonArticleDynamic" value="n" <?php checkRadioval($options['braftonArticleDynamic'], 'n'); ?> />Disable<br />-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="n" data-on="y" value="1"<?php checkRadioVal($options['braftonArticleDynamic'], "y", 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonArticleDynamic" value="<?php echo $options['braftonArticleDynamic']; ?>">
    <?php
    }

    //Displays the options for selecting the default author of imported content from a list of users.
    function braftonArticleAuthorDefault(){
        $options = $this->options;
        $tip = 'Set the Default Author for Articles upon Import';
        $this->tooltip($tip);
        wp_dropdown_users(array(
                'name' => 'braftonArticleAuthorDefault',
                'hide_if_only_one_author' => true,
                'selected' => $options['braftonArticleAuthorDefault']
            ));
    }

    //Displays the Options for turning the Article Importer OFF/ON
    function braftonArticleStatus(){
        $options = $this->options;
        $tip = 'Turns the Article Importer ON/OFF.';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonArticleStatus" value="1" <?php checkRadioval($options['braftonArticleStatus'], 1); ?>> ON
        <input type="radio" name="braftonArticleStatus" value="0" <?php checkRadioval($options['braftonArticleStatus'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonArticleStatus'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonArticleStatus" value="<?php echo $options['braftonArticleStatus']; ?>">
    <?php
    }

    //Displays the option for using custom article categories
    function braftonCustomArticleCategories(){
        $options = $this->options;
        $tip = 'Each category seperated by a comma. I.E. (first,second,third)';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonCustomArticleCategories" value="<?php
            echo $options['braftonCustomArticleCategories'];
    ?>"/>
    <?php
    }

    //Displays the Options for turning on custom post types for brafton content_ur
    function braftonArticlePostType(){
        $options = $this->options;
        $tip = 'Turn this option on to set custom post type for '.BRAFTON_BRAND.' Content.  If Using Custom Post type set a url slug to appear before in the url. Default is: content-blog';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonArticlePostType" value="1" <?php checkRadioval($options['braftonArticlePostType'], 1); ?>> ON
        <input type="radio" name="braftonArticlePostType" value="0" <?php checkRadioval($options['braftonArticlePostType'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" id="BraftonPostTypeCheck" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonArticlePostType'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonArticlePostType" value="<?php echo $options['braftonArticlePostType']; ?>">
        <br/>Post Type Name <input type="text" name="braftonCustomSlug" value="<?php echo $options['braftonCustomSlug']; ?>" style="width:150px;">
    <?php
    }

    function braftonArticleExistingPostType(){
        $options = $this->options;
        $tip = "Select an option from the dropdown menu to make ".BRAFTON_BRAND." articles load into a custom pre-existing post type. Default option is 'None' which will leave ".BRAFTON_BRAND." articles loading into default 'Post' post type.";
        $this->tooltip($tip);
        $array = array('posts','post', 'page', 'attachment', 'revision', 'nav_menu_item');
        $post_types = get_post_types(); ?>

        <select name="braftonArticleExistingPostType" id="braftonArticleExistingPostType" <?php checkRadioval($options["braftonArticlePostType"], 1, 'disabled'); ?>>
            <option value='0' <?php checkRadioval($options["braftonArticleExistingPostType"], 0, 'selected'); ?>>None</option>
            <?php foreach($post_types as $post_type) {
            if(array_search($post_type, $array)){
                continue;
            } ?>
            <option value="<?php echo $post_type; ?>" <?php checkRadioval(strval($options["braftonArticleExistingPostType"]), $post_type, 'selected'); ?>><?php echo $post_type; ?></option>
    <?php
            }
        ?></select><?php
    }


    function braftonArticleExistingCategory(){
        $options = $this->options;
        $tip = "To associate a pre-existing custom category type, enter the machine name of the category. Leave blank for default.";
        $this->tooltip($tip);
        $hidden = ($options['braftonArticleExistingPostType'])? 'inline-block': 'none'; ?>
        <input type="text" name="braftonArticleExistingCategory" value="<?php echo $options['braftonArticleExistingCategory']; ?>" style="width:200px;display:<?php echo $hidden; ?>;">
    <?php
    }

    function braftonArticleExistingTag(){
        $options = $this->options;
        $tip = "To associate a pre-existing custom tag type, enter the machine name of the tag. Leave blank for default.";
        $this->tooltip($tip);
        $hidden = ($options['braftonArticleExistingPostType'])? 'inline-block': 'none'; ?>
        <input type="text" name="braftonArticleExistingTag" value="<?php echo $options['braftonArticleExistingTag']; ?>" style="width:200px;display:<?php echo $hidden; ?>;">
    <?php
    }

    /*
     *****************************************************************************************************
     *
     * Archive Settings Tab function Section
     *
     *****************************************************************************************************
     */
    function ArchiveSettingSetup(){
        //Archives Tab
            register_setting(
                'brafton_archive_options', // Option group
                'brafton_archive' );
            //sets a section name for the options
            add_settings_section(
                'archive', // ID
                'Upload an Article Archive', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_archive' // Page
            );
            add_settings_field(
                'braftonArchiveImporterStatus',
                'Archive Importer Status',
                array($this, 'braftonArchiveImporterStatus'),
                'brafton_archive',
                'archive'
            );
            add_settings_field(
                'braftonArchiveUpload',
                'Upload an XML File',
                array($this, 'braftonArchiveUpload'),
                'brafton_archive',
                'archive'
            );
    }
    //Displays the options for uploading an archive file in place of retrieving a remote feed url
    function braftonArchiveUpload(){
        $tip = 'Select an XML file to upload';
        $this->tooltip($tip); ?>
    <input type="file" id="braftonUpload" name="archive" size="40" disabled>
    <?php
    }
    //Display sthe option for turning on the archive importer.  Must be tuned ON to be able to upload an archive file.
    function braftonArchiveImporterStatus(){
        $options = $this->options;
        $tip = 'Turns the ARchive Importer ON/OFF.  If this option is turned OFF selecting a file will result in nothing being imported.  You must turn this option ON AND upload a file.';
        $this->tooltip($tip); ?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <!--<input type="radio" class="archiveStatus" name="braftonArchiveImporterStatus" value="1" <?php checkRadioval($options['braftonArchiveImporterStatus'], 1); ?>> ON
        <input type="radio" class="archiveStatus" name="braftonArchiveImporterStatus" value="0" <?php checkRadioval($options['braftonArchiveImporterStatus'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" id="BraftonArchiveOptionCheck" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonArchiveImporterStatus'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonArchiveImporterStatus" value="<?php echo $options['braftonArchiveImporterStatus']; ?>">
    <?php
    }

    /*
     **********************************************************************************************
     *
     * Video Settings Tab function Section
     *
     **********************************************************************************************
     */

    function VideoSettingsSetup(){
        //Videos Tab
            register_setting(
                'brafton_video_options', // Option group
                'brafton_video' );
            //sets a section name for the options
            add_settings_section(
                'video', // ID
                'Video Importer Settings', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_video' // Page
            );
            add_settings_field(
                'braftonVideoStatus',
                'Video Importer Status',
                array($this, 'braftonVideoStatus'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoPublicKey',
                'Public Key',
                array($this, 'braftonVideoPublicKey'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoPrivateKey',
                'Private Key',
                array($this, 'braftonVideoPrivateKey'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoFeed',
                'Feed Number',
                array($this, 'braftonVideoFeed'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonCustomVideoCategories',
                'Custom Video Categories',
                array($this, 'braftonCustomVideoCategories'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoHeaderScript',
                'Include Player on Pages',
                array($this, 'braftonVideoHeaderScript'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoPlayer',
                'Video Player',
                array($this, 'braftonVideoPlayer'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoOutput',
                'Insert Video',
                array($this, 'braftonVideoOutput'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoLimit',
                '# Videos to Import',
                array($this, 'braftonVideoLimit'),
                'brafton_video',
                'video'
            );
            add_settings_field(
                'braftonVideoCTAs',
                "AtlantisJS CTA's<br/><span id='show_hide_cta'>(Show Settings)</span>",
                array($this, 'braftonVideoCTAs'),
                'brafton_video',
                'video'
            );
    }
    function braftonVideoLimit(){
        $options = $this->options;
        $tip = 'The higher the number here the longer the importer will take to run.  Default is 5';
        $this->tooltip($tip); ?>
        <input type="number" name="braftonVideoLimit" value="<?php echo $options['braftonVideoLimit']; ?>" />
    <?php 

    }
    //Displays the options to turn the Video Importer OFF/ON
    function braftonVideoStatus(){
        $options = $this->options;
        $tip = 'Turns the Video Importer ON/OFF.';
        $this->tooltip($tip); ?>
        <!--<input type="radio" name="braftonVideoStatus" value="1" <?php checkRadioval($options['braftonVideoStatus'], 1); ?>> ON
        <input type="radio" name="braftonVideoStatus" value="0" <?php checkRadioval($options['braftonVideoStatus'], 0); ?>> OFF-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonVideoStatus'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonVideoStatus" value="<?php echo $options['braftonVideoStatus']; ?>">
    <?php
    }

    //Displays the option for entering Public Key for Video Feed
    function braftonVideoPublicKey(){
        $options = $this->options;
        $tip = 'Enter your Public Key provided to you from your CMS';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonVideoPublicKey" id="brafton_video_public" value="<?php
            echo $options['braftonVideoPublicKey']; ?>" />
    <?php
    }

    //displays the option for entering Private key for video feed
    function braftonVideoPrivateKey(){
        $options = $this->options;
        $tip = 'Enter your Prive Key provided to you from your CMS';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonVideoPrivateKey" id="brafton_video_secret" value="<?php
            echo $options['braftonVideoPrivateKey']; ?>" />
    <?php
    }

    //displays the option for enterign the Feed Number {ID}
    function braftonVideoFeed(){
        $options = $this->options;
        $tip = 'Enter your Feed Number. *NOTE: This is usually 0';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonVideoFeed" value="<?php
            echo $options['braftonVideoFeed']; ?>" />
    <?php
    }

    //Displays the option for using custom video categories
    function braftonCustomVideoCategories(){
        $options = $this->options;
        $tip = 'Each category seperated by a comma. I.E. (first,second,third)';
        $this->tooltip($tip); ?>
    <input type="text" name="braftonCustomVideoCategories" value="<?php
            echo $options['braftonCustomVideoCategories'];
    ?>"/>
    <?php
    }

    //displays the option for selecting where to get the javascript used for playing videos
    function braftonVideoHeaderScript(){
        $options = $this->options;
        $tip = "Enable or disable the addition of the video scripts to the head of your page.  NOTE: This is required for your videos to play.";
        $this->tooltip($tip);
        ?>
        <!--<input type="radio" id="embed_type" name="braftonVideoHeaderScript" value="0" <?php checkRadioval($options['braftonVideoHeaderScript'], 0); ?> /> OFF
        <input type="radio" id="atlantis" name="braftonVideoHeaderScript" value="1" <?php checkRadioval($options['braftonVideoHeaderScript'], 1); ?>/> ON-->
        <label class="brafton-switch" style="display:inline-block;">
            <input type="checkbox" data-off="0" data-on="1" value="1"<?php checkRadioVal($options['braftonVideoHeaderScript'], 1, 'checked'); ?>>
            <span class="brafton-switch">
                <span class="brafton-switch-on">ON</span>
                <span class="brafton-switch-off">OFF</span>
                <span class="brafton-cursor-switch"></span>
            </span>
        </label>
            <input type="hidden" name="braftonVideoHeaderScript" value="<?php echo $options['braftonVideoHeaderScript']; ?>">
    <?php
    }
    function braftonVideoPlayer(){
        $options = $this->options;
        $tip = "Select the type of Video Player to use.  Video JS is a barebones html5 Player. Atlantis JS is a HTMl5 Video player that uses Jquery and provides support for Call To Action events.";
        $this->tooltip($tip);
        ?>
        <!--<input type="radio" id="embed_type" name="braftonVideoPlayer" value="videojs" <?php checkRadioval($options['braftonVideoPlayer'], 'videojs'); ?> /> Video JS
        <input type="radio" id="atlantis" name="braftonVideoPlayer" value="atlantisjs" <?php checkRadioval($options['braftonVideoPlayer'], 'atlantisjs'); ?>/> Atlantis JS -->
        <select name="braftonVideoPlayer">
            <option value="videojs" <?php checkRadioval($options['braftonVideoPlayer'], 'videojs', 'selected'); ?> > Video JS</option>
            <option value="atlantisjs" <?php checkRadioval($options['braftonVideoPlayer'], 'atlantisjs', 'selected'); ?>> Atlantis JS </option>
        </select>
    <?php

    }
    function braftonVideoOutput(){
        $options = $this->options;
        $tip = 'Output your videos before or after your article text copy.  Turning this option ON will remove the feaured image from your video blog articles on the single article page.  If you wish to display both the featured image and the video you must modify your template directly.';
        $this->tooltip($tip); ?>
    <!--<input type="radio" name="braftonVideoOutput" value="0" <?php checkRadioval(strval($options['braftonVideoOutput']), '0'); ?> /> OFF
    <input type="radio" name="braftonVideoOutput" value="before" <?php	checkRadioval(strval($options['braftonVideoOutput']), 'before'); ?>/> Before Copy
    <input type="radio" name="braftonVideoOutput" value="after" <?php	checkRadioval(strval($options['braftonVideoOutput']), 'after'); ?>/> After Copy-->
        <select name="braftonVideoOutput">
            <option value="0" <?php checkRadioval(strval($options['braftonVideoOutput']), '0', 'selected'); ?> > OFF</option>
            <option value="before" <?php	checkRadioval(strval($options['braftonVideoOutput']), 'before', 'selected'); ?>> Before Copy</option>
            <option value="after" <?php	checkRadioval(strval($options['braftonVideoOutput']), 'after', 'selected'); ?>> After Copy</option>
        </select>
    <?php
    }

    function braftonVideoCTAs(){
        $options = $this->options;
        $tip = "If using Atlantis JS for video playback you can set specific CTA's for when the Video is paused and finished";
        $this->tooltip($tip); ?>
        <div class="b_v_cta">
            <label>Paused CTA Text</label><br/><input type="text" name="braftonVideoCTA[pausedText]" value="<?php echo $options['braftonVideoCTA']['pausedText']; ?>"><br>
            <label>Paused CTA Link</label><br/><input type="text" name="braftonVideoCTA[pausedLink]" value="<?php echo $options['braftonVideoCTA']['pausedLink']; ?>"><br>
            <label>Pause Asset Gateway ID</label><br/><input type="text" name="braftonVideoCTA[pauseAssetGatewayId]" value="<?php echo $options['braftonVideoCTA']['pauseAssetGatewayId']; ?>" /><br>
            <label>Ending CTA Title</label><br/><input type="text" name="braftonVideoCTA[endingTitle]" value="<?php echo $options['braftonVideoCTA']['endingTitle']; ?>"><br>
            <label>Ending CTA Subtitle</label><br/><input type="text" name="braftonVideoCTA[endingSubtitle]" value="<?php echo $options['braftonVideoCTA']['endingSubtitle']; ?>"><br>
            <label>Ending CTA Button Image</label><br/><input type="text" name="braftonVideoCTA[endingButtonImage]" value="<?php echo $options['braftonVideoCTA']['endingButtonImage']; ?>"><input type="button" class="upload_image_button" value="Add Image" data-target="brafton-end-button-preview"><br/>
            <label>Button Position Require 2 coordinates</label><br/>
            <select name="braftonVideoCTA[endingButtonPositionOne]" style="width:90px" class="braftonPositionInput">
                <option value="0"></option>
                <option value="top" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionOne'], 'top', 'selected'); ?>>Top</option>
                <option value="right" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionOne'], 'right', 'selected'); ?> >Right</option>
                <option value="bottom" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionOne'], 'bottom', 'selected'); ?> >Bottom</option>
                <option value="left" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionOne'], 'left', 'selected'); ?> >Left</option>
            </select>
            <input type="number" name="braftonVideoCTA[endingButtonPositionOneValue]" value="<?php echo $options['braftonVideoCTA']['endingButtonPositionOneValue']; ?>" style="width:90px" class="braftonPositionInput"><br/>
            <select name="braftonVideoCTA[endingButtonPositionTwo]" style="width:90px" class="braftonPositionInput">
                <option value="0"></option>
                <option value="top" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionTwo'], 'top', 'selected'); ?>>Top</option>
                <option value="right" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionTwo'], 'right', 'selected'); ?> >Right</option>
                <option value="bottom" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionTwo'], 'bottom', 'selected'); ?> >Bottom</option>
                <option value="left" <?php checkRadioVal($options['braftonVideoCTA']['endingButtonPositionTwo'], 'left', 'selected'); ?> >Left</option>
            </select>
            <input type="number" name="braftonVideoCTA[endingButtonPositionTwoValue]" value="<?php echo $options['braftonVideoCTA']['endingButtonPositionTwoValue']; ?>" style="width:90px" class="braftonPositionInput"><br/>
            <label>Ending CTA Button Text</label><br/><input type="text" name="braftonVideoCTA[endingButtonText]" value="<?php echo $options['braftonVideoCTA']['endingButtonText']; ?>"><br>
            <label>Ending CTA Button Link</label><br/><input type="text" name="braftonVideoCTA[endingButtonLink]" value="<?php echo $options['braftonVideoCTA']['endingButtonLink']; ?>"><br>
            <label>Ending Asset Gateway ID</label><br/><input type="text" name="braftonVideoCTA[endingAssetGatewayId]" value="<?php echo $options['braftonVideoCTA']['endingAssetGatewayId']; ?>" /><br>
            <label>Ending Background Image</label><br/><input type="text" name="braftonVideoCTA[endingBackground]" value="<?php echo $options['braftonVideoCTA']['endingBackground']; ?>"><input type="button" class="upload_image_button" value="Add Image" data-target="brafton-end-background-preview"><br/>
            <div id="v_cta_preview">
                <img src="<?php echo $options['braftonVideoCTA']['endingBackground']; ?>" id="brafton-end-background-preview"><h2 id="brafton-end-title-preview"><?php echo $options['braftonVideoCTA']['endingTitle']; ?></h2><p id="brafton-end-subtitle-preview"><?php echo $options['braftonVideoCTA']['endingSubtitle']; ?></p>
                <?php if($options['braftonVideoCTA']['endingButtonImage'] != ''){ ?>
                <img style="" src="<?php echo $options['braftonVideoCTA']['endingButtonImage']; ?>" id="brafton-end-button-preview"><?php } else{ ?>
                    <a class="ajs-call-to-action-button" href="#"><?php echo $options['braftonVideoCTA']['endingButtonText']; ?></a>
                <?php } ?>
            </div>
        </div>
    <?php
    }

    /*
     ************************************************************************************************ 
     * 
     * Advanced Settings Section
     * 
     * ***********************************************************************************************
     * */
    
     function AdvancedSettingsSetup(){
        //Marpro Tab
            register_setting(
                'brafton_advanced_options', // Option group
                'brafton_advanced' );
            //sets a section name for the options
            add_settings_section(
                'advanced', // ID
                'Advanced Settings', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_advanced' // Page
            );
         add_settings_field(
                'braftonRestyle',
                'Add Premium Styles',
                array($this, 'braftonRestyle'),
                'brafton_advanced',
                'advanced'
            );
         add_settings_field(
                'braftonOpenGraphStatus',
                'Add OG Tags',
                array($this, 'braftonOpenGraphStatus'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonUpdateContent',
                'Update Existing Content',
                array($this, 'braftonUpdateContent'),
                'brafton_advanced',
                'advanced'
            );
         add_settings_field(
                'braftonTags',
                'Tag Options',
                array($this, 'braftonTags'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonCustomTags',
                'Custom Tags',
                array($this, 'braftonCustomTags'),
                'brafton_advanced',
                'advanced'
            );
         
         add_settings_field(
                'braftonArticlePostType',
                BRAFTON_BRAND.' Post Type',
                array($this, 'braftonArticlePostType'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonArticleExistingPostType',
                'Set as Pre-existing Custom Post Type',
                array($this, 'braftonArticleExistingPostType'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonArticleExistingCategory',
                'Choose Pre-existing Custom Category',
                array($this, 'braftonArticleExistingCategory'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonArticleExistingTag',
                'Choose Pre-existing Custom Tag',
                array($this, 'braftonArticleExistingTag'),
                'brafton_advanced',
                'advanced'
            );
            add_settings_field(
                'braftonDebugger', // ID
                'Debug Mode', // Title
                array($this, 'braftonDebugger') , // Callback
                'brafton_advanced', // Page
                'advanced' // Section
            );

    }
    /*
     ************************************************************************************************
     *
     * Marpro Setting Section // Temp renamed to Pumpkin // Final product name ARCH
     *
     ************************************************************************************************
     */
    /*
    function MarproSettingsSetup(){
        //Marpro Tab
            register_setting(
                'brafton_marpro_options', // Option group
                'brafton_marpro' );
            //sets a section name for the options
            add_settings_section(
                'marpro', // ID
                'Arch Settings', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_marpro' // Page
            );
            add_settings_field(
                'braftonMarproStatus',
                'Arch Status',
                array($this, 'braftonMarproStatus'),
                'brafton_marpro',
                'marpro'
            );
            add_settings_field(
                'braftonMarproId',
                'Arch Id',
                array($this, 'braftonMarproId'),
                'brafton_marpro',
                'marpro'
            );
    }
    //function for the marpro section
    function braftonMarproStatus(){
        $options = $this->options;
        $tip = 'Turning on Arch will add our custom script to the footer allowing for connection to your Asset Gateway for your marketing resources';
        $this->tooltip($tip); ?>
        <input type="radio" name="braftonMarproStatus" value="on" <?php	checkRadioval($options['braftonMarproStatus'], 'on'); ?> /> On
        <input type="radio" name="braftonMarproStatus" value="off" <?php checkRadioval($options['braftonMarproStatus'], 'off'); ?>/> Off
    <?php
    }

    /*
     **************************************************************************************************
     *
     * Manual Import Section
     *
     **************************************************************************************************
     */
    function ManualSettingsSetup(){
        //EManual Control Tab
            register_setting(
                'brafton_control_options', // Option group
                'brafton_control' );
            //sets a section name for the options
            add_settings_section(
                'control', // ID
                'Manual Control', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_control' // Page
            );
            add_settings_section(
                'braftonManualImport',
                'Select an Import Option',
                array($this, 'braftonManualImport'),
                'brafton_control',
                'control'
            );
    }

    //Manual Import Settings
    function braftonManualImport(){?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <div class="manual_buttons"><?php submit_button('Import Articles'); ?></div>
        <div class="manual_buttons"><?php submit_button('Import Videos'); ?></div>
        <div class="manual_buttons"><?php submit_button('Get Categories'); ?></div>
        <?php
    }
    function PremiumStylesAtlantisVideoSetup(){
        register_setting(
            'brafton_atlantis_style_options',
            'brafton_atlantis'
        );
        add_settings_section(
            'atlantis',
            'Atlantis Video Player',
            array($this, 'print_section_info'),
            'brafton_atlantis'
        );
        add_settings_field(
            'braftonEnableCustomCSS',
            'Enable Custom CSS Below',
            array($this, 'braftonEnableCustomCSS'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonCustomCSS',
            'Custom CSS rules',
            array($this, 'braftonCustomCSS'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonPauseColor',
            'Pause Text Color',
            array($this, 'braftonPauseColor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndBackgroundcolor',
            'Ending Background Color',
            array($this, 'braftonEndBackgroundcolor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndTitleColor',
            'Ending Title Color',
            array($this, 'braftonEndTitleColor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndTitleBackground',
            'Ending Title Background Color',
            array($this, 'braftonEndTitleBackground'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndTitleAlign',
            'Ending Title Alignment',
            array($this, 'braftonEndTitleAlign'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndSubTitleColor',
            'Ending SubTitle Color',
            array($this, 'braftonEndSubTitleColor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndSubTitleBackground',
            'Ending SubTitle Background Color',
            array($this, 'braftonEndSubTitleBackground'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndSubTitleAlign',
            'Ending SubTitle Alignment',
            array($this, 'braftonEndSubTitleAlign'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndButtonBackgroundColor',
            'Ending Button Color',
            array($this, 'braftonEndButtonBackgroundColor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndButtonTextColor',
            'Ending Text Button Color',
            array($this, 'braftonEndButtonTextColor'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndButtonBackgroundColorHover',
            'Ending Button Color Hover',
            array($this, 'braftonEndButtonBackgroundColorHover'),
            'brafton_atlantis',
            'atlantis'
        );
        add_settings_field(
            'braftonEndButtonTextColorHover',
            'Ending Text Button Color Hover',
            array($this, 'braftonEndButtonTextColorHover'),
            'brafton_atlantis',
            'atlantis'
        );
    }
    function braftonEnableCustomCSS(){
        $options = $this->options;
        $tip = 'When this is on the CSS entered below will be used instead of the options choosen above';
        $this->tooltip($tip); ?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <select name="braftonEnableCustomCSS">
            <option value="1" <?php checkRadioval($options['braftonEnableCustomCSS'], 1, "selected"); ?> > Custom CSS Sheet Below</option>
            <option value="0" <?php checkRadioval($options['braftonEnableCustomCSS'], 0, "selected"); ?>> None</option>
            <option value="2" <?php checkRadioval($options['braftonEnableCustomCSS'], 2, "selected"); ?>> Use Selections Below</option>
        </select>
    <?php
    }
    function braftonEndTitleBackground(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video Title Background.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndTitleBackground" style="width:150px" value="<?php echo $options['braftonEndTitleBackground']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndTitleBackground'])? $color : "#000000"; ?>">
    <?php
    }
    function braftonCustomCSS(){
        $options = $this->options;
        $tip = "Use CSS to style the Video Player.  Any CSS here will override any presets as well as any options selected above";
        $this->tooltip($tip); ?>
        <textarea name="braftonCustomCSS" class="braftonCustomCSS" style="width:100%;height:500px;display:none"><?php echo $options['braftonCustomCSS']; ?></textarea>
    <?php
    }
    function braftonEndButtonTextColorHover(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video button Text on Hover.  You may enter a hex code or use the color picker.";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndButtonTextColorHover" style="width:150px" value="<?php echo $options['braftonEndButtonTextColorHover']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndButtonTextColorHover']) ? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndButtonBackgroundColorHover(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video button Background on Hover.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndButtonBackgroundColorHover" style="width:150px" value="<?php echo $options['braftonEndButtonBackgroundColorHover']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndButtonBackgroundColorHover']) ? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndButtonTextColor(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video button Text.  You may enter a hex code or use the color picker.";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndButtonTextColor" style="width:150px" value="<?php echo $options['braftonEndButtonTextColor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndButtonTextColor']) ? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndButtonBackgroundColor(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video button Background.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndButtonBackgroundColor" style="width:150px" value="<?php echo $options['braftonEndButtonBackgroundColor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndButtonBackgroundColor']) ? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndSubTitleAlign(){
         $options = $this->options;
        $tip = "Choose the Title Text Alignment";
        $this->tooltip($tip); ?>
            <select name="braftonEndSubTitleAlign" style="width:90px" class="braftonEndTitleAlign">
                <option value="0"></option>
                <option value="left" <?php checkRadioVal($options['braftonEndSubTitleAlign'], 'left', 'selected'); ?>>Left</option>
                <option value="center" <?php checkRadioVal($options['braftonEndSubTitleAlign'], 'center', 'selected'); ?> >Center</option>
                <option value="right" <?php checkRadioVal($options['braftonEndSubTitleAlign'], 'right', 'selected'); ?> >Right</option>
            </select>
    <?php
    }
    function braftonEndSubTitleBackground(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video Subtitle Background.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndSubTitleBackground" style="width:150px" value="<?php echo $options['braftonEndSubTitleBackground']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndSubTitleBackground'])? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndSubTitleColor(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video Subtitle Text.  You may enter a hex code or use the color picker.";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndSubTitleColor" style="width:150px" value="<?php echo $options['braftonEndSubTitleColor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndSubTitleColor'])? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndTitleAlign(){
        $options = $this->options;
        $tip = "Choose the Title Text Alignment";
        $this->tooltip($tip); ?>
            <select name="braftonEndTitleAlign" style="width:90px" class="braftonEndTitleAlign">
                <option value="0"></option>
                <option value="left" <?php checkRadioVal($options['braftonEndTitleAlign'], 'left', 'selected'); ?>>Left</option>
                <option value="center" <?php checkRadioVal($options['braftonEndTitleAlign'], 'center', 'selected'); ?> >Center</option>
                <option value="right" <?php checkRadioVal($options['braftonEndTitleAlign'], 'right', 'selected'); ?> >Right</option>
            </select>
    <?php
    }
    function braftonEndTitleColor(){
        $options = $this->options;
        $tip = "Choose the color for the End of Video Title Text.  You may enter a hex code or use the color picker.";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndTitleColor" style="width:150px" value="<?php echo $options['braftonEndTitleColor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndTitleColor'])? $color : "#000000"; ?>">
    <?php
    }
    function braftonEndBackgroundcolor(){
         $options = $this->options;
        $tip = "Choose the color for the End of Video Background.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonEndBackgroundcolor" style="width:150px" value="<?php echo $options['braftonEndBackgroundcolor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonEndBackgroundcolor'])? $color : "#000000"; ?>">
    <?php
    }
    function braftonPauseColor(){
        $options = $this->options;
        $tip = "Choose the color for the Pause CTA.  You may enter a hex code or use the color picker. Enter &ldquo;transparent &ldquo; for no background color";
        $this->tooltip($tip); ?>
        <input type="text" name="braftonPauseColor" style="width:150px" value="<?php echo $options['braftonPauseColor']; ?>" > <input type="color" class="braftonColorChoose" title="ColorPicker" value="<?php echo ($color = $options['braftonPauseColor'])? $color : "#000000"; ?>">
    <?php
    }
    function PremiumStylesArticleSetup(){
        register_setting(
            'brafton_article_style_options',
            'brafton_article_style'
        );
        add_settings_section(
            'article_style',
            'Premium Content Styles',
            array($this, 'print_section_info'),
            'brafton_article_style'
        );
        add_settings_field(
            'braftonPullQuotes',
            'Enable PullQuote Styles',
            array($this, 'braftonPullQuotes'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonPullQuoteWidth',
            'Width of PullQuotes',
            array($this, 'braftonPullQuoteWidth'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonPullQuoteFloat',
            'Pull Quote Float',
            array($this, 'braftonPullQuoteFloat'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonPullQuoteMargin',
            'Pull Quote Margins',
            array($this, 'braftonPullQuoteMargin'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonInlineImages',
            'Enable InlineImage Styles',
            array($this, 'braftonInlineImages'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonInlineImageWidth',
            'Width of InlineImages',
            array($this, 'braftonInlineImageWidth'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonInlineImageFloat',
            'Inline Images Float',
            array($this, 'braftonInlineImageFloat'),
            'brafton_article_style',
            'article_style'
        );
        add_settings_field(
            'braftonInlineImageMargin',
            'Inline Image Margins',
            array($this, 'braftonInlineImageMargin'),
            'brafton_article_style',
            'article_style'
        );
    }
    function braftonInlineImageMargin(){
        $options = $this->options;
        $tip = "Changes the margin of the Inline Images seperating it from the surronding content in pixels.  NOTE: this number should remain low.";
        $this->tooltip($tip); ?>
            <input type="number" name="braftonInlineImageMargin" value="<?php echo $options['braftonInlineImageMargin']; ?>" />
    <?php
    }
    function braftonInlineImageFloat(){
        $options = $this->options;
        $tip = "Float the pullquote to either side";
        $this->tooltip($tip); ?>
        <input type="hidden" name="braftonSettingsPages" value="1">
        <select name="braftonInlineImageFloat" style="width:90px" class="braftonInlineImageFloat">
                <option value="0"></option>
                <option value="left" <?php checkRadioVal($options['braftonInlineImageFloat'], 'left', 'selected'); ?>>Left</option>
                <option value="right" <?php checkRadioVal($options['braftonInlineImageFloat'], 'right', 'selected'); ?> >Right</option>
                <option value="none" <?php checkRadioVal($options['braftonInlineImageFloat'], 'none', 'selected'); ?> >None</option>
            </select>
    <?php
    }
    function braftonInlineImageWidth(){
        $options = $this->options;
        $tip = "Changes the width of the Inline Images in relation to the container in percentage";
        $this->tooltip($tip); ?>
            <input type="number" name="braftonInlineImageWidth" value="<?php echo $options['braftonInlineImageWidth']; ?>" />
    <?php
    }
    function braftonInlineImages(){
        $options = $this->options;
        $tip = 'Enables the correction of Inline Image Styles in your blog posts.  This will affect ALL Brafton Imline Images including CTAs';
        $this->tooltip($tip); ?>
        <input type="radio" name="braftonInlineImages" value="1" <?php	checkRadioval($options['braftonInlineImages'], 1); ?> /> On
        <input type="radio" name="braftonInlineImages" value="0" <?php checkRadioval($options['braftonInlineImages'], 0); ?>/> Off
    <?php
    }
    function braftonPullQuoteMargin(){
        $options = $this->options;
        $tip = "Changes the margin of the pullquote seperating it from the surronding content in pixels.  NOTE: this number should remain low.";
        $this->tooltip($tip); ?>
            <input type="number" name="braftonPullQuoteMargin" value="<?php echo $options['braftonPullQuoteMargin']; ?>" />
    <?php
    }
    function braftonPullQuoteFloat(){
        $options = $this->options;
        $tip = "Float the pullquote to either side";
        $this->tooltip($tip); ?>
        <select name="braftonPullQuoteFloat" style="width:90px" class="braftonPullQuoteFloat">
                <option value="0"></option>
                <option value="left" <?php checkRadioVal($options['braftonPullQuoteFloat'], 'left', 'selected'); ?>>Left</option>
                <option value="right" <?php checkRadioVal($options['braftonPullQuoteFloat'], 'right', 'selected'); ?> >Right</option>
                <option value="none" <?php checkRadioVal($options['braftonPullQuoteFloat'], 'none', 'selected'); ?> >None</option>
            </select>
    <?php
    }
    function braftonPullQuotes(){
        $options = $this->options;
        $tip = 'Enables the correction of PullQuotes Styles in your blog posts';
        $this->tooltip($tip); ?>
        <input type="radio" name="braftonPullQuotes" value="1" <?php	checkRadioval($options['braftonPullQuotes'], 1); ?> /> On
        <input type="radio" name="braftonPullQuotes" value="0" <?php checkRadioval($options['braftonPullQuotes'], 0); ?>/> Off
    <?php
    }
    function braftonPullQuoteWidth(){
        $options = $this->options;
        $tip = "Changes the width of the pullquote in relation to the container in percentage";
        $this->tooltip($tip); ?>
            <input type="number" name="braftonPullQuoteWidth" value="<?php echo $options['braftonPullQuoteWidth']; ?>" />
    <?php
    }
    function SearchSetup(){
        //EManual Control Tab
            register_setting(
                'brafton_search_options', // Option group
                'brafton_search' );
            //sets a section name for the options
            add_settings_section(
                'b_search', // ID
                'Search '.BRAFTON_BRAND.' Content', // Title
                array($this, 'print_section_info'), // Callback
                'brafton_search' // Page
            );
            add_settings_section(
                'SearchFunctionality',
                'Brafton ID',
                array($this, 'SearchFunctionality'),
                'brafton_search',
                'b_search'
            );
    }
    
    function SearchFunctionality(){
        ?>
        <div class="b_search_form">
            <input type="text" name="braf_id" id="braf_id_input" size="50"><input type="submit" value="Find Article" id="findArticleSubmit">
            <div id="b_searchResults">
            
            </div>
        </div>
    <?php
    }
}