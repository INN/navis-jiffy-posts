<?php
/**
 * Plugin Name: Navis Jiffy Posts
 * Description: Makes it easy to quickly create a post from a URL
 * Version: 0.1
 * Author: Marc Lavallee 
 * License: GPLv2
*/
/*
    Copyright 2011 National Public Radio, Inc. 

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class Navis_Jiffy_Posts {
    function __construct() {
        add_action( 'init', array( &$this, 'register_post_type' ) );

        if ( is_admin() )
            add_action( 'init', array( &$this, 'admin_init' ) );
    }


    function admin_init() {
        add_action( 'admin_menu', array( &$this, 'add_options_page' ) );

        add_action( 'admin_init', array( &$this, 'init_settings' ) );

        add_action( 'admin_head-post.php', 
            array( &$this, 'provide_embedly_config' )
        );
        add_action( 'admin_head-post-new.php', 
            array( &$this, 'provide_embedly_config' )
        );

        add_action( 'admin_print_scripts-post.php', 
            array( &$this, 'register_admin_scripts' )
        );
        add_action( 'admin_print_scripts-post-new.php', 
            array( &$this, 'register_admin_scripts' )
        );

        add_action( 'admin_menu', array( &$this, 'add_post_meta_boxes' ) );

        add_filter( 'tiny_mce_before_init', array( &$this, 'init_tiny_mce' ) );

        add_action( 'save_post', array( &$this, 'save_post' ) );
        add_filter( 'wp_insert_post_data', 
            array( &$this, 'insert_post_content' ) 
        );
    }


    function register_post_type() {
        register_post_type( 'jiffypost', array(
            'labels' => array(
                'name' => 'Jiffy Posts',
                'singular_name' => 'Jiffy Post',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Jiffy Post',
                'edit' => 'Edit',
                'edit_item' => 'Edit Jiffy Post',
                'view' => 'View',
                'view_item' => 'View Jiffy Post',
                'search_items' => 'Search Jiffy Posts',
                'not_found' => 'No Jiffy Posts found',
                'not_found_in_trash' => 'No Jiffy Posts found in Trash',
            ),
            'description' => 'Jiffy Posts',
            'supports' => array( 'title', 'comments', 'author' ),
            'public' => true,
            'menu_position' => 6,
            'taxonomies' => array(),
        ) );
    }


    function register_admin_scripts() {
        if ( 'jiffypost' != get_post_type() )
            return;

        // Embed.ly's JavaScript client
        $libsrc = plugins_url( 
            'js/embedly-jquery/jquery.embedly.js', __FILE__ 
        );
        wp_enqueue_script( 'jquery-embedly', $libsrc, 
            array( 'jquery' ), '2.0.0' 
        );

        // Our JS routines
        $oursrc = plugins_url( 'js/navis-jiffy-posts-admin.js', __FILE__ );
        wp_enqueue_script( 'navis-jiffy-posts-admin', $oursrc, 
            array( 'jquery-embedly' ), '0.1' 
        );
    }


    function init_tiny_mce( $initArray ) {
        $initArray[ 'editor_selector' ] = 'leadintext';
        $initArray[ 'setup' ] = 'tinyMCESetup';
        return $initArray;
    }


    function add_post_meta_boxes() {
        add_meta_box( 'navisembedurl', 'Embed a URL', 
            array( &$this, 'embed_url_box' ), 'jiffypost', 
            'normal', 'high' 
        );

        add_meta_box( 'navisleadin', 'Lead in text',
            array( &$this, 'embed_leadin_box' ), 'jiffypost', 
            'normal', 'high' 
        );

        add_meta_box( 'navisembedpreview', 'Preview Embed',
            array( &$this, 'embed_preview_box' ), 'jiffypost',
            'normal', 'high'
        );
    }


    function embed_url_box( $post ) {
        $navis_embed_url = get_post_meta( $post->ID, '_navis_embed_url', true );
    ?>
        URL: <input type="text" name="navis_embed_url" id="navis_embed_url" value="<?php echo $navis_embed_url; ?>" style="width: 80%;" />
        <input type="button" class="button" id="submitUrl" value="Embed" label="Embed" />
    <?php
    }



    function embed_leadin_box( $post ) {
        $leadintext = get_post_meta( $post->ID, '_leadintext', true );
    ?>
        <p align="right">
            <a id="edButtonHTML" class="">HTML</a>
            <a id="edButtonPreview" class="active">Visual</a>
        </p>
        <textarea id="leadintext" class="leadintext" name="leadintext" style="width: 98%"><?php echo $leadintext; ?></textarea>
    <?php
    }


    function embed_preview_box() {
        $leadintext = get_post_meta( $post->ID, '_leadintext', true );
    ?>
        <p id="leadinPreviewArea"><?php echo $leadintext; ?></p>
        <div id="embedlyPreviewArea" style="overflow: hidden;"></div>
        <input type="hidden" id="embedlyarea" name="embedlyarea" value="" />
    <?php
    }


    function save_post( $post_id ) {
        if ( isset( $_POST[ 'navis_embed_url' ] ) ) {
            update_post_meta( $post_id, '_navis_embed_url', 
                $_POST[ 'navis_embed_url' ] 
            );
        }

        if ( isset( $_POST[ 'leadintext' ] ) ) {
            update_post_meta( $post_id, '_leadintext', $_POST[ 'leadintext' ] );
        }

        if ( isset( $_POST[ 'embedlyarea' ] ) ) {
            update_post_meta( $post_id, '_embedlyarea', $_POST[ 'embedlyarea' ] );
            $content .= $_POST[ 'embedlyarea' ];
        }
    }


    function insert_post_content( $data ) {
        $content = '';
        if ( isset( $_POST[ 'leadintext' ] ) ) {
            $content = '<p>' . $_POST[ 'leadintext' ] . '</p>';
        }

        if ( isset( $_POST[ 'embedlyarea' ] ) ) {
            $content .= $_POST[ 'embedlyarea' ];
        }

        $data[ 'post_content' ] = $content;
        return $data;
    }


    function provide_embedly_config() {
        if ( 'post' != get_post_type() )
            return;

        $embedly_api_key = get_option( 'embedly_api_key' );
        $max_width       = get_option( 'embed_size_w' );
        $max_height      = get_option( 'embed_size_h' );
    ?>
        <script>
            <?php if ( $embedly_api_key ): ?>
                EMBEDLY_API_KEY = '<?php echo $embedly_api_key; ?>';
            <?php endif; ?>
            <?php if ( $max_width ): ?>
                MAX_EMBED_WIDTH = <?php echo $max_width; ?>;
            <?php endif; ?>
            <?php if ( $max_height ): ?>
                MAX_EMBED_HEIGHT = <?php echo $max_height; ?>;
            <?php endif; ?>
        </script>
    <?php
    }


    function init_settings() {
        add_settings_section(
            'navis_jiffy_post_settings', 'Navis Jiffy Post settings', 
            array( &$this, 'settings_callback' ), 'navis_jp' 
        );

        add_settings_field( 
            'embedly_api_key', 'Embedly API Key', 
            array( &$this, 'embedly_key_callback' ), 'navis_jp', 
            'navis_jiffy_post_settings' 
        );
        
        register_setting( 'navis_jp', 'embedly_api_key' );
    }


    function settings_callback() { }

    function embedly_key_callback() {
        $option = get_option( 'embedly_api_key' );
        echo "<input type='text' value='$option' name='embedly_api_key' style='width: 300px;' />"; 
    }

    function add_options_page() {
        add_options_page( 'Jiffy Posts', 'Jiffy Posts', 'manage_options',
                          'navis_jp', array( &$this, 'options_page' ) );
    }


    function options_page() {
    ?>
        <div>
            <h2>Navis Jiffy Posts</h2>
            <form action="options.php" method="post">
                <?php settings_fields( 'navis_jp' ); ?>
                <?php do_settings_sections( 'navis_jp' ); ?>

                <input name="Submit" type="submit" value="Save Changes" />
            </form>
        </div>
    <?php
    }

}

new Navis_Jiffy_Posts;