<?php
/*
 * Plugin Name: Import Products, Variations and Attributes - Free by WP Masters
 * Plugin URI: https://wp-masters.com/products/wpm-import-products-variations-attributes
 * Description: Provide import XLSX to WooCommerce Products Variations with creation attributes
 * Author: WP-Masters
 * Text Domain: wpm-import-variations
 * Author URI: https://wp-masters.com/
 * Version: 1.0.1
 *
 * @author      WP-Masters
 * @version     v.1.0.1 (12/01/23)
 * @copyright   Copyright (c) 2023
*/

require_once( 'templates/libs/SimpleXLSX/SimpleXLSX.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/image.php' );
require_once( 'helpers/array_helper.php' ); 

define( 'WPM_PLUGIN_VARIATIONS_PATH', plugins_url( '', __FILE__ ) );

class WPM_VariationsImport {

	private $import_fields = array(
		'skip'                => 'Skip',
		'field_name'          => 'Product Name',
		'field_sku'           => 'Product SKU',
		'field_description'   => 'Product Description',
		'field_categories'    => 'Product Categories',
		'field_variation_sku' => 'Product Variation SKU',
		'field_price'         => 'Product Price',
		'field_image'         => 'Product Image',
		'field_attribute'     => 'Product Attribute',
	);

	private $required_fields = array(
		'sku',
		'variation_sku',
		'price',
	);

	private $array_helper;

	/**
	 * Initialize functions
	 */
	public function __construct() {
		// Init Functions
		add_action( 'init', [ $this, 'import_variations' ] );

		// Include Styles and Scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts_and_styles' ] );

		// Admin menu
		add_action( 'admin_menu', [ $this, 'register_menu' ] );

		// Ajax Functions
		add_action( 'wp_ajax_import_variations', [ $this, 'import_variations' ] );

		$this->array_helper = WPM_ImportArrayHelper::get_instance();
	}

	/**
	 * Save Core Settings to Option
	 */
	public function import_variations() {
		$array_helper = $this->array_helper;

		if ( ! defined( 'DOING_AJAX' ) ) {
			return false;
		}
		// Get Excel rows
		$xlsx = get_option( 'wpm_xlsx_data' );
		if ( ! $xlsx ) {
			return false;
		}
		if ( isset( $_POST['import-columns'] ) && ! empty( $_POST['import-columns'] ) ) {
			$sanitized_columns = $array_helper->sanitize_array( $_POST['import-columns'] );
			update_option( 'wpm_import_fields', $sanitized_columns );
			$fields = $sanitized_columns;
		} else {
			$fields = get_option( 'wpm_import_fields' );
		}

		$import_id = ( isset( $_POST['import_id'] ) ) ? sanitize_text_field( $_POST['import_id'] ) : rand( 1, 9999 );

		// Pagination Settings
		$limit_items = 1;
		$page_import = isset( $_POST['page_import'] ) ? (int) $_POST['page_import'] : 1;
		$offset      = ( $page_import - 1 ) * $limit_items;

		if ( $xlsx ) {
			// Limit items and Add Column name
			$excel_rows = array_slice( $xlsx, $offset, $limit_items );

			// Start Process
			foreach ( $excel_rows as $row ) {
				$product_data = array();

				foreach ( $row as $type => $value ) {
					switch ( $fields[ $type ] ) {
						case 'field_name':
							$product_data['title'] = $value;
							break;
						case 'field_sku':
							$product_data['sku'] = $value;
							break;
						case 'field_description':
							$product_data['description'] = $value;
							break;
						case 'field_categories':
							$product_data['categories'] = $value;
							break;
						case 'field_variation_sku':
							$product_data['variation_sku'] = $value;
							break;
						case 'field_price':
							$product_data['price'] = $value;
							break;
						case 'field_image':
							$product_data['image_url'] = $value;
							break;
						case 'field_attribute':
							$product_data['attributes'][ $type ] = $value;
							break;
						case 'skip':
							break;
					}
				}
				
				if ( $this->check_required_fields( $product_data ) ) {

					//Create/edit parent product
					$parent_id = $this->create_product( $product_data );

					// Create/edit Variation for Product
					$this->create_product_variation( $parent_id, $product_data );

				}
			}

			// Redirect Settings
			if ( count( $xlsx ) > $page_import * $limit_items ) {
				wp_send_json( [
					'page_import' => $page_import + 1,
					'offset'      => $offset + $limit_items,
					'all_count'   => count( $xlsx ),
					'status'      => 'processing',
					'import_id'   => $import_id
				] );
			} else {
				delete_option( 'wpm_xlsx_data' );
				wp_send_json( [
					'status' => 'finished',
				] );
			}
		}
	}

