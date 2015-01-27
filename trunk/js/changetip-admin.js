
'use strict';

(function($, changetip) {
    changetip.listen('message', function(e) {
        if(e.origin == changetip.origin && e.data ) {
            var data = JSON.parse(e.data);
            changetip.closePopup();
            changetip.registerUser(data.username, data.uuid, data.currency, data.tip_suggestions);
        }
    });

    function updateRegisteredUsernames() {
        var $fields = $('.changetip-username-field');
        var $hiddenInput = $('#changetip_username');
        var arr = [];
        $fields.each(function() {
            var $field = $(this);
            var username = $field.find('input').val();
            var uuid = $field.find('input').attr('data-uuid');
            arr.push({
                name: username,
                uuid: uuid
            });
        });
        $hiddenInput.val(JSON.stringify(arr));
    }

    $(function () {
        $('a#changetip-register').click(function () {
            changetip.popup('register', { 'ct_register' : true, 'version' : "1.1" });
            return false;
        });

        $('a.changetip-delete-account').click(function() {
            $(this).closest('.changetip-username-field').remove();
            updateRegisteredUsernames();
            return false;
        });

    });
})(jQuery, window.changetip);