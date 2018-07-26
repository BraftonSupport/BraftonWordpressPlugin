<?php
wp_enqueue_style('admin-css.css', BRAFTON_ROOT .'admin/css/_BraftonAdminCSS.css');
wp_enqueue_style('jquery-ui-css', "//code.jquery.com/ui/1.10.1/themes/base/jquery-ui.css", array(), null);
wp_enqueue_media();
wp_enqueue_script('upload_media_widget', BRAFTON_ROOT.'js/upload-media.js', array('jquery'));
wp_enqueue_script('brafton_admin_js', BRAFTON_ROOT .'admin/js/braftonAdmin.js');
wp_enqueue_script('jquery-ui', "//code.jquery.com/ui/1.10.1/jquery-ui.js", array());
$plugin_data = get_plugin_data(BRAFTON_PLUGIN);
//@Issue 64 XSS patch
$tab = isset($_GET['tab'])? (int)$_GET['tab'] : 0;
?>
<script>
tab = <?php echo $tab; ?>;
jQuery(function() {
jQuery( "#tab-cont" ).tabs({
  active: tab
});
jQuery( document ).tooltip();
});
</script>
<?php 
    if(isset($_GET['error'])){

    }
?>
<div class="importer_header" style="display:none;">
    <!--directory from the api image folder-->
    <img src="<?php echo plugin_dir_url(__FILE__); ?>/img/banner_<?php echo strtolower(BRAFTON_BRAND); ?>.jpg">
</div>
<div id="tab-cont" class="tabs">
    <img src="<?php echo plugin_dir_url(__FILE__); ?>/img/banner_<?php echo strtolower(BRAFTON_BRAND); ?>.jpg" style="width:100%;">
    <!-- @Issue 65 permission error on saving for multisites -->
    <form method="post" action="<?php echo get_admin_url(null,'admin.php').'?'.$_SERVER['QUERY_STRING']; ?>" class="braf_options_form" onsubmit="return settingsValidate()">
        <div class="menu-container">
    <ul id="braftonMenuNavigation">
        <li class="<?php echo $admin->status; ?>" style="text-align:center"><a href="#tab-1" class="<?php echo $admin->status; ?>">Status</a></li>
        <li><a href="#tab-2">General Settings</a></li>
        <li><a href="#tab-3">Articles</a></li>
        <li><a href="#tab-4">Videos</a></li>
        <li><a href="#tab-5">Advanced</a></li>
        <li><a href="#tab-6">Archives</a></li>
        <li><a href="#tab-7">Error Logs</a></li>
        <li><a href="#tab-8">Manual Control</a></li>
        <li><a href="#tab-9">Instructions</a></li>
        <?php if($admin->options['braftonRestyle']){ ?>
        <li><a href="#tab-10">Article Styles</a></li>
        <li><a href="#tab-11">Video Styles</a></li>
        <?php } ?>
        <li><a href="#tab-12">ShortCodes</a></li>
        <li><a href="#tab-13">Find Content</a></li>
    </ul>
        </div>
        <?php $admin->render_brafton_admin_page();//render_brafton_admin_page(); ?>
    </form>
</div>
</div>
<div id="imp-details" class="ui-widget ui-widget-content ui-corner-all" style="display:none">
    <h3 class="ui-widget-header"><?php echo BRAFTON_BRAND; ?>  Importer Status</h3>
    <!--Checks for warnings and errors related to the Brafton Importer only-->
    <?php $admin->braftonWarnings(); //braftonWarnings();?>
    <table class="form-table side-info">
        <tr>
            <td>Importer Name</td>
            <td><?php echo $plugin_data['Name'];?></td>
        </tr>
        <tr>
            <td>Importer Version</td>
            <td><?php echo $plugin_data['Version']; ?></td>
        </tr>
        <tr>
            <td>Author</td>
            <td><?php echo $plugin_data['AuthorName']; ?></td>
        </tr>
        <tr>
            <td>Support URL</td>
            <td><a href="<?php echo $plugin_data['PluginURI']; ?>">Brafton.com</a></td>
        </tr>
    </table>
    
</div>
<?php if($_GET['page'] == 'BraftonArticleLoader'){
    add_action('admin_footer_text', 'brafton_custom_footer');
    function brafton_custom_footer(){
        echo '<div>Thank You for choosing <a href="http://www.'.BRAFTON_BRAND.'.com" target="_blank">'.BRAFTON_BRAND.'</a> for your Content Marketing Needs</div>';
    }
}
?>