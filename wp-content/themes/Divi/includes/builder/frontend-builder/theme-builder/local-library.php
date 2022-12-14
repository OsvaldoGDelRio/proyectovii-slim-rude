<?php
/**
 * Save (template and preset) to the local library functionality.
 *
 * @package Builder.
 */

/**
 * Insert terms from comma seperated string.
 *
 * @since 4.18.0
 * @param string $terms_str Comma seperated list of new terms.
 * @param string $tax Taxonomy name.
 *
 * @return (void|array)
 */
function et_theme_builder_insert_terms_from_str( $terms_str, $tax ) {
	// Insert categories.
	if ( '' === $terms_str ) {
		return;
	}

	// Multiple terms could be provided.
	$term_names   = explode( ',', $terms_str );
	$new_term_ids = array();

	foreach ( $term_names as $term_name ) {
		$new_term = wp_insert_term( $term_name, $tax );

		if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
			$new_term_ids[] = (int) $new_term['term_id'];
		}
	}

	return $new_term_ids;
}

/**
 * Gets the Library Item name.
 *
 * @param array  $preferences Preferences set in the Save Builder Preset/Template modals.
 * @param string $item_type Preset / Template item type.
 *
 * @return string
 */
function et_theme_builder_local_library_get_item_name( $preferences, $item_type ) {
	$_         = et_();
	$item_name = '';

	if ( ! is_array( $preferences ) ) {
		$preferences = [];
	}

	switch ( trim( $item_type ) ) {
		case ET_THEME_BUILDER_ITEM_SET:
			$item_name = isset( $preferences['set_name'] ) && '' !== $preferences['set_name']
			? sanitize_text_field( $preferences['set_name'] )
			: esc_html__( 'Divi Theme Builder Set', 'et_builder' );

			break;
		case ET_THEME_BUILDER_ITEM_TEMPLATE:
			$item_name = isset( $preferences['template_name'] ) && '' !== $preferences['template_name']
			? sanitize_text_field( $preferences['template_name'] )
			: esc_html__( 'Divi Theme Builder Template', 'et_builder' );

			break;
	}

	return $item_name;
}

/**
 * Sets the taxomomy for Template & Preset.
 *
 * @param int   $post_id Post ID.
 * @param array $preferences Preferences set in the Save Builder Preset/Template modals.
 */
function et_theme_builder_local_library_set_item_taxonomy( $post_id, $preferences ) {
	$_         = et_();
	$tax_input = [];

	$item_type = $_->array_get( $preferences, 'item_type' );

	// Taxonomy: TB item type and selected category and tags.
	if ( ! empty( $item_type ) ) {
		$tax_input = array_merge( et_theme_builder_local_library_get_selected_taxonomy( $preferences ), [ 'et_tb_item_type' => $item_type ] );
	}

	// Insert new category and tags.
	$new_taxs = et_theme_builder_local_library_get_new_taxonomy( $preferences );

	foreach ( $new_taxs as $tax => $new_terms ) {
		if ( '' !== $new_terms ) {
			$inserted_terms_ids = et_theme_builder_insert_terms_from_str( $new_terms, $tax );
			if ( ! empty( $inserted_terms_ids ) ) {
				$tax_input[ $tax ] = array_merge( $tax_input[ $tax ], $inserted_terms_ids );
			}
		}
	}

	// Set category and tags for the template saved into local library.
	if ( ! empty( $tax_input ) ) {
		foreach ( $tax_input as $taxonomy => $terms ) {
			wp_set_post_terms( $post_id, $terms, $taxonomy );
		}
	}
}

/**
 * Gets the newly added taxonomies set in the Preset/Template modals.
 *
 * @param array $preferences Preferences set in the Save Builder Preset/Template modals.
 *
 * @return array
 */
function et_theme_builder_local_library_get_new_taxonomy( $preferences ) {
	$_ = et_();

	return [
		'layout_category' => $_->array_get( $preferences, 'new_category_name', '' ),
		'layout_tag'      => $_->array_get( $preferences, 'new_tag_name', '' ),
	];
}

/**
 * Gets the selected taxonomies from Preset/Template modals.
 *
 * @param array $preferences Preferences set in the Save Builder Preset/Template modals.
 *
 * @return array
 */
function et_theme_builder_local_library_get_selected_taxonomy( $preferences ) {
	$_ = et_();

	$selected_cats = $_->array_get( $preferences, 'selected_cats', array() );
	$selected_tags = $_->array_get( $preferences, 'selected_tags', array() );

	return [
		'layout_category' => array_map( 'intval', $selected_cats ),
		'layout_tag'      => array_map( 'intval', $selected_tags ),
	];
}

