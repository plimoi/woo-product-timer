<?php
/*
Plugin Name: WPC Product Timer for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Timer helps you add many actions for the product based on the conditionals of the time.
Version: 4.3.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: woo-product-timer
Domain Path: /languages/
Requires at least: 4.0
Tested up to: 6.2
WC requires at least: 3.0
WC tested up to: 7.7
*/

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

! defined( 'WOOPT_VERSION' ) && define( 'WOOPT_VERSION', '4.3.1' );
! defined( 'WOOPT_FILE' ) && define( 'WOOPT_FILE', __FILE__ );
! defined( 'WOOPT_URI' ) && define( 'WOOPT_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOPT_DIR' ) && define( 'WOOPT_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOPT_DOCS' ) && define( 'WOOPT_DOCS', 'https://doc.wpclever.net/woopt/' );
! defined( 'WOOPT_SUPPORT' ) && define( 'WOOPT_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woopt&utm_campaign=wporg' );
! defined( 'WOOPT_REVIEWS' ) && define( 'WOOPT_REVIEWS', 'https://wordpress.org/support/plugin/woo-product-timer/reviews/?filter=5' );
! defined( 'WOOPT_CHANGELOG' ) && define( 'WOOPT_CHANGELOG', 'https://wordpress.org/plugins/woo-product-timer/#developers' );
! defined( 'WOOPT_DISCUSSION' ) && define( 'WOOPT_DISCUSSION', 'https://wordpress.org/support/plugin/woo-product-timer' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOPT_URI );

include 'includes/wpc-dashboard.php';
include 'includes/wpc-menu.php';
include 'includes/kit/wpc-kit.php';

