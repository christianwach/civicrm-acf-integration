<?php
/**
 * ACF Blocks Class.
 *
 * Handles ACF Blocks functionality.
 *
 * @package CiviCRM_ACF_Integration
 * @since 0.8
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM ACF Integration ACF Blocks Class.
 *
 * A class that encapsulates ACF Blocks functionality.
 *
 * @since 0.8
 */
class CiviCRM_ACF_Integration_ACF_Blocks {

	/**
	 * Plugin (calling) object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.8
	 * @access public
	 * @var object $acf The parent object.
	 */
	public $acf;



	/**
	 * Constructor.
	 *
	 * @since 0.8
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store reference to plugin.
		$this->plugin = $parent->plugin;

		// Store reference to parent.
		$this->acf = $parent;

		// Init when this plugin is loaded.
		add_action( 'civicrm_acf_integration_acf_loaded', [ $this, 'register_hooks' ] );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.8
	 */
	public function register_hooks() {

		// Can we add Blocks?
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		// Add some Blocks.
		add_action( 'acf/init', [ $this, 'register_blocks' ] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Register some Blocks.
	 *
	 * @since 0.8
	 */
	public function register_blocks() {

		// Add some Blocks.

		// Define Block.
		$block = [
			'name' => 'cai-phone',
			'title' => __( 'CiviCRM Phone', 'civicrm-acf-integration' ),
			'description' => __( 'A custom phone block.', 'civicrm-acf-integration' ),
			'render_callback' => [ 'CiviCRM_ACF_Integration_ACF_Blocks', 'block_test_render' ],
			'category' => 'common',
			'keywords' => [ 'civicrm' ],
			'post_types' => [ 'page' ],
		];

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'block' => $block,
			'callable' => is_callable( $block['render_callback'] ),
			//'backtrace' => $trace,
		], true ) );
		*/

		// Register it.
		$result = acf_register_block_type( $block );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'result' => $result,
			//'backtrace' => $trace,
		], true ) );
		*/

	}



	/**
	 * Render a Test Block.
	 *
	 * @since 0.8
	 *
	 * @param array $block The Block settings and attributes.
	 * @param string $content The Block inner HTML (empty).
	 * @param bool $is_preview True during AJAX preview.
	 * @param (int|string) $post_id The Post ID this Block is saved to.
	 */
	public function block_test_render( $block, $content = '', $is_preview = false, $post_id = 0 ) {

		// Create ID attribute allowing for custom "anchor" value.
		$id = 'cai-test-' . $block['id'];
		if ( ! empty( $block['anchor'] ) ) {
			$id = $block['anchor'];
		}

		// Create class attribute allowing for custom "className" and "align" values.
		$class_name = 'cai-test-class';
		if ( ! empty( $block['className'] ) ) {
			$class_name .= ' ' . $block['className'];
		}
		if ( ! empty( $block['align'] ) ) {
			$class_name .= ' align' . $block['align'];
		}

		// Load values and assign defaults.
		//$data = get_field( 'selector' );

		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
			<span class="-text">Markup via complex class method</span>
		</div>
		<?php

	}



} // Class ends.