/**
 * Gets the layouts(shortcodes from header/body/footer area) information.
 *
 * @param array $template Template information from Save Builder Preset/Template modals.
 *
 * @return array
 */
function et_theme_builder_local_library_get_layouts( $template ) {
	$_ = et_();

	$header_id = (int) $_->array_get( $template, 'layouts.header.id', 0 );
	$body_id   = (int) $_->array_get( $template, 'layouts.body.id', 0 );
	$footer_id = (int) $_->array_get( $template, 'layouts.footer.id', 0 );

	// get_post_field returns empty string on failure.
	$header_content = get_post_field( 'post_content', $header_id );
	$body_content   = get_post_field( 'post_content', $body_id );
	$footer_content = get_post_field( 'post_content', $footer_id );

	return [
		'header' => $header_content,
		'body'   => $body_content,
		'footer' => $footer_content,
	];
}

/**
 * Save a Theme Builder template to the local library.
 *
 * @since 4.18.0
 * @param array $template Template.
 * @param array $preferences Preferences for the save template.
 *
 * @return (integer|false) Return false on failure.
 */
function et_theme_builder_save_template_to_library( $template, $preferences = array() ) {
	$_               = et_();
	$is_set_template = false;

	if ( ! is_array( $preferences ) ) {
		$preferences = [];
	}

	// Preferences.
	if ( ! array_key_exists( 'item_type', $preferences ) ) {
		$preferences = array_merge( $preferences, [ 'item_type' => ET_THEME_BUILDER_ITEM_TEMPLATE ] );
	}

	$template_name       = sanitize_text_field( et_theme_builder_local_library_get_item_name( $preferences, ET_THEME_BUILDER_ITEM_TEMPLATE ) );
	$title               = sanitize_text_field( $_->array_get( $template, 'title', '' ) );
	$enabled             = (bool) $_->array_get( $template, 'enabled', true );
	$header_enabled      = (bool) $_->array_get( $template, 'layouts.header.enabled', true );
	$body_enabled        = (bool) $_->array_get( $template, 'layouts.body.enabled', true );
	$footer_enabled      = (bool) $_->array_get( $template, 'layouts.footer.enabled', true );
	$autogenerated_title = (bool) $_->array_get( $template, 'autogenerated_title', true );
	$use_on              = array_map( 'sanitize_text_field', $_->array_get( $template, 'use_on', array() ) );
	$exclude_from        = array_map( 'sanitize_text_field', $_->array_get( $template, 'exclude_from', array() ) );
	$is_default          = (bool) $_->array_get( $template, 'default', false );
	$preset_id           = intval( $_->array_get( $preferences, 'preset_id', 0 ) );
	$item_id             = intval( $_->array_get( $template, 'item_id', 0 ) );
	$status              = sanitize_text_field( $_->array_get( $template, 'status', 'publish' ) );

	// Layout's shortcode.
	$layout_types       = array( 'header', 'body', 'footer' );
	$layout_shortcodes  = array();
	$global_layout_meta = array();
	$layout_meta        = array();

	$update = false; // Editing the saved template?.
	if ( $item_id ) {
		$item   = get_post( $item_id );
		$update = $item && ET_TB_ITEM_POST_TYPE === $item->post_type && ( 'publish' === $item->post_status || $_->array_get( $preferences, 'force_update', false ) );
	}

	foreach ( $layout_types as $layout_type ) {
		$layout_id        = (int) $_->array_get( $template, "layouts.$layout_type.id", 0 );
		$global_layout_id = (int) $_->array_get( $template, "global_layouts.$layout_type.id", 0 );
		$is_global_layout = $layout_id && $global_layout_id && $layout_id === $global_layout_id;

		if ( $is_default && ET_THEME_BUILDER_ITEM_SET === $_->array_get( $preferences, 'item_type', '' ) ) {
			if ( $preset_id && $global_layout_id ) {
				update_post_meta( $preset_id, '_et_has_global_layouts', '1' );
			}
		}

		// Mark whether layout is global layout.
		$global_layout_meta[ "_et_{$layout_type}_layout_global" ] = $is_global_layout ? '1' : '0';
		$layout_meta[ "_et_{$layout_type}_layout_id" ]            = $layout_id;

		// Do not save the global layout conent if template is not a Default Website Template.
		$layout_post = get_post( $layout_id );
		if ( $layout_post ) {
			$layout_content = $layout_post->post_content;
			if ( ! empty( $layout_content ) ) {
				$layout_shortcodes[ $layout_type ] = array(
					'post_content' => $layout_content,
					'post_meta'    => et_core_get_post_builder_meta( $layout_id ),
				);
			}
		}
	}

	$post_content = ! empty( $layout_shortcodes ) ? wp_slash( wp_json_encode( $layout_shortcodes ) ) : '';

	if ( $update ) {
		// Update template into the local library.
		$post_id = wp_update_post(
			array(
				'ID'           => $item_id,
				'post_title'   => $title,
				'post_content' => $post_content,
			)
		);
	} else {
		// Insert template into the local library.
		$is_set_template = ET_THEME_BUILDER_ITEM_SET === $_->array_get( $preferences, 'item_type', '' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => ET_TB_ITEM_POST_TYPE,
				'post_status'  => $status,
				'post_title'   => $is_set_template ? $title : $template_name,
				'post_content' => $post_content,
			)
		);
	}

	if ( 0 === $post_id || is_wp_error( $post_id ) ) {
		return false;
	}

	$item_type = $_->array_get( $preferences, 'item_type', '' );

	// Taxonomy: TB item type and selected category and tags.
	if ( ET_THEME_BUILDER_ITEM_TEMPLATE === $item_type ) {
		et_theme_builder_local_library_set_item_taxonomy( $post_id, $preferences );
	} elseif ( ET_THEME_BUILDER_ITEM_SET === $item_type ) {
		$template_preferences = [
			'item_type' => ET_THEME_BUILDER_ITEM_TEMPLATE,
		];

		$is_set_template = true;

		if ( $is_default && $preset_id > 0 ) {
			update_post_meta( $preset_id, '_et_has_default_template', '1' );
		}

		et_theme_builder_local_library_set_item_taxonomy( $post_id, $template_preferences );
	}

	// Template meta.
	$metas = array_merge(
		array(
			'_et_autogenerated_title'   => $autogenerated_title ? '1' : '0',
			'_et_default'               => $is_default ? '1' : '0',
			'_et_enabled'               => $enabled ? '1' : '0',
			'_et_header_layout_enabled' => $header_enabled ? '1' : '0',
			'_et_body_layout_enabled'   => $body_enabled ? '1' : '0',
			'_et_footer_layout_enabled' => $footer_enabled ? '1' : '0',
			'_et_template_title'        => $title,
		),
		$global_layout_meta
	);

	if ( $is_set_template ) {
		$metas = array_merge(
			$metas,
			[
				'_et_set_template' => '1',
			]
		);
	}

	foreach ( $metas as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	// In a case of update, delete existing meta `_et_use_on` and `_et_exclude_from` before adding new.
	if ( $update ) {
		delete_post_meta( $post_id, '_et_use_on' );
		delete_post_meta( $post_id, '_et_exclude_from' );
	}

	if ( $use_on ) {
		$use_on_unique = array_unique( $use_on );

		foreach ( $use_on_unique as $condition ) {
			add_post_meta( $post_id, '_et_use_on', $condition );
		}
	}

	if ( $exclude_from ) {
		$exclude_from_unique = array_unique( $exclude_from );

		foreach ( $exclude_from_unique as $condition ) {
			add_post_meta( $post_id, '_et_exclude_from', $condition );
		}
	}

	if ( $is_default ) {
		update_post_meta( $preset_id, '_et_default_template_id', $post_id );
	}

	return $post_id;
}

