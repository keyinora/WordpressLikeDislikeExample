<?php 
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
?>