
'use strict';

(function (window, $) {
    if(window.changetip) {
        return console.error('changetip.js already loaded.')
    }
    var changetip = window.changetip = pez.bind({});
    changetip.origin = 'https://www.changetip.com';

    // debugging/logging
    // changetip.debug = true;
     if(changetip.debug) {
        changetip.origin = 'http://localhost:8000';
     }

    changetip.log = function() {
        if(changetip.debug && console.log) {
            console.log.apply(console, arguments);
        }
    };
    changetip.log('debugging turned on.');

    //options
    changetip.options = {};
    $(function() {
       $('head meta[property^="changetip:"]').each(function() {
          var $meta = $(this);
          var metaProperty = $meta.attr('property');
          var metaContent = $meta.attr('content');
          changetip.options[metaProperty.split(':')[1]] = JSON.parse(metaContent);
       });
       changetip.log('changetip options:');
       changetip.log(changetip.options);
    });

    //iframe listening
    changetip._listener = function(e) {
        if(e.origin == changetip.origin) {
            changetip.log('iframe message received:');
            changetip.log(e);
            changetip.did('message', e);
        }
    };
    window.addEventListener('message', changetip._listener);

    //iframe messaging
    changetip._receivers = changetip._receivers || [];
    changetip.listenTo = function(receiverName, receiver) {
        changetip._receivers[receiverName] = receiver;
    };
    changetip.message = function(receiverName, data) {
        var receiver = changetip._receivers[receiverName];
        if(receiver) {
            changetip.log('sent message to ' + receiverName + ': ');
            changetip.log(data);
            receiver.postMessage(data, changetip.origin);
        } else {
            changetip.log('could not find the window you\'re trying to message: ' + receiverName);
        }
    };

    //popup, still used for registration
    changetip.popupId = 'changetip-popup';
    changetip.$popup = function() {
        return $('#' + changetip.popupId);
    };
    changetip.popupUrls = {
        register: changetip.origin + '/developers/applications/q5TL7owpVHBSHFXAoX8hPm?nonav=1'
    };
    changetip.getPopupUrl = function(urlName, vars) {
        var url = changetip.popupUrls[urlName];
        if(!url) {
            return null;
        }
        var varRegex = new RegExp('\{([^\}]+)\}', 'g');
        var match = varRegex.exec(url);
        while(match != null) {
            var key = match[1];
            var value = null;
            if(vars && vars[key]) {
                value = vars[key];
            } else {
                value = changetip.options[key] || '';
            }
            url = url.replace(match[0], value);
            match = varRegex.exec(url);
        }
        return url;
    };
    changetip.popup = function(iframeSrc, callback, iframeOptions) {
        changetip.$popup().remove();
        var $body = $('body');
        var iframeName = iframeSrc;
        if(changetip.popupUrls[iframeName]) {
            iframeSrc = changetip.getPopupUrl(iframeName, iframeOptions);
        }
        var $popup = $('<div />')
            .attr('id', 'changetip-popup')
            .appendTo($body)
            .height($body.height());
        var $wrap = $('<div />')
            .addClass('changetip-iframe')
            .appendTo($popup);
        var $iframe = $('<iframe />')
            .attr('src', iframeSrc)
            .attr('allowFullScreen', '')
            .appendTo($wrap);
        $popup.fadeIn().click(function (e) {
            changetip.closePopup();
        });
        $iframe.load(function(){
            var iframe = $(this).get(0);
            var win = iframe.contentWindow;
            changetip.listenTo(iframeName, win);

            //can't do these:
            //alert(win.document.body.offsetHeight);
            //alert($(this).contents().height());

            if(callback) {
                if(typeof(callback) === 'function') {
                    callback();
                } else {
                    changetip.message(iframeName, callback);
                }
            }
        });
    };

    changetip.closePopup = function() {
        changetip.$popup().fadeOut();
    };


    // pop over, used for tipping in comments
    changetip.popupOverId = 'changetip-popover';
    changetip.$popover = function() {
        return $('#' + changetip.popupOverId);
    };
    changetip.popover = function(iframeSrc, callback, iframeOptions) {
        // only expecting one button so far
        $(".changetip_tipme_button").each(function(idx, button) {
            var uid = button.getAttribute("data-uid");
            var buttonId = button.getAttribute("data-bid");

            // use exposed methods from widget.js
            var popoverIframe = Changetip.widget.buildPopoverIframe();
            popoverIframe.id = "changetip-popover";
            $('body').prepend(popoverIframe);

            popoverIframe.onload = function() {
                Changetip.widget.renderPopoverIframe(popoverIframe); // default styling
                // additional styling:
                popoverIframe.style.top = "10%";
                // horizontal center + half the width of popover
                popoverIframe.style.left = "50%";
                popoverIframe.style.marginLeft = "-200px";
                // static during scrolling
                popoverIframe.style.position = "fixed";
                popoverIframe.style.zIndex = 999999; // keep any tip buttons on the blog from leaking through
                // let widget know we're ready
                popoverIframe.contentWindow.postMessage({open:true, meta:callback}, "*");
            };

            // set src of iframe and load the popover
            Changetip.widget.loadWordPressPopOver(popoverIframe, uid, buttonId);

            // check for different messages from the popover
            window.addEventListener("message", function(msg) {
                if (msg.origin != changetip.origin) {
                    return;
                }
                // Only message so far is if the user just completed log-in flow
                if(msg.data.loggedin) {
                    Changetip.widget.loadWordPressPopOver(popoverIframe, uid, buttonId);
                }
            });

        });
    };

    changetip.closePopover = function() {
        changetip.$popover().fadeOut(function() {
            changetip.$popover().remove();

            var search = insertParams({
             changetip_approve : null
            });
            var url = window.location.href.split('?')[0] + '?' + search;
            var hash = window.location.hash;
            if(hash){
                url += hash;
            }
            if(window.history) {
                window.history.replaceState({}, document.title, url);
                location.reload();
            } else {
                insertParamsAndRefresh({
                    changetip_approve : null
                }, null);
            }
        });
    };

    //url manipulation
    //used to pass data back to plugin
    function insertParam(key, value, qp) {
        if(!qp) {
            qp = document.location.search;
        }

        var kvp = [];
        if(qp.length) {
            if(qp[0] == '?') {
                qp = qp.substr(1);
            }
            kvp = qp.split('&');
        }
        var i=kvp.length; var x; while(i--)
        {
            x = kvp[i].split('=');
            if (x[0]==key)
            {
                if(value !== null) {
                    x[1] = value;
                    kvp[i] = x.join('=');
                } else {
                    kvp.splice(i, 1);
                }
                break;
            }
        }
        if(i<0) {kvp[kvp.length] = [key,value].join('=');}
        return kvp.join('&');
    }
    function insertParams(kvps, qp) {
        for(var key in kvps) {
            if(kvps.hasOwnProperty(key)) {
                var value = kvps[key];
                qp = insertParam(key, value, qp);
            }
        }
        return qp;
    }

    function insertParamsAndRefresh(kvps, qp) {
        qp = insertParams(kvps, qp);
        document.location.search = qp;
    }

    //register user
    changetip.registerUser = function(username, uuid, currency, tipSuggestions) {
        if(!username) {
            return console.error('Username cannot be null.');
        }
        var tipSuggestionsStr = '';
        if(!tipSuggestions) {
            tipSuggestions = [{ amount: '.25 USD' }];
        }
        for(var i in tipSuggestions) {
            if(tipSuggestionsStr.length) tipSuggestionsStr += '||';
            tipSuggestionsStr += tipSuggestions[i].amount;
        }
        insertParamsAndRefresh({
            changetip_register: username,
            changetip_uuid: uuid,
            changetip_currency: currency,
            changetip_tip_suggestions: tipSuggestionsStr
        });
    };

    //autoreply
    changetip.autoreplyToComment = function(message, comment) {
        $.post(ajaxurl, {
            action: 'changetip_receive_message_callback',
            changetipType: 'comment',
            changetipMessageSender: message.sender,
            changetipMessageAmount: message.amount,
            changetipApproveId: comment.comment_ID,
            changetipCommentKey: comment.changetip_key
        }, function(response) {
            var data = JSON.parse(response);
            if(data.message) {
                changetip.log(data.message);
            }
            if(data.error) {
                changetip.closePopup();
                return changetip.log(data);
            }
        });
    };
    changetip.autoreplyToTip = function(message) {
        if(changetip.options.postId) {
            $.post(ajaxurl, {
                action: 'changetip_receive_message_callback',
                changetipType: 'button',
                changetipMessageSender: message.sender,
                changetipMessageAmount: message.amount,
                changetipPostId: changetip.options.postId
            });
        }
    }

    //id generation
    changetip.guid = (function() {
      function s4() {
        return Math.floor((1 + Math.random()) * 0x10000)
                   .toString(16)
                   .substring(1);
      }
      return function() {
        return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
      };
    })();
})(window, jQuery);