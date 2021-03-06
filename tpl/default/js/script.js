// == Init
	$(document).ready(function() {
		// svg fallback
		svgeezy.init(false, 'png');
		// modalbox autolaunch
		ModalboxAutoLaunch();

		// init touch actions
		init_touch();

		// init tab in settings
		init_tabs();

		$(".Toggle").addClass("closed");
		$(".Toggle-btn").click(function(ev) {
			$(this).parent().toggleClass("closed");
		});

		$(".Share-button").click(function(ev) {
			$(this).parents('.Article').find('.Article-share').toggle();
		});
	});

// == Functions
	/**
	 * Sends an ajax query to the target URL and run callback upon success.
	 *
	 * @param: caller is the caller element.
	 * @param: target is the URL to call
	 * @param: callback is a function to call upon successful AJAX call
	 */
	function ajax(caller, target, callback) {
		$.get(target, function(data) {
			if (data.status == 'OK') {
				callback(caller, data);
			}
		}, "json");
		return false;
	}


	/**
	 * Add a new tag to an entry via the form.
	 *
	 * @param: caller is the caller element
	 * @param: entry_id is the id of the entry to tag
	 * @param tag_baselink is the baselink for the tags (as in template)
	 */
	function tag_form(caller, entry_id, tag_baselink) {
		var tag_input = $('input[name="newTag"]', caller);
		var tag_value = tag_input.val();
		ajax(caller, '{base_url}api/tags.php?entry='+entry_id+'&tag='+tag_value, function(c, d) {
			tag_input.val("");
			var article = $(c).parents('.Article');
			$('ul', $(article)).append('<li class="TagList-completeTag CompleteTag"><a class="TagList-tagName TagName" href="'+tag_baselink+tag_value+'">'+tag_value+'</a></li>');
			$('.Side-tagList').append('<li class="TagList-completeTag CompleteTag"><a class="TagList-tagName TagName" href="'+tag_baselink+tag_value+'">'+tag_value+'</a></li>');
		});
		return false;
	}


	/**
	 * Add a tag to an entry
	 *
	 * @param: caller is the caller element
	 * @param: entry_id is the id of the entry to tag
	 * @param: tag_value is the tag to add
	 */
	function tag_entry(caller, entry_id, tag_value, overload_callback) {
		var callback;
		switch (tag_value) {
			case "_read":
				callback = function(c, d) {
					$(c).html("Unread");
					$(c).click(function () { untag_entry(c, entry_id, '_read') });

					// If on homepage and not feed view
					if ($(".UnreadNumber").length > 0) {
						$(c).parents('.Article').remove();

						var unread_items = parseInt($('#ItemsNumberCounter').html());
						if (unread_items >= 1) {
							$('#ItemsNumberCounter').html(unread_items - 1);
						}
						if (unread_items <= 2) {
							$('#ItemsNumberPlural').html('');
						}
					}

					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				}
				break;

			case "_sticky":
				callback = function(c, d) {
					$(c).html("Unstick");
					$(c).click(function() { untag_entry(c, entry_id, '_sticky') });

					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				}
				break;

			default:
				callback = function (c, d) {
					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				};
				break;
		}
		ajax(caller, '{$base_url}api/tags.php?entry='+entry_id+'&tag='+tag_value, callback);
	}


	/**
	 * Remove a tag on an entry
	 *
	 * @param: caller is the caller element
	 * @param: entry_id is the id of the entry to tag
	 * @param: tag_value is the tag to remove
	 */
	function untag_entry(caller, entry_id, tag_value, overload_callback) {
		var callback;
		switch (tag_value) {
			case "_read":
				callback = function(c, d) {
					$(c).html("Read");
					$(c).click(function () { tag_entry(c, entry_id, '_read') });

					if ($(".UnreadNumber").length > 0) {
						var unread_items = parseInt($('#ItemsNumberCounter').html());
						$('#ItemsNumberCounter').html(unread_items + 1);

						if (unread_items <= 1) {
							$('#ItemsNumberPlural').html('s');
						}
					}

					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				}
				break;

			case "_sticky":
				callback = function(c, d) {
					$(c).html("Stick");
					$(c).click(function () { tag_entry(c, entry_id, '_sticky') });

					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				}
				break;

			default:
				callback = function (c, d) {
					if (typeof(overload_callback) !== 'undefined') {
						overload_callback();
					}
				};
				break;
		}
		ajax(caller, '{$base_url}api/tags.php?entry='+entry_id+'&tag='+tag_value+'&remove=1', callback);
	}


	/**
	 * Mark all entries as read.
	 *
	 * @param: caller is the caller element
	 * @param: tag_value is the tag to add
	 */
	function tag_all(caller, tag_value) {
		ajax(caller, '{$base_url}api/tags.php?all=1&tag='+tag_value, function(c, d) {
			$('main article').remove();
			$('.ItemsNumber').hide();
		});
	}


