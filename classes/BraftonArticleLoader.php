<?php
require BRAFTON_DIR.'libs/ArticleAPILibrary/ApiHandler.php';
// 45b8688e-c6bd-4335-8633-0cbf497b71af
// 528a432c-2f60-4dc8-80fe-4cebc1fe25ca
class BraftonArticleLoader extends BraftonFeedLoader {
    //set as costants instead
    private $API_Domain;
    private $API_Key;
    private $articles;
    private $fh;
    private $counter;
    private $connection;
    

    public function __construct(){
        parent::__construct();
        //set the url and api key for use during the entire run.
        $this->API_Domain = 'https://'.$this->options['braftonApiDomain'];
        $this->API_Key = $this->options['braftonApiKey'];
        if($this->API_Key == 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' || $this->API_Key == ''){
            trigger_error('You have not set your API key');
            $this->fail = true;
            return;
        }
        $this->errors->set_section('Connect to XML Feed @ '.$this->API_Domain.'/'.$this->API_Key);
        $this->connection = new ApiHandler($this->API_Key, $this->API_Domain);
    }
    //method for full import of articles
    public function ImportArticles(){
        if($this->fail){
            return;
        }
        //Gets the feed for importing
        $this->getArticleFeed();
        //Gets the complete category tree and adds any new categories
        $this->ImportCategories();
        //imports each article in the feed
        $msg = $this->runLoop();
        $this->option_ini->saveOption("braftonUpdateContent", 0);
        return $msg;
    }
    public function loadXMLArchive(){
        $this->errors->debug_trace(array('message' => 'Starting Archive Load', 'file' => __FILE__, 'line' => __LINE__));
        if($this->fail){
            return;
        }
		$this->articles = NewsItem::getNewsList($_FILES['archive']['tmp_name'], "html");
        $this->ImportCategories();
        $msg = $this->runLoop();
        echo $msg;
    }
    public function getArticleFeed(){
        $this->errors->debug_trace(array('message' => 'Retrieving Article Feed XML', 'file' => __FILE__, 'line' => __LINE__));
        $this->articles = $this->connection->getNewsHTML();
    }

    static function manualImportCategories() {
        $import = new BraftonArticleLoader();
        $import->ImportCategories();
    }

    static function manualImportArticles() {
        $import = new BraftonArticleLoader();
        $msg = $import->ImportArticles();
        echo $msg;

    }
    static function manualImportArchive() {
        $archive = new BraftonArticleLoader();
        $archive->loadXMLArchive();
    }
    // XML feed only handles 1 level of child categories.
    public function ImportCategories(){
        $this->errors->set_section('Importing Categories');
        $this->errors->debug_trace(array('message' => 'Importing Article Categories', 'file' => __FILE__, 'line' => __LINE__));
        if($this->fail){
            return;
        }
        
        $categoryList = '';
        $CatColl = $this->connection->getCategoryDefinitions();
        $custom_cat = explode(',',$this->options['braftonCustomArticleCategories']);
        // Check for custom category/tag names.
        if( $this->options['braftonArticleExistingPostType'] && $this->options['braftonArticleExistingCategory'] != ''){
            $category_name = $this->options['braftonArticleExistingCategory'];
        } else {
            $category_name = 'category';
        }

        foreach ($CatColl as $c){
            $category = esc_sql($c->getName());
            if($cat_id = get_term_by('name', htmlspecialchars($category), $category_name)){
                $parent = $cat_id->term_id;
            }else{
                $cat_id = wp_insert_term($category, $category_name);
                $parent = $cat_id['term_id'];
            }          
            foreach($c->child as $child){
                $childName = $child['name'];
                $c_id = wp_insert_term($child['name'], $category_name, array('parent' => $parent));
                
            }
        }
        foreach($custom_cat as $cat){
                wp_insert_term($cat, $category_name);
        }
    }


    private function category_search($name, $taxonomy){
        $CatColl = $this->connection->getCategoryDefinitions();
        foreach($CatColl as $cat){
            if($slugObj = get_term_by('slug', $name.'-'.$cat->getName(), $taxonomy)){
                   return $slugObj->term_id;
            }
        }
        $slugObj = get_term_by('slug', $name, $taxonomy);
        return $slugObj->term_id;
    }
    
