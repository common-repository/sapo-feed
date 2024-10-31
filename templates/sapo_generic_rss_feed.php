<?php
/**
 * SAPO feed template.
 *
 */

header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';

$default_settings = [
	'sapo_rss_post_limit' => 20,
	'sapo_rss_post_type' => ['post' => 1]
];

$settings = get_option( 'sapo_rss_options', $default_settings );

$limit = $settings['sapo_rss_post_limit'];

$post_types = isset( $settings['sapo_rss_post_type'] ) ? $settings['sapo_rss_post_type'] : $default_settings['sapo_rss_post_type'];
$types = array_keys($post_types, 1);

$invalid_request = false;
$categories      = [];
$tags            = '';

if (!empty ($_SERVER['QUERY_STRING'])) {
	$params = null;
	parse_str($_SERVER['QUERY_STRING'], $params);

	if (!empty($params['limit']) and (is_numeric($params['limit']))) {
		$limit = (($params['limit'] > 0) and ($params['limit'] < $limit)) ? $params['limit'] : $limit;
	}

	if (!empty($params['category'])) {
		$slugs = explode(',', $params['category']);
		foreach ($slugs as $s) {
			$cat_obj = get_category_by_slug( $s );

			if ( $cat_obj instanceof WP_Term ) {
				$categories[] = $cat_obj->term_id;
			}
		}

		if ( (count($slugs) > 0) and (count($categories) == 0) ) {
			$invalid_request = true;
		}
	}

	if (!empty($params['tags'])) {
		$tags = $params['tags'];
	}
}

/**
 * Fires between the xml and rss tags in a feed.
 *
 * @since 4.0.0
 *
 * @param string $context Type of feed. Possible values include 'rss2', 'rss2-comments',
 *                        'rdf', 'atom', and 'atom-comments'.
 */
do_action( 'rss_tag_pre', 'rss2' );
?>
<rss version="2.0"
	xmlns:sapo="http://purl.org/rss/1.0/modules/content/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	<?php
	/**
	 * Fires at the end of the RSS root to add namespaces.
	 *
	 * @since 2.0.0
	 */
	do_action( 'rss2_ns' );
	?>
>

<channel>
	<title><![CDATA[<?php wp_title_rss(); ?>]]></title>
	<link><?php bloginfo_rss( 'url' ); ?></link>
	<description><?php bloginfo_rss( 'description' ); ?></description>
	<pubDate><?php

	$searchParams = [
		'numberposts'  => $limit,
		'category__in' => $categories,
		'post_type'    => $types,
		'tag'          => $tags,
		'post_status'  => 'publish'
	];

	$posts = [];

	if (! $invalid_request) {
		$posts = wp_get_recent_posts($searchParams, 'OBJECT');
	}

	if (empty($posts)) {
		echo '';
	} else {
		$date = date_create($posts[0]->post_date);
		echo date_format($date, 'r');
	}

	?></pubDate>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<?php

	if (!empty ($posts)) {
		global $post;
		while ($post = array_shift($posts)) {
			setup_postdata( $post );

			?>
			<item>
				<title><![CDATA[<?php echo get_the_title(); ?>]]></title>
				<link><?php echo get_permalink(); ?></link>

				<?php if ( get_option( 'rss_use_excerpt' ) ) : ?>
					<description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
				<?php else : ?>
					<description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
				<?php endif; ?>

				<?php the_category_rss( 'rss2' ); ?>

				<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
				<guid isPermaLink="false"><?php the_guid(); ?></guid>
				<?php
				$thumbnail_id = get_post_thumbnail_id();
				if ($thumbnail_id > 0) {
					$thumbnail = get_post(get_post_thumbnail_id());
					$thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
					if (! $thumbnail_url) { $thumbnail_url = $thumbnail->guid; }
					?>
						<enclosure url="<?php echo htmlspecialchars($thumbnail_url) ?>" type="<?php echo $thumbnail->post_mime_type ?>"/>
					<?php
				}
				$post_nuts = get_post_meta( get_the_ID(), 'sapo_feed_post_nuts', true );
				$post_nuts = preg_replace('/0{6},{0,1}/', '', $post_nuts);
				if ( ! empty( $post_nuts ) ) {
					?>
						<sapo:nuts><![CDATA[<?php echo $post_nuts; ?>]]></sapo:nuts>
					<?php
				}
				?>
				<sapo:lead><![CDATA[<?php echo wp_strip_all_tags(get_the_excerpt()); ?>]]></sapo:lead>

				<?php
					$content  = get_the_content();
					$content  = apply_filters( 'sapo_feed_handle_post_content_before', $content );
					$postData = handle_post_content($content);
					$postData = apply_filters( 'sapo_feed_handle_post_content_after', $postData, $post );
				?>

				<sapo:body><![CDATA[<?php echo $postData['content']; ?>]]></sapo:body>
				<sapo:embeds>
					<?php

					foreach ($postData['embeds'] as $embed) {
						?>

							<sapo:embed type="<?php echo $embed['type'] ?>">
								<sapo:id><?php echo $embed['id'] ?></sapo:id>
								<?php if (array_key_exists('title', $embed)) {
									?> <sapo:title><?php echo $embed['title'] ?></sapo:title>
								<?php } ?>
								<?php if (array_key_exists('description', $embed)) {
									?> <sapo:title><?php echo $embed['description'] ?></sapo:title>
								<?php } ?>
								<sapo:items>
									<?php

									foreach ($embed['items'] as $item) {
										?>
										<sapo:item url="<?php echo htmlspecialchars($item['url']) ?>" type="<?php echo $item['type'] ?>"/>
										<?php
									}

									?>
								</sapo:items>
							</sapo:embed>

						<?php
					}

					?>
				</sapo:embeds>

				<?php
				/**
				 * Fires at the end of each RSS2 feed item.
				 *
				 * @since 2.0.0
				 */
				do_action( 'rss2_item' );
				?>
			</item>
			<?php
			wp_reset_postdata();
		}
	}
	 ?>
