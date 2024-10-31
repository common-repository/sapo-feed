<?php
/**
 * Plugin Name:       SAPO Feed
 * Description:       Geração de feeds num formato compatível com serviços SAPO.
 * Version:           2.3.1
 * Author:            SAPO
 * Author URI:        https://www.sapo.pt/
 * License:           GPLv3
 *
 * @author            SAPO
 * @link              https://www.sapo.pt/
 * @package           sapofeedgenerator
 * @license           https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright         Copyright (c) 2021 SAPO
 */

final class SAPO_Feed {
	private $default_settings = [
		'sapo_rss_endpoint'     => 'sapo',
		'sapo_rss_post_limit'   => 20,
		'sapo_rss_post_type'    => ['post' => 1],
		'sapo_rss_default_nuts' => '000000',
	];

	private $version = '2.3.0';
	private $options = [];
	private $max_posts = 100;
	private $options_page;
	private $nuts_data;

	function __construct() {
		global $wp_version;

		$this->options = get_option( 'sapo_rss_options', $this->default_settings );

		// Add feed support
		add_action( 'init', array($this, 'add_sapo_rss') );

		// We support basic functionality on old WordPress versions,
		//  but keep the rich features for those more up to date.
		if ( version_compare($wp_version, '5.0.0') >= 0 ) {
			// Location post meta
			register_post_meta( 'post', 'sapo_feed_post_nuts', array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			) );

			// Gutenberg sidebar
			add_action( 'enqueue_block_editor_assets', array($this, 'sapo_rss_sidebar_assets_enqueue') );

			// Classic editor
			add_action( 'add_meta_boxes', array($this, 'sapo_rss_classic_assets_enqueue') );
			add_action( 'save_post',      array($this, 'sapo_rss_classic_save') );
		}
	}

	/*
	 *   Are custom fields supported in the current post_type?
	 */
	function sapo_rss_is_nuts_supported( $post_type = null) {
		$post_type = empty( $post_type ) ? get_post_type() : $post_type;

		if (! empty( $post_type ) ) {
			return post_type_supports($post_type, 'custom-fields');
		}

		return false;
	}

	/*
	 *   Load NUTS data
	 */
	function sapo_rss_get_nuts_data() {
		if ( empty($this->nuts_data) ) {
			$url = plugins_url( './data/nuts.json', __FILE__ );
			$request = wp_remote_get( $url );

			if(! is_wp_error( $request ) ) {
				$body = wp_remote_retrieve_body( $request );
				$this->nuts_data = json_decode( $body );
			} else {
				error_log( 'Loading NUTS data failed: ' . $request->get_error_message() );
			}
		}

		return $this->nuts_data;
	}

	function add_sapo_rss() {
		add_feed($this->options['sapo_rss_endpoint'], array($this, 'sapo_generate_custom_rss_feed'));

		if ( get_option('sapo_rss_options') === false || get_option('sapo_flush_rewrite_rules') ) {
			flush_rewrite_rules();
			update_option('sapo_flush_rewrite_rules', 0);
		}

		if (current_user_can('administrator')) {
			add_action( 'admin_menu', array($this, 'add_sapo_rss_settings_page') );
			add_action( 'admin_init', array($this, 'sapo_rss_register_settings') );

			add_filter('pre_update_option_sapo_rss_options', array($this, 'sapo_validate_update'), 10, 2);
			add_action('update_option_sapo_rss_options', array($this, 'sapo_handle_update_endpoint'), 10, 3);
		}
	}

	function sapo_generate_custom_rss_feed() {
		add_filter('pre_option_rss_use_excerpt', '__return_zero');
		load_template( plugin_dir_path( __FILE__ ) . 'templates/sapo_generic_rss_feed.php' );
	}

	function add_sapo_rss_settings_page() {
		global $wp_version;

		$this->options_page = add_options_page( 'SAPO Feed', 'SAPO Feed', 'manage_options', 'sapo-rss-settings-page', array($this, 'sapo_rss_settings_page') );

		if ( version_compare($wp_version, '5.0.0') >= 0 ) {
			add_action('admin_enqueue_scripts', array($this, 'sapo_rss_settings_scripts'));
		}
	}

	function sapo_rss_settings_page() {
		if ( get_option('permalink_structure') ) {
			$feed_url   = '/feed/'  . $this->options['sapo_rss_endpoint'];
			$feed_sigil = '?';
		} else {
			$feed_url   = '/?feed=' . $this->options['sapo_rss_endpoint'];
			$feed_sigil = '&';
		}
		$feed_cat_url       = $feed_url . $feed_sigil . 'category=destaques';
		$feed_cat_url_multi = $feed_url . $feed_sigil . 'category=economia,cultura';
		$feed_tag_url       = $feed_url . $feed_sigil . 'tags=governo';
		$feed_tag_url_multi = $feed_url . $feed_sigil . 'tags=teatro,cinema';
		?>
		<div class="wrap">
			<h1>SAPO Feed</h1>
			<p><?php echo __( 'Este plugin gera uma feed num formato compatível com serviços SAPO.' ) ?></p>
			<h2><?php echo __( 'Exemplos' ) ?></h2>
			<p>
				<ul>
					<li><strong><?php echo __('Feed completa') ?>:</strong> <em><?php echo get_site_url()  ?><?php echo $feed_url ?></em></li>
				</ul>
				<ul>
					<li><strong><?php echo __('Conteúdo de uma categoria') ?>:</strong> <em><?php echo get_site_url()  ?><?php echo $feed_cat_url ?></em></li>
					<li><strong><?php echo __('Conteúdo de múltiplas categorias') ?>:</strong> <em><?php echo get_site_url()  ?><?php echo $feed_cat_url_multi ?></em></li>
				</ul>
			</p>
			<p>
				<ul>
					<li><strong><?php echo __('Conteúdo de uma etiqueta') ?>:</strong> <em><?php echo get_site_url()  ?><?php echo $feed_tag_url ?></em></li>
					<li><strong><?php echo __('Conteúdo de múltiplas etiquetas') ?>:</strong> <em><?php echo get_site_url()  ?><?php echo $feed_tag_url_multi ?></em></li>
				</ul>
			</p>
			<p>Um pedido com múltiplas categorias e etiquetas devolve todos os artigos que pertençam a pelo menos uma das categorias e que tenham pelo menos uma das etiquetas pedidas.</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'sapo_rss_options' );
				do_settings_sections( 'sapo_rss_settings_page' ); ?>
				<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
			</form>
		</div>
		<?php
	}

	function sapo_rss_settings_scripts( $hook ) {
		if ( $hook != $this->options_page ) { // Only load this on our settings page
			return;
		}

		wp_register_script(
			'sapofeed-settings-js',
			plugins_url( './js/settings.js', __FILE__ ),
			array('jquery'),
			$this->version
		);

		$nuts = $this->sapo_rss_get_nuts_data();

		if ( ! empty($nuts) ) {
			$data = array(
				'option' => $this->options['sapo_rss_default_nuts'],
				'list'   => $nuts,
			);
			// Localize script exposing $data contents
			wp_localize_script( 'sapofeed-settings-js', 'nutsJSON', $data );
		}

		wp_enqueue_script( 'sapofeed-settings-js' );
	}

	function sapo_rss_register_settings() {
		global $wp_version;

		register_setting( 'sapo_rss_options', 'sapo_rss_options' );
		add_settings_section( 'feed_settings', __('Definições'), array($this, 'sapo_rss_section_text'), 'sapo_rss_settings_page' );

		add_settings_field( 'sapo_rss_endpoint', __('Endpoint'), array($this, 'sapo_rss_endpoint_field'), 'sapo_rss_settings_page', 'feed_settings' );
		add_settings_field( 'sapo_rss_post_limit', __('Limite de posts'), array($this, 'sapo_rss_post_limit_field'), 'sapo_rss_settings_page', 'feed_settings' );
		add_settings_field( 'sapo_rss_post_type', __('Tipo de posts'), array($this, 'sapo_rss_post_type_field'), 'sapo_rss_settings_page', 'feed_settings' );

		if ( version_compare($wp_version, '5.0.0') >= 0 ) {
			add_settings_section( 'feed_geo_settings', __('Informação Geográfica'), array($this, 'sapo_rss_geo_section_text'), 'sapo_rss_settings_page' );

			add_settings_field( 'sapo_rss_default_nuts', __('Localização'), array($this, 'sapo_rss_default_nuts_field'), 'sapo_rss_settings_page', 'feed_geo_settings' );
		}
	}

	function sapo_rss_section_text() {
		echo '<p>' . __('Altere aqui as definições da sua feed SAPO.') . '</p>';
	}

	function sapo_rss_endpoint_field() {
		echo "<input id='sapo_rss_endpoint' name='sapo_rss_options[sapo_rss_endpoint]' type='text' value='" . esc_attr($this->options['sapo_rss_endpoint']) . "' />";
	}

	function sapo_rss_post_limit_field() {
		echo "<input id='sapo_rss_post_limit' name='sapo_rss_options[sapo_rss_post_limit]' type='text' value='" . esc_attr($this->options['sapo_rss_post_limit']) . "' />";
	}

	function sapo_rss_post_type_field() {
		$html = "";
		$post_types = isset( $this->options['sapo_rss_post_type'] ) ? $this->options['sapo_rss_post_type'] : $this->default_settings['sapo_rss_post_type'];
		foreach (get_post_types(array('public' => true), 'objects') as $post_type) {
			$html .= '<p><input type="checkbox" name="sapo_rss_options[sapo_rss_post_type][' . $post_type->name . ']" value="1" ' . (empty($post_types[$post_type->name]) ? '' : 'checked') . '>' . $post_type->label . '</input></p>';
		}

		echo $html;
	}

	function sapo_rss_geo_section_text() {
		echo '<p>' . __('Para facilitar a adição de informação geográfica aos seus artigos, pode definir aqui a sua localização principal.') . '</p>';
		echo '<p>' . __('Esta opção define apenas um valor inicial, e não acrescenta dados de localização automaticamente aos artigos (é feito manualmente no editor).') . '</p>';
	}

	function sapo_rss_default_nuts_field() {
		$html = "<input id='sapo_rss_default_nuts' name='sapo_rss_options[sapo_rss_default_nuts]' type='hidden' value='" . esc_attr($this->options['sapo_rss_default_nuts']) . "'></input>";
		echo "<div id='sapo_rss_default_nuts_container'>$html</div>";
	}

	function sapo_validate_update( $new_value, $old_value ) {
		foreach ($this->default_settings as $k => $v) {
			if ( empty($new_value[$k]) ) {
				$new_value[$k] = $v;
			}
		}
		$post_limit = intval($new_value['sapo_rss_post_limit']);
		if (is_int($post_limit) and ($post_limit > 0)) {
			$new_value['sapo_rss_post_limit'] = ($post_limit > $this->max_posts) ? $this->max_posts : $post_limit;
		} else {
			$new_value['sapo_rss_post_limit'] = $this->default_settings['sapo_rss_post_limit'];
		}

		return $new_value;
	}

	function sapo_handle_update_endpoint($old_value, $value, $option) {
		if ( $old_value['sapo_rss_endpoint'] !== $value['sapo_rss_endpoint'] ) {
			update_option('sapo_flush_rewrite_rules', 1);
		}
	}

	/*
	 *   Classic Editor sidebar
	 */
	function sapo_rss_meta_box_callback( $post ) {
		$values = get_post_custom( $post->ID );
		$nuts = isset( $values['sapo_feed_post_nuts'] ) ? esc_attr( $values['sapo_feed_post_nuts'][0] ) : '';
		?>
		<p class="howto">Associar dados de localiza&ccedil;&atilde;o (opcional)</p>
		<input type="hidden" name="sapo_feed_post_nuts" id="sapo_feed_post_nuts" value="<?php echo $nuts; ?>" />
		<div class="sapo-rss-classic-add">
			<button id="sapo-rss-classic-add-geo" type="button" class="button">Adicionar</button>
		</div>
		<div id="sapo-rss-classic-sidebar-content"></div>
		<?php
	}

	function sapo_rss_classic_assets_enqueue() {
		$post_type = get_post_type();

		// Only load assets if the post_type supports 'custom-fields'
		if ( $this->sapo_rss_is_nuts_supported($post_type) ) {
			wp_register_script(
				'sapofeed-classic-js',
				plugins_url( './js/classic.js', __FILE__ ),
				array('jquery'),
				$this->version
			);

			$nuts = $this->sapo_rss_get_nuts_data();

			if ( ! empty($nuts) ) {
				$data = array(
					'option' => $this->options['sapo_rss_default_nuts'],
					'list'   => $nuts,
				);
				// Localize script exposing $data contents
				wp_localize_script( 'sapofeed-classic-js', 'nutsJSON', $data );
			}

			wp_enqueue_script( 'sapofeed-classic-js' );

			// CSS
			wp_register_style(
				'sapo_rss_sidebar_style',
				plugins_url( './css/style.css', __FILE__ ),
				array(),
				$this->version
			);
			wp_enqueue_style( 'sapo_rss_sidebar_style' );

			add_meta_box( 'sapo-rss-meta-box', 'Informação Geográfica',
				array($this, 'sapo_rss_meta_box_callback'), $post_type, 'side', 'default',
				array( '__back_compat_meta_box' => true, )
			);
		}
	}

	function sapo_rss_classic_save( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( isset($_POST['sapo_feed_post_nuts']) ) {
			$post_nuts = esc_attr($_POST['sapo_feed_post_nuts']);

			if( ($post_nuts !== '') && (! preg_match('/^(?:\d{6},?)+$/', $post_nuts)) ) {
				return;
			}

			update_post_meta( $post_id, 'sapo_feed_post_nuts',  $post_nuts );
		}
	}

	/*
	 *   Gutenberg sidebar
	 */
	function sapo_rss_sidebar_assets_enqueue() {
		// Only load assets if the post_type supports 'custom-fields'
		if ( $this->sapo_rss_is_nuts_supported() ) {
			wp_register_script(
				'sapofeed-sidebar-js',
				plugins_url( './js/sidebar.js', __FILE__ ),
				array(
					'wp-plugins',
					'wp-edit-post',
					'wp-element',
					'wp-data',
					'wp-components'
				),
				$this->version
			);

			$nuts = $this->sapo_rss_get_nuts_data();

			if ( ! empty($nuts) ) {
				$data = array(
					'option' => $this->options['sapo_rss_default_nuts'],
					'list'   => $nuts,
				);
				// Localize script exposing $data contents
				wp_localize_script( 'sapofeed-sidebar-js', 'nutsJSON', $data );
			}

			wp_enqueue_script( 'sapofeed-sidebar-js' );

			// CSS
			wp_register_style(
				'sapo_rss_sidebar_style',
				plugins_url( './css/style.css', __FILE__ ),
				array(),
				$this->version
			);
			wp_enqueue_style( 'sapo_rss_sidebar_style' );
		}
	}
}

new SAPO_Feed();

?>
