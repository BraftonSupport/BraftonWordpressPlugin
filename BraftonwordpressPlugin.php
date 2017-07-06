<?php
/*
	Plugin Name: Content Importer
	Plugin URI: http://www.brafton.com/support/wordpress
	Description: Wordpress Plugin for Importing marketing content from Brafton, ContentLEAD, and Castleford Media Corp.  Support in line content, dynamic Authors, Updating and Error reporting. video requires php 5.3 or higher.
	Version: 3.4.12
    Requires: 3.5
	Author: Brafton, Inc.
	Author URI: http://www.brafton.com/support/wordpress
    Domain Path: http://www.brafton.com
    //@author: Deryk W. King <deryk.king@brafton.com>

*/

if( class_exists( 'XMLHandler' ) ){
        echo '<div class="error">
				<p><strong>CONTENT IMPORTER</strong>: You may have another Brafton, ContentLEAD, or Castleford Content Importer Installed.  Please Disable your previous version before activating your new one.<br/><blockquote>XMLHandler and XMLException Classes already declared indicating a previous version is installed.</blockquote></p>
				</div>';
    exit();
}

define("BRAFTON_VERSION", '3.4.12');

define("BRAFTON_ROOT", plugin_dir_url(__FILE__));

define("BRAFTON_PLUGIN", dirname(__FILE__).'/BraftonwordpressPlugin.php');

define("BRAFTON_BASENAME", plugin_basename(__FILE__));

define("BRAFTON_DIR", dirname(__FILE__).'/');

define("BRAFTON_BASE_URL", "http://updater.brafton.com/");
//define("BRAFTON_BASE_URL", "http://staging.updater.brafton.com/");
//define("BRAFTON_BASE_URL", "http://localtest.updater.com/");

define("BRAFTON_ERROR_KEY", "ucocfukkuineaxf2lzl3x6h9");

include_once 'BraftonIncludes.php';

class BraftonWordpressPlugin {
    /*
     *All these variables are only used within this class however this class is instantiated in each method of itself
     *
     */
    //Loads all the options into an array
    public $options;

