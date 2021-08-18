<?php 
function register_my_custom_post_meta_data(){
	# Tracks liked counts for each post
	register_meta(
		'post',
		'post_likes',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'sanitize_post_integer',
			'description'       => 'used to track all likes to a single post',
			'single'            => true,
			'show_in_rest'      => true,
		)
	);
	# Tracks disliked counts for each post
	register_meta(
		'post',
		'post_dislikes',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'sanitize_post_integer',
			'description'       => 'used to track all likes to a single post',
			'single'            => true,
			'show_in_rest'      => true,
		)
	);
}

#Convert a value to non-negative integer.
function sanitize_post_integer($meta_value, $meta_key, $meta_type){
	return absint($meta_value);
}

function load_api_settings_frontend(){
	if (is_single()){
		$wpApiSettings = array(
			'post_id' => esc_js(get_the_ID()),
			'user_id' => esc_js(get_current_user_id()),
			'root' => esc_url_raw(rest_url()),
			'X-WP-Nonce' => esc_js(wp_create_nonce('wp_rest')),
			'is_logged_in' => is_user_logged_in()? 'true' : 'false'
		);
		wp_localize_script('jquery', 'wpApi', $wpApiSettings);
	}
}
function rest_api_get_post_stats($r){
	$post_id = (isset($r['id']))? $r['id'] : false;
	$user_id = (isset($r['user_id']))? $r['user_id'] : false;
	$rtn = [];
	if ($post_id && $user_id){
		$posts_user_interaction = (get_user_meta($user_id, 'posts_user_interaction', true))? json_decode(get_user_meta($user_id, 'posts_user_interaction', true), true) : [];
		$rtn['like_count'] = (get_post_meta($post_id, 'post_likes', true))? get_post_meta($post_id, 'post_likes', true) : 0;
		$rtn['dislike_count'] = (get_post_meta($post_id, 'post_dislikes', true))? get_post_meta($post_id, 'post_dislikes', true) : 0;
		$rtn['posts_user_interaction'] = false;
		if (count($posts_user_interaction) > 0){
			foreach($posts_user_interaction as $key => $value){
				if ($value['post_id'] == $post_id){
					$rtn['posts_user_interaction'] = $value;
				}
			}
		}
	}
	echo json_encode($rtn);
	die(1);
}
function users_post_liked_or_disliked($post_id=false, $user_id=false, $type=false, $voted_status){
	$rtn = [];
	if ($post_id && $user_id && $type){
		$post_liked_count = (get_post_meta($post_id, 'post_likes', true))? get_post_meta($post_id, 'post_likes', true) : 0;
		$post_disliked_count = (get_post_meta($post_id, 'post_dislikes', true))? get_post_meta($post_id, 'post_dislikes', true) : 0;
		$posts_user_interaction = (get_user_meta($user_id, 'posts_user_interaction', true))? json_decode(get_user_meta($user_id, 'posts_user_interaction', true), true) : [];
		$alt_type = ($type == 'disliked')? 'liked' : 'disliked';
		$current_key = 0;
		$user_did_not_interact = true;
		if (count($posts_user_interaction) > 0){
			foreach($posts_user_interaction as $key => $value){
				if ($value['post_id'] == $post_id){
					$current_key = $key;
					$user_did_not_interact = false;
					if ($voted_status == true && $value[$type] == false){
						if ($type == 'disliked' && $post_disliked_count >= 0) $post_disliked_count += 1;
						if ($type == 'liked' && $post_liked_count >= 0) $post_liked_count += 1;
						if ($value[$alt_type] == true){
							if ($alt_type == 'disliked' && $post_disliked_count > 0) $post_disliked_count -= 1;
							if ($alt_type == 'liked' && $post_liked_count > 0) $post_liked_count -= 1;						
							$posts_user_interaction[$key][$alt_type] = false;
						}
					}

					if ($voted_status == false && $value[$type] == true){
						if ($type == 'disliked' && $post_disliked_count > 0) $post_disliked_count -= 1;
						if ($type == 'liked' && $post_liked_count > 0) $post_liked_count -= 1;
					}
					$posts_user_interaction[$key][$type] = $voted_status;
				}				
			}

		}

		if ($user_did_not_interact || count($posts_user_interaction) == 0){
			if ($type == 'disliked'){
				$posts_user_interaction[] = ['post_id' => $post_id, 'liked' => false, 'disliked' => $voted_status];
				$post_disliked_count += 1;
			}
			if ($type == 'liked'){
				$posts_user_interaction[] = ['post_id' => $post_id, 'liked' => $voted_status, 'disliked' => false];
				$post_liked_count += 1;
			}
			$current_key = array_key_last($posts_user_interaction);
		}

		update_user_meta($user_id, 'posts_user_interaction', json_encode($posts_user_interaction));
		update_post_meta($post_id, 'post_dislikes', $post_disliked_count);
		update_post_meta($post_id, 'post_likes', $post_liked_count);
		$rtn['like_count'] = $post_liked_count;
		$rtn['dislike_count'] = $post_disliked_count;
		$rtn['posts_user_interaction'] = $posts_user_interaction[$current_key];
	}
	return $rtn;
}
function rest_api_post_dislikes($r){
	$post_id = (isset($r['id']))? $r['id'] : 0;
	$user_id = (isset($r['user_id']))? $r['user_id'] : 0;
	$voted_status = (isset($r['disliked']) && $r['disliked'] == 'true')? true : false;
	echo json_encode(users_post_liked_or_disliked($post_id, $user_id, 'disliked', $voted_status));
}
function rest_api_post_likes($r){
	$post_id = (isset($r['id']))? $r['id'] : 0;
	$user_id = (isset($r['user_id']))? $r['user_id'] : 0;
	$voted_status = (isset($r['liked']) && $r['liked'] == 'true')? true : false;
	echo json_encode(users_post_liked_or_disliked($post_id, $user_id, 'liked', $voted_status));
	die(1);
}
function rest_api_is_logged_in(){
	// Restrict endpoint to only users who have the edit_posts capability.
	return (!is_user_logged_in())? new WP_Error('rest_forbidden', esc_html__('You must be logged in to use this Endpoint.', 'my-text-domain'), array( 'status' => 401 )) : true;
}
#on all page load, fire method
add_action('init', 'register_my_custom_post_meta_data');
# action fires when WP enqueue's scripts, add's local reference for Rest Route access
add_action('wp_enqueue_scripts', 'load_api_settings_frontend');
# add's rest routes to WP
add_action('rest_api_init', function(){
	//like logic
	register_rest_route('post_tracker/v1', '/get_post_stats/(?P<user_id>\d+)/(?P<id>\d+)/', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => 'rest_api_get_post_stats',
	));
	register_rest_route('post_tracker/v1', '/post_like/(?P<user_id>\d+)/(?P<id>\d+)/(?P<liked>[a-zA-Z_-]+)', array(
		'methods' => WP_REST_Server::CREATABLE,
		'callback' => 'rest_api_post_likes',
		'permission_callback' => 'rest_api_is_logged_in'
	));	
	register_rest_route('post_tracker/v1', '/post_dislike/(?P<user_id>\d+)/(?P<id>\d+)/(?P<disliked>[a-zA-Z_-]+)', array(
		'methods' => WP_REST_Server::CREATABLE,
		'callback' => 'rest_api_post_dislikes',
		'permission_callback' => 'rest_api_is_logged_in'
	));
});
?>