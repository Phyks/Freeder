<?php
/** Freeder
 *  -------
 *  @file
 *  @copyright Copyright (c) 2014 Freeder, MIT License, See the LICENSE file for copying permissions.
 *  @brief Functions to handle the feeds (includes feed2array)
 */

require_once(INC_DIR . 'entries.php');
require_once(INC_DIR . 'favicons.php');
require_once(INC_DIR . 'feed2array.php');
require_once(INC_DIR . 'functions.php');
require_once(INC_DIR . 'tags.php');


/**
 * Refresh the specified feeds and returns an array with URLs in error
 *
 * @param $feeds should be an array of associative arrays ('id', 'url', 'post'} as values. post is a JSON array of post parameters to send with the URL.
 * @param $update_feeds_infos should be true to update the feed infos from values in the RSS / ATOM
 * @todo assert(false)
 */
function refresh_feeds($feeds, $check_favicons=false) {
	global $dbh, $config;

	// Download the feeds
	$download = curl_downloader($feeds);
	$errors = array();
	$favicons_to_check = array();
	foreach ($download['status_codes'] as $url=>$status_code) {
		// Keep the errors to return them and display them to the user
		if ($status_code != 200) {
			$errors[] = array('url'=>$url, 'msg'=>'Feed page not found (http status: ' . $status_code . ')');
		}
	}

	$updated_feeds = $download['results'];

	// Put everything in a transaction to make it faster
	$dbh->beginTransaction();
	// Delete old tags which were not user added
	delete_auto_added_tags();

	// Query to update feeds table with latest infos in the RSS / ATOM
	$query_feeds = $dbh->prepare('UPDATE feeds SET title=(CASE WHEN has_user_title=1 THEN title ELSE :title END), links=:links, description=:description, ttl=(CASE WHEN has_user_ttl=1 THEN ttl ELSE :ttl END), image=:image WHERE url=:old_url');
	$query_feeds->bindParam(':title', $feed_title);
	$query_feeds->bindParam(':links', $feed_links);
	$query_feeds->bindParam(':description', $feed_description);
	$query_feeds->bindParam(':ttl', $feed_ttl, PDO::PARAM_INT);
	$query_feeds->bindParam(':image', $image);
	$query_feeds->bindParam(':old_url', $url);

	// Two queries, to upsert (update OR insert) entries : update the existing entry and insert a new one if the update errorred
	$query_entries = $dbh->prepare('UPDATE entries SET authors=:authors, title=:title, links=:links, description=:description, content=:content, enclosures=:enclosures, comments=:comments, pubDate=:pubDate, lastUpdate=:lastUpdate WHERE guid=:guid');
	$query_entries->bindParam(':authors', $authors);
	$query_entries->bindParam(':title', $title);
	$query_entries->bindParam(':links', $links);
	$query_entries->bindParam(':description', $description);
	$query_entries->bindParam(':content', $content);
	$query_entries->bindParam(':enclosures', $enclosures);
	$query_entries->bindParam(':comments', $comments);
	$query_entries->bindParam(':guid', $guid);
	$query_entries->bindParam(':pubDate', $pubDate, PDO::PARAM_INT);
	$query_entries->bindParam(':lastUpdate', $last_update, PDO::PARAM_INT);
	$query_entries_fail = $dbh->prepare('INSERT INTO entries(feed_id, authors, title, links, description, content, enclosures, comments, pubDate, lastUpdate, guid) VALUES(:feed_id, :authors, :title, :links, :description, :content, :enclosures, :comments, :pubDate, :lastUpdate, :guid)');
	$query_entries_fail->bindParam(':feed_id', $feed_id);
	$query_entries_fail->bindParam(':authors', $authors);
	$query_entries_fail->bindParam(':title', $title);
	$query_entries_fail->bindParam(':links', $links);
	$query_entries_fail->bindParam(':description', $description);
	$query_entries_fail->bindParam(':content', $content);
	$query_entries_fail->bindParam(':enclosures', $enclosures);
	$query_entries_fail->bindParam(':comments', $comments);
	$query_entries_fail->bindParam(':guid', $guid);
	$query_entries_fail->bindParam(':pubDate', $pubDate, PDO::PARAM_INT);
	$query_entries_fail->bindParam(':lastUpdate', $last_update, PDO::PARAM_INT);

	if($config->use_tags_from_feeds != 0) {
		// Query to insert tags if not already existing
		$query_insert_tag = $dbh->prepare('INSERT OR IGNORE INTO tags(name) VALUES(:name)');
		$query_insert_tag->bindParam(':name', $tag_name);

		// Register the tags of the feed
		$query_feeds_tags = $dbh->prepare('INSERT OR IGNORE INTO tags_feeds(tag_id, feed_id, auto_added_tag) VALUES((SELECT id FROM tags WHERE name=:name), :feed_id, 1)');
		$query_feeds_tags->bindParam(':name', $tag_name);
		$query_feeds_tags->bindParam(':feed_id', $feed_id);

		// Finally, query to register the tags of the entry
		$query_tags = $dbh->prepare('INSERT OR IGNORE INTO tags_entries(tag_id, entry_id, auto_added_tag) VALUES((SELECT id FROM tags WHERE name=:name), (SELECT id FROM entries WHERE guid=:entry_guid), 1)');
		$query_tags->bindParam(':name', $tag_name);
		$query_tags->bindParam(':entry_guid', $guid);
	}

	foreach ($updated_feeds as $url=>$feed) {
		$feed_id = multiarray_search('url', $url, $feeds, array());
		if (empty($feed_id)) {
			assert(false); // TODO
			exit();
		}
		$feed_id = $feed_id['id'];
		// Parse feed
		$parsed = @feed2array($feed);

		// If an error has occurred, keep a trace of it
		if ($parsed === false || empty($parsed['infos']) || empty($parsed['items'])) {
			$errors[] = array('url'=>$url, 'msg'=>'Unable to parse feed file');
			continue;
		}

		// Define feed params
		$feed_title = isset($parsed['infos']['title']) ? $parsed['infos']['title'] : '';
		$feed_links = isset($parsed['infos']['links']) ? json_encode(multiarray_filter('rel', 'self', $parsed['infos']['links'])) : '';
		$feed_description = isset($parsed['infos']['description']) ? $parsed['infos']['description'] : '';
		$feed_ttl = isset($parsed['infos']['ttl']) ? $parsed['infos']['ttl'] : 0;
		$feed_image = isset($parsed['infos']['image']) ? json_encode($parsed['infos']['image']) : '';
		if ($check_favicons && empty($feed_image)) {
			$favicons_to_check[] = array('url'=>$url);
		}
		$query_feeds->execute();

		// Feeds tags
		if($config->use_tags_from_feeds != 0) {
			if (!empty($parsed['infos']['categories'])) {
				foreach ($parsed['infos']['categories'] as $tag_name) {
					// Create tags if needed, get their id and add bind the articles to these tags
					$query_insert_tag->execute();
					$query_feeds_tags->execute();
				}
			}
		}

		// Insert / Update entries
		$items = $parsed['items'];
		foreach ($items as $event) {
			$authors = isset($event['authors']) ? json_encode($event['authors']) : '';
			$title = isset($event['title']) ? $event['title'] : '';
			$links = isset($event['links']) ? json_encode(multiarray_filter('rel', 'self', $event['links'])) : '';
			$description = isset($event['description']) ? $event['description'] : '';
			$content = isset($event['content']) ? $event['content'] : '';
			$enclosures = isset($event['enclosures']) ? json_encode($event['enclosures']) : '';
			$comments = isset($event['comments']) ? $event['comments'] : ((isset($event['links'])) ? multiarray_search('rel', 'replies', $event['links'], array('href'=>''))['href'] : '');
			$guid = isset($event['guid']) ? $event['guid'] : '';
			$pubDate = isset($event['pubDate']) ? $event['pubDate'] : '';
			$last_update = isset($event['updated']) ? $event['updated'] : '';

			$query_entries->execute();
			if ($query_entries->rowCount() == 0) {
				$query_entries_fail->execute();
			}

			if($config->use_tags_from_feeds != 0) {
				if (!empty($event['categories'])) {
					foreach ($event['categories'] as $tag_name) {
						// Create tags if needed, get their id and add bind the articles to these tags
						$query_insert_tag->execute();
						$query_tags->execute();
					}
				}
				if (!empty($event['enclosures'])) {
					foreach ($event['enclosures'] as $enclosure) {
						$tag_name = '_'.get_category_mime_type($enclosure['type']);
						if ($tag_name !== false) {
							$query_tags->execute();
						}
					}
				}
			}
		}
	}

	// Check favicons
	if ($check_favicons && !empty($favicons_to_check)) {
		$favicons = get_favicon($favicons_to_check)['favicons'];
		$query_favicon = $dbh->prepare('UPDATE feeds SET image=:image WHERE url=:url');
		$query_favicon->bindParam(':url', $url);
		$query_favicon->bindParam(':image', $image);
		foreach($favicons as $url->$favicon) {
			if(!empty($favicon[0]['favicon_url'])) {
				$image = $favicon[0]['favicon_url'];
				$query_favicon->execute();
			}
		}
	}

	// TODO : Remove old entries
	delete_old_entries();

	$dbh->commit();

	return $errors;
}


