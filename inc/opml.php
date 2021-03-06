<?php
/** Freeder
 *  -------
 *  @file
 *  @copyright Copyright (c) 2014 Freeder, MIT License, See the LICENSE file for copying permissions.
 *  @brief Functions to handle the OPML files
 */

require_once(INC_DIR . 'functions.php');


/**
 * Generate an OPML file to export the feeds.
 *
 * @copyright Heavily based on a function from FreshRSS.
 */
function opml_export($feeds) {
	$tags = array();
	foreach ($feeds as $key=>$feed) {
		if (empty($feed['tags'])) {
			$tags['untagged'][] = $key;
			continue;
		}
		foreach ($feed['tags'] as $tag) {
			$tags[$tag][] = $key;
		}
	}

	$now = new Datetime();
	$txt = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$txt .= '<opml version="2.0">'.PHP_EOL;
	$txt .= "\t".'<head>'.PHP_EOL;
	$txt .= "\t\t".'<title>Export of Freeder feeds</title>'.PHP_EOL;
	$txt .= "\t\t".'<dateCreated>'.$now->format(DateTime::RFC822).'</dateCreated>'.PHP_EOL;
	$txt .= "\t".'</head>'.PHP_EOL;
	$txt .= "\t".'<body>'.PHP_EOL;

	foreach ($tags as $tag=>$id_feeds) {
		$txt .= "\t\t".'<outline text="'.$tag.'">'.PHP_EOL;

		foreach ($id_feeds as $id_feed) {
			$website = multiarray_search('rel', 'alternate', $feeds[$id_feed]['links'], '');
			if (!empty($website)) {
				$website = 'htmlUrl="'.htmlspecialchars($website['href'], ENT_COMPAT, 'UTF-8').'"';
			}
			$txt .= "\t\t\t".'<outline text="'.htmlspecialchars($feeds[$id_feed]['title'], ENT_COMPAT, 'UTF-8').'" type="rss" xmlUrl="'.htmlspecialchars($feeds[$id_feed]['url'], ENT_COMPAT, 'UTF-8').'" '.$website.' description="'.htmlspecialchars($feeds[$id_feed]['description'], ENT_COMPAT, 'UTF-8').'" />'.PHP_EOL;
		}

		$txt .= "\t\t".'</outline>'.PHP_EOL;
	}

	$txt .= "\t".'</body>'.PHP_EOL;
	$txt .= '</opml>';

	return $txt;
}


/**
 * Parse an OPML file.
 *
 * @return An array of associative array for each feed with URL, title and associated tags.
 * @copyright Heavily based on a function from FreshRSS.
 */
function opml_import($xml) {
	$opml = simplexml_load_string($xml);

	if (!$opml) {
		return false;
	}

	$categories = array ();
	$feeds = array ();

	foreach ($opml->body->outline as $outline) {
		if (!isset ($outline['xmlUrl'])) {  // Folder
			$tag = '';

			if (isset ($outline['text'])) {
				$tag = (string) $outline['text'];
			}
			elseif (isset ($outline['title'])) {
				$tag = (string) $outline['title'];
			}

			if ($tag) {
				foreach($outline->outline as $feed) {
					if(!isset($feed['xmlUrl'])) {
						continue;
					}

					$search = multiarray_search_key('url', (string) $feed['xmlUrl'], $feeds);
					if($search === -1) {
						// Feed was not yet encountered, so add it first
						if(isset($feed['title'])) {
							$feed_title = (string) $feed['title'];
						}
						elseif(isset($feed['text'])) {
							$feed_title = (string) $feed['text'];
						}
						else {
							$feed_title = '';
						}

						$feeds[] = array(
							'url'=>(string) $feed['xmlUrl'],
							'title'=>$feed_title,
							'tags'=>array(),
							'post'=>''
						);
						$search = count($feeds) - 1;
					}
					$feeds[$search]['tags'][] = $tag;
				}
			}
		}
		else {  // This is a RSS feed without any folder
			if(isset($outline['title'])) {
				$title = (string) $outline['title'];
			}
			elseif(isset($outline['text'])) {
				$title = (string) $outline['text'];
			}
			else {
				$title = '';
			}

			if(multiarray_search_key('url', (string) $outline['xmlUrl'], $feeds) !== -1) {
				$feeds[] = array(
					'url'=>(string) $outline['xmlUrl'],
					'title'=>$title,
					'tags'=>array(),
					'post'=>''
				);
			}
		}
	}

	return $feeds;
}