if ( ! function_exists( 'woopt_init' ) ) {
	add_action( 'plugins_loaded', 'woopt_init', 11 );

	function woopt_init() {
		// load text-domain
		load_plugin_textdomain( 'woo-product-timer', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woopt_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WPCleverWoopt' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWoopt {
				public static $global_actions = [];
				public static $features = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$global_actions = (array) get_option( 'woopt_actions', [] );
					self::$features       = (array) get_option( 'woopt_features', [] );

					// Settings
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// Enqueue backend scripts
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

					// Product data tabs
					add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );

					// Product data panels
					add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
					add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

					// Add settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// AJAX
					add_action( 'wp_ajax_woopt_save_actions', [ $this, 'save_actions' ] );
					add_action( 'wp_ajax_woopt_add_conditional', [ $this, 'add_conditional' ] );
					add_action( 'wp_ajax_woopt_add_apply_conditional', [ $this, 'add_apply_conditional' ] );
					add_action( 'wp_ajax_woopt_search_term', [ $this, 'search_term' ] );

					// Product class
					add_filter( 'woocommerce_post_class', [ $this, 'woopt_post_class' ], 99, 2 );

					// Features
					if ( empty( self::$features ) || in_array( 'stock', self::$features ) ) {
						add_filter( 'woocommerce_product_is_in_stock', [ $this, 'woopt_is_in_stock' ], 99, 2 );
					}

					if ( empty( self::$features ) || in_array( 'visibility', self::$features ) ) {
						add_filter( 'woocommerce_product_is_visible', [ $this, 'woopt_is_visible' ], 99, 2 );
						add_filter( 'woocommerce_variation_is_visible', [ $this, 'woopt_is_visible' ], 99, 2 );
						add_filter( 'woocommerce_variation_is_active', [ $this, 'woopt_is_visible' ], 99, 2 );
					}

					if ( empty( self::$features ) || in_array( 'featured', self::$features ) ) {
						add_filter( 'woocommerce_product_get_featured', [ $this, 'woopt_is_featured' ], 99, 2 );
					}

					if ( empty( self::$features ) || in_array( 'purchasable', self::$features ) ) {
						add_filter( 'woocommerce_is_purchasable', [ $this, 'woopt_is_purchasable' ], 99, 2 );
					}

					if ( empty( self::$features ) || in_array( 'individually', self::$features ) ) {
						add_filter( 'woocommerce_is_sold_individually', [ $this, 'woopt_sold_individually' ], 99, 2 );
					}

					if ( empty( self::$features ) || in_array( 'price', self::$features ) ) {
						add_filter( 'woocommerce_product_get_regular_price', [
							$this,
							'woopt_get_regular_price'
						], 99, 2 );
						add_filter( 'woocommerce_product_get_sale_price', [ $this, 'woopt_get_sale_price' ], 99, 2 );
						add_filter( 'woocommerce_product_get_price', [ $this, 'woopt_get_price' ], 99, 2 );

						// Variation
						add_filter( 'woocommerce_product_variation_get_regular_price', [
							$this,
							'woopt_get_regular_price'
						], 99, 2 );
						add_filter( 'woocommerce_product_variation_get_sale_price', [
							$this,
							'woopt_get_sale_price'
						], 99, 2 );
						add_filter( 'woocommerce_product_variation_get_price', [ $this, 'woopt_get_price' ], 99, 2 );

						// Variations
						add_filter( 'woocommerce_variation_prices_regular_price', [
							$this,
							'woopt_get_regular_price'
						], 99, 2 );
						add_filter( 'woocommerce_variation_prices_sale_price', [
							$this,
							'woopt_get_sale_price'
						], 99, 2 );
						add_filter( 'woocommerce_variation_prices_price', [ $this, 'woopt_get_price' ], 99, 2 );
					}

					// Product columns
					add_filter( 'manage_edit-product_columns', [ $this, 'product_columns' ], 10 );
					add_action( 'manage_product_posts_custom_column', [ $this, 'custom_column' ], 10, 2 );

					// Ajax edit
					add_action( 'wp_ajax_woopt_edit', [ $this, 'edit_timer' ] );
					add_action( 'wp_ajax_woopt_edit_save', [ $this, 'save_timer' ] );

					// Ajax import / export
					add_action( 'wp_ajax_woopt_import_export', [ $this, 'import_export' ] );
					add_action( 'wp_ajax_woopt_import_export_save', [ $this, 'import_export_save' ] );

					// Export
					add_filter( 'woocommerce_product_export_column_names', [ $this, 'export_columns' ] );
					add_filter( 'woocommerce_product_export_product_default_columns', [ $this, 'export_columns' ] );
					add_filter( 'woocommerce_product_export_product_column_woopt_actions', [
						$this,
						'export_data'
					], 10, 2 );

					// Import
					add_filter( 'woocommerce_csv_product_import_mapping_options', [ $this, 'import_options' ] );
					add_filter( 'woocommerce_csv_product_import_mapping_default_columns', [ $this, 'import_columns' ] );
					add_filter( 'woocommerce_product_import_pre_insert_product_object', [
						$this,
						'import_process'
					], 10, 2 );

					// HPOS compatibility
					add_action( 'before_woocommerce_init', function () {
						if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
							FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOPT_FILE );
						}
					} );
				}

				public static function woopt_action_data( $action ) {
					if ( ! empty( $action ) ) {
						$action_arr = explode( '|', $action );

						if ( strpos( $action, 'apply_' ) === false ) {
							// product
							return [
								'action'      => isset( $action_arr[0] ) ? $action_arr[0] : '',
								'value'       => isset( $action_arr[1] ) ? $action_arr[1] : '100%',
								'conditional' => isset( $action_arr[2] ) ? $action_arr[2] : '',
								'roles'       => isset( $action_arr[3] ) ? $action_arr[3] : '',
							];
						} else {
							// global
							return [
								'apply'       => isset( $action_arr[0] ) ? $action_arr[0] : 'apply_all',
								'apply_val'   => isset( $action_arr[1] ) ? $action_arr[1] : '',
								'action'      => isset( $action_arr[2] ) ? $action_arr[2] : '',
								'value'       => isset( $action_arr[3] ) ? $action_arr[3] : '100%',
								'conditional' => isset( $action_arr[4] ) ? $action_arr[4] : '',
								'roles'       => isset( $action_arr[5] ) ? $action_arr[5] : '',
							];
						}
					} else {
						// null
						return [
							'apply'       => '',
							'apply_val'   => '',
							'action'      => '',
							'value'       => '',
							'conditional' => '',
							'roles'       => '',
						];
					}
				}

				public static function woopt_check_apply( $product, $apply, $apply_val, $is_variation = false ) {
					$product_id = 0;

					if ( is_numeric( $product ) ) {
						$product_id = $product;
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					}

					if ( ! $product_id ) {
						return false;
					}

					switch ( $apply ) {
						case 'apply_all':
							return true;
						case 'apply_variation':
							if ( $is_variation ) {
								return true;
							}

							return false;
						case 'apply_not_variation':
							if ( ! $is_variation ) {
								return true;
							}

							return false;
						case 'apply_product':
							if ( ! empty( $apply_val ) ) {
								$ids = explode( ',', $apply_val );

								if ( in_array( $product_id, $ids ) ) {
									return true;
								}
							}

							return false;
						case 'apply_category':
							if ( ! empty( $apply_val ) ) {
								$cats = explode( ',', $apply_val );

								if ( has_term( $cats, 'product_cat', $product_id ) ) {
									return true;
								}
							}

							return false;
						case 'apply_tag':
							if ( ! empty( $apply_val ) ) {
								$tags = array_map( 'trim', explode( ',', $apply_val ) );

								if ( has_term( $tags, 'product_tag', $product_id ) ) {
									return true;
								}
							}

							return false;
						case 'apply_combination':
							if ( ! empty( $apply_val ) ) {
								$match_all       = true;
								$conditional_arr = explode( '&', $apply_val );

								if ( is_array( $conditional_arr ) && ( count( $conditional_arr ) > 0 ) ) {
									foreach ( $conditional_arr as $conditional_item ) {
										$match                    = false;
										$conditional_item_arr     = explode( '>', $conditional_item );
										$conditional_item_key     = trim( isset( $conditional_item_arr[0] ) ? $conditional_item_arr[0] : '' );
										$conditional_item_val     = trim( isset( $conditional_item_arr[1] ) ? $conditional_item_arr[1] : '' );
										$conditional_item_val_arr = array_map( 'trim', explode( ',', $conditional_item_val ) );

										if ( $conditional_item_key === 'variation' ) {
											if ( $is_variation ) {
												$match = true;
											}
										} elseif ( $conditional_item_key === 'not_variation' ) {
											if ( ! $is_variation ) {
												$match = true;
											}
										} else {
											if ( $is_variation ) {
												$variation = wc_get_product( $product_id );
												$attrs     = $variation->get_attributes();

												if ( ! empty( $attrs[ $conditional_item_key ] ) ) {
													if ( in_array( $attrs[ $conditional_item_key ], $conditional_item_val_arr ) ) {
														$match = true;
													}
												}
											} else {
												if ( has_term( $conditional_item_val_arr, $conditional_item_key, $product_id ) ) {
													$match = true;
												}
											}
										}

										$match_all &= $match;
									}
								} else {
									$match_all = false;
								}

								return $match_all;
							}

							return false;
						default:
							if ( ! empty( $apply_val ) ) {
								$taxonomy = substr( $apply, 6 ); // trim from $apply
								$terms    = array_map( 'trim', explode( ',', $apply_val ) );

								if ( $is_variation ) {
									$variation = wc_get_product( $product_id );
									$attrs     = $variation->get_attributes();

									if ( ! empty( $attrs[ $taxonomy ] ) ) {
										if ( in_array( $attrs[ $taxonomy ], $terms ) ) {
											return true;
										}
									}
								} else {
									if ( has_term( $terms, $taxonomy, $product_id ) ) {
										return true;
									}
								}
							}

							return false;
					}
				}

				public static function woopt_check_conditional( $conditionals, $product_id = null ) {
					$condition        = true;
					$conditionals_arr = explode( '&', $conditionals );

					if ( count( $conditionals_arr ) > 0 ) {
						foreach ( $conditionals_arr as $conditional ) {
							$condition_item  = false;
							$conditional_arr = explode( '>', $conditional );

							if ( count( $conditional_arr ) > 0 ) {
								$conditional_key   = isset( $conditional_arr[0] ) ? $conditional_arr[0] : '';
								$conditional_value = isset( $conditional_arr[1] ) ? $conditional_arr[1] : '';

								if ( $conditional_value !== '' ) {
									switch ( $conditional_key ) {
										case 'date_range':
											$date_range_arr = explode( '-', $conditional_value );

											if ( count( $date_range_arr ) === 2 ) {
												$date_range_start = trim( $date_range_arr[0] );
												$date_range_end   = trim( $date_range_arr[1] );
												$current_date     = strtotime( current_time( 'm/d/Y' ) );

												if ( $current_date >= strtotime( $date_range_start ) && $current_date <= strtotime( $date_range_end ) ) {
													$condition_item = true;
												}
											} elseif ( count( $date_range_arr ) === 1 ) {
												$date_range_start = trim( $date_range_arr[0] );

												if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $date_range_start ) ) {
													$condition_item = true;
												}
											}

											break;
										case 'date_multi':
											$multiple_dates_arr = explode( ', ', $conditional_value );

											if ( in_array( current_time( 'm/d/Y' ), $multiple_dates_arr ) ) {
												$condition_item = true;
											}

											break;
										case 'date_even':
											if ( (int) current_time( 'd' ) % 2 === 0 ) {
												$condition_item = true;
											}

											break;
										case 'date_odd':
											if ( (int) current_time( 'd' ) % 2 !== 0 ) {
												$condition_item = true;
											}

											break;
										case 'date_on':
											if ( strtotime( current_time( 'm/d/Y' ) ) === strtotime( $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'date_before':
											if ( strtotime( current_time( 'm/d/Y' ) ) < strtotime( $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'date_after':
											if ( strtotime( current_time( 'm/d/Y' ) ) > strtotime( $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'date_time_before':
											$current_time = current_time( 'm/d/Y h:i a' );

											if ( strtotime( $current_time ) < strtotime( $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'date_time_after':
											$current_time = current_time( 'm/d/Y h:i a' );

											if ( strtotime( $current_time ) > strtotime( $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'time_range':
											$time_range_arr = explode( '-', $conditional_value );

											if ( count( $time_range_arr ) === 2 ) {
												$current_time     = strtotime( current_time( 'm/d/Y h:i a' ) );
												$current_date     = current_time( 'm/d/Y' );
												$time_range_start = $current_date . ' ' . trim( $time_range_arr[0] );
												$time_range_end   = $current_date . ' ' . trim( $time_range_arr[1] );

												if ( $current_time >= strtotime( $time_range_start ) && $current_time <= strtotime( $time_range_end ) ) {
													$condition_item = true;
												}
											}

											break;
										case 'time_before':
											$current_time = current_time( 'm/d/Y h:i a' );
											$current_date = current_time( 'm/d/Y' );

											if ( strtotime( $current_time ) < strtotime( $current_date . ' ' . $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'time_after':
											$current_time = current_time( 'm/d/Y h:i a' );
											$current_date = current_time( 'm/d/Y' );

											if ( strtotime( $current_time ) > strtotime( $current_date . ' ' . $conditional_value ) ) {
												$condition_item = true;
											}

											break;
										case 'weekly_every':
											if ( strtolower( current_time( 'D' ) ) === $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'week_even':
											if ( (int) current_time( 'W' ) % 2 === 0 ) {
												$condition_item = true;
											}

											break;
										case 'week_odd':
											if ( (int) current_time( 'W' ) % 2 !== 0 ) {
												$condition_item = true;
											}

											break;
										case 'week_no':
											if ( (int) current_time( 'W' ) === (int) $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'monthly_every':
											if ( strtolower( current_time( 'j' ) ) === $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'month_no':
											if ( (int) current_time( 'm' ) === (int) $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'days_less_published':
											$published = get_the_time( 'U', $product_id );

											if ( ( current_time( 'U' ) - $published ) < 60 * 60 * 24 * (int) $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'days_greater_published':
											$published = get_the_time( 'U', $product_id );

											if ( ( current_time( 'U' ) - $published ) > 60 * 60 * 24 * (int) $conditional_value ) {
												$condition_item = true;
											}

											break;
										case 'every_day':
											$condition_item = true;

											break;
									}
								}
							}

							$condition &= $condition_item;
						}
					}

					return $condition;
				}

				public static function woopt_check_roles( $roles ) {
					if ( is_string( $roles ) ) {
						$roles = explode( ',', $roles );
					}

					if ( empty( $roles ) || in_array( 'all', (array) $roles ) ) {
						return true;
					}

					if ( is_user_logged_in() ) {
						$current_user = wp_get_current_user();

						foreach ( $current_user->roles as $role ) {
							if ( in_array( $role, (array) $roles ) ) {
								return true;
							}
						}
					} else {
						if ( in_array( 'guest', (array) $roles ) ) {
							return true;
						}
					}

					return false;
				}

				public static function woopt_get_action_result( $result, $product, $action_true = '', $action_false = '' ) {
					$variation_id = 0;
					$product_id   = $product->get_id();

					if ( $product->is_type( 'variation' ) ) {
						$variation_id = $product_id;
						$product_id   = $product->get_parent_id();
					}

					// global actions
					if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( $action_key !== $action_true && $action_key !== $action_false ) {
								continue;
							}

							if ( self::woopt_check_apply( $product_id, $action_apply, $action_apply_val ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( $action_key === $action_true ) {
									$result = true;
								} else {
									$result = false;
								}
							}
						}
					}

					// product actions
					$actions = get_post_meta( $product_id, 'woopt_actions', true );

					if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
						foreach ( $actions as $action ) {
							$action_data        = self::woopt_action_data( $action );
							$action_key         = $action_data['action'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( $action_key !== $action_true && $action_key !== $action_false ) {
								continue;
							}

							if ( self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( $action_key === $action_true ) {
									$result = true;
								} else {
									$result = false;
								}
							}
						}
					}

					// global actions for variation
					if ( $variation_id && is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_apply !== 'apply_product' ) && ( $action_apply !== 'apply_combination' ) ) {
								continue;
							}

							if ( $action_key !== $action_true && $action_key !== $action_false ) {
								continue;
							}

							if ( self::woopt_check_apply( $variation_id, $action_apply, $action_apply_val, true ) && self::woopt_check_conditional( $action_conditional, $variation_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( $action_key === $action_true ) {
									$result = true;
								} else {
									$result = false;
								}
							}
						}
					}

					return $result;
				}

				public static function woopt_post_class( $classes, $product ) {
					if ( apply_filters( 'woopt_ignore', false, $product, 'post_class' ) ) {
						return $classes;
					}

					if ( $product && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
						$product_id = $product->get_parent_id();
					} else {
						$product_id = $product->get_id();
					}

					// global actions
					if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							if ( empty( $global_action ) ) {
								continue;
							}

							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( self::woopt_check_apply( $product_id, $action_apply, $action_apply_val ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								$classes[] = 'woopt_global';
								$classes[] = 'woopt_global_' . $action_key;
							}
						}
					}

					$actions = get_post_meta( $product_id, 'woopt_actions', true );

					if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
						foreach ( $actions as $action ) {
							$action_data        = self::woopt_action_data( $action );
							$action_key         = $action_data['action'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								$classes[] = 'woopt';
								$classes[] = 'woopt_' . $action_key;
							}
						}
					}

					return $classes;
				}

				public static function woopt_get_regular_price( $regular_price, $product ) {
					$variation_id = 0;
					$product_id   = $product->get_id();

					if ( apply_filters( 'woopt_ignore', false, $product, 'regular_price' ) ) {
						return $regular_price;
					}

					if ( $product->is_type( 'variation' ) ) {
						$variation_id = $product_id;
						$product_id   = $product->get_parent_id();
					}

					// global actions
					if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_key === 'set_regularprice' ) && self::woopt_check_apply( $product_id, $action_apply, $action_apply_val ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $regular_price * $percentage / 100;
								}

								$regular_price = $action_price;
							}
						}
					}

					// product actions
					$actions = get_post_meta( $product_id, 'woopt_actions', true );

					if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
						foreach ( $actions as $action ) {
							$action_data        = self::woopt_action_data( $action );
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_key === 'set_regularprice' ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $regular_price * $percentage / 100;
								}

								$regular_price = $action_price;
							}
						}
					}

					// global actions for variation
					if ( $variation_id && is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_apply !== 'apply_product' ) && ( $action_apply !== 'apply_combination' ) ) {
								continue;
							}

							if ( ( $action_key === 'set_regularprice' ) && self::woopt_check_apply( $variation_id, $action_apply, $action_apply_val, true ) && self::woopt_check_conditional( $action_conditional, $variation_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $regular_price * $percentage / 100;
								}

								$regular_price = $action_price;
							}
						}
					}

					return apply_filters( 'woopt_get_regular_price', $regular_price, $product );
				}

				public static function woopt_get_sale_price( $sale_price, $product ) {
					$variation_id = 0;
					$product_id   = $product->get_id();

					if ( apply_filters( 'woopt_ignore', false, $product, 'sale_price' ) ) {
						return $sale_price;
					}

					if ( $product->is_type( 'variation' ) ) {
						$variation_id = $product_id;
						$product_id   = $product->get_parent_id();
					}

					// global actions
					if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_key === 'set_saleprice' ) && self::woopt_check_apply( $product_id, $action_apply, $action_apply_val ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $sale_price * $percentage / 100;
								}

								$sale_price = $action_price;
							}
						}
					}

					// product actions
					$actions = get_post_meta( $product_id, 'woopt_actions', true );

					if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
						foreach ( $actions as $action ) {
							$action_data        = self::woopt_action_data( $action );
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_key === 'set_saleprice' ) && self::woopt_check_conditional( $action_conditional, $product_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $sale_price * $percentage / 100;
								}

								$sale_price = $action_price;
							}
						}
					}

					// global actions for variation
					if ( $variation_id && is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
						foreach ( self::$global_actions as $global_action ) {
							$action_data        = self::woopt_action_data( $global_action );
							$action_apply       = $action_data['apply'];
							$action_apply_val   = $action_data['apply_val'];
							$action_key         = $action_data['action'];
							$action_price       = $action_data['value'];
							$action_conditional = $action_data['conditional'];
							$action_roles       = $action_data['roles'];

							if ( ( $action_apply !== 'apply_product' ) && ( $action_apply !== 'apply_combination' ) ) {
								continue;
							}

							if ( ( $action_key === 'set_saleprice' ) && self::woopt_check_apply( $variation_id, $action_apply, $action_apply_val, true ) && self::woopt_check_conditional( $action_conditional, $variation_id ) && self::woopt_check_roles( $action_roles ) ) {
								if ( strpos( $action_price, '%' ) !== false ) {
									$percentage   = (float) preg_replace( '/[^0-9.]/', '', $action_price );
									$action_price = (float) $sale_price * $percentage / 100;
								}

								$sale_price = $action_price;
							}
						}
					}

					return apply_filters( 'woopt_get_sale_price', $sale_price, $product );
				}

				public static function woopt_get_price( $price, $product ) {
					if ( apply_filters( 'woopt_ignore', false, $product, 'price' ) ) {
						return $price;
					}

					if ( $product->is_on_sale() ) {
						return self::woopt_get_sale_price( $price, $product );
					}

					return self::woopt_get_regular_price( $price, $product );
				}

				public static function woopt_is_in_stock( $in_stock, $product ) {
					if ( apply_filters( 'woopt_ignore', false, $product, 'in_stock' ) ) {
						return $in_stock;
					}

					$in_stock = self::woopt_get_action_result( $in_stock, $product, 'set_instock', 'set_outofstock' );

					return apply_filters( 'woopt_is_in_stock', $in_stock, $product );
				}

				public static function woopt_is_visible( $visible, $product_id ) {
					$product = wc_get_product( $product_id );

					if ( apply_filters( 'woopt_ignore', false, $product, 'visible' ) ) {
						return $visible;
					}

					$visible = self::woopt_get_action_result( $visible, $product, 'set_visible', 'set_hidden' );

					return apply_filters( 'woopt_is_visible', $visible, $product );
				}

				public static function woopt_is_featured( $featured, $product ) {
					if ( is_numeric( $product ) ) {
						$product = wc_get_product( $product );
					}

					if ( apply_filters( 'woopt_ignore', false, $product, 'featured' ) ) {
						return $featured;
					}

					$featured = self::woopt_get_action_result( $featured, $product, 'set_featured', 'set_unfeatured' );

					return apply_filters( 'woopt_is_featured', $featured, $product );
				}

				public static function woopt_is_purchasable( $purchasable, $product ) {
					if ( apply_filters( 'woopt_ignore', false, $product, 'purchasable' ) ) {
						return $purchasable;
					}

					$purchasable = self::woopt_get_action_result( $purchasable, $product, 'set_purchasable', 'set_unpurchasable' );

					return apply_filters( 'woopt_is_purchasable', $purchasable, $product );
				}

				public static function woopt_sold_individually( $sold_individually, $product ) {
					if ( apply_filters( 'woopt_ignore', false, $product, 'sold_individually' ) ) {
						return $sold_individually;
					}

					$sold_individually = self::woopt_get_action_result( $sold_individually, $product, 'enable_sold_individually', 'disable_sold_individually' );

					return apply_filters( 'woopt_sold_individually', $sold_individually, $product );
				}

				function register_settings() {
					// settings
					register_setting( 'woopt_settings', 'woopt_features' );
					register_setting( 'woopt_settings', 'woopt_actions' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Product Timer', 'woo-product-timer' ), esc_html__( 'Product Timer', 'woo-product-timer' ), 'manage_options', 'wpclever-woopt', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'global';
					?>
					<div class="wpclever_settings_page wrap">
						<h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Timer', 'woo-product-timer' ) . ' ' . WOOPT_VERSION; ?></h1>
						<div class="wpclever_settings_page_desc about-text">
							<p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-product-timer' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
								<br/>
								<a href="<?php echo esc_url( WOOPT_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'woo-product-timer' ); ?></a> |
								<a href="<?php echo esc_url( WOOPT_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'woo-product-timer' ); ?></a> |
								<a href="<?php echo esc_url( WOOPT_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'woo-product-timer' ); ?></a>
							</p>
						</div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
							<div class="notice notice-success is-dismissible">
								<p><?php esc_html_e( 'Settings updated.', 'woo-product-timer' ); ?></p>
							</div>
						<?php } ?>
						<div class="wpclever_settings_page_nav">
							<h2 class="nav-tab-wrapper">
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woopt&tab=how' ); ?>" class="<?php echo esc_attr( $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'How to use?', 'woo-product-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woopt&tab=global' ); ?>" class="<?php echo esc_attr( $active_tab === 'global' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Global Timer', 'woo-product-timer' ); ?>
								</a> <a href="<?php echo esc_url( WOOPT_DOCS ); ?>" class="nav-tab" target="_blank">
									<?php esc_html_e( 'Docs', 'woo-product-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-woopt&tab=premium' ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'woo-product-timer' ); ?>
								</a>
								<a href="<?php echo admin_url( 'admin.php?page=wpclever-kit' ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'woo-product-timer' ); ?>
								</a>
							</h2>
						</div>
						<div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'how' ) { ?>
								<div class="wpclever_settings_page_content_text">
									<p>
										<?php esc_html_e( '1. Global timer: Switch to Global Timer tab to set the timer for all products, categories or tags.', 'woo-product-timer' ); ?>
									</p>
									<p>
										<?php esc_html_e( '2. Product basis timer: When adding/editing the product you can choose the Timer tab then add action & time conditional.', 'woo-product-timer' ); ?>
									</p>
								</div>
							<?php } elseif ( $active_tab === 'global' ) {
								// delete product transients to refresh variable prices
								wc_delete_product_transients();
								?>
								<form method="post" action="options.php">
									<table class="form-table">
										<tr>
											<th>
												<?php esc_html_e( 'Performance', 'woo-product-timer' ); ?>
											</th>
											<td>
												<ul>
													<li>
														<input type="checkbox" name="woopt_features[]" value="stock" <?php echo esc_attr( empty( self::$features ) || in_array( 'stock', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Stock (in stock, out of stock)', 'woo-product-timer' ); ?>
													</li>
													<li>
														<input type="checkbox" name="woopt_features[]" value="visibility" <?php echo esc_attr( empty( self::$features ) || in_array( 'visibility', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Visibility (visible, hidden)', 'woo-product-timer' ); ?>
													</li>
													<li>
														<input type="checkbox" name="woopt_features[]" value="featured" <?php echo esc_attr( empty( self::$features ) || in_array( 'featured', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Featured (featured, unfeatured)', 'woo-product-timer' ); ?>
													</li>
													<li>
														<input type="checkbox" name="woopt_features[]" value="purchasable" <?php echo esc_attr( empty( self::$features ) || in_array( 'purchasable', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Purchasable (purchasable, unpurchasable)', 'woo-product-timer' ); ?>
													</li>
													<li>
														<input type="checkbox" name="woopt_features[]" value="price" <?php echo esc_attr( empty( self::$features ) || in_array( 'price', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Price (regular price, sale price)', 'woo-product-timer' ); ?>
													</li>
													<li>
														<input type="checkbox" name="woopt_features[]" value="individually" <?php echo esc_attr( empty( self::$features ) || in_array( 'individually', self::$features ) ? 'checked' : '' ); ?>/>
														<?php esc_html_e( 'Sold individually (enable, disable)', 'woo-product-timer' ); ?>
													</li>
												</ul>
												<span class="description"><?php esc_html_e( 'Uncheck the feature(s) you don\'t use in all timers for better performance.', 'woo-product-timer' ); ?></span>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Current time', 'woo-product-timer' ); ?>
											</th>
											<td>
												<code><?php echo current_time( 'l' ); ?></code>
												<code><?php echo current_time( 'm/d/Y' ); ?></code>
												<code><?php echo current_time( 'h:i a' ); ?></code>
												<code><?php echo esc_html__( 'Week No.', 'woo-product-timer' ) . ' ' . current_time( 'W' ); ?></code>
												<a href="<?php echo admin_url( 'options-general.php' ); ?>" target="_blank"><?php esc_html_e( 'Date/time settings', 'woo-product-timer' ); ?></a>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Actions', 'woo-product-timer' ); ?>
											</th>
											<td>
												<div class="woopt_actions">
													<?php
													if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
														foreach ( self::$global_actions as $action ) {
															self::action( $action, true );
														}
													} else {
														self::action( null, true, true );
													}
													?>
												</div>
												<div class="woopt_add_action">
													<div>
														<a href="https://wpclever.net/downloads/product-timer?utm_source=pro&utm_medium=woopt&utm_campaign=wporg" target="_blank" class="button" onclick="return confirm('This feature only available in Premium Version!\nBuy it now? Just $29')">
															<?php esc_html_e( '+ Add action', 'woo-product-timer' ); ?>
														</a> <a href="#" class="woopt_expand_all">
															<?php esc_html_e( 'Expand All', 'woo-product-timer' ); ?>
														</a> <a href="#" class="woopt_collapse_all">
															<?php esc_html_e( 'Collapse All', 'woo-product-timer' ); ?>
														</a>
													</div>
												</div>
											</td>
										</tr>
										<tr>
											<th>
												<?php esc_html_e( 'Suggestion', 'woo-product-timer' ); ?>
											</th>
											<td>
												To display custom engaging real-time messages on any wished positions, please install
												<a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages for WooCommerce</a> plugin. It's free and available now on the WordPress repository.
											</td>
										</tr>
										<tr class="submit">
											<th colspan="2">
												<?php settings_fields( 'woopt_settings' ); ?><?php submit_button(); ?>
											</th>
										</tr>
									</table>
								</form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
								<div class="wpclever_settings_page_content_text">
									<p>Get the Premium Version just $29!
										<a href="https://wpclever.net/downloads/product-timer?utm_source=pro&utm_medium=woopt&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/product-timer</a>
									</p>
									<p><strong>Extra features for Premium Version:</strong></p>
									<ul style="margin-bottom: 0">
										<li>- Add multiple actions.</li>
										<li>- Get the lifetime update & premium support.</li>
									</ul>
								</div>
							<?php } ?>
						</div>
					</div>
					<?php
				}

				function admin_enqueue_scripts() {
					// wpcdpk
					wp_enqueue_style( 'wpcdpk', WOOPT_URI . 'assets/libs/wpcdpk/css/datepicker.css' );
					wp_enqueue_script( 'wpcdpk', WOOPT_URI . 'assets/libs/wpcdpk/js/datepicker.js', [ 'jquery' ], WOOPT_VERSION, true );

					// backend
					wp_enqueue_style( 'woopt-backend', WOOPT_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOPT_VERSION );
					wp_enqueue_script( 'woopt-backend', WOOPT_URI . 'assets/js/backend.js', [
						'jquery',
						'jquery-ui-sortable',
						'jquery-ui-dialog',
						'wc-enhanced-select',
						'selectWoo',
					], WOOPT_VERSION, true );
					wp_localize_script( 'woopt-backend', 'woopt_vars', [
						'woopt_nonce' => wp_create_nonce( 'woopt_nonce' )
					] );
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$how                  = '<a href="' . admin_url( 'admin.php?page=wpclever-woopt&tab=how' ) . '">' . esc_html__( 'How to use?', 'woo-product-timer' ) . '</a>';
						$global               = '<a href="' . admin_url( 'admin.php?page=wpclever-woopt&tab=global' ) . '">' . esc_html__( 'Global Timer', 'woo-product-timer' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . admin_url( 'admin.php?page=wpclever-woopt&tab=premium' ) . '">' . esc_html__( 'Premium Version', 'woo-product-timer' ) . '</a>';
						array_unshift( $links, $how, $global );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'docs'    => '<a href="' . esc_url( WOOPT_DOCS ) . '" target="_blank">' . esc_html__( 'Docs', 'woo-product-timer' ) . '</a>',
							'support' => '<a href="' . esc_url( WOOPT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'woo-product-timer' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function product_data_tabs( $tabs ) {
					$tabs['woopt'] = [
						'label'  => esc_html__( 'Timer', 'woo-product-timer' ),
						'target' => 'woopt_settings',
					];

					return $tabs;
				}

				function product_data_panels() {
					global $post;
					$post_id = $post->ID;
					$actions = get_post_meta( $post_id, 'woopt_actions', true );
					?>
					<div id='woopt_settings' class='panel woocommerce_options_panel woopt_settings'>
						<div class="woopt_global_timer"><span class="dashicons dashicons-admin-site"></span>
							<a href="<?php echo admin_url( 'admin.php?page=wpclever-woopt&tab=global' ); ?>" target="_blank"><?php esc_html_e( 'Click here to configure the Global Timer', 'woo-product-timer' ); ?></a>
						</div>
						<div class="woopt_current_time">
							<?php esc_html_e( 'Current time', 'woo-product-timer' ); ?>
							<code><?php echo current_time( 'l' ); ?></code>
							<code><?php echo current_time( 'm/d/Y' ); ?></code>
							<code><?php echo current_time( 'h:i a' ); ?></code>
							<code><?php echo esc_html__( 'Week No.', 'woo-product-timer' ) . ' ' . current_time( 'W' ); ?></code>
							<a href="<?php echo admin_url( 'options-general.php' ); ?>" target="_blank"><?php esc_html_e( 'Date/time settings', 'woo-product-timer' ); ?></a>
						</div>
						<div class="woopt_actions">
							<?php
							if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
								foreach ( $actions as $action ) {
									self::action( $action );
								}
							} else {
								self::action( null, false, true );
							}
							?>
						</div>
						<div class="woopt_add_action">
							<div>
								<a href="https://wpclever.net/downloads/product-timer?utm_source=pro&utm_medium=woopt&utm_campaign=wporg" target="_blank" class="button" onclick="return confirm('This feature only available in Premium Version!\nBuy it now? Just $29')">
									<?php esc_html_e( '+ Add action', 'woo-product-timer' ); ?>
								</a> <a href="#" class="woopt_expand_all">
									<?php esc_html_e( 'Expand All', 'woo-product-timer' ); ?>
								</a> <a href="#" class="woopt_collapse_all">
									<?php esc_html_e( 'Collapse All', 'woo-product-timer' ); ?>
								</a>
							</div>
							<div>
								<a href="#" class="woopt_save_actions button button-primary">
									<?php esc_html_e( 'Save actions', 'woo-product-timer' ); ?>
								</a>
							</div>
						</div>
					</div>
					<?php
				}

				function action( $action_val = null, $global = false, $active = false ) {
					$action_data = self::woopt_action_data( $action_val );
					$apply       = isset( $action_data['apply'] ) ? $action_data['apply'] : 'apply_all';
					$apply_val   = isset( $action_data['apply_val'] ) ? $action_data['apply_val'] : '';
					$action      = $action_data['action'];
					$price       = $action_data['value'];
					$conditional = $action_data['conditional'];
					$roles       = $action_data['roles'];
					?>
					<div class="woopt_action <?php echo esc_attr( $active ? 'active' : '' ); ?>">
						<div class="woopt_action_heading">
							<span class="woopt_action_move"></span> <span class="woopt_action_label"></span>
							<a href="https://wpclever.net/downloads/product-timer?utm_source=pro&utm_medium=woopt&utm_campaign=wporg" target="_blank" class="woopt_action_duplicate" onclick="return confirm('This feature only available in Premium Version!\nBuy it now? Just $29')">
								<?php esc_html_e( 'duplicate', 'woo-product-timer' ); ?>
							</a>
							<a href="#" class="woopt_action_remove"><?php esc_html_e( 'remove', 'woo-product-timer' ); ?></a>
						</div>
						<div class="woopt_action_content">
							<?php if ( $global ) { ?>
								<div class="woopt_tr">
									<div class="woopt_th"><?php esc_html_e( 'Apply for', 'woo-product-timer' ); ?></div>
									<div class="woopt_td woopt_action_td">
										<select class="woopt_apply_selector">
											<option value="apply_all" <?php selected( $apply, 'apply_all' ); ?>><?php esc_html_e( 'All products', 'woo-product-timer' ); ?></option>
											<option value="apply_variation" <?php selected( $apply, 'apply_variation' ); ?>><?php esc_html_e( 'Variations only', 'woo-product-timer' ); ?></option>
											<option value="apply_not_variation" <?php selected( $apply, 'apply_not_variation' ); ?>><?php esc_html_e( 'Non-variation products', 'woo-product-timer' ); ?></option>
											<option value="apply_product" <?php selected( $apply, 'apply_product' ); ?>><?php esc_html_e( 'Products', 'woo-product-timer' ); ?></option>
											<option value="apply_combination" <?php selected( $apply, 'apply_combination' ); ?>><?php esc_html_e( 'Combined sources', 'woo-product-timer' ); ?></option>
											<?php
											//$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );
											$taxonomies = get_object_taxonomies( 'product', 'objects' );

											foreach ( $taxonomies as $taxonomy ) {
												echo '<option value="apply_' . $taxonomy->name . '" ' . ( $apply === 'apply_' . $taxonomy->name ? 'selected' : '' ) . '>' . $taxonomy->label . '</option>';
											}
											?>
										</select>
									</div>
								</div>
								<div class="woopt_tr hide_apply show_if_apply_product">
									<div class="woopt_th"><?php esc_html_e( 'Products', 'woo-product-timer' ); ?></div>
									<div class="woopt_td woopt_action_td">
										<select class="wc-product-search woopt-product-search" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woo-product-timer' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-val="<?php echo esc_attr( $apply === 'apply_product' ? $apply_val : '' ); ?>">
											<?php
											$_product_ids = explode( ',', $apply_val );

											foreach ( $_product_ids as $_product_id ) {
												$_product = wc_get_product( $_product_id );

												if ( $_product ) {
													echo '<option value="' . esc_attr( $_product_id ) . '" selected>' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
												}
											}
											?>
										</select>
										<?php echo '<script>jQuery(document.body).trigger( \'wc-enhanced-select-init\' );</script>'; ?>
									</div>
								</div>
								<div class="woopt_tr hide_apply show_if_apply_category">
									<div class="woopt_th"><?php esc_html_e( 'Categories', 'woo-product-timer' ); ?></div>
									<div class="woopt_td woopt_action_td">
										<select class="wc-category-search woopt-category-search" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for a category&hellip;', 'woo-product-timer' ); ?>" data-val="<?php echo esc_attr( $apply === 'apply_category' ? $apply_val : '' ); ?>">
											<?php
											$category_slugs = explode( ',', $apply_val );

											if ( count( $category_slugs ) > 0 ) {
												foreach ( $category_slugs as $category_slug ) {
													$category = get_term_by( 'slug', $category_slug, 'product_cat' );

													if ( $category ) {
														echo '<option value="' . esc_attr( $category_slug ) . '" selected>' . wp_kses_post( $category->name ) . '</option>';
													}
												}
											}
											?>
										</select>
										<?php echo '<script>jQuery(document.body).trigger( \'wc-enhanced-select-init\' );</script>'; ?>
									</div>
								</div>
								<div class="woopt_tr hide_apply show_if_apply_combination">
									<div class="woopt_th"><?php esc_html_e( 'Applied conditions', 'woo-product-timer' ); ?></div>
									<div class="woopt_td woopt_action_td">
										<div class="woopt_apply_conditionals">
											<p class="description"><?php esc_html_e( '* Configure to find products that match all listed conditions.', 'woo-product-timer' ); ?></p>
											<?php self::apply_conditional( $apply_val ); ?>
										</div>
										<div class="woopt_add_apply_conditional">
											<a class="woopt_new_apply_conditional" href="#"><?php esc_attr_e( '+ Add condition', 'woo-product-timer' ); ?></a>
										</div>
									</div>
								</div>
								<div class="woopt_tr show_apply hide_if_apply_all hide_if_apply_variation hide_if_apply_not_variation hide_if_apply_product hide_if_apply_category hide_if_apply_combination">
									<div class="woopt_th woopt_apply_text"><?php esc_html_e( 'Terms', 'woo-product-timer' ); ?></div>
									<div class="woopt_td woopt_action_td">
										<input class="woopt_apply_val" type="hidden" value="<?php echo esc_attr( $apply_val ); ?>"/>
										<?php
										if ( ! is_array( $apply_val ) ) {
											$apply_val = array_map( 'trim', explode( ',', $apply_val ) );
										}
										?>
										<select class="woopt_terms" multiple="multiple" data-<?php echo esc_attr( $apply ); ?>="<?php echo esc_attr( implode( ',', $apply_val ) ); ?>">
											<?php
											$taxonomy = substr( $apply, 6 );

											if ( $taxonomy === 'tag' ) {
												$taxonomy = 'product_tag';
											}

											if ( ! empty( $apply_val ) ) {
												foreach ( $apply_val as $t ) {
													if ( $term = get_term_by( 'slug', $t, $taxonomy ) ) {
														echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
													}
												}
											}
											?>
										</select>
									</div>
								</div>
							<?php } ?>
							<div class="woopt_tr">
								<div class="woopt_th"><?php esc_html_e( 'Action', 'woo-product-timer' ); ?></div>
								<div class="woopt_td woopt_action_td">
									<input class="woopt_action_val" type="hidden" name="woopt_actions[]" value="<?php echo esc_attr( $action_val ); ?>"/>
									<span>
                                        <select class="woopt_action_selector">
                                            <option value=""><?php esc_html_e( 'Choose action', 'woo-product-timer' ); ?></option>
                                            <option value="set_instock" <?php selected( $action, 'set_instock' ); ?>><?php esc_html_e( 'Set in stock', 'woo-product-timer' ); ?></option>
                                            <option value="set_outofstock" <?php selected( $action, 'set_outofstock' ); ?>><?php esc_html_e( 'Set out of stock', 'woo-product-timer' ); ?></option>
                                            <option value="set_visible" <?php selected( $action, 'set_visible' ); ?>><?php esc_html_e( 'Set visible', 'woo-product-timer' ); ?></option>
                                            <option value="set_hidden" <?php selected( $action, 'set_hidden' ); ?>><?php esc_html_e( 'Set hidden', 'woo-product-timer' ); ?></option>
                                            <option value="set_featured" <?php selected( $action, 'set_featured' ); ?>><?php esc_html_e( 'Set featured', 'woo-product-timer' ); ?></option>
                                            <option value="set_unfeatured" <?php selected( $action, 'set_unfeatured' ); ?>><?php esc_html_e( 'Set unfeatured', 'woo-product-timer' ); ?></option>
                                            <option value="set_purchasable" <?php selected( $action, 'set_purchasable' ); ?>><?php esc_html_e( 'Set purchasable', 'woo-product-timer' ); ?></option>
                                            <option value="set_unpurchasable" <?php selected( $action, 'set_unpurchasable' ); ?>><?php esc_html_e( 'Set unpurchasable', 'woo-product-timer' ); ?></option>
                                            <option value="set_regularprice" data-show="price" <?php selected( $action, 'set_regularprice' ); ?>><?php esc_html_e( 'Set regular price', 'woo-product-timer' ); ?></option>
                                            <option value="set_saleprice" data-show="price" <?php selected( $action, 'set_saleprice' ); ?>><?php esc_html_e( 'Set sale price', 'woo-product-timer' ); ?></option>
                                            <option value="enable_sold_individually" <?php selected( $action, 'enable_sold_individually' ); ?>><?php esc_html_e( 'Enable sold individually', 'woo-product-timer' ); ?></option>
                                            <option value="disable_sold_individually" <?php selected( $action, 'disable_sold_individually' ); ?>><?php esc_html_e( 'Disable sold individually', 'woo-product-timer' ); ?></option>
                                        </select>
                                    </span> <span class="woopt_hide woopt_show_if_price">
                                        <input class="woopt_price" value="<?php echo $price; ?>" type="text" placeholder="price or percentage" style="width: 150px"/>
                                    </span>
								</div>
							</div>
							<div class="woopt_tr">
								<div class="woopt_th"><?php esc_html_e( 'Time conditions', 'woo-product-timer' ); ?></div>
								<div class="woopt_td">
									<div class="woopt_conditionals">
										<p class="description"><?php esc_html_e( '* Configure date and time of the action that must match all listed conditions.', 'woo-product-timer' ); ?></p>
										<?php self::conditional( $conditional ); ?>
									</div>
									<div class="woopt_add_conditional">
										<a href="#" class="woopt_new_conditional"><?php esc_html_e( '+ Add time', 'woo-product-timer' ); ?></a>
									</div>
								</div>
							</div>
							<div class="woopt_tr">
								<div class="woopt_th"><?php esc_html_e( 'User roles', 'woo-product-timer' ); ?></div>
								<div class="woopt_td">
									<div class="woopt_user_roles">
										<p class="description"><?php esc_html_e( '* Configure user role(s) that apply the action.', 'woo-product-timer' ); ?></p>
										<?php
										global $wp_roles;
										$roles_arr = explode( ',', $roles );

										if ( empty( $roles ) || in_array( 'all', $roles_arr ) ) {
											$roles_arr = [ 'all' ];
										}

										echo '<select class="woopt_user_roles_select" multiple="multiple" style="height: 120px">';
										echo '<option value="all" ' . ( in_array( 'all', $roles_arr ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'woo-product-timer' ) . '</option>';
										echo '<option value="guest" ' . ( in_array( 'guest', $roles_arr ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'woo-product-timer' ) . '</option>';

										if ( ! empty( $wp_roles->roles ) ) {
											foreach ( $wp_roles->roles as $role => $details ) {
												echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $roles_arr ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
											}
										}

										echo '</select>';
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php
				}

				function apply_conditional( $conditional = null ) {
					$conditional_arr = explode( '&', $conditional );

					if ( is_array( $conditional_arr ) && ( count( $conditional_arr ) > 0 ) ) {
						foreach ( $conditional_arr as $conditional_item ) {
							$conditional_item_arr = explode( '>', $conditional_item );
							$conditional_item_key = trim( isset( $conditional_item_arr[0] ) ? $conditional_item_arr[0] : '' );
							$conditional_item_val = trim( isset( $conditional_item_arr[1] ) ? $conditional_item_arr[1] : '' );
							?>
							<div class="woopt_apply_conditional">
								<span class="woopt_apply_conditional_remove">&times;</span> <span>
                                    <select class="woopt_apply_conditional_select">
	                                    <option value="variation" <?php selected( $conditional_item_key, 'variation' ); ?>><?php esc_html_e( 'Variations only', 'woo-product-timer' ); ?></option>
	                                    <option value="not_variation" <?php selected( $conditional_item_key, 'not_variation' ); ?>><?php esc_html_e( 'Non-variation products', 'woo-product-timer' ); ?></option>
                                        <?php
                                        $taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

                                        foreach ( $taxonomies as $taxonomy ) {
	                                        echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $conditional_item_key === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </span> <span>
                                    <select class="woopt_apply_terms woopt_apply_conditional_val" multiple="multiple">
                                        <?php
                                        $apply_conditional_val = array_map( 'trim', explode( ',', $conditional_item_val ) );

                                        if ( ! empty( $apply_conditional_val ) ) {
	                                        foreach ( $apply_conditional_val as $t ) {
		                                        if ( $term = get_term_by( 'slug', $t, $conditional_item_key ) ) {
			                                        echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
		                                        }
	                                        }
                                        }
                                        ?>
                                    </select>
                                </span>
							</div>
							<?php
						}
					}
				}

				function conditional( $conditional = null ) {
					$conditional_arr = explode( '&', $conditional );

					if ( is_array( $conditional_arr ) && ( count( $conditional_arr ) > 0 ) ) {
						foreach ( $conditional_arr as $conditional_item ) {
							$conditional_item_arr       = explode( '>', $conditional_item );
							$conditional_item_key       = trim( isset( $conditional_item_arr[0] ) ? $conditional_item_arr[0] : '' );
							$conditional_item_val       = trim( isset( $conditional_item_arr[1] ) ? $conditional_item_arr[1] : '' );
							$conditional_item_time_from = '';
							$conditional_item_time_to   = '';

							if ( $conditional_item_key === 'time_range' ) {
								$conditional_item_time      = explode( '-', $conditional_item_val );
								$conditional_item_time_from = trim( isset( $conditional_item_time[0] ) ? $conditional_item_time[0] : '' );
								$conditional_item_time_to   = trim( isset( $conditional_item_time[1] ) ? $conditional_item_time[1] : '' );
							}
							?>
							<div class="woopt_conditional_item">
								<span class="woopt_conditional_remove">&times;</span> <span>
							<select class="woopt_conditional">
								<option value=""><?php esc_html_e( 'Choose the time', 'woo-product-timer' ); ?></option>
								<option value="date_on" data-show="date" <?php selected( $conditional_item_key, 'date_on' ); ?>><?php esc_html_e( 'On the date', 'woo-product-timer' ); ?></option>
                                <option value="date_time_before" data-show="date_time" <?php selected( $conditional_item_key, 'date_time_before' ); ?>><?php esc_html_e( 'Before date & time', 'woo-product-timer' ); ?></option>
								<option value="date_time_after" data-show="date_time" <?php selected( $conditional_item_key, 'date_time_after' ); ?>><?php esc_html_e( 'After date & time', 'woo-product-timer' ); ?></option>
								<option value="date_before" data-show="date" <?php selected( $conditional_item_key, 'date_before' ); ?>><?php esc_html_e( 'Before date', 'woo-product-timer' ); ?></option>
								<option value="date_after" data-show="date" <?php selected( $conditional_item_key, 'date_after' ); ?>><?php esc_html_e( 'After date', 'woo-product-timer' ); ?></option>
								<option value="date_multi" data-show="date_multi" <?php selected( $conditional_item_key, 'date_multi' ); ?>><?php esc_html_e( 'Multiple dates', 'woo-product-timer' ); ?></option>
								<option value="date_range" data-show="date_range" <?php selected( $conditional_item_key, 'date_range' ); ?>><?php esc_html_e( 'Date range', 'woo-product-timer' ); ?></option>
								<option value="date_even" data-show="none" <?php selected( $conditional_item_key, 'date_even' ); ?>><?php esc_html_e( 'All even dates', 'woo-product-timer' ); ?></option>
								<option value="date_odd" data-show="none" <?php selected( $conditional_item_key, 'date_odd' ); ?>><?php esc_html_e( 'All odd dates', 'woo-product-timer' ); ?></option>
								<option value="time_range" data-show="time_range" <?php selected( $conditional_item_key, 'time_range' ); ?>><?php esc_html_e( 'Daily time range', 'woo-product-timer' ); ?></option>
                                <option value="time_before" data-show="time" <?php selected( $conditional_item_key, 'time_before' ); ?>><?php esc_html_e( 'Daily before time', 'woo-product-timer' ); ?></option>
								<option value="time_after" data-show="time" <?php selected( $conditional_item_key, 'time_after' ); ?>><?php esc_html_e( 'Daily after time', 'woo-product-timer' ); ?></option>
                                <option value="weekly_every" data-show="weekday" <?php selected( $conditional_item_key, 'weekly_every' ); ?>><?php esc_html_e( 'Weekly on every', 'woo-product-timer' ); ?></option>
                                <option value="week_even" data-show="none" <?php selected( $conditional_item_key, 'week_even' ); ?>><?php esc_html_e( 'All even weeks', 'woo-product-timer' ); ?></option>
								<option value="week_odd" data-show="none" <?php selected( $conditional_item_key, 'week_odd' ); ?>><?php esc_html_e( 'All odd weeks', 'woo-product-timer' ); ?></option>
                                <option value="week_no" data-show="weekno" <?php selected( $conditional_item_key, 'week_no' ); ?>><?php esc_html_e( 'On week No.', 'woo-product-timer' ); ?></option>
                                <option value="monthly_every" data-show="monthday" <?php selected( $conditional_item_key, 'monthly_every' ); ?>><?php esc_html_e( 'Monthly on the', 'woo-product-timer' ); ?></option>
                                <option value="month_no" data-show="monthno" <?php selected( $conditional_item_key, 'month_no' ); ?>><?php esc_html_e( 'On month No.', 'woo-product-timer' ); ?></option>
                                <option value="days_less_published" data-show="number" <?php selected( $conditional_item_key, 'days_less_published' ); ?>><?php esc_html_e( 'Days of being published are smaller than', 'woo-product-timer' ); ?></option>
                                <option value="days_greater_published" data-show="number" <?php selected( $conditional_item_key, 'days_greater_published' ); ?>><?php esc_html_e( 'Days of being published are bigger than', 'woo-product-timer' ); ?></option>
                                <option value="every_day" data-show="none" <?php selected( $conditional_item_key, 'every_day' ); ?>><?php esc_html_e( 'Everyday', 'woo-product-timer' ); ?></option>
							</select>
						</span> <span class="woopt_hide woopt_show_if_date_time">
							<input value="<?php echo $conditional_item_val; ?>" class="woopt_date_time woopt_date_time_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="woopt_hide woopt_show_if_date">
							<input value="<?php echo $conditional_item_val; ?>" class="woopt_date woopt_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="woopt_hide woopt_show_if_date_range">
							<input value="<?php echo $conditional_item_val; ?>" class="woopt_date_range woopt_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="woopt_hide woopt_show_if_date_multi">
							<input value="<?php echo $conditional_item_val; ?>" class="woopt_date_multi woopt_date_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="woopt_hide woopt_show_if_time_range">
							<input value="<?php echo $conditional_item_time_from; ?>" class="woopt_time woopt_time_start woopt_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="from"/>
							<input value="<?php echo $conditional_item_time_to; ?>" class="woopt_time woopt_time_end woopt_time_input" type="text" readonly="readonly" style="width: 300px" placeholder="to"/>
						</span> <span class="woopt_hide woopt_show_if_time">
							<input value="<?php echo $conditional_item_val; ?>" class="woopt_time woopt_time_on woopt_time_input" type="text" readonly="readonly" style="width: 300px"/>
						</span> <span class="woopt_hide woopt_show_if_weekday">
							<select class="woopt_weekday">
                                <option value="mon" <?php selected( $conditional_item_val, 'mon' ); ?>><?php esc_html_e( 'Monday', 'woo-product-timer' ); ?></option>
                                <option value="tue" <?php selected( $conditional_item_val, 'tue' ); ?>><?php esc_html_e( 'Tuesday', 'woo-product-timer' ); ?></option>
                                <option value="wed" <?php selected( $conditional_item_val, 'wed' ); ?>><?php esc_html_e( 'Wednesday', 'woo-product-timer' ); ?></option>
                                <option value="thu" <?php selected( $conditional_item_val, 'thu' ); ?>><?php esc_html_e( 'Thursday', 'woo-product-timer' ); ?></option>
                                <option value="fri" <?php selected( $conditional_item_val, 'fri' ); ?>><?php esc_html_e( 'Friday', 'woo-product-timer' ); ?></option>
                                <option value="sat" <?php selected( $conditional_item_val, 'sat' ); ?>><?php esc_html_e( 'Saturday', 'woo-product-timer' ); ?></option>
                                <option value="sun" <?php selected( $conditional_item_val, 'sun' ); ?>><?php esc_html_e( 'Sunday', 'woo-product-timer' ); ?></option>
                            </select>
						</span> <span class="woopt_hide woopt_show_if_monthday">
							<select class="woopt_monthday">
                                <?php for ( $i = 1; $i < 32; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $conditional_item_val === $i ? 'selected' : '' ) . '>' . $i . '</option>';
                                } ?>
                            </select>
						</span> <span class="woopt_hide woopt_show_if_number">
							<input type="number" step="1" min="0" class="woopt_number" value="<?php echo esc_attr( (int) $conditional_item_val ); ?>"/>
						</span> <span class="woopt_hide woopt_show_if_weekno">
							<select class="woopt_weekno">
                                <?php
                                for ( $i = 1; $i < 54; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $conditional_item_val === $i ? 'selected' : '' ) . '>' . $i . '</option>';
                                }
                                ?>
                            </select>
						</span> <span class="woopt_hide woopt_show_if_monthno">
							<select class="woopt_monthno">
                                <?php
                                for ( $i = 1; $i < 13; $i ++ ) {
	                                echo '<option value="' . esc_attr( $i ) . '" ' . ( (int) $conditional_item_val === $i ? 'selected' : '' ) . '>' . $i . '</option>';
                                }
                                ?>
                            </select>
						</span>
							</div>
							<?php
						}
					}
				}

				function save_actions() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					$pid       = $_POST['pid'];
					$form_data = $_POST['form_data'];

					if ( $pid && $form_data ) {
						$actions = [];
						parse_str( $form_data, $actions );

						if ( isset( $actions['woopt_actions'] ) ) {
							update_post_meta( $pid, 'woopt_actions', $actions['woopt_actions'] );
						}
					}

					wp_die();
				}

				function add_conditional() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					self::conditional();
					wp_die();
				}

				function add_apply_conditional() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					self::apply_conditional();
					wp_die();
				}

				function search_term() {
					$return = [];

					$args = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ),
					];

					$terms = get_terms( $args );

					if ( count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
					wp_die();
				}

				function process_product_meta( $post_id ) {
					if ( isset( $_POST['woopt_actions'] ) && is_array( $_POST['woopt_actions'] ) ) {
						update_post_meta( $post_id, 'woopt_actions', self::clean( $_POST['woopt_actions'] ) );
					} else {
						delete_post_meta( $post_id, 'woopt_actions' );
					}
				}

				function product_columns( $columns ) {
					$columns['woopt'] = esc_html__( 'Timer', 'woo-product-timer' );

					return $columns;
				}

				function custom_column( $column, $postid ) {
					if ( $column === 'woopt' ) {
						echo '<div class="woopt-icons">';

						// global actions
						if ( is_array( self::$global_actions ) && ( count( self::$global_actions ) > 0 ) ) {
							$global  = false;
							$running = false;

							foreach ( self::$global_actions as $global_action ) {
								$action_data        = self::woopt_action_data( $global_action );
								$action_apply       = $action_data['apply'];
								$action_apply_val   = $action_data['apply_val'];
								$action_conditional = $action_data['conditional'];

								if ( self::woopt_check_apply( $postid, $action_apply, $action_apply_val ) ) {
									$global = true;

									if ( ! empty( $action_conditional ) && self::woopt_check_conditional( $action_conditional, $postid ) ) {
										$running = true;
									}
								}
							}

							if ( $global ) {
								if ( $running ) {
									echo '<span class="woopt-icon woopt-icon-global running"><span class="dashicons dashicons-admin-site"></span></span>';
								} else {
									echo '<span class="woopt-icon woopt-icon-global"><span class="dashicons dashicons-admin-site"></span></span>';
								}
							}
						}

						$actions = get_post_meta( $postid, 'woopt_actions', true );

						if ( is_array( $actions ) && ( count( $actions ) > 0 ) ) {
							$running = false;

							foreach ( $actions as $action ) {
								$action_data = self::woopt_action_data( $action );

								if ( ! empty( $action_data['conditional'] ) && self::woopt_check_conditional( $action_data['conditional'], $postid ) ) {
									$running = true;
								}
							}

							if ( $running ) {
								echo '<span class="woopt-icon running"><span class="dashicons dashicons-clock"></span></span>';
							} else {
								echo '<span class="woopt-icon"><span class="dashicons dashicons-clock"></span></span>';
							}
						}

						echo '</div>';

						// edit button
						echo '<a href="#" class="woopt_edit" data-pid="' . esc_attr( $postid ) . '" data-name="' . esc_attr( get_the_title( $postid ) ) . '"><span class="dashicons dashicons-edit"></span></a>';
					}
				}

				function edit_timer() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					$product_id = absint( $_POST['pid'] );

					if ( $product_id ) {
						$actions = get_post_meta( $product_id, 'woopt_actions', true );
						echo '<textarea class="woopt_edit_data" style="width: 100%; height: 200px">' . ( ! empty( $actions ) ? json_encode( $actions ) : '' ) . '</textarea>';
						echo '<div style="display: flex; align-items: center"><button class="button button-primary woopt_edit_save" data-pid="' . $product_id . '">' . esc_html__( 'Update', 'woo-product-timer' ) . '</button>';
						echo '<span class="woopt_edit_message" style="margin-left: 10px"></span></div>';
					}

					wp_die();
				}

				function save_timer() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					$product_id = absint( $_POST['pid'] );
					$actions    = sanitize_textarea_field( trim( $_POST['actions'] ) );

					if ( empty( $actions ) ) {
						delete_post_meta( $product_id, 'woopt_actions' );
						esc_html_e( 'Timer was removed!', 'woo-product-timer' );
					} else {
						$actions = json_decode( stripcslashes( $actions ) );

						if ( $actions !== null ) {
							update_post_meta( $product_id, 'woopt_actions', $actions );
							esc_html_e( 'Updated successfully!', 'woo-product-timer' );
						} else {
							esc_html_e( 'Have an error!', 'woo-product-timer' );
						}
					}

					wp_die();
				}

				function import_export() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					$actions = self::$global_actions;
					echo '<textarea class="woopt_import_export_data" style="width: 100%; height: 200px">' . ( ! empty( $actions ) ? json_encode( $actions ) : '' ) . '</textarea>';
					echo '<div style="display: flex; align-items: center"><button class="button button-primary woopt-import-export-save">' . esc_html__( 'Update', 'woo-product-timer' ) . '</button>';
					echo '<span style="color: #ff4f3b; font-size: 10px; margin-left: 10px">' . esc_html__( '* All current Actions will be replaced after pressing Update!', 'woo-product-timer' ) . '</span>';
					echo '</div>';

					wp_die();
				}

				function import_export_save() {
					if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'woopt_nonce' ) ) {
						die( 'Permissions check failed!' );
					}

					$actions = sanitize_textarea_field( trim( $_POST['actions'] ) );

					if ( ! empty( $actions ) ) {
						$actions = json_decode( stripcslashes( $actions ) );

						if ( $actions !== null ) {
							update_option( 'woopt_actions', $actions );
							echo 'Done!';
						}
					}

					wp_die();
				}

				function export_columns( $columns ) {
					$columns['woopt_actions'] = esc_html__( 'Timer', 'woo-product-timer' );

					return $columns;
				}

				function export_data( $value, $product ) {
					$value = get_post_meta( $product->get_id(), 'woopt_actions', true );

					if ( ! empty( $value ) ) {
						return json_encode( $value );
					} else {
						return '';
					}
				}

				function import_options( $options ) {
					$options['woopt_actions'] = esc_html__( 'Timer', 'woo-product-timer' );

					return $options;
				}

				function import_columns( $columns ) {
					$columns['Timer']         = 'woopt_actions';
					$columns['timer']         = 'woopt_actions';
					$columns['woopt actions'] = 'woopt_actions';

					return $columns;
				}

				function import_process( $object, $data ) {
					if ( ! empty( $data['woopt_actions'] ) ) {
						$object->update_meta_data( 'woopt_actions', json_decode( html_entity_decode( stripcslashes( $data['woopt_actions'] ) ) ) );
					}

					return $object;
				}

				function clean( $var ) {
					if ( is_array( $var ) ) {
						return array_map( [ __CLASS__, 'clean' ], $var );
					} else {
						return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
					}
				}
			}

			return WPCleverWoopt::instance();
		}
	}
}

if ( ! function_exists( 'woopt_notice_wc' ) ) {
	function woopt_notice_wc() {
		?>
		<div class="error">
			<p><strong>WPC Product Timer</strong> require WooCommerce version 3.0 or greater.</p>
		</div>
		<?php
	}
}