/**
 * Add feeds in the database and refresh them.
 *
 * @param $urls is an array of associative arrays {url, post} for each url. Post is a JSON array of POST data to send
 * @return errored urls in array
 */
function add_feeds($urls) {
	global $dbh;

	$errors = array();
	$added = array();
	$dbh->beginTransaction();
	$query = $dbh->prepare('INSERT OR IGNORE INTO feeds(url, title, has_user_title, post) VALUES(:url, :title, CASE WHEN :title="" THEN 0 ELSE 1 END, :post)');
	$query->bindParam(':url', $url);
	$query->bindParam(':title', $title);
	$query->bindParam(':post', $post);
	foreach($urls as $url_array) {
		$url = $url_array['url'];
		if (isset($url_array['post'])) {
			$post = $url_array['post'];
		}
		else {
			$post = '';
		}
		if (isset($url_array['title'])) {
			$title = $url_array['title'];
		}
		else {
			$title = '';
		}
		if (isset($url_array['tags'])) {
			$tags = $url_array['tags'];
		}
		else {
			$tags = array();
		}
		if (filter_var($url, FILTER_VALIDATE_URL)) {
			$query->execute();
			$added[] = array('id'=>$dbh->lastInsertId(), 'url'=>$url, 'post'=>$post, 'tags'=>$tags);
		}
		else {
			$errors[] = array('url'=>$url, 'msg'=>'Invalid URL');
		}
	}
	$dbh->commit();
	$errors_refresh = refresh_feeds($added, true);
	$errors_urls = array();
	foreach ($errors_refresh as $error) {
		delete_feed_url($error['url']);
		$errors_urls[] = $error['url'];
	}

	// Add feeds tags
	$dbh->beginTransaction();
	$query_insert_tag = $dbh->prepare('INSERT OR IGNORE INTO tags(name) VALUES(:name)');
	$query_insert_tag->bindParam(':name', $tag_name);

	// Register the tags of the feed
	$query_tags = $dbh->prepare('INSERT INTO tags_feeds(tag_id, feed_id, auto_added_tag) VALUES((SELECT id FROM tags WHERE name=:name), :feed_id, 0)');
	$query_tags->bindParam(':name', $tag_name);
	$query_tags->bindParam(':feed_id', $feed_id);

	foreach($added as $url_array) {
		if(in_array($url_array['url'], $errors_urls)) {
			continue;
		}

		$feed_id = $url_array['id'];
		foreach($url_array['tags'] as $tag_name) {
			$query_insert_tag->execute();
			$query_tags->execute();
		}
	}
	$dbh->commit();

	return array_merge($errors, $errors_refresh);
}