/**
 * Save a Theme Builder preset to the local library.
 *
 * @since 4.18.0
 * @param array $templates List of templates.
 * @param array $preferences Preset preferences.
 *
 * @return (integer|false) Returns Post ID on success and FALSE on failure.
 */
function et_theme_builder_save_preset_to_library( $templates, $preferences ) {
	if ( ! is_array( $templates ) ) {
		return false;
	}

	$_                 = et_();
	$template_post_ids = [];

	// Preferences.
	if ( is_array( $preferences ) ) {
		$preferences = array_merge( $preferences, [ 'item_type' => ET_THEME_BUILDER_ITEM_SET ] );
	} else {
		// Returning FALSE sends back wp_send_json_error().
		return false;
	}

	/*
	 * List of all Template IDs as part of this preset.
	 *
	 * The following hierarchy will explain the relation between Presets, Templates & Layouts.
	 *
	 * - Preset
	 *     - Templates
	 *         - Layouts
	 *
	 * Each Template pertaining to this preset will be stored as `_et_template_id` post meta.
	 *
	 * SELECT post_id, meta_key, meta_value
	 * FROM wp_postmeta
	 * WHERE
	 * meta_key='_et_template_id' AND post_id={post_id};
	 */
	$template_post_ids = [];

	$preset_name = et_theme_builder_local_library_get_item_name( $preferences, ET_THEME_BUILDER_ITEM_SET );

	$post_id = wp_insert_post(
		array(
			'post_type'   => ET_TB_ITEM_POST_TYPE,
			'post_status' => et_()->array_get( $preferences, 'post_status', 'publish' ),
			'post_title'  => $preset_name,
		)
	);

	if ( 0 === $post_id || is_wp_error( $post_id ) ) {
		return false;
	}

	$preferences = array_merge( $preferences, [ 'preset_id' => $post_id ] );

	// Insert template into the local library.
	foreach ( $templates as $template ) {
		$template_post_ids[] = et_theme_builder_save_template_to_library( $template, $preferences );
	}

	// Taxonomy: TB item type and selected category and tags.
	et_theme_builder_local_library_set_item_taxonomy( $post_id, $preferences );
	et_theme_builder_add_template_to_preset( $post_id, $template_post_ids );

	return $post_id;
}