	/**
	 * Check product required fields
	 *
	 * @param array $product_data
	 *
	 * @return bool
	 */
	public function check_required_fields( $product_data ) {
		foreach ( $this->required_fields as $field ) {
			if ( array_key_exists( $field, $product_data ) ) {
				continue;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Set image to Post
	 */
	public function set_image_to_post( $url, $post_id ) {
		$image = media_sideload_image( $url, $post_id, '', 'id' );
		set_post_thumbnail( $post_id, $image );
	}

	/**
	 * Create product
	 *
	 * @param array $product_data
	 *
	 * @return int $product_id
	 */
	public function create_product( $product_data ) {
		
		$product_id = wc_get_product_id_by_sku( $product_data['sku'] );

		if( $product_id ){
			$product = wc_get_product( $product_id );
		}else{
			$product = new WC_Product_Variable();
			$product_id = $product->save();
		}
		
		$product->set_name( isset( $product_data['title'] ) ? $product_data['title'] : $product_data['sku'] );
		$product->set_status( "publish" );  // can be publish,draft or any wordpress post status
		
		$product->set_catalog_visibility( 'visible' ); // add the product visibility status
		$product->set_description( isset( $product_data['description'] ) ? $product_data['description'] : '' );
		$product->set_sku( $product_data['sku'] ); //can be blank in case you don't have sku, but You can't add duplicate sku's
		$product->set_price( $product_data['price'] ); // set product price
		$product->set_regular_price( $product_data['price'] ); // set product regular price
		$product->set_stock_status( 'instock' );
		$product->set_manage_stock( false );
		
		$product->save();

		if ( $product_data['image_url'] ) {
			$this->set_image_to_post( $product_data['image_url'], $product_id );
		}

		if ( isset( $product_data['categories'] ) ) {
			$this->set_product_categories( $product_id, $product_data['categories'] );
		}
		
		return $product_id;
	}

	/**
	 * Set product categories
	 *
	 * @param int $product_id
	 * @param string $categories
	 *
	 * @return bool
	 */
	public function set_product_categories( $product_id, $categories ) {
		$cat_list = explode( ';', $categories );

		$categories = [];
		foreach ( $cat_list as $cat_name ) {
			$category_id = get_term_by( 'name', trim( $cat_name ), 'product_cat' );
			if ( ! $category_id ) {
				$category_id = wp_insert_term( trim( $cat_name ), 'product_cat' );
				$term_id     = $category_id['term_id'];
			} else {
				$term_id = $category_id->term_id;
			}
			$categories[] = intval( $term_id );
		}
		
		wp_set_object_terms( $product_id, $categories, 'product_cat' );

		return true;
	}

	/**
	 * Create Variations for Main product
	 */
	public function create_product_variation( $product_id, $variation_data ) {
				
		if ( !isset( $variation_data['variation_sku'] ) ) {
			return;
		}


		// Get the Variable product object (parent)
		$product = wc_get_product( $product_id );

		$variation_post = array(
			'post_title'  => isset( $variation_data['title'] ) ? $variation_data['title'] : $product->get_name(),
			'post_name'   => 'product-' . $product_id . '-variation',
			'post_status' => 'publish',
			'post_parent' => $product_id,
			'post_type'   => 'product_variation',
			'guid'        => $product->get_permalink()
		);

		//Check if variation exists
		$variation_id = wc_get_product_id_by_sku( $variation_data['variation_sku'] );

		if( !$variation_id ){
			// Creating the product variation
			$variation_id = wp_insert_post( $variation_post );
		}

		$this->set_image_to_post( $variation_data['image_url'], $variation_id );

		// Get an instance of the WC_Product_Variation object
		$variation = new WC_Product_Variation( $variation_id );

		// Iterating through the variations attributes
		foreach ( $variation_data['attributes'] as $attribute => $term_name ) {
			if ( $term_name == '' || empty( $term_name ) ) {
				continue;
			}

			wc_create_attribute( array(
				'name' => $attribute,
				'type' => 'select'
			) );

			$taxonomy = 'pa_' . $attribute; // The attribute taxonomy

			// If taxonomy doesn't exists we create it
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, 'product', array(
						'hierarchical' => false,
						'label'        => ucfirst( $attribute ),
						'query_var'    => true,
						'rewrite'      => array( 'slug' => sanitize_title( $attribute ) ), // The base slug
					) );
			}

			// Check if the Term name exist and if not we create it.
			if ( ! term_exists( $term_name, $taxonomy ) ) {
				wp_insert_term( $term_name, $taxonomy );
			} // Create the term

			$term_slug = get_term_by( 'name', $term_name, $taxonomy )->slug; // Get the term slug

			// Get the post Terms names from the parent variable product.
			$post_term_names = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );

			// Check if the post term exist and if not we set it in the parent variable product.
			if ( ! in_array( $term_name, $post_term_names ) ) {
				wp_set_post_terms( $product_id, $term_name, $taxonomy, true );
			}

			// Set/save the attribute data in the product variation
			update_post_meta( $variation_id, 'attribute_' . $taxonomy, $term_slug );

			// Set attributes to Main Product
			$term_taxonomy_ids = wp_set_object_terms( $product_id, $term_name, $taxonomy, true );
			$thedata           = array(
				$taxonomy => array(
					'name'         => $taxonomy,
					'value'        => $term_name,
					'is_visible'   => '1',
					'is_variation' => '1',
					'is_taxonomy'  => '1'
				)
			);
			$all_attrs         = get_post_meta( $product_id, '_product_attributes', $thedata );
			update_post_meta( $product_id, '_product_attributes', is_array( $all_attrs ) ? $all_attrs + $thedata : $thedata );
		}

		// SKU
		$variation->set_sku( $variation_data['variation_sku'] );

		// Prices
		$variation->set_price( $variation_data['price'] );
		$variation->set_regular_price( $variation_data['price'] );
		$variation->set_stock_status( 'instock' );
		$variation->set_manage_stock( false );

		$variation->set_weight( '' ); // weight (reset)
		$variation->save(); // Save the data
	}