    public function __construct(){

        if(version_compare(get_option('BraftonVersion', 0), BRAFTON_VERSION, '!=')){
            add_action('admin_notices', array($this, 'BraftonUpdatedPluginNotification'));
            BraftonOptions::ini_BraftonOptions();
        }
        //fires when the plugin is activated
        register_activation_hook(__FILE__, array($this, 'BraftonActivation'));

        //fires when the plugin is deactivated
        register_deactivation_hook(__FILE__, array($this, 'BraftonDeactivation'));

        if(!function_exists('curl_init') || !class_exists('DOMDocument') ){
            add_action('admin_init', array($this, 'BraftonAutoDeactivate'));
        }

        if(function_exists('is_multisite') && is_multisite()){
            add_action('wpmu_new_blog', array($this, 'BraftonMultisiteActivation'), 10,6);
        }
        //enable Featured Images if it isn't already
        if(!current_theme_supports('post-thumbnails')){
            add_theme_support('post-thumbnails');
        }

        $init_options = new BraftonOptions();
        $this->options = $init_options->getAll();

        define('BRAFTON_BRAND', switchCase($this->options['braftonApiDomain']));

        //Adds our needed hooks
        add_action('wp_head', array($this, 'BraftonOpenGraph'));
        add_action('wp_head', array($this, 'BraftonVideoHead'));
        add_action('wp_footer', array($this, 'BraftonRestyle'));
        add_action('admin_menu', array($this, 'BraftonAdminMenu'));
        add_action('braftonSetUpCron', array($this, 'BraftonCronArticle'));
        add_action('braftonSetUpCronVideo', array($this, 'BraftonCronVideo'));
        add_action('init', array($this, 'brafton_activate_updater'));
        if(is_admin() && isset($_GET['page']) && ($_GET['page'] == 'BraftonArticleLoader' || $_GET['page'] == 'BraftonPremiumStyles') ){
            add_action('init', array('BraftonAdministration','saveSettings'));
        }
        add_action('wp_dashboard_setup', array($this, 'BraftonDashboardWidget'));
        add_action('admin_notices', array($this, 'BraftonNotices'));
        add_action('wp_enqueue_scripts', array($this, 'BraftonScripts'));
        add_action('wp_ajax_loadAtlantisCss', array($this, 'loadAtlantisCss'));
        add_action( 'wp_ajax_health_check', array('BraftonAdministration', 'health_check'));
        add_action('wp_ajax_getBraftonArticles', array('BraftonAdministration', 'getBraftonArticles'));

        //conditional upon enabling the option
        if($this->options['braftonArticlePostType']){
            add_action('init', array('BraftonCustomType', 'BraftonInitializeType'));
            add_action('widgets_init', array('BraftonWidgets', 'CustomTypeCategory'));
            add_action('widgets_init', array('BraftonWidgets', 'CustomTypeDateArchives'));
        }

        if($this->options['braftonRemoteOperation']){
            add_action('wp_head', array($this, 'RemoteOperation'));
        }

        if((bool)$this->options['braftonMarproId']){
            $marpro = new BraftonMarpro();
        }

        //Adds our needed filters
        add_filter('language_attributes', array($this, 'BraftonOpenGraphNamespace'), 100);
        add_filter('the_content', array($this, 'BraftonContentModifyVideo'));
        add_filter('plugin_row_meta', array($this, 'BraftonPluginMeta'), 10, 2);
        add_filter('plugin_action_links_'.BRAFTON_BASENAME, array($this, 'BraftonPluginLinks'));
        add_filter( 'xmlrpc_methods', array($this, 'BraftonXMLRPC' ));
        add_filter('post_thumbnail_html', array($this, 'BraftonRemoveFeatured'), 10, 5);
    }
    static function BraftonAutoDeactivate(){
        //Deactivate the plugin due to missing dependancies.
        deactivate_plugins(plugin_basename(__FILE__));
        $list[] = function_exists('curl_init')? '' : 'function curl_init()';
        $list[] = function_exists('fopen') && ini_get('allow_url_fopen') ? '' : 'function fopen() with allow_url_fopen set to on or 1 (VIDEO IMPORT ONLY)';
        $list[] = class_exists('DOMDocument')? '' : 'Class DOMDocument';

        //strinify list of dependancies missing filtering out any empty values indicating the dependancy does exist.
        $list = array_diff($list, array(''));
        $missing = implode(', ', $list);

        echo '<div class="error">
				<p>Your Content Importer for Brafton, ContentLEAD, and Castleford Content has been disabled due to the missing dependancies.Ensure '.$missing.' are enabled on your server.</p>
				</div>';

    }
    static function BraftonUpdatedPluginNotification(){
        //use this function to register what changes were made and direct the user to check out the new features.
        echo "<div class='brafton".BRAFTON_VERSION."updateFeatures-notice notice error is-dismissible'>
                <p style='width:85%'>Updated Importer: Check out the new features.</p>
                </div>";
    }
    private function _BraftonActivation(){
        $option_init = BraftonOptions::ini_BraftonOptions();

        if(BraftonOptions::getSingleOption('braftonArticleStatus')){
            //importer is set to go off 2 minutes after it is enabled than hourly after that
            $schedule = wp_schedule_event(time()+120, 'hourly', 'braftonSetUpCron');
        }
        if(BraftonOptions::getSingleOption('braftonVideoStatus')){
            //importer is set to go off 2 minutes after it is enabled than daily after that
            $schedule = wp_schedule_event(time()+120, 'twicedaily', 'braftonSetUpCronVideo');
        }
    }
    public function BraftonActivation($network){
        global $wpdb;
        if(function_exists('is_multisite') && is_multisite()){
            if($network){
                $main_blog = $wpdb->blogid;
                $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                foreach($blogids as $blog_id){
                    switch_to_blog($blog_id);
                    $this->_BraftonActivation();
                }
                switch_to_blog($main_blog);
                return;
                }
            }
        $this->_BraftonActivation();
    }