/**
 * Add template to the saved preset.
 *
 * @param int   $preset_id The preset id.
 * @param array $template_post_ids List of the template post ids.
 *
 * @since 4.18.0
 */
function et_theme_builder_add_template_to_preset( $preset_id, $template_post_ids ) {
	foreach ( $template_post_ids as $template_post_id ) {
		if ( 0 === absint( $template_post_id ) ) {
			continue;
		}

		// `add_post_meta` used to create new { key: value } pair using the same key.
		add_post_meta( $preset_id, '_et_template_id', $template_post_id );
	}
}

/**
 * Retrieves an array of the terms in a given taxonomy in following format:
 *
 *   $terms = array(
 *       '1' => array(
 *           'id'    => 1,
 *           'name'  => 'Uncategorized',
 *           'slug'  => 'uncategorized',
 *           'count' => 10,
 *       ),
 *   );
 *
 * @param string $tax_name Taxonomy name.
 */
function et_theme_builder_get_terms( $tax_name ) {
	$terms       = get_terms( $tax_name, array( 'hide_empty' => false ) );
	$terms_by_id = array();

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	foreach ( $terms as $term ) {
		$term_id = $term->term_id;

		$terms_by_id[ $term_id ]['id']    = $term_id;
		$terms_by_id[ $term_id ]['name']  = $term->name;
		$terms_by_id[ $term_id ]['slug']  = $term->slug;
		$terms_by_id[ $term_id ]['count'] = $term->count;
	}

	return $terms_by_id;
}

/**
 * Retrieves the library item type attached with library item.
 *
 * @since 4.18.0
 *
 * @param int|WP_Post $item Library item post ID or object.
 * @return string|WP_Error The library item type. WP_Error on failure.
 */
function et_theme_builder_get_library_item_type( $item ) {
	// Initalize item type.
	$terms = get_the_terms( $item, 'et_tb_item_type' );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return new WP_Error( 'et_theme_builder_no_item_type_attached', __( 'The library item type is not attached to a given item.', 'et_builder' ) );
	}

	$item_type = $terms[0]->slug;

	// Allowed item types, i.e template and preset.
	if ( ! in_array( $item_type, array( ET_THEME_BUILDER_ITEM_SET, ET_THEME_BUILDER_ITEM_TEMPLATE ), true ) ) {
		return new WP_Error( 'et_theme_builder_invalid_item_type', __( 'Invalid library item type.', 'et_builder' ) );
	}

	return $item_type;
}

/**
 * Retrieves library item post given a library post ID or post object.
 *
 * @since 4.18.0
 *
 * @param int|WP_Post $item Library item's post ID or WP_Post object.
 * @return WP_Post|WP_Error The library item post object. WP_Error on failure.
 */
function et_theme_builder_get_library_item_post( $item ) {
	// Initalize item post.
	$item_post = get_post( $item );

	if ( ! $item_post ) {
		return new WP_Error( 'et_theme_builder_item_not_found', __( 'Library item not found', 'et_builder' ) );
	}

	$item_type = et_theme_builder_get_library_item_type( $item_post );

	if ( is_wp_error( $item_type ) ) {
		return $item_type;
	}

	return $item_post;
}

