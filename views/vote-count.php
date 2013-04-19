<div class="dcv_votecount" id="dcvvotecount<?php the_ID(); ?>">

	<?php if ( !$this->user_voted( $postID, $user_ID, $author_ID, $user_IP ) ) { ?>
		<img title="Loading" alt="Loading" src="<?php echo plugins_url( 'dc-vote/images/ajax-loader.gif' ); ?>" class="loadingimage" style="visibility: hidden; display: none;"/>
	<?php } ?>

	<span class="dcv_vcount"><?php echo $curr_votes; ?></span>
	<?php echo $voted_custom_txt; ?>

</div>