    public function BraftonDeactivation(){
        wp_clear_scheduled_hook('braftonSetUpCron');
        wp_clear_scheduled_hook('braftonSetUpCronVideo');
    }
    public function BraftonMultisiteActivation($blod_id, $user_id, $domain, $path, $site_id, $meta){
        if(is_plugin_active_for_network(BRAFTON_PLUGIN)){
            switch_to_blog($blog_id);
            $this->BraftonActivation();
            restore_current_blog();
        }
    }
    static function brafton_activate_updater(){
        $brand = BraftonOptions::getSingleOption('braftonApiDomain');
        $brafton_plugin_remote_path = 'http://updater.brafton.com/u/wordpress/update/';
        new Brafton_Update(BRAFTON_VERSION, $brafton_plugin_remote_path, BRAFTON_BASENAME, $brand);
    }
    static function BraftonPluginMeta($links, $file){
        if($file == plugin_basename(__FILE__)){
            $admin = admin_url('/', 'admin');
            $links[] = '<a href="'.$admin.'admin.php?page=BraftonArticleLoader">Settings</a>';
            //$links[] = '<a href="http://localhost/wp_feature/wp-content/plugins/BraftonWordpressPlugin/ImporterInstructions.pdf" target="_blank">Instructions</a>';
        }
        return $links;
    }
    static function BraftonPluginLinks($links){
        $admin = admin_url('/', 'admin');
        $links[] = '<a href="'.$admin.'admin.php?page=BraftonArticleLoader">Settings</a>';
        return $links;
    }
    static function BraftonDashboardWidget(){
        $brand = BraftonOptions::getSingleOption('braftonApiDomain');
        $brand = switchCase($brand);
        wp_add_dashboard_widget('BraftonDashAtAGlance', 'Recently Imported by '.$brand, array('BraftonWordpressPlugin','BraftonDisplayDashWidget'));
    }
    static function BraftonDisplayDashWidget(){
        $brand = BraftonOptions::getSingleOption('braftonApiDomain');
        $brand = switchCase($brand);
        $array = array(
            'meta_key'  => 'brafton_id',
            'posts_per_page'    => 5
        );
        $query = new WP_Query($array);
        ?>
        <img src="<?php echo plugin_dir_url(__FILE__); ?>/admin/img/banner_<?php echo strtolower($brand); ?>.jpg" style="width:100% !important; height:auto !important;">
        <?php
        if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
            echo '<p>';
            echo '<a href="'.get_edit_post_link().'">'.get_the_title(); echo '</a><br/> Imported on: '; the_time('Y-d-m');
            echo '</p>';
        endwhile;endif;
    }

    static function BraftonRemoveFeatured($html, $post_id, $post_thumbnail_id, $size, $attr){

        if(is_single() && BraftonOptions::getSingleOption('braftonVideoOutput') && ( $meta=get_post_meta($post_id, "brafton_video", true) ) ){
            return '';
        }
        return $html;
    }
    static function BraftonContentModifyVideo($content){

        if(is_single()){
            $ops = new BraftonOptions();
            $static = $ops->getAll();
            if($static['braftonVideoOutput']){
                if($meta=get_post_meta(get_the_ID(), "brafton_video", true)){
                    $micro = get_post_meta(get_the_ID(), "brafton_video_microdata", true)!= NULL ? get_post_meta(get_the_ID(), "brafton_video_microdata", true) : " ";
                    $content = $static['braftonVideoOutput'] == 'after'? $content . $meta . $micro: $micro . $meta . $content;
                }
            }
        }

        return $content;
    }