/**
 * Create interim theme builder post for the local library editor.
 *
 * @since 4.18.0
 *
 * @return int|bool The post ID on success. The value false on failure.
 */
function et_theme_builder_insert_library_theme_builder() {
	$post_id = wp_insert_post(
		array(
			'post_type'   => ET_THEME_BUILDER_THEME_BUILDER_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Library Theme Builder',
		)
	);

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return false;
	}

	// Mark the theme builder to use during template editing.
	update_post_meta( $post_id, '_et_library_theme_builder', '1' );

	return $post_id;
}

// phpcs:disable Squiz.Commenting.FunctionComment.ParamCommentFullStop -- Respecting punctuation.
/**
 * Create a theme builder layouts from the template saved in the local library.
 *
 * @since 4.18.0
 *
 * @param WP_Post $template_post The template post.
 * @param array   $global_layouts Optional. Array containing the necessary params.
 *    $params = [
 *      'header' => (int|string) Header Layout ID. `use_global` string when TB global layout (relink option) is to be used.
 *      'body'   => (int|string) Body Layout ID. `use_global` string when TB global layout (relink option) is to be used.
 *      'footer' => (int|string) Footer Layout ID. `use_global` string when TB global layout (relink option) is to be used.
 *    ]
 *
 * @return array|WP_Error An array of the created layout post ids. WP_Error on failure.
 */
function et_theme_builder_create_layouts_from_library_template( $template_post, $global_layouts = [] ) {

	if ( ! $template_post instanceof WP_Post || empty( $template_post->post_content ) ) {
		return array();
	}

	$layouts             = (array) json_decode( $template_post->post_content, true );
	$template_settings   = et_theme_builder_get_template_settings( $template_post->ID, false );
	$is_default_template = '1' === $template_settings['_et_default'];

	if ( empty( $layouts ) ) {
		return new WP_Error(
			'et_theme_builder_invalid_json',
			esc_html__(
				'Incorrect JSON string.',
				'et-builder'
			)
		);
	}

	$new_layout_ids = array();

	foreach ( $layouts as $layout_type => $layout ) {
		$layout_post_content = et_()->array_get( $layout, 'post_content', '' );

		// Skip if the layout `post_content` value is empty.
		if ( empty( $layout_post_content ) ) {
			continue;
		}

		if ( ! $is_default_template && '1' === $template_settings[ "_et_{$layout_type}_layout_global" ] && array_key_exists( $layout_type, $global_layouts ) ) {
			$new_layout_ids[ $layout_type ] = $global_layouts[ $layout_type ];
			continue;
		}

		// Insert layout.
		$post_id = et_theme_builder_insert_layout(
			array(
				'post_type'    => et_theme_builder_get_valid_layout_post_type( $layout_type ),
				'post_content' => $layout_post_content,
			)
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			$new_layout_ids[ $layout_type ] = $post_id;

			// Add post meta in the new layout from the layout `post_meta` value.
			$layout_post_meta = et_()->array_get( $layout, 'post_meta', array() );
			foreach ( $layout_post_meta as $post_meta ) {
				update_post_meta( $post_id, $post_meta['key'], $post_meta['value'] );
			}
		}
	}

	return $new_layout_ids;
}
// phpcs:enable

/**
 * Create template from the library item post.
 *
 * @since 4.18.0
 *
 * @param WP_Post $item_post The library item post object.
 * @param array   $global_layouts Array of global layouts.
 * @return int|bool The template id on success. false on failure.
 */
function et_theme_builder_create_template_from_library_template( $item_post, $global_layouts = array() ) {
	// Insert template.
	$template_id = wp_insert_post(
		array(
			'post_type'   => ET_THEME_BUILDER_TEMPLATE_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $item_post->post_title,
		)
	);

	if ( ! $template_id || is_wp_error( $template_id ) ) {
		return false;
	}

	// Insert layouts.
	$new_layouts_ids = et_theme_builder_create_layouts_from_library_template( $item_post, $global_layouts );

	if ( is_wp_error( $new_layouts_ids ) ) {
		return false;
	}

	foreach ( array( 'body', 'header', 'footer' ) as $layout_type ) {
		$layout_post_id = et_()->array_get( $new_layouts_ids, $layout_type, 0 );
		update_post_meta( $template_id, "_et_{$layout_type}_layout_id", $layout_post_id );
	}

	// Add template settings.
	$template_settings = et_theme_builder_get_template_settings( $item_post->ID, false );
	foreach ( $template_settings as $meta_key => $meta_value ) {
		if ( is_array( $meta_value ) ) {
			foreach ( $meta_value as $value ) {
				if ( ! empty( $value ) ) {
					add_post_meta( $template_id, $meta_key, $value );
				}
			}
		} else {
			add_post_meta( $template_id, $meta_key, $meta_value );
		}
	}

	add_post_meta( $template_id, '_et_library_item_id', $item_post->ID );

	return $template_id;
}

