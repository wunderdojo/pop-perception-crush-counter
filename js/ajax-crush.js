jQuery(document).ready(function($){
    var url=myAjax.ajaxurl;

// start by checking to see if the user is logged in //
$('.crush-button.in').click(function(e){
        /* start the animation or whatever it's going to do to indicate processing */
        $(e.target).next('.crush-counter').addClass('processing');
        $.post(url, {action:'ajax-crush', post_id: this.value, crush_type: $(this).attr('rel')},function(data){
        $(e.target).next('.crush-counter').removeClass('processing');
        /* if they're not logged in but still somehow managed to submit then display an error */
        if(data['status'] ===false){
            alert("You must be logged in to submit a crush");
        }
        
        /* they're logged in and their crush has been registered.
         * update the counter in a visually obvious way.
         */
        else{
         $('#crush-count').html("<b>"+data[1]['count']+"</b> "+data[1]['term']);
         $('#gender-crush-count').html("<b>"+data[2]['count']+"</b> "+data[2]['term']);
         /* if they haven't voted before of they have and they're removing their vote, toggle the class */
         }
    }, 'json');
   });

});