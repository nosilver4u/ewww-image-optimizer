/*globals jQuery,Window,HTMLElement,HTMLDocument,HTMLCollection,NodeList,MutationObserver */
/*exported Arrive*/
/*jshint latedef:false */

/*
 * arrive.js
 * v2.4.1
 * https://github.com/uzairfarooq/arrive
 * MIT licensed
 *
 * Copyright (c) 2014-2017 Uzair Farooq
 */
var Arrive = (function(window, $, undefined) {

        "use strict";

        if(!window.MutationObserver || typeof HTMLElement === 'undefined'){
                return; //for unsupported browsers
        }

        var arriveUniqueId = 0;

        var utils = (function() {
                var matches = HTMLElement.prototype.matches || HTMLElement.prototype.webkitMatchesSelector || HTMLElement.prototype.mozMatchesSelector
                || HTMLElement.prototype.msMatchesSelector;

                return {
                        matchesSelector: function(elem, selector) {
                                return elem instanceof HTMLElement && matches.call(elem, selector);
                        },
                        // to enable function overloading - By John Resig (MIT Licensed)
                        addMethod: function (object, name, fn) {
                                var old = object[ name ];
                                object[ name ] = function(){
                                        if ( fn.length == arguments.length ) {
                                                return fn.apply( this, arguments );
                                        }
                                        else if ( typeof old == 'function' ) {
                                                return old.apply( this, arguments );
                                        }
                                };
                        },
                        callCallbacks: function(callbacksToBeCalled, registrationData) {
                                if (registrationData && registrationData.options.onceOnly && registrationData.firedElems.length == 1) {
                                        // as onlyOnce param is true, make sure we fire the event for only one item
                                        callbacksToBeCalled = [callbacksToBeCalled[0]];
                                }

                                for (var i = 0, cb; (cb = callbacksToBeCalled[i]); i++) {
                                        if (cb && cb.callback) {
                                                cb.callback.call(cb.elem, cb.elem);
                                        }
                                }

                                if (registrationData && registrationData.options.onceOnly && registrationData.firedElems.length == 1) {
                                        // unbind event after first callback as onceOnly is true.
                                        registrationData.me.unbindEventWithSelectorAndCallback.call(
                                                registrationData.target, registrationData.selector, registrationData.callback
                                        );
                                }
                        },
                        // traverse through all descendants of a node to check if event should be fired for any descendant
                        checkChildNodesRecursively: function(nodes, registrationData, matchFunc, callbacksToBeCalled) {
                                // check each new node if it matches the selector
                                for (var i=0, node; (node = nodes[i]); i++) {
                                        if (matchFunc(node, registrationData, callbacksToBeCalled)) {
                                                callbacksToBeCalled.push({ callback: registrationData.callback, elem: node });
                                        }

                                        if (node.childNodes.length > 0) {
                                                utils.checkChildNodesRecursively(node.childNodes, registrationData, matchFunc, callbacksToBeCalled);
                                        }
                                }
                        },
                        mergeArrays: function(firstArr, secondArr){
                                // Overwrites default options with user-defined options.
                                var options = {},
                                attrName;
                                for (attrName in firstArr) {
                                        if (firstArr.hasOwnProperty(attrName)) {
                                                options[attrName] = firstArr[attrName];
                                        }
                                }
                                for (attrName in secondArr) {
                                        if (secondArr.hasOwnProperty(attrName)) {
                                                options[attrName] = secondArr[attrName];
                                        }
                                }
                                return options;
                        },
                        toElementsArray: function (elements) {
                                // check if object is an array (or array like object)
                                // Note: window object has .length property but it's not array of elements so don't consider it an array
                                if (typeof elements !== 'undefined' && (typeof elements.length !== 'number' || elements === window)) {
                                        elements = [elements];
                                }
                                return elements;
                        }
                };
        })();


        // Class to maintain state of all registered events of a single type
        var EventsBucket = (function() {
                var EventsBucket = function() {
                        // holds all the events

                        this._eventsBucket    = [];
                        // function to be called while adding an event, the function should do the event initialization/registration
                        this._beforeAdding    = null;
                        // function to be called while removing an event, the function should do the event destruction
                        this._beforeRemoving  = null;
                };

                EventsBucket.prototype.addEvent = function(target, selector, options, callback) {
                        var newEvent = {
                                target:             target,
                                selector:           selector,
                                options:            options,
                                callback:           callback,
                                firedElems:         []
                        };

                        if (this._beforeAdding) {
                                this._beforeAdding(newEvent);
                        }

                        this._eventsBucket.push(newEvent);
                        return newEvent;
                };

                EventsBucket.prototype.removeEvent = function(compareFunction) {
                        for (var i=this._eventsBucket.length - 1, registeredEvent; (registeredEvent = this._eventsBucket[i]); i--) {
                                if (compareFunction(registeredEvent)) {
                                        if (this._beforeRemoving) {
                                                this._beforeRemoving(registeredEvent);
                                        }

                                        // mark callback as null so that even if an event mutation was already triggered it does not call callback
                                        var removedEvents = this._eventsBucket.splice(i, 1);
                                        if (removedEvents && removedEvents.length) {
                                                removedEvents[0].callback = null;
                                        }
                                }
                        }
                };

                EventsBucket.prototype.beforeAdding = function(beforeAdding) {
                        this._beforeAdding = beforeAdding;
                };

                EventsBucket.prototype.beforeRemoving = function(beforeRemoving) {
                        this._beforeRemoving = beforeRemoving;
                };

                return EventsBucket;
        })();


        /**
        * @constructor
        * General class for binding/unbinding arrive and leave events
        */
        var MutationEvents = function(getObserverConfig, onMutation) {
                var eventsBucket    = new EventsBucket(),
                me              = this;

                var defaultOptions = {
                        fireOnAttributesModification: false
                };

                // actual event registration before adding it to bucket
                eventsBucket.beforeAdding(function(registrationData) {
                        var
                        target    = registrationData.target,
                        observer;

                        // mutation observer does not work on window or document
                        if (target === window.document || target === window) {
                                target = document.getElementsByTagName("html")[0];
                        }

                        // Create an observer instance
                        observer = new MutationObserver(function(e) {
                                onMutation.call(this, e, registrationData);
                        });

                        var config = getObserverConfig(registrationData.options);

                        observer.observe(target, config);

                        registrationData.observer = observer;
                        registrationData.me = me;
                });

                // cleanup/unregister before removing an event
                eventsBucket.beforeRemoving(function (eventData) {
                        eventData.observer.disconnect();
                });

                this.bindEvent = function(selector, options, callback) {
                        options = utils.mergeArrays(defaultOptions, options);

                        var elements = utils.toElementsArray(this);

                        for (var i = 0; i < elements.length; i++) {
                                eventsBucket.addEvent(elements[i], selector, options, callback);
                        }
                };

                this.unbindEvent = function() {
                        var elements = utils.toElementsArray(this);
                        eventsBucket.removeEvent(function(eventObj) {
                                for (var i = 0; i < elements.length; i++) {
                                        if (this === undefined || eventObj.target === elements[i]) {
                                                return true;
                                        }
                                }
                                return false;
                        });
                };

                this.unbindEventWithSelectorOrCallback = function(selector) {
                        var elements = utils.toElementsArray(this),
                        callback = selector,
                        compareFunction;

                        if (typeof selector === "function") {
                                compareFunction = function(eventObj) {
                                        for (var i = 0; i < elements.length; i++) {
                                                if ((this === undefined || eventObj.target === elements[i]) && eventObj.callback === callback) {
                                                        return true;
                                                }
                                        }
                                        return false;
                                };
                        }
                        else {
                                compareFunction = function(eventObj) {
                                        for (var i = 0; i < elements.length; i++) {
                                                if ((this === undefined || eventObj.target === elements[i]) && eventObj.selector === selector) {
                                                        return true;
                                                }
                                        }
                                        return false;
                                };
                        }
                        eventsBucket.removeEvent(compareFunction);
                };

                this.unbindEventWithSelectorAndCallback = function(selector, callback) {
                        var elements = utils.toElementsArray(this);
                        eventsBucket.removeEvent(function(eventObj) {
                                for (var i = 0; i < elements.length; i++) {
                                        if ((this === undefined || eventObj.target === elements[i]) && eventObj.selector === selector && eventObj.callback === callback) {
                                                return true;
                                        }
                                }
                                return false;
                        });
                };

                return this;
        };


        /**
        * @constructor
        * Processes 'arrive' events
        */
        var ArriveEvents = function() {
                // Default options for 'arrive' event
                var arriveDefaultOptions = {
                        fireOnAttributesModification: false,
                        onceOnly: false,
                        existing: false
                };

                function getArriveObserverConfig(options) {
                        var config = {
                                attributes: false,
                                childList: true,
                                subtree: true
                        };

                        if (options.fireOnAttributesModification) {
                                config.attributes = true;
                        }

                        return config;
                }

                function onArriveMutation(mutations, registrationData) {
                        mutations.forEach(function( mutation ) {
                                var newNodes    = mutation.addedNodes,
                                targetNode = mutation.target,
                                callbacksToBeCalled = [],
                                node;

                                // If new nodes are added
                                if( newNodes !== null && newNodes.length > 0 ) {
                                        utils.checkChildNodesRecursively(newNodes, registrationData, nodeMatchFunc, callbacksToBeCalled);
                                }
                                else if (mutation.type === "attributes") {
                                        if (nodeMatchFunc(targetNode, registrationData, callbacksToBeCalled)) {
                                                callbacksToBeCalled.push({ callback: registrationData.callback, elem: targetNode });
                                        }
                                }

                                utils.callCallbacks(callbacksToBeCalled, registrationData);
                        });
                }

                function nodeMatchFunc(node, registrationData, callbacksToBeCalled) {
                        // check a single node to see if it matches the selector
                        if (utils.matchesSelector(node, registrationData.selector)) {
                                if(node._id === undefined) {
                                        node._id = arriveUniqueId++;
                                }
                                // make sure the arrive event is not already fired for the element
                                if (registrationData.firedElems.indexOf(node._id) == -1) {
                                        registrationData.firedElems.push(node._id);

                                        return true;
                                }
                        }

                        return false;
                }

                arriveEvents = new MutationEvents(getArriveObserverConfig, onArriveMutation);

                var mutationBindEvent = arriveEvents.bindEvent;

                // override bindEvent function
                arriveEvents.bindEvent = function(selector, options, callback) {

                        if (typeof callback === "undefined") {
                                callback = options;
                                options = arriveDefaultOptions;
                        } else {
                                options = utils.mergeArrays(arriveDefaultOptions, options);
                        }

                        var elements = utils.toElementsArray(this);

                        if (options.existing) {
                                var existing = [];

                                for (var i = 0; i < elements.length; i++) {
                                        var nodes = elements[i].querySelectorAll(selector);
                                        for (var j = 0; j < nodes.length; j++) {
                                                existing.push({ callback: callback, elem: nodes[j] });
                                        }
                                }

                                // no need to bind event if the callback has to be fired only once and we have already found the element
                                if (options.onceOnly && existing.length) {
                                        return callback.call(existing[0].elem, existing[0].elem);
                                }

                                setTimeout(utils.callCallbacks, 1, existing);
                        }

                        mutationBindEvent.call(this, selector, options, callback);
                };

                return arriveEvents;
        };


        /**
        * @constructor
        * Processes 'leave' events
        */
        var LeaveEvents = function() {
                // Default options for 'leave' event
                var leaveDefaultOptions = {};

                function getLeaveObserverConfig() {
                        var config = {
                                childList: true,
                                subtree: true
                        };

                        return config;
                }

                function onLeaveMutation(mutations, registrationData) {
                        mutations.forEach(function( mutation ) {
                                var removedNodes  = mutation.removedNodes,
                                callbacksToBeCalled = [];

                                if( removedNodes !== null && removedNodes.length > 0 ) {
                                        utils.checkChildNodesRecursively(removedNodes, registrationData, nodeMatchFunc, callbacksToBeCalled);
                                }

                                utils.callCallbacks(callbacksToBeCalled, registrationData);
                        });
                }

                function nodeMatchFunc(node, registrationData) {
                        return utils.matchesSelector(node, registrationData.selector);
                }

                leaveEvents = new MutationEvents(getLeaveObserverConfig, onLeaveMutation);

                var mutationBindEvent = leaveEvents.bindEvent;

                // override bindEvent function
                leaveEvents.bindEvent = function(selector, options, callback) {

                        if (typeof callback === "undefined") {
                                callback = options;
                                options = leaveDefaultOptions;
                        } else {
                                options = utils.mergeArrays(leaveDefaultOptions, options);
                        }

                        mutationBindEvent.call(this, selector, options, callback);
                };

                return leaveEvents;
        };


        var arriveEvents = new ArriveEvents(),
        leaveEvents  = new LeaveEvents();

        function exposeUnbindApi(eventObj, exposeTo, funcName) {
                // expose unbind function with function overriding
                utils.addMethod(exposeTo, funcName, eventObj.unbindEvent);
                utils.addMethod(exposeTo, funcName, eventObj.unbindEventWithSelectorOrCallback);
                utils.addMethod(exposeTo, funcName, eventObj.unbindEventWithSelectorAndCallback);
        }

        /*** expose APIs ***/
        function exposeApi(exposeTo) {
                exposeTo.arrive = arriveEvents.bindEvent;
                exposeUnbindApi(arriveEvents, exposeTo, "unbindArrive");

                exposeTo.leave = leaveEvents.bindEvent;
                exposeUnbindApi(leaveEvents, exposeTo, "unbindLeave");
        }

        exposeApi(HTMLElement.prototype);
        exposeApi(NodeList.prototype);
        exposeApi(HTMLCollection.prototype);
        exposeApi(HTMLDocument.prototype);
        exposeApi(Window.prototype);

        var Arrive = {};
        // expose functions to unbind all arrive/leave events
        exposeUnbindApi(arriveEvents, Arrive, "unbindAllArrive");
        exposeUnbindApi(leaveEvents, Arrive, "unbindAllLeave");

        return Arrive;

})(window, null, undefined);

