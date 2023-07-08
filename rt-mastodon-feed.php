<?php
/**
 * Plugin Name: RT Mastodon Feed
 * Plugin URI: https://github.com/SethRobinson/rt-mastodon-feed
 * Description: A simple widget for Wordpress that displays a Mastodon feed. Uses SimplePie.
 * Version: 1.0.0
 * Author: Seth A. Robinson
 * Author URI: https://rtsoft.com/
 * License: MIT
 */

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);



if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/php/autoloader.php')) {
    function rt_mastodon_feed_error_notice() {
        ?>
        <div class="error notice">
            <p><?php _e( 'RT Mastodon Feed Plugin requires the SimplePie library to be installed. Please install it first.', 'text_domain' ); ?></p>
        </div>
        <?php
        deactivate_plugins(plugin_basename(__FILE__));
    }
    add_action( 'admin_notices', 'rt_mastodon_feed_error_notice' );
    return; // Exit early
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/php/autoloader.php');


class RT_Mastodon_Feed_Widget extends WP_Widget
{

    function __construct()
	{
        parent::__construct(
            'rt_mastodon_feed_widget',
            __('RT Mastodon Feed Widget', 'text_domain'),
            array('description' => __( 'A widget that displays your Mastodon feed', 'text_domain' ))
        );
    }


