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

	if ( dcvAjax.allow_fb_vote ) {
		window.fbAsyncInit = function() {
			FB.init({
				appId: dcvAjax.fb_app_id,
				status: true,
				cookie: true,
				xfbml: true,
				oauth: true
			});
			FB.Event.subscribe('edge.create', function(response) {
				FB.getLoginStatus(function (loginResponse) {
					console.log(loginResponse);
					FB.api('/me', function (graph) {
						var token = loginResponse.session.access_token;
						var fbid = loginResponse.session.uid;
					});
					if ( token )
						console.log(token);

					if ( fbid ) {
						console.log(fbid);
					}
				});
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

});
