/*global window, jQuery, ajaxurl*/
/*jslint browser:true, white:true*/

/**
 * Import external attachments admin javascript functions
 */
/**
 * Begin the process of re-sizing all of the checked images
 */

(function () {
  'use strict';

  /**
   * fired when all images have been resized
   */
  var external_images_import_complete;
  external_images_import_complete = function () {

    var target = jQuery('#import_process');
    jQuery('#import_posts #processing').fadeOut();
    target.prepend('<div style="color:#21759B; margin: 0 0 10px; padding: 5px 10px; background-color: #FFFFE0; border:1px solid #E6DB55;">IMPORT COMPLETE</div>');
    target.animate({
        scrollTop: target.height()
      },
      500
    );
  };

  var d = new Date();
  var import_images_start_time;
  import_images_start_time = function () {
    d.getTime();
  };
  var import_images_one_minute;
  var import_images_three_minute;
  var import_images_five_minute;
  var import_images_ten_minute;
  var import_images_twenty_minute;

  /**
   * recursive function for resizing images
   */
  // recursive functions have to be declared before being assigned, or
  // else they are out of scope
  var external_images_import_all;
  external_images_import_all = function (posts, next_post) {

    if (next_post >= posts.length) {
      return external_images_import_complete();
    }

    var target_message = jQuery('#import_posts');

    var target = jQuery('#import_process');

    var current_time = d.getTime();

    if (current_time > (import_images_start_time + 60000) && import_images_one_minute !== 'set') {
      target_message.prepend('<div>1 minute passed ... Looking good!</div>');
      import_images_one_minute = 'set';
    }
    if (current_time > (import_images_start_time + 180000) && import_images_three_minute !== 'set') {
      target_message.prepend('<div>3 minutes passed... Still looking good!</div>');
      import_images_three_minute = 'set';
    }
    if (current_time > (import_images_start_time + 300000) && import_images_five_minute !== 'set') {
      target_message.prepend('<div>5 minutes have passed ... Still looking good ... But if we seem stuck on one post, you may want to refresh and try again.</div>');
      import_images_five_minute = 'set';
    }
    if (current_time > (import_images_start_time + 600000) && import_images_ten_minute !== 'set') {
      target_message.prepend('<div>10 minutes have passed ... Woah! You have a lot of media.</div>');
      import_images_ten_minute = 'set';
      // var lets_stop_here = true;
    }
    if (current_time > (import_images_start_time + 1200000) && import_images_twenty_minute !== 'set') {
      target_message.prepend("<div style='color:#990000'>20 minutes have passed... You REALLY have a lot of media ... Let's take a break. Refresh this page to start again.</div>");
      import_images_twenty_minute = 'set';
    }
    var data = {
      action: 'external_image_import_all_ajax',
      import_images_post: posts[next_post]
    };

    if (import_images_twenty_minute !== 'set') {

      jQuery.post(ajaxurl, data, function (response) {
        var result = JSON.parse(response);

        target.prepend('<div style="padding:2px 30px 0 10px;">' + result + '</div>');
        target.slideDown();

        // recurse
        external_images_import_all(posts, next_post + 1);
      });

    }

  };




  var external_images_import_images;
  external_images_import_images = function () {

    import_images_start_time();
    import_images_one_minute = '';
    import_images_three_minute = '';
    import_images_five_minute = '';
    import_images_ten_minute = '';

    var target = jQuery('#import_posts');
    jQuery('#posts_list').fadeOut('3000');

    target.html('');
    target.show();
    //jQuery(document).scrollTop(target.offset().top);

    var data = {
      action: 'external_image_get_backcatalog_ajax'
    };

    jQuery.post(ajaxurl, data, function (response) {
      //alert('Got this from the server: ' + response);

      var results = JSON.parse(response);
      var posts_to_process = results.posts;
      var insert = '<div id="processing"><p class="howto">Importing images from ' + posts_to_process.length + ' posts...</p><h2>Please Wait...</h2></div>';
      insert += '<div id="import_process" style="padding:2px 2px 10px; margin:10px 0;background: none repeat scroll 0 0 #C5E5F5;border: 1px solid #298CBA;"></div>';
      target.html(insert);

      target.slideDown();
      // recurse
      external_images_import_all(posts_to_process, 0);
    });
  };

  window.external_images_import_complete = external_images_import_complete;
  window.import_images_start_time = import_images_start_time;
  window.external_images_import_all = external_images_import_all;
  window.external_images_import_images = external_images_import_images;

}());