    public function widget($args, $instance)
	{
        echo $args['before_widget'];

		// Fetch the settings from the database
        $feedUrl = get_option('mastodon_feed_url');
        $feedUrlRss =$feedUrl.".rss";
          
	    $profilePicUrl = get_option('mastodon_profile_pic_link');
        $profilePicLink = $feedUrl;
		
        $feedMast = new SimplePie();
        $feedMast->set_feed_url($feedUrlRss);
        $feedMast->set_cache_location($_SERVER['DOCUMENT_ROOT'] . '/cache');
        $cacheEnabled = get_option('mastodon_cache_enabled', 0);
		$feedMast->enable_cache($cacheEnabled);
        $feedMast->init();

		//$profilePicUrl = 'https://cdn.masto.host/mastodongamedevplace/accounts/avatars/110/672/077/339/445/959/original/6a25cddafe093b73.png';
		
	
      echo '<style>
.mastodon-toot {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 10px;
}
.mastodon-date {
  font-weight: bold;
    margin-bottom: 10px;
    font-size: calc(1.2vw); 
}
.mastodon-title {
    color: #333;
}
.mastodon-content {
    color: #555;
}
.mastodon-media img, .mastodon-media video {
  max-width: 100%;
  height: 240px;
  display: block;
  margin-left: auto;
  margin-right: auto;
}
</style>';

		// Fetch the option value
		$pre_toots_html = get_option('mastodon_pre_toots_html');

		// Check if the option has been set and isn't empty
		if (!empty($pre_toots_html)) {
			// The option is set and isn't empty, so let's output it
			echo $pre_toots_html;
		}
			
        if ($feedMast->data) 
		{ 
			$max_to_get = get_option('mastodon_max_feed_items') ?: 10; // fallback to 10 if option is not set
  
            $max = $feedMast->get_item_quantity($max_to_get);
           
            $authorName = $feedMast->get_title(); 

			// Fetch the profile picture URL once from the feed
			
            for ($x = 0; $x < $max; $x++) 
			{
                $item = $feedMast->get_item($x);

                $date = $item->get_date('n/j/Y');
                $link = $item->get_permalink();
                $content = $item->get_content();

                echo '<div class="mastodon-toot">';
               echo '<div class="mastodon-date"><a rel="me" href="'.$profilePicLink.'"><img src="'.$profilePicUrl.'" alt="Profile Picture" style="height: 20px; width: 20px;"></a><a href="'.$link.'"> '.$authorName.' on '.$date.'</a></div>';
			echo '<div class="mastodon-content">'.$content.'</div>';

                $enclosure = $item->get_enclosure();
                if ($enclosure) 
				{
                    $mediaUrl = $enclosure->get_link();
                    $mediaType = $enclosure->get_type();

                    echo '<div class="mastodon-media">';
                    if (strpos($mediaType, 'image') !== false)
					{
                        echo '<a href="' . $mediaUrl . '" target="_blank"><img src="'.$mediaUrl.'" alt="Mastodon image" class="media"></a>';
                    } elseif (strpos($mediaType, 'video') !== false)
					{
                        echo '<video controls width="100%" height="240"  src="'.$mediaUrl.'">Your browser does not support the video tag.</video>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            } 
        }

        echo $args['after_widget'];
    }
}



function rt_register_mastodon_feed_widget() 
{
    register_widget( 'RT_Mastodon_Feed_Widget' );
}
add_action( 'widgets_init', 'rt_register_mastodon_feed_widget' );


// Create custom plugin settings menu
add_action('admin_menu', 'rt_mastodon_feed_widget_create_menu');

function rt_mastodon_feed_widget_create_menu()
 {
add_menu_page('RT Mastodon Feed Widget Settings', 'RT Mastodon Feed Settings', 'administrator', __FILE__, 'rt_mastodon_feed_widget_settings_page', 'dashicons-admin-generic');
    //call register settings function
    add_action( 'admin_init', 'rt_register_mastodon_feed_widget_settings' );
	
}


function rt_register_mastodon_feed_widget_settings() 
{
    //register our settings
    register_setting( 'rt-mastodon-feed-widget-settings-group', 'mastodon_feed_url' );
    register_setting( 'rt-mastodon-feed-widget-settings-group', 'mastodon_profile_pic_link' );
	register_setting( 'rt-mastodon-feed-widget-settings-group', 'mastodon_max_feed_items' ); // this line
	register_setting( 'rt-mastodon-feed-widget-settings-group', 'mastodon_cache_enabled' );
	register_setting( 'rt-mastodon-feed-widget-settings-group', 'mastodon_pre_toots_html' );
	
	
	
}

register_activation_hook( __FILE__, 'rt_set_default_options_mastodon_feed' );

function rt_set_default_options_mastodon_feed()
{
    $default_options = array(
        'mastodon_feed_url' => 'https://mastodon.gamedev.place/@rtsoft',
        'mastodon_profile_pic_link' => 'https://cdn.masto.host/mastodongamedevplace/accounts/avatars/110/672/077/339/445/959/original/6a25cddafe093b73.png',
        'mastodon_max_feed_items' => 10,
        'mastodon_cache_enabled' => 1, // Enable caching by default
		'mastodon_pre_toots_html' => '<h2>My Mastodon Feed</h2>'
    );

    foreach ($default_options as $key => $value)
    {
        if (get_option($key) === false)
        {
            update_option($key, $value);
        }
    }
}

function rt_mastodon_feed_widget_settings_page() 
{
?>
<div class="wrap">
<h1>RT Mastodon Feed Widget</h1>

<h2>After entering your data here, you'll need to add the "RT Mastodon Feed" widget to your sidebar or wherever to actually see it.</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'rt-mastodon-feed-widget-settings-group' ); ?>
    <?php do_settings_sections( 'rt-mastodon-feed-widget-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Mastodon Feed URL</th>
        <td>
		<input type="text" name="mastodon_feed_url" size="100" value="<?php echo esc_attr( get_option('mastodon_feed_url') ); ?>" />
		<p class="description">The URL to your Mastodon feed - don't enter the .rss part at the end, we'll do that.</p>
		</td>
		</tr>
      
        <tr valign="top">
        <th scope="row">Mastodon Profile Picture Link</th>
        <td><input type="text" name="mastodon_profile_pic_link" size="100" value="<?php echo esc_attr( get_option('mastodon_profile_pic_link') ); ?>" />   <p class="description">Seth sucks and couldn't figure out how to automatically find this link, so enter the a URL to your profile pic.</p>
		</td>
        </tr>
		
		<tr valign="top">
		<th scope="row">Max Feed Items</th>
		<td><input type="text" name="mastodon_max_feed_items" size="100" value="<?php echo esc_attr( get_option('mastodon_max_feed_items') ); ?>" /></td>
		</tr>
		
		
        <tr valign="top">
        <th scope="row">HTML before Toots</th>
        <td>
		<textarea name="mastodon_pre_toots_html" rows="5" cols="100"><?php echo esc_textarea( get_option('mastodon_pre_toots_html') ); ?></textarea>
		<p class="description">This text (raw html is ok) will be output directly before the toots are shown.  Leave blank if you don't want any.</p>
	
		</td>
        </tr>
		
		
		<tr valign="top">
		<th scope="row">Enable Caching</th>
		<td>
			<input type="checkbox" name="mastodon_cache_enabled" <?php checked( get_option('mastodon_cache_enabled'), 1 ); ?> value="1">
			<p class="description">Enable caching of the feed. When active, SimplePie will only update from your real feed every hour.</p>
		</td>
		</tr>
		
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php } ?>