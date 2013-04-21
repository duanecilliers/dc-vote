/*jshint multistr: true */
jQuery(document).ready( function($) {

	$(".dc_vote").click( function(){
		var currentobj = $(this);
		var loggedIn = $('body').hasClass('logged-in') ? true : false ;
		var dcv_votewidget = currentobj.parents(".dcv_votebtn").parents(".dcv_votebtncon").parents(".dcv_votewidget");
		var dcv_votebtn = currentobj.parents(".dcv_votebtn");
		var pID = currentobj.children(".postID").val();
		var uID = currentobj.children(".userID").val();
		var vType = ( loggedIn === true ) ? 'logged-in' : 'logged-out' ;
		var vValue = ( vType === 'logged-in' ) ? 2 : 1 ;
		var aID = currentobj.children(".authorID").val();

		/*Display loading image*/
		dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "visible");
		dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("display", "inline-block");

		/*Do voting*/
		$.post(
			dcvAjax.ajaxurl,
			{
				action: 'dcv-submit',
				postID: pID,
				userID: uID,
				voteType: vType,
				voteValue: vValue,
				authorID: aID,
				dcv_nonce: dcvAjax.dcv_nonce
			},
			function(response){
				currentobj.css("display", "none");
				dcv_votebtn.children(".dcv_voted_icon").css("display", "inline-block");
				dcv_votebtn.children(".dcv_votebtn_txt").css("display", "inline-block");
				dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "hidden");
				dcv_votewidget.children(".dcv_votecount").children(".loadingimage").remove();
				dcv_votewidget.children(".dcv_votecount").children(".dcv_vcount").html(response+' ');
				dcv_votewidget.find('.dcv_votebtn').addClass('dcv_votedbtn');
				var voted_btn_text = ( dcvAjax.voted_btn_text !== '' ) ? dcvAjax.voted_btn_text : 'Voted' ;
				dcv_votewidget.find('.dcv_votebtn_txt').text(voted_btn_text);
				currentobj.remove();

				/*Do updating widget*/
				$.post(
					dcvAjax.ajaxurl,
					{
						action: 'dcv-top-widget',
						postID: pID,
						userID: uID,
						authorID: aID,
						dcv_nonce: dcvAjax.dcv_nonce
					},
					function(response){
						if($(".widget_dcv_top_voted_widget"))
							$(".widget_dcv_top_voted_widget").children(".dcvtopvoted").html(response);
					}
				);
			}
		);
		return false;
	});

	$('.dcv_votewidget').delegate('a', 'click', function(e) {
		if ( $(this).data('permission') ) {
			e.preventDefault();
			if ( $(this).data('permission') === 'yes' ) {
				var currentobj = $(this);
				var dcv_votewidget = currentobj.closest('.dcv_votewidget');
				var pID = dcv_votewidget.find(".postID").val();
				var aID = dcv_votewidget.find(".authorID").val();

				/*Display loading image*/
				dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "visible");
				dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("display", "inline-block");
				setTimeout( function() {}, 1000 ); // Set timeout so that the pID and aID are assigned before recording the like
				dcvRecordLike(pID, aID, dcv_votewidget);
				$('.fb-permission').hide();
			}
			else if ( $(this).data('permission') === 'no' ) {
				$('.fb-permission').hide();
			}
		}
	});

});

function dcvLogin(postID, authorID, voteWidget) {
	FB.login(function(response) {
		if (response.authResponse) {
			// testAPI();
			dcvRecordLike(postID, authorID, voteWidget);
		} else {
			// console.log('cancelled');
		}
	});
}

function testAPI() {
	console.log('Welcome!  Fetching your information.... ');
	FB.api('/me', function(response) {
		console.log('Good to see you, ' + response.name + '.');
	});
}

function dcvRecordLike(postID, authorID, voteWidget) {
	FB.getLoginStatus(function(response) {
		if ( response.status === 'connected' ) {
			FB.api('/me', function (graph) {
				var uID = graph.id;
				var pID = postID;
				var aID = authorID;
				var vType = 'facebook';
				var vValue = dcvAjax.fb_vote_value;
				var dcv_votewidget = voteWidget;
				$.post(
					dcvAjax.ajaxurl,
					{
						action: 'dcv-fblike',
						postID: pID,
						userID: uID,
						voteType: vType,
						voteValue: vValue,
						authorID: aID,
						dcv_nonce: dcvAjax.dcv_nonce
					},
					function(response){
						dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "hidden");
						dcv_votewidget.children(".dcv_votecount").children(".loadingimage").remove();
						dcv_votewidget.children(".dcv_votecount").children(".dcv_vcount").html(response+' ');
						// console.log(dcv_votewidget);
						$('.fb-permission').remove();

						/*Do updating widget*/
						/*$.post(
							dcvAjax.ajaxurl,
							{
								action: 'dcv-top-widget',
								postID: pID,
								userID: uID,
								authorID: aID,
								dcv_nonce: dcvAjax.dcv_nonce
							},
							function(response){
								if($(".widget_dcv_top_voted_widget"))
									$(".widget_dcv_top_voted_widget").children(".dcvtopvoted").html(response);
							}
						);*/
					}
				); // end $.post
			});
		}
		else if ( response.status === 'not_authorized' ) {
			dcvLogin(postID, authorID, voteWidget);
		}
		else {
			dcvLogin(postID, authorID, voteWidget);
		}
	});
}