/**
 * Retrive template settings.
 *
 * @since 4.18.0
 *
 * @param int  $item_id The library item post ID.
 * @param bool $formatted Whether to remove "_et" prefix from setting keys.
 *
 * @return array An array of template settings.
 */
function et_theme_builder_get_template_settings( $item_id, $formatted ) {
	// Template settings meta.
	$metas = array(
		'_et_template_title'        => true,
		'_et_autogenerated_title'   => true,
		'_et_default'               => true,
		'_et_enabled'               => true,
		'_et_header_layout_enabled' => true,
		'_et_body_layout_enabled'   => true,
		'_et_footer_layout_enabled' => true,
		'_et_use_on'                => false,
		'_et_exclude_from'          => false,
		'_et_header_layout_global'  => true,
		'_et_body_layout_global'    => true,
		'_et_footer_layout_global'  => true,
	);

	$settings = array();

	foreach ( $metas as $meta_key => $is_single ) {
		$key = $formatted ? substr( $meta_key, 4 ) : $meta_key;

		$settings[ $key ] = get_post_meta( $item_id, $meta_key, $is_single );
	}

	return $settings;
}

/**
 * Intalize the library item editor.
 */
function et_theme_builder_init_library_item_editor() {
	if ( ! et_theme_builder_library_is_item_editor() ) {
		return;
	}

	$tb_item_id  = et_theme_builder_get_item_id();
	$item_editor = ET_Theme_Builder_Local_Library_Item_Editor::instance( $tb_item_id );
	$item_editor->init_library_item_editor();
}

add_action( 'admin_init', 'et_theme_builder_init_library_item_editor', 10 );

/**
 * Return custom style from the passed layout meta.
 *
 * @since 4.18.0
 *
 * @param array  $layout_meta All layout meta saved in the local library.
 * @param string $layout_type The layout type i.e body, header, footer.
 * @return string Page custom style.
 */
function et_theme_builder_get_preview_custom_css( $layout_meta, $layout_type ) {
	$overflow        = et_pb_overflow();
	$selector_prefix = '.et-l--post';

	switch ( $layout_type ) {
		case 'header':
			$selector_prefix = '.et-l--header';
			break;

		case 'body':
			$selector_prefix = '.et-l--body';
			break;

		case 'footer':
			$selector_prefix = '.et-l--footer';
			break;
	}

	$page_settings = array();
	foreach ( $layout_meta as $post_meta ) {
		$page_settings[ $post_meta->key ] = $post_meta->value;
	}

	$selector_prefix = ' ' . ET_BUILDER_CSS_PREFIX . $selector_prefix;
	$output          = isset( $page_settings['_et_pb_custom_css'] ) ? $page_settings['_et_pb_custom_css'] : '';

	if ( isset( $page_settings['_et_pb_light_text_color'] ) ) {
		$output .= sprintf(
			'%2$s .et_pb_bg_layout_dark { color: %1$s !important; }',
			esc_html( $page_settings['_et_pb_light_text_color'] ),
			esc_html( $selector_prefix )
		);
	}

	if ( isset( $page_settings['_et_pb_dark_text_color'] ) ) {
		$output .= sprintf(
			'%2$s .et_pb_bg_layout_light { color: %1$s !important; }',
			esc_html( $page_settings['_et_pb_dark_text_color'] ),
			esc_html( $selector_prefix )
		);
	}

	if ( isset( $page_settings['_et_pb_content_area_background_color'] ) ) {
		$content_area_bg_selector = et_is_builder_plugin_active() ? $selector_prefix : ' .page.et_pb_pagebuilder_layout #main-content';
		$output                  .= sprintf(
			'%1$s { background-color: %2$s; }',
			esc_html( $content_area_bg_selector ),
			esc_html( $page_settings['_et_pb_content_area_background_color'] )
		);
	}

	if ( isset( $page_settings['_et_pb_section_background_color'] ) ) {
		$output .= sprintf(
			'%2$s > .et_builder_inner_content > .et_pb_section { background-color: %1$s; }',
			esc_html( $page_settings['_et_pb_section_background_color'] ),
			esc_html( $selector_prefix )
		);
	}

	$overflow_x = $overflow->get_value_x( $page_settings, '', '_et_pb_' );
	$overflow_y = $overflow->get_value_y( $page_settings, '', '_et_pb_' );

	if ( ! empty( $overflow_x ) ) {
		$output .= sprintf(
			'%2$s .et_builder_inner_content { overflow-x: %1$s; }',
			esc_html( $overflow_x ),
			esc_html( $selector_prefix )
		);
	}

	if ( ! empty( $overflow_y ) ) {
		$output .= sprintf(
			'%2$s .et_builder_inner_content { overflow-y: %1$s; }',
			esc_html( $overflow_y ),
			esc_html( $selector_prefix )
		);
	}

	if ( isset( $page_settings['_et_pb_page_z_index'] ) && '' !== $page_settings['_et_pb_page_z_index'] ) {
		$output .= sprintf(
			'%2$s .et_builder_inner_content { z-index: %1$s; }',
			esc_html( $page_settings['et_pb_page_z_index'] ),
			esc_html( '.et-db #et-boc .et-l' . $selector_prefix )
		);
	}

	/**
	 * Filters page custom css.
	 *
	 * @since 4.18.0
	 *
	 * @param string $output
	 */
	return apply_filters( 'et_pb_page_custom_css', $output );
}

