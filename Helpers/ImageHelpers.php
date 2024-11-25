<?php

namespace FFFlabel\Services\Helpers;

class ImageHelpers {

	/**
	 * Convert image to base64
	 *
	 * @param $image
	 *
	 * @return string
	 */
	public static function imageToBase64($image) {
		$type = pathinfo($image, PATHINFO_EXTENSION);
		$data = file_get_contents($image);

		return 'data:image/'.$type.';base64,'.base64_encode($data);
	}

	/**
	 *
	 * Added file (url or path) to media Library
	 *
	 * @param     $file
	 * @param int $post_id
	 */
	public static function addFileToMediaLibrary($file, $post_id = 0) {

		$filename    = basename($file);
		$upload_file = wp_upload_bits($filename, null, file_get_contents($file));
		if (!$upload_file['error']) {
			$wp_filetype   = wp_check_filetype($filename, null);
			$attachment    = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent'    => $post_id,
				'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);
			$attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $post_id);
			if (!is_wp_error($attachment_id)) {
				require_once(ABSPATH."wp-admin".'/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
				wp_update_attachment_metadata($attachment_id, $attachment_data);
			}
		}
	}

	/**
	 * get Id of attachment to post file by src value
	 *
	 * @param $post_id
	 * @param $src
	 *
	 * @return bool
	 */
	public static function getAttachmentIDOfPostBySrc($post_id, $src) {
		$args = array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_parent'    => $post_id
		);

		$attachments = get_posts($args);

		if (isset($attachments) && is_array($attachments)) {

			foreach ($attachments as $attachment) {
				$image = wp_get_attachment_image_src($attachment->ID, 'full');

				if (strpos($src, $image[0])!==false) {
					return $attachment->ID;
				}
			}
		}

		return false;
	}


}