	/**
	 * Include Scripts And Styles on Admin Pages
	 */
	public function admin_scripts_and_styles() {
		$ajax_nonce = wp_create_nonce( 'ajax_nonce' );

		// Register styles
		wp_enqueue_style( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.css', __FILE__ ) );
		wp_enqueue_style( 'wpm-core-tips', plugins_url( 'templates/libs/tips/tips.css', __FILE__ ) );
		wp_enqueue_style( 'wpm-core-admin', plugins_url( 'templates/assets/css/admin.css?6', __FILE__ ) );

		// Register Scripts
		wp_enqueue_script( 'wpm-font-awesome', plugins_url( 'templates/libs/font-awesome/scripts/all.min.js', __FILE__ ) );
		wp_enqueue_script( 'wpm-core-tips', plugins_url( 'templates/libs/tips/tips.js', __FILE__ ) );
		wp_enqueue_script( 'wpm-core-admin', plugins_url( 'templates/assets/js/admin.js?6', __FILE__ ) );
		wp_localize_script( 'wpm-core-admin', 'admin', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => $ajax_nonce,
		) );
		wp_enqueue_script( 'wpm-core-admin' );
	}

	/**
	 * Add Settings to Admin Menu
	 */
	public function register_menu() {
		add_menu_page( 'WPM Import Variations', 'WPM Import Variations', 'edit_others_posts', 'wpm_import_variations_settings' );
		add_submenu_page( 'wpm_import_variations_settings', 'WPM Import Variations', 'WPM Import Variations', 'manage_options', 'wpm_import_variations_settings', function () {
			global $wp_version, $wpdb;

			$import_fields = $this->import_fields;

			include 'templates/admin/settings.php';
		} );
	}
}

new WPM_VariationsImport();