/**
 * Render the saved template.
 *
 * @since 4.18.0
 *
 * @param int $item_id The saved library item id.
 * @retun void
 */
function et_theme_builder_render_library_template_preview( $item_id ) {
	$item          = new ET_Theme_Builder_Local_Library_Item( $item_id );
	$layouts       = json_decode( $item->item_post->post_content );
	$template_html = '';

	$layout_post_types = [
		'header' => ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
		'body'   => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
		'footer' => ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
	];

	foreach ( $layouts as $layout_type => $layout ) {
		$layout_meta      = $layout->post_meta;
		$layout_post_type = $layout_post_types[ $layout_type ];
		$layout_id        = 0;

		et_theme_builder_frontend_render_common_wrappers( $layout_type, true );

		$template_html .= et_builder_get_layout_opening_wrapper( $layout_post_type );
		ET_Builder_Element::begin_theme_builder_layout( $layout_id );

		$template_html .= et_core_intentionally_unescaped( et_builder_render_layout( $layout->post_content ), 'html' );

		$result                                 = ET_Builder_Element::setup_advanced_styles_manager( $layout_id );
		$advanced_styles_manager                = $result['manager'];
		$advanced_styles_manager->forced_inline = true;

		if ( isset( $result['deferred'] ) ) {
			$deferred_styles_manager = $result['deferred'];
		}

		$is_critical_enabled = apply_filters( 'et_builder_critical_css_enabled', false );
		$custom              = et_theme_builder_get_preview_custom_css( $layout_meta, $layout_type );
		$critical            = $is_critical_enabled ? ET_Builder_Element::get_style( false, $layout_id, true ) . ET_Builder_Element::get_style( true, $layout_id, true ) : [];
		$styles              = ET_Builder_Element::get_style( false, $layout_id ) . ET_Builder_Element::get_style( true, $layout_id );

		if ( empty( $critical ) ) {
			// No critical styles defined, just enqueue everything as usual.
			$styles = $custom . $styles;
			if ( ! empty( $styles ) ) {
				if ( isset( $deferred_styles_manager ) ) {
					$deferred_styles_manager->set_data( $styles, 40 );
				} else {
					$advanced_styles_manager->set_data( $styles, 40 );
				}
			}
		} else {
			// Add page css to the critical section.
			$critical = $custom . $critical;
			$advanced_styles_manager->set_data( $critical, 40 );
			if ( ! empty( $styles ) ) {
				// Defer everything else.
				$deferred_styles_manager->set_data( $styles, 40 );
			}
		}

		ET_Builder_Element::end_theme_builder_layout();
		$template_html .= et_builder_get_layout_closing_wrapper( $layout_post_type );
	}

	return $template_html;
}

/**
 * Check whether current page is library template preview page.
 *
 * @return bool
 */
function is_et_theme_builder_template_preview() {
	global $wp_query;
	// phpcs:ignore WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
	return ( 'true' === $wp_query->get( 'et_pb_preview' ) && isset( $_GET['et_pb_preview_nonce'] ) && isset( $_GET['item_id'] ) );
}

