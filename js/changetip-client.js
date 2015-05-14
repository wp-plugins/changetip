
'use strict';

(function($, changetip) {

    $(function() {
        $.getScript(changetip.origin + "/public/js/widgets.js")
        .done(function() {

            // timeout to let tip.me button load, then bring the default zindex down a notch so that
            // it doesn't cover standard wordpress nav bars.
            window.setTimeout(function() {
                $(".changetip_tipme_button").each(function(idx, button) {
                    var popoverIframe = $(button).find("iframe").first();
                    if(popoverIframe.length>0) {
                        popoverIframe[0].style.zIndex = 99999;
                    }
                });
            }, 1000)

            if(changetip.options.approve) {
                var contextUrl = changetip.options.approve.context_url;
                changetip.popover('approve', {
                    comment: changetip.options.approve.comment_content,
                    contextUrl: contextUrl, // reference the url to the blog entry's comment
                    commentId: changetip.uuid.UUID.v5(contextUrl) // unique but always the same to prevent duplicate tips
                });
            }

            changetip.listen('message', function(e) {
                if(e.origin == changetip.origin && e.data ) {
                    var data = e.data;
                    if(data.success) {
                        if(changetip.options.approve) {
                            changetip.autoreplyToComment(data.meta, changetip.options.approve);
                        } else {
                            changetip.autoreplyToTip(data.meta);
                        }
                    } else if(data.close) {
                        changetip.closePopover();
                    }
                }
            });
        })
        .fail(function() {
        });
    });
})(jQuery, window.changetip);