function dcvRemoveLike(postID, authorID, voteWidget) {
	FB.getLoginStatus(function(response) {
		if ( response.status === 'connected' ) {
			FB.api('/me', function (graph) {
				var uID = graph.id;
				var pID = postID;
				var aID = authorID;
				var vType = 'facebook';
				var vValue = dcvAjax.fb_vote_value;
				var dcv_votewidget = voteWidget;
				$.post(
					dcvAjax.ajaxurl,
					{
						action: 'dcv-fb-unlike',
						postID: pID,
						userID: uID,
						voteType: vType,
						voteValue: vValue,
						authorID: aID,
						dcv_nonce: dcvAjax.dcv_nonce
					},
					function(response){
						dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "hidden");
						dcv_votewidget.children(".dcv_votecount").children(".loadingimage").remove();
						dcv_votewidget.children(".dcv_votecount").children(".dcv_vcount").html(response+' ');
						/*Do updating widget*/
						/*$.post(
							dcvAjax.ajaxurl,
							{
								action: 'dcv-top-widget',
								postID: pID,
								userID: uID,
								authorID: aID,
								dcv_nonce: dcvAjax.dcv_nonce
							},
							function(response){
								if($(".widget_dcv_top_voted_widget"))
									$(".widget_dcv_top_voted_widget").children(".dcvtopvoted").html(response);
							}
						);*/
					}
				); // end $.post
			});
		}
	});
}

if ( dcvAjax.allow_fb_vote ) {

	$('.fb-permission').hide();

	window.fbAsyncInit = function() {

		FB.init({
			appId: dcvAjax.fb_app_id,
			status: true,
			cookie: true,
			xfbml: true,
			oauth: true
		});

		FB.Event.subscribe('edge.create', function(href, widget) {
			FB.getLoginStatus(function(response) {
				if ( response.status === 'connected' ) {
					var currentobj = $(widget.dom.parentNode);
					var dcv_votewidget = currentobj.closest('.dcv_votewidget');
					var pID = dcv_votewidget.find(".postID").val();
					var aID = dcv_votewidget.find(".authorID").val();

					/*Display loading image*/
					dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "visible");
					dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("display", "inline-block");
					setTimeout( function() {}, 1000 ); // Set timeout so that the pID and aID are assigned before recording the like
					dcvRecordLike(pID, aID, dcv_votewidget);
				}
				else {
					$(widget.dom.parentNode).after('<div class="fb-permission"><div class="arrow"></div><p>Give us permission to record your like as ' + dcvAjax.fb_vote_value + ' votes?</p><a data-permission="yes" href="#">Yes</a><a data-permission="no" href="#">No</a></div>');

					//get the position of the placeholder element
					var pos = $(widget.dom.parentNode).offset();
					var width = $(widget.dom.parentNode).width();
					var tooltipWidth = $('.fb-permission').width();
					var tooltipHeight = $('.fb-permission').height();

					//show the menu directly over the placeholder
					$(".fb-permission").css( {
						"left": (pos.left - ( tooltipWidth / 2) ) + "px",
						"margin-left": width / 2,
						"top":pos.top - ( tooltipHeight + 25 ) + "px"
					} );
					$(".fb-permission").fadeIn('fast');
				}
			});
		});

		FB.Event.subscribe('edge.remove', function(href, widget) {
			var $fb_box = $(widget.dom.parentNode);
			dcv_votewidget = $fb_box.closest('.dcv_votewidget');
			dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("visibility", "visible");
			dcv_votewidget.children(".dcv_votecount").children(".loadingimage").css("display", "inline-block");

			var pID = dcv_votewidget.find(".postID").val();
			var aID = dcv_votewidget.find(".authorID").val();
			setTimeout( function() {}, 1000 ); // Set timeout so pID and aID is assigned before removing like
			dcvRemoveLike(pID, aID, dcv_votewidget);
		});

	};

	(function(d) {
		var js, id = 'facebook-jssdk';
		if (d.getElementById(id)) {
			return;
		}
		js = d.createElement('script');
		js.id = id;
		js.async = true;
		js.src = "//connect.facebook.net/en_US/all.js";
		d.getElementsByTagName('head')[0].appendChild(js);
	}(document));

}