    //Assigns the categories listed for the post to the post including any custom categories.
    private function assignCategories($obj){
        $loop = $this->errors->get_section();

        $this->errors->set_section('assign categories');
        
        $cats = array();
        $CatColl = $obj->getCategories();
        $custom_cat = explode(',',$this->options['braftonCustomArticleCategories']);

        // Check for custom category name.
        if($this->options['braftonArticleExistingPostType'] && $this->options['braftonArticleExistingCategory'] != ''){
            $category_name = $this->options['braftonArticleExistingCategory'];
        } else {
            $category_name = 'category';
        }

        if($this->options['braftonCategories'] == 'categories'){
            foreach($CatColl as $cat){
                //$slugObj = get_category_by_slug(esc_sql($cat->getName()));
                //$slugObj = get_term_by('slug', esc_sql($cat->getName()), $category_name);
                //$cats[] = $slugObj->term_id;
                $cats[] = $this->category_search(esc_sql($cat->getName()), $category_name);
            }
        }
        foreach($custom_cat as $cat){
            //if($slugObj = get_category_by_slug(esc_sql($cat))){
            if($slugObj = get_term_by('slug', esc_sql($cat), $category_name)) {
                $cats[] = $slugObj->term_id;
            }
        }
        $this->errors->set_section($loop);
        return $cats;
    }
    //Assigns the tags based on the option selected for the importer
    private function assignTags($obj){
        $loop = $this->errors->get_section();
        $this->errors->set_section('assign Tags');

        $tags = array();
        if($this->options['braftonTags'] != 'none_tags'){
            switch($this->options['braftonTags']){
                case 'keywords':
                $TagColl = $obj->getKeywords();
                break;
                case 'cats':
                $TagColl = $obj->getCategories();
                break;
                default:
                $TagColl = $obj->getTags();
                break;
            }
            if(!is_array($TagColl)){
                $TagColl = explode(',', $TagColl);
            }
            foreach($TagColl as $tag){
                $tags[] = esc_sql($tag);
            }
        }
        $custom_tags = explode(',', $this->options['braftonCustomTags']);
        foreach($custom_tags as $tag){
            $tags[] = esc_sql($tag);
        }
        $this->errors->set_section($loop);

        return $tags;
    }
    private function cleanseString($m){
        return "'<' . strtolower('$m')";
    }
    public function runLoop(){
        $this->errors->set_section('Master Article loop');
        $this->errors->debug_trace(array('message' => 'Starting Import of Articles', 'file' => __FILE__, 'line' => __LINE__));
        $list = array();
        global $level, $post, $wp_rewrite;
        $article_count = count($this->articles);
        $counter = 0;
        foreach($this->articles as $article){//start individual article loop
            $brafton_id = $article->getId();
            if(!($post_id = $this->brafton_post_exists($brafton_id)) || $this->override){//Start actual importing
                if($counter == $this->options['braftonArticleLimit']){ return; }
                $this->errors->set_section('Individual article loop. run: '.$counter);
                set_time_limit(60);
                $post_title = $article->getHeadline();

                $post_content = $article->getText();
                //format the content for use with wp
                $post_content = preg_replace_callback('|<(/?[A-Z]+)|', array($this, 'cleanseString'), $post_content);
                $post_content = str_replace('<br>', '<br />', $post_content);
                $post_content = str_replace('<hr>', '<hr />', $post_content);
                $keywords = $article->getKeywords();
                $post_author = $this->checkAuthor($this->options['braftonArticleDynamic'], $article->getByLine());
                $post_status = $this->publish_status;
                $photos = $article->getPhotos();
                $photo_option = 'large';
		        $post_image = NULL;
		        $post_image_caption = NULL;
                if (!empty($photos))
                {
                    $this->errors->debug_trace(array('message' => 'Photos for Article exists', 'file' => __FILE__, 'line' => __LINE__));
                    if ($photo_option == 'large') //Large photo
                        $image = $photos[0]->getLarge();

                    if (!empty($image))
                    {
                        $this->errors->debug_trace(array('message' => 'Found individual Large Photo', 'file' => __FILE__, 'line' => __LINE__));
                        $post_image = $image->getUrl();
                        $post_image_caption = $photos[0]->getCaption();
                        $image_id = $photos[0]->getId();
                        $image_alt = $photos[0]->getAlt();
                    }
                }
                $post_excerpt = ($e = $article->getHtmlMetaDescription())? $e: $article->getExtract();
                $seo_title = ($t = $article->getHtmlTitle())? $t: $post_title;
                $post_excerpt = $post_excerpt == null? '' : $post_excerpt;
                $post_date_array = $this->getPostDate($article);
                $post_date = $post_date_array[1];
                $post_date_gmt = $post_date_array[0];

                $compacted_article = compact('post_author', 'post_date', 'post_content', 'post_title', 'post_status', 'post_excerpt');
                
                // Check for custom category name.
                if($this->options['braftonArticleExistingPostType'] && $this->options['braftonArticleExistingCategory'] != ''){
                    $category_name = $this->options['braftonArticleExistingCategory'];
                } else {
                    $category_name = 'category';
                }
                if($this->options['braftonArticleExistingPostType'] && $this->options['braftonArticleExistingTag'] != ''){
                    $tag_name = $this->options['braftonArticleExistingTag'];
                } else {
                    $tag_name = 'post_tag';
                }

                $the_categories = $this->assignCategories($article);
                $this->errors->set_section('Main article loop');
                $the_tags = $this->assignTags($article);
                $keywords = $article->getKeywords();
                
                $this->errors->set_section('Main article loop');
                if($this->options['braftonArticlePostType']){
                     $type = strtolower(
                        str_replace(' ', '-', 
                                    preg_replace("/[^a-z0-9 ]/i", "",$this->options['braftonCustomSlug'])
                                   )
                    );
                    $compacted_article['post_type'] = $type;
                    
                }
                // Load Brafton articles as pre-existing post type if specified
                elseif($this->options['braftonArticleExistingPostType']) {
                    $compacted_article['post_type'] = $this->options['braftonArticleExistingPostType'];
   
                }    

                $compacted_article['tax_input'] = array($category_name => $the_categories, $tag_name => $the_tags);

                if($post_id){//If the post existed but we are overriding values
                    $this->errors->set_section('Updating Article with ID: '.$post_id);
                    $compacted_article['ID'] = $post_id;
                    $post_id = wp_update_post($compacted_article, true);
                }
                else{//if the post doesn't exists we add it to the database
                    $this->errors->set_section('Inserting New Article');
                    $post_id = wp_insert_post($compacted_article, true);
                    // Extra work to set custom tags.
                    wp_set_object_terms($post_id, $the_tags, $tag_name);
                }
                if(is_object($post_id) && get_class($post_id) == 'WP_Error'){
                    $wp_error_msg = implode(', ',$post_id->error_data);
                    trigger_error($wp_error_msg);
                    continue;
                }
                $meta_array = array(
                    'brafton_id'        => $brafton_id
                );
                if(is_plugin_active('wordpress-seo/wp-seo.php')){
                    $meta_array = array_merge($meta_array, array(
                        '_yoast_wpseo_title'    => $seo_title,
                        '_yoast_wpseo_metadesc' => $post_excerpt,
                        '_yoast_wpseo_metakeywords' => $keywords,
                        '_yoast_wpseo_focuskw'      => $keywords
                    ));
                }
                if(is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php')){
                    $meta_array = array_merge($meta_array, array(
                        '_aioseo_description'  => $post_excerpt,
                        '_aioseo_keywords'     => $keywords,
                        '_aioseo_title'        => $seo_title
                    ));
                }
                $this->add_needed_meta($post_id, $meta_array);

                //update_post_meta($post_id, 'brafton_id', $brafton_id);
                if($post_image != 'NULL' && $post_image != NULL){
                    $temp_name = $this->image_download($post_image, $post_id, $image_id, $image_alt, $post_image_caption);
                    update_post_meta($post_id, 'pic_id', $image_id);
                }

                $list['titles'][] = array(
                    'title' => $post_title,
                    'link'  => "post.php?post={$post_id}&action=edit"
                );

                // Hook for custom plugins passing in WP $post_id and XML $article
                do_action('brafton_article_after_save_hook', $post_id, $article);

                //post meta data
                ++$counter;
                ++$this->errors->level;
            }//end actual importing

        }//end individual article loop
        $list['counter'] = $counter;
        $returnMessage = '';
        $returnMessage .= '<div id="imported-list" style="position:absolute;top:50px;width:50%;left:25%;z-index:9999;background-color:#CCC;padding:25px;box-sizing:border-box;line-height:24px;font-size:18px;border-radius:7px;border:2px outset #000000;">';
        $returnMessage .= '<h3>'.$list['counter'].' Articles Imported</h3>';
        if($list['counter']){
            foreach($list['titles'] as $item => $title){
                $returnMessage .= '<a href="'.$title['link'].'"> VIEW </a> '.$title['title'].'<br/>';
            }
        }
        $returnMessage .= '<a class="close-imported" id="close-imported" style="position:absolute;top:0px;right:0px;padding:10px 15px;cursor:pointer;font-size:18px;">CLOSE</a>';
        $returnMessage .= '</div>';
        return $returnMessage;
    }

}
?>