</channel>
</rss>

<?php
function handle_post_content($post_content) {
	$embedCounters = [];
	$embeds = [];

	try {
		$jit = ini_get('pcre.jit');
		ini_set('pcre.jit', false); /* With large wp_embeds, namely VERY BIG photo galleries,
		                             * this handler dies because of a 'PREG_JIT_STACKLIMIT_ERROR'.
		                             * We consciously disable it to trade speed for reliability.
		                             */

		$content_aux = handle_sapo_galleries_gutenberg($post_content, $embedCounters, $embeds);
		$content_aux = handle_sapo_html_blockquote_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_wp_embeds_gutenberg($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_videos_block($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_wpcore_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_wp_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_td_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_classic_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_iframe_embeds($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_feed_classic_images($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_classic_galleries($content_aux, $embedCounters, $embeds);
		$content_aux = handle_sapo_script_tags($content_aux);

		$content_aux = apply_filters('the_content', $content_aux);

		ini_set('pcre.jit', $jit);
	} catch (Exception $e) {
		$content_aux = "";
		$embeds = [];
	}

	return ['content' => $content_aux, 'embeds' => $embeds, 'embed_counters' => $embedCounters];
}

function handle_sapo_wpcore_embeds($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:core-embed\/(.*?) ({.*?}) -->(.|\n)*?<!-- \/wp:core-embed\/(.*?) -->/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		switch ($matches[1]) {
			case 'youtube':
			case 'vimeo':
			case 'soundcloud':
			case 'twitter':
			case 'facebook':
			case 'instagram':
			case 'tiktok':
				$type = $matches[1];
				$params = json_decode($matches[2], true);
				array_push($items, [
					'url' => $params['url'],
					'type' => $type
				]);
			break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_wp_embeds($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:(.+?) (?:{.*?}\s)?-->((?:.|\n)+?)<!-- \/wp:\1 -->/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(trim($matches[2]));

		switch ($matches[1]) {
			case 'video':
				$type = 'video';

				$figure = $dom->getElementsByTagName('figure')[0];
				if (! empty($figure) ) {
					$video = $figure->getElementsByTagName('video')[0];
					if (! empty($video) ) {
						$url = $video->attributes->getNamedItem('src')->value;

						if (! empty($url) ) {
							array_push($items, [
								'url' => $url,
								'type' => 'video'
							]);
						}
					}
				}
			break;
			case 'audio':
				$type = 'audio';

				$figure = $dom->getElementsByTagName('figure')[0];
				if (! empty($figure) ) {
					$audio = $figure->getElementsByTagName('audio')[0];
					if (! empty($audio) ) {
						$url = $audio->attributes->getNamedItem('src')->value;

						if (! empty($url) ) {
							array_push($items, [
								'url' => $url,
								'type' => 'audio'
							]);
						}
					}
				}
			break;
			case 'image':
				$type = 'photo';

				$figure = $dom->getElementsByTagName('figure')[0];
				if (! empty($figure) ) {
					$image = $figure->getElementsByTagName('img')[0];
					if (! empty($image) ) {
						$url = $image->attributes->getNamedItem('src')->value;

						if (! empty($url) ) {
							array_push($items, [
								'url' => $url,
								'type' => 'photo'
							]);
						}
					}
				}
			break;
			case 'gallery':
				$type = 'photo-gallery';

				$xpath  = new DomXPath($dom);
				$images = $xpath->query("//*[contains(@class, 'blocks-gallery-item')]");
				$images = empty($images) ? [] : $images;

				foreach ($images as $image) {
					$figure = $image->getElementsByTagName('figure')[0];
					if (! empty($figure) ) {
						$img = $figure->getElementsByTagName('img')[0];
						if (! empty($img) ) {
							$url = $img->attributes->getNamedItem('src')->value;

							if (! empty($url) ) {
								array_push($items, [
									'url' => $url,
									'type' => 'photo'
								]);
							}
						}
					}
				}
			break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_td_embeds($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:td-(.*?)-embed-(.*?)\/(.*?) ({.*?}) \/-->/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		switch ($matches[1]) {
			case 'tiktok':
				$type = $matches[1];
				$params = json_decode($matches[4], true);
				array_push($items, [
					'url' => $params['url'],
					'type' => $type
				]);
			break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_classic_embeds($post_content, &$embedCounters, &$embeds) {
	$regex = '/\[embed\](.*?)\[\/embed\]/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$oembed = _wp_oembed_get_object();

		$type = get_link_type($matches[1]);

		switch ($type) {
			case 'youtube':
			case 'vimeo':
			case 'soundcloud':
			case 'twitter':
			case 'facebook':
			case 'instagram':
			case 'tiktok':
				array_push($items, [
					'url' => $matches[1],
					'type' => $type
				]);
			break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_iframe_embeds($post_content, &$embedCounters, &$embeds) {
	$regex = '/<iframe(.|\n)*?<\/iframe>/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(trim($matches[0]));

		$url = $dom->getElementsByTagName('iframe')[0]
		->attributes
		->getNamedItem('src')->value;

		$type = get_link_type($url);

		switch ($type) {
			case 'youtube':
			case 'vimeo':
			case 'soundcloud':
			case 'twitter':
			case 'facebook':
			case 'instagram':
			case 'tiktok':
			case 'video-sapo':
			case 'spotify':
				array_push($items, [
					'url' => $url,
					'type' => $type
				]);
				break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_feed_classic_images($post_content, &$embedCounters, &$embeds) {
	$regex = '/<img(.|\n)*?\/>/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(trim($matches[0]));

		$url = $dom->getElementsByTagName('img')[0]
		->attributes
		->getNamedItem('src')->value;

		$type = 'photo';
		array_push($items, [
			'url' => $url,
			'type' => 'photo'
		]);

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_classic_galleries($post_content, &$embedCounters, &$embeds) {
	$regex = '/\[gallery ids=\"(.*?)\"(.|\n)*?\]/';

	return preg_replace_callback($regex, function($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(trim($matches[0]));

		$images = explode(',', $matches[1]);

		$type = 'photo-gallery';

		foreach ($images as $imageId) {
			array_push($items, [
				'url' => wp_get_attachment_url($imageId),
				'type' => 'photo'
			]);
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_script_tags($post_content) {
	$regex = '/<script(.*?)>(.*?)<\/script>/ms';

	return preg_replace($regex, '', $post_content);
}

function get_link_type ($link) {
	$types = [
		'#https?://((m|www)\.)?youtube\.com/watch.*#i' => 'youtube',
		'#https?://((m|www)\.)?youtube\.com/playlist.*#i' => 'youtube',
		'#https?://((m|www)\.)?youtube(?:-nocookie)?\.com/embed.*#i' => 'youtube',
		'#https?://youtu\.be/.*#i'                     => 'youtube',
		'#https?://(.+\.)?vimeo\.com/.*#i'             => 'vimeo',
		'#https?://(www\.)?twitter\.com/\w{1,15}/status(es)?/.*#i' => 'twitter',
		'#https?://(www\.)?twitter\.com/\w{1,15}$#i'   => 'twitter',
		'#https?://(www\.)?twitter\.com/\w{1,15}/likes$#i' => 'twitter',
		'#https?://(www\.)?twitter\.com/\w{1,15}/lists/.*#i' => 'twitter',
		'#https?://(www\.)?twitter\.com/\w{1,15}/timelines/.*#i' => 'twitter',
		'#https?://(www\.)?twitter\.com/i/moments/.*#i' => 'twitter',
		'#https?://(www\.)?soundcloud\.com/.*#i'       => 'soundcloud',
		'#https?://(.+?\.)?slideshare\.net/.*#i'       => 'slideshare',
		'#https?://(www\.)?instagr(\.am|am\.com)/(p|tv|reel)/.*#i' => 'instagram',
		'#https?://(.+\.)?imgur\.com/.*#i'             => 'imgur',
		'#https?://www\.facebook\.com/.*/posts/.*#i'   => 'facebook',
		'#https?://www\.facebook\.com/.*/activity/.*#i' => 'facebook',
		'#https?://www\.facebook\.com/.*/photos/.*#i'  => 'facebook',
		'#https?://www\.facebook\.com/photo(s/|\.php).*#i' => 'facebook',
		'#https?://www\.facebook\.com/permalink\.php.*#i' => 'facebook',
		'#https?://www\.facebook\.com/media/.*#i'      => 'facebook',
		'#https?://www\.facebook\.com/questions/.*#i'  => 'facebook',
		'#https?://www\.facebook\.com/notes/.*#i'      => 'facebook',
		'#https?://www\.facebook\.com/.*/videos/.*#i'  => 'facebook',
		'#https?://www\.facebook\.com/video\.php.*#i'  => 'facebook',
		'#https?://(www\.)?tiktok\.com/.*/video/.*#i'  => 'tiktok',
		'#https?://www\.facebook\.com/.*/post.*#i'   => 'facebook',
		'#https?://(www\.)?threads\.net/.*/post/.*#i'   => 'threads',
		'#https?://([\w\d]+\.)??spotify\.com/.*#i' => 'spotify',
		'#https?://([\w\d]+\.)??videos\.sapo\.pt.*#i' => 'video-sapo',
	];

	foreach (array_keys($types) as $regex) {
		if (preg_match($regex, $link)) {
			return $types[$regex];
		}
	}
}

function handle_sapo_galleries_gutenberg($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:gallery (?:{.*?}\s)?-->((?:.|\n)+?)<!-- \/wp:gallery -->/';
	return preg_replace_callback($regex, function ($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];

		$dom = new DOMDocument();
		@$dom->loadHTML(trim($matches[1]));

		$type = 'photo-gallery';
		$xpath  = new DomXPath($dom);
		$images = $xpath->query("//*[contains(@class, 'wp-block-gallery')]/descendant::img[contains(@class, 'wp-image')]");
		$images = empty($images) ? [] : $images;

		foreach ($images as $image) {
			$items[] =  [
				'url' => $image->getAttribute('src'),
				'type' => 'photo'
			];
		}
		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);
		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_wp_embeds_gutenberg($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:(.*?) ((?:{.*?}\s)?)-->((?:.|\n)*?)<!-- \/wp:\1 -->/';

	return preg_replace_callback($regex, function ($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];
		switch ($matches[1]) {
			case 'embed':
				$info = json_decode($matches[2], true);
				if ($info && isset($info['url'])) {
					$type = get_link_type($info['url']);
					if($type) {
						array_push($items, [
							'url'  => $info['url'],
							'type' => $type
						]);
					}
				} else {
					return $matches[0];
				}
				break;
			case 'code':
			case 'html':
				if (empty($matches[3])) return $matches[0];
				$dom = new DOMDocument();
				@$dom->loadHTML($matches[3]);
				$xpath = new DOMXPath($dom);
				// Use XPath to query all blockquote tags
				$blockquoteNodes = $xpath->query('//blockquote');

				// Iterate through the results
				foreach ($blockquoteNodes as $node) {
					// Find anchor (a) tags within each blockquote
					$anchorNodes = $xpath->query('.//a', $node);
					// Iterate through anchor nodes
					foreach ($anchorNodes as $anchorNode) {
						// Get the href attribute value
						$url = $anchorNode->getAttribute('href');
						$type = get_link_type($url);
						if($type){
							array_push($items, [
								'url'  => $url,
								'type' => $type
							]);
							break;
						}
					}
				}
				//no type found, so it is a block code with no embeds
				if(!$type) {
					return $matches[0];
				}
				break;
			default:
				return $matches[0];
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}

function handle_sapo_videos_block($post_content, &$embedCounters, &$embeds) {
	$regex = '/<!-- wp:(.*?) ((?:{.*?}\s)?)\/-->/';

	return preg_replace_callback($regex, function ($matches) use (&$embedCounters, &$embeds) {
		$type = null;
		$items = [];
		if ($matches[1]) {
			$info = json_decode($matches[2], true);
			if ($info && isset($info['url'])) {
				$type = get_link_type($info['url']);
				if($type) {
					array_push($items, [
						'url'  => $info['url'],
						'type' => $type
					]);
				}
			} else {
				return $matches[0];
			}
		}

		if (array_key_exists($type, $embedCounters)) {
			$embedCounters[$type] = $embedCounters[$type] + 1;
		} else {
			$embedCounters[$type] = 1;
		}

		$id = $type . '-' . $embedCounters[$type];

		array_push($embeds, [
			'id' => $id,
			'type' => $type,
			'items' => $items
		]);

		return '<span id="' . $id . '"></span>';
	}, $post_content);
}


function handle_sapo_html_blockquote_embeds($post_content, &$embedCounters, &$embeds) {
    $regex = '/<blockquote (.*?)<\/blockquote>/ms';

    return preg_replace_callback($regex, function ($matches) use (&$embedCounters, &$embeds) {
        $type = null;
        $items = [];
        if ( !empty($matches[0])) {
                $dom = new DOMDocument();
                @$dom->loadHTML($matches[0]);
                $xpath = new DOMXPath($dom);
                // Use XPath to query all blockquote tags
                $blockquoteNodes = $xpath->query('//blockquote');

                // Iterate through the results
                foreach ($blockquoteNodes as $node) {
                    // Get the cite attribute value, if it exists, used by TikTok
                    $url = $node->getAttribute('cite');
                    $type = get_link_type($url);
                    if($type){
                        array_push($items, [
                            'url'  => $url,
                            'type' => $type
                        ]);
                        break;
                    }
                    // Find anchor (a) tags within each blockquote
                    $anchorNodes = $xpath->query('.//a', $node);
                    // Iterate through anchor nodes
                    foreach ($anchorNodes as $anchorNode) {
                        // Get the href attribute value
                        $url = $anchorNode->getAttribute('href');
                        $type = get_link_type($url);
                        if($type){
                            array_push($items, [
                                'url'  => $url,
                                'type' => $type
                            ]);
                            break;
                        }
                    }
                }
                //no type found, so it is a block code with no embeds
                if(!$type) {
                    return $matches[0];
                }
        }

        if (array_key_exists($type, $embedCounters)) {
            $embedCounters[$type] = $embedCounters[$type] + 1;
        } else {
            $embedCounters[$type] = 1;
        }

        $id = $type . '-' . $embedCounters[$type];

        array_push($embeds, [
            'id' => $id,
            'type' => $type,
            'items' => $items
        ]);

        return '<span id="' . $id . '"></span>';
    }, $post_content);
}



?>
