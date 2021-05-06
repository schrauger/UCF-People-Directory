<?php get_header(); the_post();
global $post;

// Add directory search bar on person pages
$obj_shortcode_attributes = new ucf_people_directory_shortcode_attributes();
echo ucf_people_directory_shortcode::search_bar_html($obj_shortcode_attributes);


?>

<!-- Add Search Bar here to be floated right of page title -->


<article class="<?php echo $post->post_status; ?> post-list-item">
	<div class="container my-5">
		<div class="row">
			<div class="col-md-4 mb-5">

				<aside class="person-contact-container">

					<a href="javascript:history.back()" class="return-tp"><i class="fa fa-chevron-circle-left icongrey"></i> Return to Directory</a>

					<div class="mb-4">
						<?php echo get_person_thumbnail( $post, 'rounded-circle' ); ?>
					</div>

					<h1 class="h5 person-title text-center mb-2">
						<?php echo get_person_name( $post ); ?>
					</h1>

					<?php if ( $job_title = get_field( 'person_jobtitle' ) ): ?>
					<div class="person-job-title text-center mb-4"><?php echo $job_title; ?></div>
					<?php endif; ?>

					<?php if ( $cv_url = get_field( 'person_cv' ) ): ?>
					<p>
						<a class="btn btn-secondary mt-3" href="<?php echo $cv_url; ?>">Download CV</a>
					</p>
					<?php endif; ?>

					<?php echo get_person_contact_btns_markup( $post ); ?>

					<?php echo get_person_dept_markup( $post ); ?>
					<?php echo get_person_office_markup( $post ); ?>
					<?php echo get_person_email_markup( $post ); ?>
					<?php echo get_person_phones_markup( $post ); ?>

					<!--<a class="btn btn-secondary" href="javascript:history.back()">Return to Directory</a> -->

				</aside>

			</div>

			<div class="col-md-8 col-lg-7 pl-md-5">

				<?php if( get_field('ucf_tf_person') ) { ?> <div class="alert alert-warning" role="alert">IF YOU ARE A UCF HEALTH PATIENT OF THIS PHYSICIAN AND WISH TO COMMUNICATE WITH THEM, PLEASE USE THE <a href="https://ucfhealth.com/patient-portal/">PATIENT PORTAL</a>.</div> <?php } ?>

				<section class="person-content">
					<ul class="nav nav-tabs" id="myTab" role="tablist">
					  <li class="nav-item">
					    <a class="nav-link active" id="bio-tab" data-toggle="tab" href="#bio" role="tab" aria-controls="bio" aria-selected="true">Biography & Research</a>
					  </li>
					  <li class="nav-item">
					    <a class="nav-link" id="edu-tab" data-toggle="tab" href="#edu" role="tab" aria-controls="edu" aria-selected="false">Education & Specialties</a>
					  </li>
					  <li class="nav-item">
					    <a class="nav-link" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="false">News & Media</a>
					  </li>
					</ul>

					<div class="tab-content" id="myTabContent">
					  <div class="tab-pane fade show active" id="bio" role="tabpanel" aria-labelledby="bio-tab">
					  	<?php echo get_person_desc_heading( $post ); ?>
					  	<?php
						if ( $post->post_content ) {
							the_content();
						} else {
							echo '<p>No description available.</p>';
						}
						?>
					  </div>
					  <div class="tab-pane fade" id="edu" role="tabpanel" aria-labelledby="edu-tab">
					  	

							<?php if ( get_field( 'person_educationspecialties' ) ) { ?>

								<h2 class="person-subheading">Education & Specialties</h2>

								<?php the_field( 'person_educationspecialties' ); ?>

							<?php } else { ?>

								<p>No information specified.</p>

							<?php } ?>

						
					  </div>
					  <div class="tab-pane fade" id="media" role="tabpanel" aria-labelledby="media-tab">

					  	<?php if ( get_person_news_publications_markup_com( $post ) || get_person_videos_markup( $post ) ) {

					  	 echo get_person_news_publications_markup_com( $post ); ?>

						<?php echo get_person_videos_markup( $post ); } else { ?>

						<p>No recent media. Please check back soon.</p>

						<?php } ?>

					  </div>
					</div>
					
				</section>

			</div>
		</div>

		
	</div>
</article>

<?php get_footer(); ?>
