<?php
/**
 * Super Forms - PDF Builder
 *
 * @package   Super Forms - PDF Builder
 * @author    feeling4design
 * @link      http://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866
 * @copyright 2017 by feeling4design
 *
 * @wordpress-plugin
 * Plugin Name: Super Forms - PDF Builder
 * Plugin URI:  http://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866
 * Description: Build PDF attachments for each form with the possibility to dynamically include form data with the use of {tags}
 * Version:     1.0.0
 * Author:      feeling4design
 * Author URI:  http://codecanyon.net/user/feeling4design
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require 'vendor/autoload.php';

// reference the Dompdf namespace
use Dompdf\Dompdf;

if(!class_exists('SUPER_PDF_Builder')) :


    /**
     * Main SUPER_PDF_Builder Class
     *
     * @class SUPER_PDF_Builder
     * @version 1.0.0
     */
    final class SUPER_PDF_Builder {
    
        
        /**
         * @var string
         *
         *  @since      1.0.0
        */
        public $version = '1.0.0';


        /**
         * @var string
         *
         *  @since      1.0.0
        */
        public $add_on_slug = 'pdf_builder';
        public $add_on_name = 'PDF Builder';

        
        /**
         * @var SUPER_PDF_Builder The single instance of the class
         *
         *  @since      1.0.0
        */
        protected static $_instance = null;

        
        /**
         * Main SUPER_PDF_Builder Instance
         *
         * Ensures only one instance of SUPER_PDF_Builder is loaded or can be loaded.
         *
         * @static
         * @see SUPER_PDF_Builder()
         * @return SUPER_PDF_Builder - Main instance
         *
         *  @since      1.0.0
        */
        public static function instance() {
            if(is_null( self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        
        /**
         * SUPER_PDF_Builder Constructor.
         *
         *  @since      1.0.0
        */
        public function __construct(){
            $this->init_hooks();
            do_action('super_pdf_builder_loaded');
        }

        
        /**
         * Define constant if not already set
         *
         * @param  string $name
         * @param  string|bool $value
         *
         *  @since      1.0.0
        */
        private function define($name, $value){
            if(!defined($name)){
                define($name, $value);
            }
        }

        
        /**
         * What type of request is this?
         *
         * string $type ajax, frontend or admin
         * @return bool
         *
         *  @since      1.0.0
        */
        private function is_request($type){
            switch ($type){
                case 'admin' :
                    return is_admin();
                case 'ajax' :
                    return defined( 'DOING_AJAX' );
                case 'cron' :
                    return defined( 'DOING_CRON' );
                case 'frontend' :
                    return (!is_admin() || defined('DOING_AJAX')) && ! defined('DOING_CRON');
            }
        }

        
        /**
         * Hook into actions and filters
         *
         *  @since      1.0.0
        */
        private function init_hooks() {
            
            if ( $this->is_request( 'admin' ) ) {
                
                // Filters since 1.0.0
                add_filter( 'super_shortcodes_end_filter', array( $this, 'deregister_elements' ), 10, 2 );
                add_filter( 'super_form_builder_modes_filter', array( $this, 'add_pdf_builder_tab' ), 10, 1 );

                // Actions since 1.0.0
                add_action( 'init', array( $this, 'update_plugin' ) );
                add_action( 'all_admin_notices', array( $this, 'display_activation_msg' ) );

            }
            
            if ( $this->is_request( 'ajax' ) ) {

                // Actions since 1.0.0
                add_action( 'super_before_load_preview', array( $this, 'preview_pdf' ), 10, 1 );

            }
            
        }
        

        /**
         * Deregister elements that we do not use when building a PDF attachment
         *
         *  @since      1.0.0
        */
        public static function preview_pdf( $arg ) {
	        
	        $form_id = absint( $arg['form_id'] );
        	
			$active_builder = get_post_meta( $form_id, '_super_active_builder_mode', true );
			
			// Only preview PDF when 'pdf' is our current active builder mode
			if( $active_builder!='pdf' ) {
				return;
			}

			//<div style="background-image: url(/dev/wp-content/uploads/2018/03/Example.jpg);">

	        $result = '';
			$result .= '
			<!DOCTYPE html>
			<html>
			<head>
				<style type="text/css">
				.super-grid {
				    width:100%;
				}
				.super-column {
				    float:left;
				}
				.super-button[data-action="submit"] {
				    display:none;
				}
				.super-html textarea {
				    display:none;
				}
				</style>
			</head>
			<body>
			';

	        // Loop through all PDF elements
	        $elements = get_post_meta( $form_id, '_super_pdf_elements', true );
	        if( !is_array($elements) ) {
	            $elements = json_decode( $elements, true );
	        }
	        if( !empty( $elements ) ) {
	            $shortcodes = SUPER_Shortcodes::shortcodes();
	            // Before doing the actuall loop we need to know how many columns this form contains
	            // This way we can make sure to correctly close the column system
	            $GLOBALS['super_column_found'] = 0;
	            foreach( $elements as $k => $v ) {
	                if( $v['tag']=='column' ) $GLOBALS['super_column_found']++;
	            }
	            foreach( $elements as $k => $v ) {
	                if( empty($v['data']) ) $v['data'] = null;
	                if( empty($v['inner']) ) $v['inner'] = null;
	                $result .= SUPER_Shortcodes::output_element_html( $v['tag'], $v['group'], $v['data'], $v['inner'], $shortcodes, array(), array() );
	            }
	        }

			$result .= '</body>
			</html>';
			//echo $result;
			//die();

			// instantiate and use the dompdf class
			$dompdf = new Dompdf();
			$dompdf->loadHtml($result);

			// (Optional) Setup the paper size and orientation
			//$dompdf->setPaper('A4', 'landscape');
			$dompdf->setPaper('A4');

			// Render the HTML as PDF
			$dompdf->render();

    		$output = $dompdf->output();

        	$file_location = '/pdf-preview.pdf';
        	$source = urldecode( dirname( __FILE__ ) . $file_location );
    		$result = file_put_contents( $source, $output );
        	$url = urldecode( plugin_dir_url( __FILE__ ) . $file_location );
    		echo '<iframe style="width:100%;height:600px;" src="' . $url . '" />';
    		//var_dump($source);
    		//var_dump($result);
			// Output the generated PDF to Browser
			//$dompdf->stream();

        	die();

        	/*
			$html = '';
			$html .= '<style>';
			$html .= '
			.super-grid {
			    border:1px solid green;
			    width:100%;
			}
			.super-column {
			    float:left;
			    width:50%;
			    border:1px solid red;
			}
			.super-button[data-action="submit"] {
			    display:none;
			}
			.super-html textarea {
			    display:none;
			}
			';
			$html .= '</style>';

			$html .= '
			<div class="super-grid super-shortcode">
			  <div class="super-shortcode super_one_half super-column column-number-1 grid-level-0 first-column ">
			    <div class="super-shortcode super-field super-html  ungrouped  ">
			      <div class="super-html-content" data-fields="[]">Your HTML here...</div><textarea>Your HTML here...</textarea></div>
			  </div>
			  <div class="super-shortcode super_one_half super-column column-number-2 grid-level-0  ">
			    <div class="super-shortcode super-field super-html  ungrouped  ">
			      <div class="super-html-content" data-fields="[]">Your HTML here...</div><textarea>Your HTML here...</textarea></div>
			  </div>
			  <div style="clear:both;"></div>
			</div>
			';

			// reference the Dompdf namespace
			use Dompdf\Dompdf;

			// instantiate and use the dompdf class
			$dompdf = new Dompdf();
			$dompdf->loadHtml($html);

			// (Optional) Setup the paper size and orientation
			//$dompdf->setPaper('A4', 'landscape');
			$dompdf->setPaper('A4');

			// Render the HTML as PDF
			$dompdf->render();

			// Output the generated PDF to Browser
			$dompdf->stream();
			*/

        }


        /**
         * Deregister elements that we do not use when building a PDF attachment
         *
         *  @since      1.0.0
        */
        public static function deregister_elements( $array, $arg ) {
        	
			$active_builder = get_post_meta( absint($arg['form_id']), '_super_active_builder_mode', true );
			
			// Only deregister when 'pdf' is our current active builder mode
			if( $active_builder!='pdf' ) {
				return $array;
			}

        	$type = 'shortcodes';

        	// We only keep our columns but remove the multi-part
        	$group = 'layout_elements';
        	$deregister = array(
        		'multipart',
        		'multipart_pre'
        	);
        	foreach( $deregister as $v ) {
        		if( isset( $array[$group][$type][$v] ) ) {
        			unset($array[$group][$type][$v]);
        		}
        	}

        	// Delete all form elements (no use for them, but maybe in future)
        	$group = 'form_elements';
        	if( isset( $array[$group] ) ) {
				unset($array[$group]);
        	}

        	// Delete all HTML elements (no use for them, but maybe in future)
        	/*
        	$group = 'html_elements';
        	if( isset( $array[$group] ) ) {
				unset($array[$group]);
        	}
        	*/


        	return $array;
        }


        /**
         * Add the PDF builder TAB to switch to pdf builder interface
         *
         *  @since      1.0.0
        */
        public static function add_pdf_builder_tab( $array ) {
            $array['pdf'] = array( 'title' =>__( 'PDF', 'super-forms' ), 'desc' => __( 'Here you can build a possible PDF attachment', 'super-forms' ) );
            return $array;
        }


        /**
         * Display activation message for automatic updates
         *
         *  @since      1.0.0
        */
        public function display_activation_msg() {
            if( !class_exists('SUPER_Forms') ) {
                echo '<div class="notice notice-error">'; // notice-success
                    echo '<p>';
                    echo sprintf( 
                        __( '%sPlease note:%s You must install and activate %4$s%1$sSuper Forms%2$s%5$s in order to be able to use %1$s%s%2$s!', 'super_forms' ), 
                        '<strong>', 
                        '</strong>', 
                        'Super Forms - ' . $this->add_on_name, 
                        '<a target="_blank" href="https://codecanyon.net/item/super-forms-drag-drop-form-builder/13979866">', 
                        '</a>' 
                    );
                    echo '</p>';
                echo '</div>';
            }
        }


        /**
         * Automatically update plugin from the repository
         *
         *  @since      1.0.0
        */
        function update_plugin() {
            if( defined('SUPER_PLUGIN_DIR') ) {
                require_once ( SUPER_PLUGIN_DIR . '/includes/admin/update-super-forms.php' );
                $plugin_remote_path = 'http://f4d.nl/super-forms/';
                $plugin_slug = plugin_basename( __FILE__ );
                new SUPER_WP_AutoUpdate( $this->version, $plugin_remote_path, $plugin_slug, '', '', $this->add_on_slug );
            }
        }


        /**
         * Add attachment(s) to admin emails
         *
         *  @since      1.0.0
        */
        public static function add_pdf_builder_admin_attachment( $attachments, $data ) {
        	/*
			$html = '';
			$html .= '<style>';
			$html .= '
			.super-grid {
			    border:1px solid green;
			    width:100%;
			}
			.super-column {
			    float:left;
			    width:50%;
			    border:1px solid red;
			}
			.super-button[data-action="submit"] {
			    display:none;
			}
			.super-html textarea {
			    display:none;
			}
			';
			$html .= '</style>';

			$html .= '
			<div class="super-grid super-shortcode">
			  <div class="super-shortcode super_one_half super-column column-number-1 grid-level-0 first-column ">
			    <div class="super-shortcode super-field super-html  ungrouped  ">
			      <div class="super-html-content" data-fields="[]">Your HTML here...</div><textarea>Your HTML here...</textarea></div>
			  </div>
			  <div class="super-shortcode super_one_half super-column column-number-2 grid-level-0  ">
			    <div class="super-shortcode super-field super-html  ungrouped  ">
			      <div class="super-html-content" data-fields="[]">Your HTML here...</div><textarea>Your HTML here...</textarea></div>
			  </div>
			  <div style="clear:both;"></div>
			</div>
			';

			// reference the Dompdf namespace
			use Dompdf\Dompdf;

			// instantiate and use the dompdf class
			$dompdf = new Dompdf();
			$dompdf->loadHtml($html);

			// (Optional) Setup the paper size and orientation
			//$dompdf->setPaper('A4', 'landscape');
			$dompdf->setPaper('A4');

			// Render the HTML as PDF
			$dompdf->render();

			// Output the generated PDF to Browser
			$dompdf->stream();
			*/
        }

    }
        
endif;


/**
 * Returns the main instance of SUPER_PDF_Builder to prevent the need to use globals.
 *
 * @return SUPER_PDF_Builder
 */
function SUPER_PDF_Builder() {
    return SUPER_PDF_Builder::instance();
}


// Global for backwards compatibility.
$GLOBALS['SUPER_PDF_Builder'] = SUPER_PDF_Builder();