<?php
class Toplytics_WP_Widget_Most_Visited_Posts extends WP_Widget {

	function Toplytics_WP_Widget_Most_Visited_Posts() {
		$widget_ops = array('classname' => 'widget_most_visited_posts', 'description' => __( "Toplytics - The most visited posts on your site from Google Analytics") );
		$this->WP_Widget('most-visited-posts', __('Most Visited Posts'), $widget_ops);
		$this->alt_option_name = 'widget_most_visited_posts';
	}


	function widget($args, $instance) {
		
		require_once 'toplytics.class.php';

		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? __('Most visited posts') : $instance['title'], $instance, $this->id_base);
		
		//$title = '';
	  	$number = $instance['number'];
	  	$counter= $number;
		
		$type = $instance['type'];
		if (!in_array($type,array('today','week','month'))) $type = 'today';
	  
		// Get the info from transient
		$results = get_transient('gapi.cache');
	  
		// If the transient is empty then scan the visited posts from Google Analytics Account
		if ( !$results ) {
			$results = Toplytics::ga_statistics();
			set_transient('gapi.cache', $results, 48 * 1800);
		}
		?>

		<?php if (!empty($results[$type])) : echo $before_widget; ?>
			<div class="toplytics-box">
			<?php if ( $title ) echo $before_title . $title . $after_title; $count = 0; ?>
			  
		<ol>				
		<?php foreach ($results[$type] as $post_id => $pv) : ?>
				
<?php if ($number <= 0) break; ?>
				
		<li class="top<?php echo ($counter-$number+1); ?>">
			<div class="toplytics-box-bg">
				<?php $post = get_post($post_id); ?>
	  
<?php   $post_categories = wp_get_post_categories( $post_id );
        $category = get_category($post_categories[0]);
        $title = get_the_title($post_id);
			?>
        <a class="category-<?php echo $category->slug; ?>" href="<?php echo get_permalink($post_id); ?>" title="<?php echo esc_attr(get_the_title($post_id)); ?>">
		  <!--span class="details"><strong class="caption"><?php echo $title; ?></strong></span-->
				<?php 
				$images =& get_children( 'post_type=attachment&post_mime_type=image&post_parent=' . $post_id );
				if ($images) {
					$firstImageSrc = wp_get_attachment_image_src(array_shift(array_keys($images)), 'toplytics-box', false);
				  //$firstImg = str_replace('http://www.homedit.com/wp-content/uploads/','http://img.homedit.com/', $firstImageSrc[0]);
					$firstImg = $firstImageSrc[0];
			  
if (@file_get_contents($firstImg)):
	echo '<img src="'.$firstImg.'" width="315" height="50" border="0" alt="'.get_the_title($post_id).'">';
endif;
				}
				?>
		  <div class="toplytics-box-title"><?php echo get_the_title($post_id); ?><!-- - <span class="toplytics-box-views"><?php printf('%d Views', $pv); ?></span>-->
		  </div>
		</a>
		  </div>
      </li>
		<?php $number--; ?>
		<?php endforeach; ?>
		</ol>
			  
			<?php echo $after_widget; ?>
	<?php
			// Reset the global $the_post as this query will have stomped on it
			wp_reset_postdata();
	  
	  else:
	  echo "No info found!";
		endif; ?>
</div>	  
		<?php ob_get_flush();
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
	  
		if ( !$number = (int) $new_instance['number'] )
			$number = TOPLYTICS_DEFAULT_POSTS;
		else if ( $number < TOPLYTICS_MIN_POSTS )
			$number = TOPLYTICS_MIN_POSTS;
		else if ( $number > TOPLYTICS_MAX_POSTS )
			$number = TOPLYTICS_MAX_POSTS;

		$instance['number'] = $number;
		  
		$instance['type'] = $new_instance['type'];
		if (!in_array($instance['type'],array('today','week','month')))	$instance['type'] = 'today';
		$instance['list_type'] = $new_instance['list_type'];
		if (!in_array($instance['list_type'],array('views','popular'))) $instance['list_type'] = 'views';
		return $instance;
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		if ( !isset($instance['number']) || !$number = (int) $instance['number'] )
			$number = 5;
		$type = isset($instance['type']) ? $instance['type'] : 'today';
		$list_type = isset($instance['list_type']) ? $instance['list_type'] : 'views';
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>
		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
		<p><label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Type of posts to show:'); ?></label>
		<select id="<?php echo $this->get_field_id('type'); ?>" name="<?php echo $this->get_field_name('type'); ?>">
			<option value="today" <?php if ($type == 'today') echo 'selected="selected"'; ?>>Today</option>
			<option value="week" <?php if ($type == 'week') echo 'selected="selected"'; ?>>Week</option>
			<option value="month" <?php if ($type == 'month') echo 'selected="selected"'; ?>>Month</option>
		</select>
		</p>
		<!--p><label for="<?php echo $this->get_field_id('list_type'); ?>"><?php _e('Listing type:'); ?></label>
		<select id="<?php echo $this->get_field_id('list_type'); ?>" name="<?php echo $this->get_field_name('list_type'); ?>">
			<option value="views" <?php if ($list_type == 'views') echo 'selected="selected"'; ?>>Most viewed</option>
		</select>
		</p-->
<?php
	}
}

