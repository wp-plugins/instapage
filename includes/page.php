<?php

class InstapagePage extends instapage
{
	var $page_stats;

	public function init()
	{
		add_filter( 'the_posts', array( &$this, 'checkCustomUrl' ), 1 );
		add_action( 'parse_request', array( &$this, 'checkRoot' ), 1 );
		add_action( 'template_redirect', array( &$this, 'check404Page' ), 1 );
	}

	public function parseRequest()
	{
		$posts = $this->getAllPosts();

		if ( !is_array( $posts ) )
		{
			return false;
		}

		// get current url
		$current = $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];

		// calculate the path
		$part = substr( $current, strlen( str_replace( array( 'https://', 'http://' ), '', home_url() ) ) );
		$part = rtrim( $part, '/' );

		if ( substr( $part, 0, 1 ) === '/' )
		{
			$part = substr( $part, 1 );
		}

		// strip parameters
		$real = explode( '?', $part );
		$tokens = $real[ 0 ];

		if ( array_key_exists( $tokens, $posts ) )
		{
			if ( $tokens == '' )
			{
				return false;
			}

			return $posts[ $tokens ];
		}

		return false;
	}

	public static function getFrontInstapage()
	{
		$v = get_option( 'instapage_front_page_id', false );
		return ( $v == '' ) ? false : $v;
	}

	public static function get404Instapage()
	{
		$v = get_option( 'instapage_404_page_id', false );
		return ( $v == '' ) ? false : $v;
	}

	public function isFrontPage( $id )
	{
		$front = $this->getFrontInstapage();
		return ($id == $front && $front !== false);
	}

	public function is404Page( $id )
	{
		$not_found = $this->get404Instapage();
		return ( $id == $not_found && $not_found !== false );
	}

	public function getMyPosts()
	{
		global $wpdb;

		$sql = "SELECT {$wpdb->posts}.ID, {$wpdb->postmeta}.meta_key, {$wpdb->postmeta}.meta_value FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id) WHERE ({$wpdb->posts}.post_type = %s) AND ({$wpdb->posts}.post_status = 'publish') AND ({$wpdb->postmeta}.meta_key IN ('instapage_my_selected_page', 'instapage_name', 'instapage_my_selected_page', 'instapage_slug'))";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, 'instapage_post' ) );
		$posts = array();

		foreach ( $rows as $k => $row )
		{
			if ( !array_key_exists( $row->ID, $posts ) )
			{
				$posts[ $row->ID ] = array();
			}

			$posts[ $row->ID ][ $row->meta_key ] = $row->meta_value;
		}

		return $posts;
	}

	public function getAllPosts()
	{
		if ( $this->posts === false )
		{
			$front = $this->getFrontInstapage();
			$p = $this->getMyPosts();
			$res = array();

			foreach ( $p as $k => $v )
			{
				if ( $front == $k )
				{
					continue;
				}

				$res[ $v[ 'instapage_slug' ] ] = array
				(
					'id' => $v[ 'instapage_my_selected_page' ],
					'name' => $v[ 'instapage_name' ]
				);
			}

			$this->posts = $res;
		}

		return $this->posts;
	}

	public function checkCustomUrl( $posts )
	{
		global $post, $wpdb;

		if( is_admin() )
		{
			return $posts;
		}

		if( self::getInstance()->includes[ 'service' ]->isServicesRequest() )
		{
			return self::getInstance()->includes[ 'service' ]->processProxyServices();
		}

		if( isset( $_GET[ 'instapage_post' ] ) )
		{
			// draft mode
			$post_id = $wpdb->get_var( "SELECT ID FROM `{$wpdb->posts}` WHERE post_name = '". $wpdb->escape( $_GET[ 'instapage_post' ] ) ."' LIMIT 1" );
			$instapage_id = get_post_meta( (int)  $post_id, 'instapage_my_selected_page' );
			$html = self::getInstance()->includes[ 'api' ]->getPageHtml( $instapage_id[ 0 ] );
		}
		else
		{
			// Determine if request should be handled by this plugin
			$requested_page = $this->parseRequest();

			if( $requested_page == false )
			{
				return $posts;
			}

			$html = self::getInstance()->includes[ 'api' ]->getPageHtml( $requested_page[ 'id' ] );
		}

		if ( ob_get_length() > 0 )
		{
			ob_end_clean();
		}

		status_header( '200' );

		header( 'Access-Control-Allow-Origin: *' );

		print $html;
		die();
	}

	public function loadMyPages()
	{
		static $this_plugin;

		if ( self::getInstance()->my_pages === false )
		{
			$response = self::getInstance()->includes[ 'api' ]->instapageApiCall
			(
				'my-pages',
				array
				(
					'user_id' => get_option( 'instapage.user_id' ),
					'plugin_hash' => get_option( 'instapage.plugin_hash' )
				)
			);

			if( !$response )
			{
				throw new Exception( 'Error connecting to instapage' );
			}

			if( $response->error )
			{
				if( $response->error_message == 'User not found' )
				{
					$this_plugin = plugin_basename( __FILE__ );
					$response->error_message .= '. Please <a href="'. admin_url( 'options-general.php?page=' . $this_plugin ) .'">relogin</a>';
				}

				throw new Exception( $response->error_message );
			}

			$pages = array();
			$pages_array_response = $response->data[ 'pages' ];

			if( $pages_array_response )
			{
				foreach( $pages_array_response as $page_array )
				{
					$page = new stdClass();

					foreach( $page_array as $key => $value )
					{
						$page->$key = $value;
					}

					$pages[] = $page;
				}
			}

			$this->my_pages = $pages;
		}

		return $this->my_pages;
	}

	public static function set404Instapage( $id )
	{
		update_option( 'instapage_404_page_id', $id );
	}

	public function getMyPage( $id )
	{
		try
		{
			if( $this->loadMyPages() )
			{
				foreach( $this->loadMyPages() as $page )
				{
					if( $page->id == $id )
					{
						return $page;
					}
				}
			}
		}
		catch( Exception $e )
		{
			echo $e->getMessage();
		}

		return false;
	}

	public function checkRoot()
	{

		if( is_admin() )
		{
			return;
		}

		// current for front page override
		$front = $this->getFrontInstapage();

		if ( $front === false )
		{
			return;
		}

		// get current full url
		$current = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
		// calculate the path
		$part = substr( $current, strlen( site_url() ) );
		// remove any variables from the url
		$pos = strpos( $part, '?' );
		$rest_part = false;

		if ( $pos !== false )
		{
			$rest_part = substr( $part, $pos, strlen( $part ) );
			$part = substr( $part, 0, $pos );
		}

		// display the homepage if enabled
		if ( $part === '' || $part == 'index.php' || $part == '/' || $part == '/index.php' )
		{
			if ( $front !== false )
			{
				$mp = $this->getPageById( $front );

				if ( $mp !== false && $mp->post_status == 'publish' )
				{
					// get and display the page at root
					$html = self::getInstance()->includes[ 'api' ]->getPageHtml( $mp->lp_id );

					if ( ob_get_length() > 0 )
					{
						ob_end_clean();
					}

					// flush previous cache
					if ( !substr_count( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ], 'gzip' ) )
					{
						ob_start();
					}

					status_header( '200' );
					print $html;
					ob_end_flush();
					die();
				}
			}
		}
	}

	public function getPageById( $post_id )
	{
		$res = get_post( $post_id );

		if ( empty( $res ) )
		{
			return false;
		}

		$url = get_post_meta( $post_id, 'instapage_url', true );
		$slug = get_post_meta( $post_id, 'instapage_slug', true );
		$id = get_post_meta( $post_id, 'instapage_my_selected_page', true );
		$res->lp_id = $id;
		$res->lp_url = $url;
		$res->slug = $slug;

		return $res;
	}

	public function displayCustom404( $id_404 )
	{
		// show the instapage
		$mp = $this->getPageById( $id_404 );
		$html = self::getInstance()->includes[ 'api' ]->getPageHtml( $mp->lp_id );

		if ( ob_get_length() > 0 )
		{
			ob_end_clean();
		}

		status_header( '404' );
		print $html;
		die();
	}

	public function check404Page()
	{
		$not_found = $this->get404Instapage();

		if( $not_found === false )
		{
			return;
		}

		if( is_404() )
		{
			$id = $this->get404Instapage();
			$this->displayCustom404( $id );
		}
	}

	public function getPageUrl( $post_id )
	{
		$path = esc_html( get_post_meta( $post_id, 'instapage_slug', true ) );
		$isFrontPage = $this->isFrontPage( $post_id );
		$is_not_found_page = $this->is404Page( $post_id );
		$url = false;

		if ( $isFrontPage )
		{
			$url = site_url() . '/';
		}
		elseif ( $is_not_found_page )
		{
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$randomString = '';
			$length = 10;

			for ( $i = 0; $i < $length; $i++ )
			{
				$randomString .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
			}

			$url = site_url() . '/random-url-' . $randomString;
		}
		else
		{
			if ( $path == '' )
			{
				return false;
			}
			else
			{
				$url = site_url() . '/' . $path;
			}
		}

		return $url;
	}

	public function getPageEditUrl( $post_id )
	{
		return get_edit_post_link( $post_id );
	}

	public function getPageScreenshot( $post_id )
	{
		return INSTAPAGE_PLUGIN_URI . 'static/img/wordpress-thumb.jpg';
	}

	public function getPageName( $post_id )
	{
		return get_post_meta( $post_id, 'instapage_name', true );
	}

	public function calcAvarageConversion( $visits, $conversions )
	{
		$average_conversion_rate = ( $visits > 0 ) ? round( ( $conversions / $visits ) * 100, 1 ) : 0;

		if ( $average_conversion_rate > 100 )
		{
			$average_conversion_rate = 100;
		}

		return $average_conversion_rate;
	}

	public function getPageStats( $post_id )
	{
		if ( isset( $this->page_stats[ $post_id ] ) )
		{
			return $this->page_stats[ $post_id ];
		}

		$page = $this->getPageById( $post_id );

		if ( !$page )
		{
			return false;
		}

		$data = array
		(
			'page_id' => $page->lp_id
		);

		$stats = self::getInstance()->includes[ 'api' ]->instapageApiCall
		(
			'my_stats/' . get_option( 'instapage.user_id' ) . '/' . get_option( 'instapage.plugin_hash' ),
			$data
		);

		if ( !$stats->stats )
		{
			return false;
		}

		$stats = $stats->stats;

		$variant_defaults = array
		(
			'visits' => 0,
			'conversions' => 0,
			'conversion_rate' => 0,
			'class' => ''
		);

		$page_stats = array
		(
			'visits' => $stats[ 'stats' ][ 'visits' ],
			'conversions' => $stats[ 'stats' ][ 'conversions' ],
			'conversion_rate' => $this->calcAvarageConversion( $stats[ 'stats' ][ 'visits' ], $stats[ 'stats' ][ 'conversions' ] ),
			'variants' => array()
		);

		$max = 0;
		$min = 999999;
		$max_variant_name = false;
		$min_variant_name = false;

		/*
		TODO: change responsive_variant_names to all_variants (when pull will be pushed live)
		*/
		foreach( $stats[ 'responsive_variant_names' ] as $variant_name )
		{
			$page_stats[ 'variants' ][ $variant_name ] = $variant_defaults;

			if ( !isset( $stats[ 'stats' ][ 'variant' ][ $variant_name ] ) )
			{
				$page_stats[ 'variants' ][ $variant_name ][ 'class' ] .= ' red';
				continue;
			}

			$page_stats[ 'variants' ][ $variant_name ] = $stats[ 'stats' ][ 'variant' ][ $variant_name ];
			$page_stats[ 'variants' ][ $variant_name ][ 'conversion_rate' ] = $this->calcAvarageConversion( $stats[ 'stats' ][ 'variant' ][ $variant_name ][ 'visits' ], $stats[ 'stats' ][ 'variant' ][ $variant_name ][ 'conversions' ] );

			if ( $page_stats[ 'variants' ][ $variant_name ][ 'conversion_rate' ] > $max )
			{
				$max = $page_stats[ 'variants' ][ $variant_name ][ 'conversion_rate' ];
				$max_variant_name = $variant_name;
			}

			if ( $page_stats[ 'variants' ][ $variant_name ][ 'conversion_rate' ] < $min )
			{
				$min = $page_stats[ 'variants' ][ $variant_name ][ 'conversion_rate' ];
				$min_variant_name = $variant_name;
			}

			if ( $stats[ 'stats' ][ 'variant' ][ $variant_name ][ 'paused' ] )
			{
				$page_stats[ 'variants' ][ $variant_name ][ 'class' ] = 'paused';
			}
		}

		if ( $min_variant_name )
		{
			$page_stats[ 'variants' ][ $min_variant_name ][ 'class' ] .= ' red';
		}

		if ( $max_variant_name )
		{
			$page_stats[ 'variants' ][ $max_variant_name ][ 'class' ] = str_replace( 'red', '', $page_stats[ 'variants' ][ $max_variant_name ][ 'class' ] );
			$page_stats[ 'variants' ][ $max_variant_name ][ 'class' ] .= ' green';
		}

		foreach( $page_stats[ 'variants' ] as $variant_name => $variant )
		{
			if ( $min_variant_name )
			{
				if ( $variant_name !== $min_variant_name && $variant[ 'conversion_rate' ] === $page_stats[ 'variants' ][ $min_variant_name ][ 'conversion_rate' ] )
				{
					$page_stats[ 'variants' ][ $variant_name ][ 'class' ] .= ' red';
				}
			}

			if ( $max_variant_name )
			{
				if ( $variant_name !== $max_variant_name && $variant[ 'conversion_rate' ] === $page_stats[ 'variants' ][ $max_variant_name ][ 'conversion_rate' ] )
				{
					$page_stats[ 'variants' ][ $variant_name ][ 'class' ] .= ' green';
				}
			}
		}

		$this->page_stats[ $post_id ] = $page_stats;
		return $page_stats;
	}
}