var ewww_webp_supported = false;
// webp detection adapted from https://developers.google.com/speed/webp/faq#how_can_i_detect_browser_support_using_javascript
function check_webp_feature(feature, callback) {
	if (ewww_webp_supported) {
                callback(ewww_webp_supported);
		return;
	}
        var kTestImages = {
                alpha: "UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",
                animation: "UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"
        };
        var img = new Image();
        img.onload = function () {
                ewww_webp_supported = (img.width > 0) && (img.height > 0);
                callback(ewww_webp_supported);
        };
        img.onerror = function () {
                callback(false);
        };
        img.src = "data:image/webp;base64," + kTestImages[feature];
}
function ewwwLoadImages(ewww_webp_supported) {
	if (ewww_webp_supported) {
		var nggImages = document.querySelectorAll('.batch-image img, .image-wrapper a, .ngg-pro-masonry-item a, .ngg-galleria-offscreen-seo-wrapper a');
		for (var i = 0, len = nggImages.length; i < len; i++){
                        ewwwAttr(nggImages[i], 'data-src', nggImages[i].getAttribute('data-webp'));
                        ewwwAttr(nggImages[i], 'data-thumbnail', nggImages[i].getAttribute('data-webp-thumbnail'));
		}
		var revImages = document.querySelectorAll('.rev_slider ul li');
		for (var i = 0, len = revImages.length; i < len; i++){
			ewwwAttr(revImages[i], 'data-thumb', revImages[i].getAttribute('data-webp-thumb'));
			var param_num = 1;
			while ( param_num < 11 ) {
                                ewwwAttr(revImages[i], 'data-param' + param_num, revImages[i].getAttribute('data-webp-param' + param_num));
				param_num++;
			}
		}
		var revImages = document.querySelectorAll('.rev_slider img');
		for (var i = 0, len = revImages.length; i < len; i++){
			ewwwAttr(revImages[i], 'data-lazyload', revImages[i].getAttribute('data-webp-lazyload'));
		}
                var wooImages = document.querySelectorAll('div.woocommerce-product-gallery__image');
		for (var i = 0, len = wooImages.length; i < len; i++){
			ewwwAttr(wooImages[i], 'data-thumb', wooImages[i].getAttribute('data-webp-thumb'));
		}
	}
        var videos = document.querySelectorAll('video');
	for (var i = 0, len = videos.length; i < len; i++){
		if (ewww_webp_supported) {
			ewwwAttr(videos[i], 'poster', videos[i].getAttribute('data-poster-webp'));
                } else {
			ewwwAttr(videos[i], 'poster', videos[i].getAttribute('data-poster-image'));
                }
	}
        var lazies = document.querySelectorAll('img.ewww_webp_lazy_load');
	for (var i = 0, len = lazies.length; i < len; i++){
		console.log('parsing an image: ' + lazies[i].getAttribute('data-src'));
		if (ewww_webp_supported) {
			console.log('webp good');
			ewwwAttr(lazies[i], 'data-lazy-srcset', lazies[i].getAttribute('data-lazy-srcset-webp'));
			ewwwAttr(lazies[i], 'data-srcset', lazies[i].getAttribute('data-srcset-webp'));
			ewwwAttr(lazies[i], 'data-lazy-src', lazies[i].getAttribute('data-lazy-src-webp'));
			ewwwAttr(lazies[i], 'data-src', lazies[i].getAttribute('data-src-webp'));
                        ewwwAttr(lazies[i], 'data-orig-file', lazies[i].getAttribute('data-webp-orig-file'));
                        ewwwAttr(lazies[i], 'data-medium-file', lazies[i].getAttribute('data-webp-medium-file'));
                        ewwwAttr(lazies[i], 'data-large-file', lazies[i].getAttribute('data-webp-large-file'));
                        var jpsrcset = lazies[i].getAttribute('srcset');
                        if (jpsrcset != null && jpsrcset !== false && jpsrcset.includes('R0lGOD')) {
                                ewwwAttr(lazies[i], 'src', lazies[i].getAttribute('data-lazy-src-webp'));
                        }
		}
		lazies[i].className = lazies[i].className.replace(/\bewww_webp_lazy_load\b/, '');
	}
        var elems = document.querySelectorAll('.ewww_webp');
	for (var i = 0, len = elems.length; i < len; i++){
		console.log('parsing an image: ' + elems[i].getAttribute('data-src'));
		if (ewww_webp_supported) {
			ewwwAttr(elems[i], 'srcset', elems[i].getAttribute('data-srcset-webp'));
			ewwwAttr(elems[i], 'src', elems[i].getAttribute('data-src-webp'));
                        ewwwAttr(elems[i], 'data-orig-file', elems[i].getAttribute('data-webp-orig-file'));
                        ewwwAttr(elems[i], 'data-medium-file', elems[i].getAttribute('data-webp-medium-file'));
                        ewwwAttr(elems[i], 'data-large-file', elems[i].getAttribute('data-webp-large-file'));
                        ewwwAttr(elems[i], 'data-large_image', elems[i].getAttribute('data-webp-large_image'));
                        ewwwAttr(elems[i], 'data-src', elems[i].getAttribute('data-webp-src'));
		} else {
                        ewwwAttr(elems[i], 'srcset', elems[i].getAttribute('data-srcset-img'));
                        ewwwAttr(elems[i], 'src', elems[i].getAttribute('data-src-img'));
		}
		elems[i].className = elems[i].className.replace(/\bewww_webp\b/, 'ewww_webp_loaded');
	}
  	if (window.jQuery && jQuery.fn.isotope && jQuery.fn.imagesLoaded) {
		jQuery('.fusion-posts-container-infinite').imagesLoaded( function() {
			if ( jQuery( '.fusion-posts-container-infinite' ).hasClass( 'isotope' ) ) {
				jQuery( '.fusion-posts-container-infinite' ).isotope();
			}
		});
		jQuery('.fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper').imagesLoaded( function() {
			jQuery( '.fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper' ).isotope();
		});
	}
}
check_webp_feature('alpha', ewwwWebPInit);
function ewwwWebPInit(ewww_webp_supported) {
        ewwwLoadImages(ewww_webp_supported);
        ewwwNggLoadGalleries(ewww_webp_supported);
        document.arrive('.ewww_webp', function() {
                ewwwLoadImages(ewww_webp_supported);
        });
        document.arrive('.ewww_webp_lazy_load', function() {
                ewwwLoadImages(ewww_webp_supported);
        });
        document.arrive('videos', function() {
                ewwwLoadImages(ewww_webp_supported);
        });
        if (document.readyState == 'loading') {
		console.log('deferring ewwwJSONParserInit until DOMContentLoaded')
		document.addEventListener('DOMContentLoaded', ewwwJSONParserInit);
        } else {
		console.log(document.readyState);
		console.log('running JSON parsers post haste')
        	if ( typeof galleries !== 'undefined' ) {
			console.log('galleries found, parsing')
                	ewwwNggParseGalleries(ewww_webp_supported);
		}
        	ewwwWooParseVariations(ewww_webp_supported);
	}
}
function ewwwAttr(elem, attr, value) {
        if (value != null && value !== false) {
                elem.setAttribute(attr, value);
        }
}
function ewwwJSONParserInit() {
        if ( typeof galleries !== 'undefined' ) {
		check_webp_feature('alpha', ewwwNggParseGalleries);
	}
	check_webp_feature('alpha', ewwwWooParseVariations);
}
function ewwwWooParseVariations(ewww_webp_supported) {
	if (!ewww_webp_supported) {
		return;
	}
        var elems = document.querySelectorAll('form.variations_form');
	for (var i = 0, len = elems.length; i < len; i++){
		var variations = elems[i].getAttribute('data-product_variations');
		var variations_changed = false;
		try {
			variations = JSON.parse(variations);
			//console.log(variations);
			console.log('parsing WC variations');
			for ( var num in variations ) {
                                if (variations[ num ] !== undefined && variations[ num ].image !== undefined) {
					console.log(variations[num].image);
					if (variations[num].image.src_webp !== undefined) {
						variations[num].image.src = variations[num].image.src_webp;
						variations_changed = true;
					}
					if (variations[num].image.srcset_webp !== undefined) {
						variations[num].image.srcset = variations[num].image.srcset_webp;
						variations_changed = true;
					}
					if (variations[num].image.full_src_webp !== undefined) {
						variations[num].image.full_src = variations[num].image.full_src_webp;
						variations_changed = true;
					}
					if (variations[num].image.gallery_thumbnail_src_webp !== undefined) {
						variations[num].image.gallery_thumbnail_src = variations[num].image.gallery_thumbnail_src_webp;
						variations_changed = true;
					}
					if (variations[num].image.thumb_src_webp !== undefined) {
						variations[num].image.thumb_src = variations[num].image.thumb_src_webp;
						variations_changed = true;
					}
				}
			}
			if (variations_changed) {
                                ewwwAttr(elems[i], 'data-product_variations', JSON.stringify(variations));
			}
		} catch (err) {
			console.log(err);
			console.log(response);
		}
	}
}
function ewwwNggParseGalleries(ewww_webp_supported) {
        if (ewww_webp_supported) {
                for(var galleryIndex in galleries) {
                        var gallery = galleries[galleryIndex];
                        galleries[galleryIndex].images_list = ewwwNggParseImageList(gallery.images_list);
                }
        }
}
function ewwwNggLoadGalleries(ewww_webp_supported) {
        if (ewww_webp_supported) {
                document.addEventListener('ngg.galleria.themeadded', function(event, themename){
                        window.ngg_galleria._create_backup = window.ngg_galleria.create;
                        window.ngg_galleria.create = function(gallery_parent, themename) {
                                var gallery_id = $(gallery_parent).data('id');
                                galleries['gallery_' + gallery_id].images_list = ewwwNggParseImageList(galleries['gallery_' + gallery_id].images_list);
                                return window.ngg_galleria._create_backup(gallery_parent, themename);
                        };
                });
        }
}
function ewwwNggParseImageList(images_list) {
        console.log('parsing gallery images');
        for(var nggIndex in images_list) {
                var nggImage = images_list[nggIndex];
                if (typeof nggImage['image-webp'] !== typeof undefined) {
                        images_list[nggIndex]['image'] = nggImage['image-webp'];
                        delete images_list[nggIndex]['image-webp'];
                }
                if (typeof nggImage['thumb-webp'] !== typeof undefined) {
                        images_list[nggIndex]['thumb'] = nggImage['thumb-webp'];
                        delete images_list[nggIndex]['thumb-webp'];
                }
                if (typeof nggImage['full_image_webp'] !== typeof undefined) {
                        images_list[nggIndex]['full_image'] = nggImage['full_image_webp'];
                        delete images_list[nggIndex]['full_image_webp'];
                }
                if (typeof nggImage['srcsets'] !== typeof undefined) {
                        for(var nggSrcsetIndex in nggImage['srcsets']) {
                                nggSrcset = nggImage['srcsets'][nggSrcsetIndex];
                                if (typeof nggImage['srcsets'][nggSrcsetIndex + '-webp'] !== typeof undefined) {
                                        images_list[nggIndex]['srcsets'][nggSrcsetIndex] = nggImage['srcsets'][nggSrcsetIndex + '-webp'];
                                        delete images_list[nggIndex]['srcsets'][nggSrcsetIndex + '-webp'];
                                }
                        }
                }
                if (typeof nggImage['full_srcsets'] !== typeof undefined) {
                        for(var nggFSrcsetIndex in nggImage['full_srcsets']) {
                                nggFSrcset = nggImage['full_srcsets'][nggFSrcsetIndex];
                                if (typeof nggImage['full_srcsets'][nggFSrcsetIndex + '-webp'] !== typeof undefined) {
                                        images_list[nggIndex]['full_srcsets'][nggFSrcsetIndex] = nggImage['full_srcsets'][nggFSrcsetIndex + '-webp'];
                                        delete images_list[nggIndex]['full_srcsets'][nggFSrcsetIndex + '-webp'];
                                }
                        }
                }
        }
        return images_list;
}