/**
 * Remove a feed and all associated tags / entries based on its id
 *
 * @param $id is the id of the feed to delete
 */
function delete_feed_id($id) {
	global $dbh;

	$query = $dbh->prepare('DELETE FROM feeds WHERE id=:id');
	$query->execute(array(':id'=>$id));
}


/**
 * Remove a feed and all associated tags / entries based on its url
 *
 * @param $url is the url of the feed to delete
 */
function delete_feed_url($url) {
	global $dbh;

	$query = $dbh->prepare('DELETE FROM feeds WHERE url=:url');
	$query->execute(array(':url'=>$url));
}


/**
 * Edit a feed in the database and refresh it.
 *
 * @param $old_url is the current URL of the feed
 * @param $new_url is the new URL to assign to this feed
 * @param $new_title (optionnal) is the new title of the feed
 * @return true upon success, false otherwise.
 * @todo  Edit more than just the URL
 */
function edit_feed($old_url, $new_url, $new_title='') {
	global $dbh;

	if (filter_var($new_url, FILTER_VALIDATE_URL) && filter_var($old_url, FILTER_VALIDATE_URL)) {
		$query = $dbh->prepare('UPDATE feeds SET url=:url WHERE url=:old_url');
		$query->execute(array(':old_url'=>$old_url, 'new_url'=>$new_url));

		if ($query->rowCount() == 0) {
			return false;
		}
		else {
			refresh_feeds(array($dbh->lastInsertId()=>$new_url));
			return true;
		}
	}
	else {
		return false;
	}
}


/**
 * Returns all the available feeds.
 *
 * @todo This function
 */
function get_feeds() {
	global $dbh;
	$query = $dbh->query('SELECT id, title, url, links, description, ttl, image, post FROM feeds');
	return $query->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Return a feed based on its id
 */
function get_feed($id) {
	global $dbh;
	$query = $dbh->prepare('SELECT id, title, url, links, description, ttl, image, post FROM feeds WHERE id=:id');
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute();
	$feed = $query->fetch(PDO::FETCH_ASSOC);

	$feed['links'] = json_decode($feed['links']);
	$feed['image'] = json_decode($feed['image']);
	$feed['post'] = json_decode($feed['post']);
	$feed['tags'] = array();

	$query = $dbh->prepare('SELECT tags.id, tags.name FROM tags INNER JOIN tags_feeds ON tags_feeds.tag_id=tags.id WHERE tags_feeds.feed_id=:id');
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute();
	$tags = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach($tags as $tag_id=>$tag) {
		$feed['tags'][$tag_id] = $tag;
	}

	return $feed;
}