/**
 * Get updated global layouts by incoming layout duplicate decision.
 *
 * @param string $incoming_layout_duplicate_decision Layout duplicate decision opted by User.
 * @param array  $global_layouts                     Global layouts.
 * @return array
 */
function et_theme_builder_get_global_layouts_by_incoming_layout_duplicate_decision( $incoming_layout_duplicate_decision, $global_layouts ) {
	$updated_global_layouts = [];

	if ( 0 === count( $global_layouts ) && 'relink' === $incoming_layout_duplicate_decision ) {
		$theme_builder_templates = et_theme_builder_get_theme_builder_templates( true, false );
		$layouts                 = [];

		foreach ( $theme_builder_templates as $template ) {
			if ( ! $template['default'] ) {
				continue;
			}

			$layouts = $template['layouts'];
		}

		foreach ( $layouts as $layout_type => $layout ) {
			$updated_global_layouts[ $layout_type ] = $layout['id'];
		}
	} else {
		foreach ( $global_layouts as $layout_type => $layout_id ) {
			$layout_post = get_post( $layout_id );

			if ( ! $layout_post ) {
				$updated_global_layouts[ $layout_type ] = 0;
			} else {
				$created_layout_id = wp_insert_post(
					[
						'post_content' => $layout_post->post_content,
						'post_title'   => $layout_post->post_title,
						'post_type'    => $layout_post->post_type,
						'post_status'  => $layout_post->post_status,
						'post_name'    => $layout_post->post_name,
					]
				);

				$updated_global_layouts[ $layout_type ] = $created_layout_id;

				$meta = et_core_get_post_builder_meta( $layout_id );

				foreach ( $meta as $entry ) {
					update_post_meta( $created_layout_id, $entry['key'], $entry['value'] );
				}
			}
		}
	}
	return $updated_global_layouts;
}

/**
 * Retrieve the item id from query parameters.
 *
 * @since 4.18.0
 *
 * @return int The template id or preset id.
 */
function et_theme_builder_get_item_id() {
	// Editing library template.
	$item_id = (int) et_()->array_get( $_GET, 'tb_template', 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verified in `et_builder_security_check`.

	if ( $item_id ) {
		return $item_id;
	}

	$item_id = (int) et_()->array_get( $_GET, 'tb_set', 0 ); // phpcs:ignore WordPress.Security.NonceVerification -- Nonce verified in `et_builder_security_check`.

	return $item_id;
}

/**
 * Update library items.
 *
 * @since 4.18.0
 *
 * @param int   $item_id The item id.
 * @param array $templates List of template ids.
 */
function et_theme_builder_update_library_item( $item_id, $templates ) {
	$item_type = et_theme_builder_get_library_item_type( $item_id );

	$global_layouts = array();
	if ( ET_THEME_BUILDER_ITEM_SET === $item_type ) {
		// Remove the templates from preset.
		$old_template_ids = get_post_meta( $item_id, '_et_template_id' );
		delete_post_meta( $item_id, '_et_template_id' );

		// Find global layouts.
		foreach ( $templates as $template ) {
			if ( isset( $template['default'] ) && '1' === $template['default'] ) {
				$global_layouts = $template['layouts'];
				break;
			}
		}
	}

	// Save templates to library.
	$template_post_ids = array();
	foreach ( $templates as $template ) {
		$template['global_layouts'] = $global_layouts;

		$preferences = array(
			'template_name' => $template['title'],
			'force_update'  => true,
		);

		$template_post_ids[] = et_theme_builder_save_template_to_library( $template, $preferences );
	}

	if ( ET_THEME_BUILDER_ITEM_SET === $item_type ) {
		// Trash the removed template.
		$removed_template_ids = array_diff( $old_template_ids, $template_post_ids );
		if ( ! empty( $removed_template_ids ) ) {
			foreach ( $removed_template_ids as $removed_template_id ) {
				wp_trash_post( $removed_template_id );
			}
		}

		// Add saved templates to preset.
		et_theme_builder_add_template_to_preset( $item_id, $template_post_ids );
	}
}

/**
 * Determines if the library item editor is open.
 *
 * @return bool
 */
function et_theme_builder_library_is_item_editor() {
	if ( ! et_builder_is_tb_admin_screen() ) {
		return false;
	}

	$item_id = et_theme_builder_get_item_id();

	if ( ! $item_id || ET_TB_ITEM_POST_TYPE !== get_post_type( $item_id ) ) {
		return false;
	}

	return true;
}