    public function BraftonAdminMenu(){
        $brand = BraftonOptions::getSingleOption('braftonApiDomain');
        $brand = switchCase($brand);
        //new admin menu
        /*
        add_menu_page('Brafton Article Loader', "{$brand} Content Importer", 'activate_plugins','BraftonArticleLoader', 'admin_page','dashicons-download');
        add_submenu_page('BraftonArticleLoader', 'Brafton Article Loader', 'General Options', 'activate_plugins', 'BraftonArticleLoader', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Article Options', 'Article Options', 'activate_plugins', 'BraftonArticleLoader&tab=1', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Video Options', 'Video Options', 'activate_plugins', 'BraftonArticleLoader&tab=2', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Arch Options', 'Arch Options', 'activate_plugins', 'BraftonArticleLoader&tab=3', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Archives', 'Archives', 'activate_plugins', 'BraftonArticleLoader&tab=4', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Error Logs', 'Error Logs', 'activate_plugins', 'BraftonArticleLoader&tab=5', 'admin_page');
        add_submenu_page('BraftonArticleLoader', 'Run Importers', 'Run Importers', 'activate_plugins', 'BraftonArticleLoader&tab=6', 'admin_page');
        if(BraftonOptions::getSingleOption('braftonRestyle')){
            add_submenu_page('BraftonArticleLoader', 'Premium Styles', 'Premium Styles', 'activate_plugins', 'BraftonPremiumStyles', 'style_page');
        }
        */
        add_menu_page('Brafton Article Loader', "{$brand} Content Importer", 'activate_plugins','BraftonArticleLoader', array('BraftonAdministration','adminInitialize'),'dashicons-download');
        add_submenu_page('BraftonArticleLoader', 'Brafton Article Loader', 'Status', 'activate_plugins', 'BraftonArticleLoader', array('BraftonAdministration','adminInitialize'));
        add_submenu_page('BraftonArticleLoader', 'Brafton Article Loader', 'General Options', 'activate_plugins', 'BraftonArticleLoader&tab=1', array('BraftonAdministration','adminInitialize'));
        add_submenu_page('BraftonArticleLoader', 'Article Options', 'Article Options', 'activate_plugins', 'BraftonArticleLoader&tab=2', array('BraftonAdministration','adminInitialize'));
        add_submenu_page('BraftonArticleLoader', 'Video Options', 'Video Options', 'activate_plugins', 'BraftonArticleLoader&tab=3', array('BraftonAdministration','adminInitialize'));
    }
    static function BraftonRestyle(){
        $ops = new BraftonOptions();
        $static = $ops->getAll();
        $restyle = $static['braftonRestyle'];
        if($restyle && is_single()){
            $p_width = $static['braftonPullQuoteWidth'];
            $p_float = $static['braftonPullQuoteFloat'];
            $p_margin = $static['braftonPullQuoteMargin'];
            $i_width = $static['braftonInlineImageWidth'];
            $i_float = $static['braftonInlineImageFloat'];
            $i_margin = $static['braftonInlineImageMargin'];
            $pullQuote = '';
            $inlineImage = '';
            $js = '';
            if($static['braftonPullQuotes']){
                $pullQuote = "'width': '{$p_width}%', 'float': '{$p_float}', 'margin': '{$p_margin}px'";
                $js .=<<<EOPQ
	//PULLQUOTE CORRECTION
	jQuery('.pullQuoteWrapper').each(function(){
        jQuery(this).css({{$pullQuote}});
	});
EOPQ;
            }
            if($static['braftonInlineImages']){
                $inlineImage = "'width': '{$i_width}%', 'float': '{$i_float}', 'margin': '{$i_margin}px'";
                $js .=<<<EOIL
	//INLINE IMAGE WRAPPER
	jQuery('.inlineImageWrapper').each(function(){
        jQuery(this).css({{$inlineImage}});
	});
EOIL;
            }
            $restyle =<<<EOC
            <script type="text/javascript">
            (function(d){
                $js
}(document));
        </script>
EOC;
            echo $restyle;
        }
    }
    //Static Article Cron Job **Goes off every hour
    static function BraftonCronArticle(){
        $import = new BraftonArticleLoader();
        $import->ImportArticles();
    }
    //Static Video Cron job **Goes off every day
    static function BraftonCronVideo(){
        $import = new BraftonVideoLoader();
        $import->ImportVideos();
    }
    //used to clear out brafton cron job add action for init
    static function BraftonXMLRPC($methods){
        $methods[ 'braftonImportRPC' ] = array('BraftonXMLRPC', 'RemoteOperation');
        return $methods;
    }
    //used to get the url for og:url tags
    static function BraftonCurlPageURL(){
    	$pageURL = 'http';

        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER["HTTPS"]) == "on")
            $pageURL .= "s";

        $pageURL .= "://";

        if ($_SERVER["SERVER_PORT"] != "80")
            $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        else
            $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];

        return $pageURL;
    }
    static function BraftonOpenGraph(){
        $static = BraftonOptions::getSingleOption('braftonOpenGraphStatus');
        if (!is_single() || (!$static))
            return;

        global $post;
        $tags = array(
            'og:type' => 'article',
            'og:site_name' => get_bloginfo('name'),
            'og:url' => BraftonWordpressPlugin::BraftonCurlPageURL(),
            'og:title' => preg_replace('/<.*?>/', '', get_the_title()),
            'og:description' => htmlspecialchars(preg_replace('/<.*?>/', '', get_the_excerpt())),
            'og:image' => wp_get_attachment_url(get_post_thumbnail_id($post->ID)),
            'article:published_time' => date('c', strtotime($post->post_date))
        );
        $twitter = array(
            'twitter:card'  => 'summary_large_image',
            'twitter:title' => preg_replace('/<.*?>/', '', get_the_title()),
            'twitter:description'   => htmlspecialchars(preg_replace('/<.*?>/', '', get_the_excerpt())),
            'twitter:image' =>  wp_get_attachment_url(get_post_thumbnail_id($post->ID))
        );
        $google = array(
            'name'  => preg_replace('/<.*?>/', '', get_the_title()),
            'description'   => htmlspecialchars(preg_replace('/<.*?>/', '', get_the_excerpt())),
            'image' => wp_get_attachment_url(get_post_thumbnail_id($post->ID))
        );

        $tagsHtml = '';
        foreach($google as $tag => $content)
            $tagsHtml .= sprintf('<meta itemprop="%s" content="%s" />', $tag, $content) . "\n";
        foreach($twitter as $tag => $content)
            $tagsHtml .= sprintf('<meta name="%s" content="%s" />', $tag, $content) . "\n";

        foreach ($tags as $tag => $content)
            $tagsHtml .= sprintf('<meta property="%s" content="%s" />', $tag, $content) . "\n";

        echo trim($tagsHtml);
    }
    static function BraftonOpenGraphNamespace($content){
    	$namespaces = array(
		  'xmlns:og="http://ogp.me/ns#"',
		  'xmlns:article="http://ogp.me/ns/article#"'
	   );

	   foreach ($namespaces as $ns){
		  if (strpos($content, $ns) === false) // don't add attributes twice
			 $content .= ' ' . $ns;
       }
	   return trim($content);
    }
    static function loadAtlantisCss(){
        require_once BRAFTON_DIR."css/atlantis.css.php";
        wp_die();
    }
    static function BraftonScripts(){
        $ops = new BraftonOptions();
        $static = $ops->getAll();

        if($static['braftonImportJquery'] == 'on'){
            wp_enqueue_script('brafton-jquery', "//ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js", array(), false);
        }

        if($static['braftonVideoHeaderScript']){
            $player = $static['braftonVideoPlayer'];
            $videojs = '//vjs.zencdn.net/4.3/video.js';
            $videocss = '//vjs.zencdn.net/4.3/video-js.css';
            $atlantisjs = 'https://atlantisjs.brafton.com/v1/atlantis.min.v1.3.js';
            $atlantiscss = 'https://atlantisjs.brafton.com/v1/atlantisjsv1.4.css';
            wp_enqueue_script('brafton-video-playback', $$player, array(), null);
            wp_enqueue_style('brafton-video-css', ($player == "atlantisjs"? $atlantiscss : $videocss), array(),null);

        }
        if((int)$static['braftonEnableCustomCSS'] == 2 && $static['braftonRestyle']){
            wp_enqueue_style('brafton-atlantis-custom', admin_url('admin-ajax.php')."?action=loadAtlantisCss", array(), null);
        }
    }

    static function BraftonVideoHead(){
        $ops = new BraftonOptions();
        $static = $ops->getAll();

        $braftonCustomCSS = $static['braftonCustomCSS'];

        if((int)$static['braftonEnableCustomCSS'] == 1 && $static['braftonRestyle']){
            echo $braftonCustomCSS;
        }
    }

    static function RemoteOperation(){
        $ops = new BraftonOptions();
        $static = $ops->getAll();
        if($static['braftonRemoteTime'] + 21600 > current_time('timestamp')){
            return;
        }
        $ops->saveOption('braftonRemoteTime', current_time('timestamp'));
        $remoteUrl = BRAFTON_BASE_URL."/wp-remote/remote.php?";
        $siteUrl = site_url();
        $functions = $static['braftonArticleStatus'] ? 'articles' : '';
        $functions .= $functions == 'articles' && ($static['braftonVideoStatus']) ? ',' : '';
        $functions .= $static['braftonVideoStatus'] ? 'videos' : '';
        $fullUrl = $remoteUrl . 'clientUrl=' . $siteUrl . '&function=' .$functions;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_exec($ch);
    }
    static function BraftonNotices(){
        //Notify user there is an update available
        $brand = BraftonOptions::getSingleOption('braftonApiDomain');
        $brand = switchCase($brand);
        $url = BRAFTON_BASE_URL."/u/wordpress/update/version/";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        $version = curl_exec($ch);

        if(version_compare(BRAFTON_VERSION, $version, '<')){
            echo "<div class='brafton".BRAFTON_VERSION."updateAvailable-notice notice error'>
                <p style='width:85%'><strong>$brand Content Importer: </strong> An Update is available for your Content Importer.  You are on version ".BRAFTON_VERSION." while version $version is available for download. Please <a href='plugins.php?plugin_status=upgrade'><strong>UPDATE</strong> your plugin</a>.</p>
                </div>";
        }
    }
}
$initialize_Brafton = new BraftonWordpressPlugin();

?>
