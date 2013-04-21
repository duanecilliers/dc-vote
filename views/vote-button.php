<?php
	$vote_type = 'logged-in';

	// not logged in
	if ( ! is_user_logged_in() && $allow_public_vote ) {
		$user_ID = 0;
		$vote_type = 'logged-out';
	}

	$user_voted = ( $this->user_voted( $postID, $user_ID, $author_ID, $user_IP, $vote_type ) ) ? true : false ;
?>
<div class="dcv_votebtncon">
	<div class="dcv_votebtn <?php echo ( $user_voted ) ? 'dcv_votedbtn' : '' ; ?>" id="wpvvoteid<?php the_ID(); ?>">

		<?php
		if ( is_user_logged_in() || $allow_public_vote ) {

			// Author can't vote on own post
			if ( $user_ID == $author_ID && !$allow_author_vote ) { ?>

				<span class="dcv_voted_icon"></span>
				<span class="dcv_votebtn_txt dcv_votedbtn_txt"><?php echo ( $user_voted ) ? $voted_btn_custom_txt : $vote_btn_custom_txt ; ?></span>

			<?php } else { ?>

				<?php // New vote, so allowed and show vote count and vote btn
				if ( ! $user_voted ) { ?>

					<a title="vote" class="dc_vote" href="javascript:void(0)" >
						<span class="dcv_vote_icon"></span>
						<span class="dcv_votebtn_txt"><?php echo $vote_btn_custom_txt; ?></span>
						<input type="hidden" class="postID" value="<?php echo $postID; ?>" />
						<input type="hidden" class="userID" value="<?php echo $user_ID; ?>" />
						<input type="hidden" class="authorID" value="<?php echo $author_ID; ?>" />
					</a>
					<span class="dcv_voted_icon" style="display: none;"></span>
					<span class="dcv_votebtn_txt dcv_votedbtn_txt" style="display: none;"><?php echo $vote_btn_custom_txt; ?></span>

				<?php // Already voted, so disallowed and show vote count and voted btn
				} else { ?>

					<span class="dcv_voted_icon"></span>
					<span class="dcv_votebtn_txt dcv_votedbtn_txt"><?php echo ( $user_voted ) ? $voted_btn_custom_txt : $vote_btn_custom_txt ; ?></span>

				<?php
				}

			} ?>

		<?php // Public vote is not allowed
		} else { ?>

			<?php // #TODO : JS to prompt user to register if "Allow public(unregistered or non logged in) users to vote" is set to "No" ?>

			<a title="vote" href="javascript:dcv_regopen();">
				<span class="dcv_vote_icon"></span>
				<span class="dcv_votebtn_txt"><?php echo $vote_btn_custom_txt; ?></span>
			</a>

		<?php
		} ?>

	</div>
</div>
