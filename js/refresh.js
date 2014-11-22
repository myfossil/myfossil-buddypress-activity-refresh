var new_activities = 0;
var original_page_title = '';

function myFOSSILBpAtivityRefresh_getLastId() {
    // get highest ID for the request
    var last_id = 0;
    jQuery('ul.activity-list').find('li').each(function() {
        objectId = jQuery(this).attr('id');
        if (objectId) {
            objectName = objectId.substring(0, objectId.indexOf('-'));
            objectNumber = parseInt(objectId.substring(objectId.indexOf('-') + 1));
            if ('acomment' == objectName || 'activity' == objectName) {
                if (last_id < objectNumber) {
                    last_id = objectNumber;
                }
            }
        }
    });
    return last_id;
}

function myFOSSILBpAtivityRefresh_loadNewActivities() {
    scope = jQuery.cookie('bp-activity-scope');
    filter = jQuery.cookie('bp-activity-filter');
    last_id = myFOSSILBpAtivityRefresh_getLastId();

    jQuery.post(ajaxurl, {
            action: 'myfossil_bp_activity_refresh',
            'last_id': last_id,
            'scope': scope,
            'filter': filter
        },
        function(response) {
            console.info(response);

            // Check for errors and append if found.
            if (response[0] + response[1] != '-1' && response.length > 0) {
                // add response to hidden field
                jQuery('#myfossil-hidden-response').html(response);

                // reset last_insert_id
                last_insert_li = false;

                // check each list item
                jQuery('#myfossil-hidden-response').children('li').each(function() {
                    objectId = jQuery(this).attr('id');
                    objectId = objectId.substring(objectId.indexOf('-') + 1);
                    if ((jQuery('ul.activity-list #activity-' + objectId).attr('id') == undefined) && (jQuery('ul.activity-list #acomment-' + objectId).attr('id') == undefined)) { // add new item
                        if (last_insert_li) {
                            jQuery(last_insert_li).after(this);
                        } else {
                            jQuery('ul.activity-list').prepend(this);
                        }
                        jQuery(this).addClass('new-update').hide().slideDown(300);
                        last_insert_li = this;
                        new_activities++;
                        document.title = original_page_title + ' (' + new_activities + ')';
                    }
                });

                // clear hidden field
                jQuery('#myfossil-hidden-response').html('');
            }
        });
}

function myFOSSILBpAtivityRefresh_automaticRefresh() {
    myFOSSILBpAtivityRefresh_loadNewActivities();
    if (myFOSSILBpActivityRefreshTimeago) {
        jQuery('span.timeago').timeago();
    }
    // reset time and start function again
    setTimeout('myFOSSILBpAtivityRefresh_automaticRefresh();', myFOSSILBpActivityRefreshRate * 1000);
}

jQuery(document).ready(function() {
    original_page_title = document.title;
    if (jQuery('ul.activity-list').length > 0 && !jQuery('body').hasClass('activity-permalink')) {
        // create hidden field
        jQuery('body').append('<div id="myfossil-hidden-response" style="display: none;"></div>');

        if (typeof myFOSSILBpActivityRefreshRate == "undefined") {
            myFOSSILBpActivityRefreshRate = 10;
        }
        if (myFOSSILBpActivityRefreshTimeago) {
            jQuery('span.timeago').timeago();
        }
        // start refreshing
        setTimeout('myFOSSILBpAtivityRefresh_automaticRefresh();', myFOSSILBpActivityRefreshRate * 1000);
    }

    jQuery('div.activity-type-tabs').click(function(event) {
        document.title = original_page_title;
    });
});