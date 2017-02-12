<?php

/*
Plugin Name: Video Importer
Plugin URI: https://github.com/hirenwadhiya/Video-Importer
Description: This plugin will import all the videos of a youtube channel as a wordpress post.
Version: 1.0
Author: Hiren Wadhiya
Author URI: http://en.gravatar.com/hirenwadhiya
*/

error_reporting(0);

function yp_youtube_post_init(){
  register_setting('yp_youtube_post_options','youtubepost');
}
add_action('admin_init','yp_youtube_post_init');

function yp_youtube_post_create_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . "yp_youtube_post";
    if($wpdb->get_var('SHOW TABLES LIKE '.$table_name) != $table_name){
        $yp_query = 'CREATE TABLE ' .$table_name.' (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            channel_id VARCHAR(100) NOT NULL,
            api_key VARCHAR(100) NOT NULL);';

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta( $yp_query );    
    }
}
add_action('admin_init','yp_youtube_post_create_table');

function yp_youtube_post_options_page(){
    global $wpdb;
    $table_name = $wpdb->prefix . "yp_youtube_post";

    ?>
        <div class="youtube_post">
            <h2>Youtube Importer Options</h2>
            <form action="" method="post">
                <span><b>Channel Id :</b></span> www.youtube.com/channel/<input type="text" name="channel" placeholder="channel id"/> &nbsp;&nbsp;
                <span><b>API Key :</b></span><input type="text" name="apikey" placeholder="api key"/> &nbsp;&nbsp;
                <span><b>Post Status :</b> </span> 
                <select id="status" name="status">
                  <option value="">-Select-</option>
                  <option value="publish">Publish</option>
                  <option value="draft">Draft</option>
                  <option value="private">Private</option>
                </select>
                <?php submit_button( 'Submit' ); ?>
            </form> 
            <b>Note:</b> <span>Set your latest post as front page. </span>
            <br/> 
            <b>For API Key </b><span>visit :<a href="https://console.developers.google.com/" target="_blank">Google API Console</a></span> 
        </div>
    <?php
}

function yp_youtube_post_json_data(){
  global $wpdb;
  $table_name = $wpdb->prefix . "yp_youtube_post";

  if (($_REQUEST['channel'] && $_REQUEST['apikey'])!="") {
          $wpdb->insert( $table_name, array(
            'channel_id' => $_REQUEST['channel'] ,
            'api_key' => $_REQUEST['apikey']), array( '%s','%s' ) );
          $lastid = $wpdb->insert_id;
        }
  
  $api_key = $wpdb->get_var('SELECT api_key FROM '.$table_name.' WHERE id = '.$lastid.'');
  $channel_id = $wpdb->get_var('SELECT channel_id FROM '.$table_name.' WHERE id = '.$lastid.'');

  $i = 0;
  $pageToken = '';

  for($exit = false; $exit == false;) {
    if ($pageToken != "") {
      $pageToken = "&pageToken=".$pageToken;
    }

    $jsondata = file_get_contents("https://www.googleapis.com/youtube/v3/search?key=$api_key&channelId=$channel_id&part=snippet,id&order=date&maxResults=50" . $pageToken);
    $json = json_decode($jsondata,true);
    $items = $json['items'];

    foreach($items as $item){ 
      $title = $item['snippet']['title'];
      $description = $item['snippet']['description'];
      $video_id = $item['id']['videoId'];
      $video = '<iframe width="854" height="480" src="https://www.youtube.com/embed/'.$video_id.'" frameborder="0" allowfullscreen></iframe>';

      $query = $wpdb->prepare(
             'SELECT ID FROM wp_posts WHERE post_title = %s AND post_type = "post"', $title
             );
      $wpdb->query( $query );

      if ( $wpdb->num_rows ) {
        $post_id = $wpdb->get_var( $query );
        $meta = get_post_meta( $post_id, 'times', TRUE );
        $meta++;
        update_post_meta( $post_id, 'times', $meta );
      } else {
        $new_post = array(
        'post_title' => $title,
        'post_content' => $description."<br>".$video,
        'post_type' => 'post',
        'post_author' => '1',
        'post_category' => '1',
        'post_status' => $_REQUEST['status']
        );
        $post_id = wp_insert_post( $new_post, $wp_error );
        add_post_meta($post_id, 'times', '1');
     }
    }
    if (isset($json['nextPageToken'])) {
      $pageToken = $json['nextPageToken'];
    } else {
      $exit = true;
    }
  }
}
add_action('admin_menu','yp_youtube_post_json_data');

function yp_youtube_post_plugin_menu() {
    add_options_page('Youtube Video Importer Settings','Video Importer','manage_options','youtube_importer','yp_youtube_post_options_page');
}
add_action('admin_menu','yp_youtube_post_plugin_menu');