// == Display Tags in the articles boxes

	/**
	 * Display the tag list and form in the article boxes
	 */
	$(".DisplayTagsButton").click(function(){
		$(this).siblings(".ArticleTagsList").slideToggle(200);
	});

// == Settings tabs
	/**
	 * Change tab on the settings page
	 */

	/**
	 * Set the view to the current tab
	 */
	function init_tabs() {
		if($(".TabContent").length > 0) {
			$(".currentTab").removeClass("currentTab");

			var toHide = $(".currentTabContent");
			toHide.toggle();
			toHide.removeClass("currentTabContent");

			var idToShow = window.location.hash;
			if (idToShow === "") {
				idToShow = '#' + $('.Tabs .OneTab-a:first-child').attr('data-targetid');
				window.location.hash = idToShow;
			}
			$(idToShow).parent().toggle();
			$(idToShow).parent().addClass("currentTabContent");

			$("a[data-targetid="+idToShow.substring(1)+"]").parent().addClass("currentTab");
		}
	}

	/**
	 * Handle actions on tab click
	 */
	function tabs_click() {
		//if current tab is clicked, don't do the hole process
		if ($(this).parent().hasClass("currentTab") == false) {
			$(".currentTab").removeClass("currentTab");
			$(this).parent().addClass("currentTab");

			var toHide = $(".currentTabContent");
			toHide.toggle();
			toHide.removeClass("currentTabContent");

			var idToShow = $(this).data("targetid");
			idToShow = "#"+idToShow; //who said it's ugly ?
			$(idToShow).parent().toggle();
			$(idToShow).parent().addClass("currentTabContent");
		}
	}
	$(".OneTab-a").click(tabs_click);

// == Modal Box

	/**
	 * Autolaunch of the modalbox at the page load if modalbox is not empty
	 */
	function ModalboxAutoLaunch() {
		if ($("#JsModalbox-content").html().length > 0) {
			ModalboxDisplay();
		}
	}

	/**
	 * Fill the modal box
	 * @param title The modalbox title
	 * @param content The modalbox content
	 */
	function ModalboxFill(title, content) {
		$("#JsModalbox-title").html(title);
		$("#JsModalbox-content").html(content);
		}

	/**
	 * Display the modal box
	 */
	function ModalboxDisplay() {
		$("#JsOverlay").show();
		$("#JsModalbox").show();

	}
	
	/**
	 * Close the modal box
	 */
	$("#JsModalbox-close").click(function(){
		ModalboxClose();
	});
	function ModalboxClose() {
		$("#JsOverlay").hide();
		$("#JsModalbox").hide();

	}

// == Touch moves
	/**
	 * Check wether the device has touch capabilities or not
	 */
	function is_touch_device() {
		return !!('ontouchstart' in window) // works on most browsers
			|| !!('onmsgesturechange' in window); // works on ie10
	};

	/**
	 * Init touch moves
	 */
	function init_touch() {
		if(is_touch_device() && $('.Article').length > 0) {
			var articles = document.querySelectorAll('.Article');
			for (var i = 0; i < articles.length; i++) {
				spawn_hammer(articles[i]);
			}
		}
	}

	/**
	 * Spawns hammer instances
	 */
	function spawn_hammer(el) {
		var mc = new Hammer(el, { multiUser: true });
		mc.on("panleft panright", slide_effect);
		mc.on('hammer.input', function(ev) {
			if(ev.isFinal) {
				slide_to_read(ev);
			}
		});
	}

	/**
	 * Handle slide effect
	 */
	function slide_effect(e) {
		e.preventDefault();
		target = $(e.target);
		if (!target.hasClass('.Article')) {
			target = target.parents('.Article');
		}

		if(Math.abs(e.deltaX) >= 2*Math.abs(e.deltaY)) {
			$(target).css('transform', 'translate('+e.deltaX+'px,0)');
			$(target).css('left', e.deltaX);
			$(target).css('opacity', 1-Math.abs(e.deltaX)/$(target).width())
		}
		return false;
	}

	/**
	 * "Slide to mark read / unread" event handler
	 */
	function slide_to_read(e) {
		target = $(e.target);
		if (!target.hasClass('.Article')) {
			target = target.parents('.Article');
		}

		if(Math.abs(e.deltaX) > $(target).width() / 2) {
			tag_entry($('.Controls .Read-button', target), target.attr('id'), '_read');
		}
		$(target).css('transform', 'translate(0,0)');
		$(target).css('opacity', '1');
		return false;
	}


// == Options
if ({$config->open_items_new_tab} > 0) {
	$(".ArticleContent h1:first-child a").click(function () {
		console.log($('.Controls .Read-button', $(this).parents(".Article")).trigger("click"));
	});
}
