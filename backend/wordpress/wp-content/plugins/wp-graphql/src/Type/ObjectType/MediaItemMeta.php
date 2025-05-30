<?php

namespace WPGraphQL\Type\ObjectType;

/**
 * Class MediaItemMeta
 *
 * @package WPGraphQL\Type\ObjectType
 */
class MediaItemMeta {

	/**
	 * Register the MediaItemMeta Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'MediaItemMeta',
			[
				'description' => static function () {
					return __( 'Meta connected to a MediaItem', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'aperture'         => [
							'type'        => 'Float',
							'description' => static function () {
								return __( 'Aperture measurement of the media item.', 'wp-graphql' );
							},
						],
						'credit'           => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The original creator of the media item.', 'wp-graphql' );
							},
						],
						'camera'           => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Information about the camera used to create the media item.', 'wp-graphql' );
							},
						],
						'caption'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The text string description associated with the media item.', 'wp-graphql' );
							},
						],
						'createdTimestamp' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The date/time when the media was created.', 'wp-graphql' );
							},
							'resolve'     => static function ( $meta ) {
								return ! empty( $meta['created_timestamp'] ) ? $meta['created_timestamp'] : null;
							},
						],
						'copyright'        => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Copyright information associated with the media item.', 'wp-graphql' );
							},
						],
						'focalLength'      => [
							'type'        => 'Float',
							'description' => static function () {
								return __( 'The focal length value of the media item.', 'wp-graphql' );
							},
							'resolve'     => static function ( $meta ) {
								return ! empty( $meta['focal_length'] ) ? $meta['focal_length'] : null;
							},
						],
						'iso'              => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The ISO (International Organization for Standardization) value of the media item.', 'wp-graphql' );
							},
						],
						'shutterSpeed'     => [
							'type'        => 'Float',
							'description' => static function () {
								return __( 'The shutter speed information of the media item.', 'wp-graphql' );
							},
							'resolve'     => static function ( $meta ) {
								return ! empty( $meta['shutter_speed'] ) ? $meta['shutter_speed'] : null;
							},
						],
						'title'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'A useful title for the media item.', 'wp-graphql' );
							},
						],
						'orientation'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The vertical or horizontal aspect of the media item.', 'wp-graphql' );
							},
						],
						'keywords'         => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'List of keywords used to describe or identfy the media item.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
