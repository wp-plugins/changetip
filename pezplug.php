<?php

if ( !class_exists( 'pezplug' ) ) :
class pezplug {
     
     public function __construct( $slug_name ) {
        $this->slug = $slug_name;
        $this->add_action( 'admin_init', 'pezplug_admin_init' );
        $this->add_action( 'wp_enqueue_scripts', 'pezplug_wp_enqueue_scripts' );
        $this->add_action( 'admin_enqueue_scripts', 'pezplug_admin_enqueue_scripts' );
     }

     private $_options = array();
     private $_styles = array();
     private $_styles_admin = array();
     private $_scripts = array();
     private $_scripts_admin = array();

     public function ref( $function_name ) {
        return array( &$this, $function_name );    
     }

     public function get_options() {
            //if ( $this->_options ) {
            //    return $this->_options;
            //}
            $this->_options = get_option( $this->slug );
            return $this->_options;
     }

     public function get_option( $key ) {
        $options = $this->get_options();   
        return isset( $options[$key] ) ? $options[$key] : NULL; 
     }

     public function update_option( $key, $value ) {
        $options = $this->get_options(); 
        $options[$key] = $value;
        update_option( $this->slug, $options ); 
     }

     public function update_some_options( $new_options ) {
         if( !$new_options ) {
             return NULL;
         }
         $options = $this->get_options(); 
         foreach( $new_options as $key => $value ) {
             $options[$key] = $value;
         }
         update_option( $this->slug, $options ); 
     }
     
     public function update_options( $options = array() ) {
        update_option( $this->slug, $options );    
     }

     public function add_style( $file ) {
        $this->_styles[] = array( 'file' => $file );
     }

     public function add_admin_style( $file ) {
        $this->_styles_admin[] = array( 'file' => $file );
     }

     public function add_script( $file, $deps = NULL ) {
        $this->_scripts[] = array( 'file' => $file, 'deps' => $deps );
     }

     public function add_admin_script( $file, $deps = NULL ) {
         $this->_scripts_admin[] = array( 'file' => $file, 'deps' => $deps );
     }

     public function add_filter( $tag, $function_to_add = NULL, $priority = 10, $accepted_args = 1 ) {
         if ( !$tag ) {
             trigger_error( "missing required parameter: tag", E_USER_NOTICE );
             return;
         }
         if ( is_array( $tag ) ) {
             foreach( $tag as $tag1 ) {
                $this->add_filter( $tag1, $function_to_add, $priority, $accepted_args );
             }
         } else {
             if ( !$function_to_add ) {
                 $function_to_add = $tag;
             }
             add_filter( $tag, $this->ref( $function_to_add ), $priority, $accepted_args );
         }
     }

     public function add_action( $hook, $function_to_add = NULL, $priority = 10, $accepted_args = 1 ) {
         if ( !$hook ) {
            trigger_error( "missing required parameter: hook", E_USER_NOTICE );
            return;
         }
         if ( is_array( $hook ) ) {
             foreach( $hook as $hook1 ) {
                 $this->add_action( $hook1, $function_to_add, $priority, $accepted_args );
             }
         } else {
             if ( !$function_to_add ) {
                 $function_to_add = $hook;
             }
             add_action( $hook, $this->ref( $function_to_add ), $priority, $accepted_args );
         }
     }

     public function render( $file ) {
         require_once( plugin_dir_path( __FILE__ ) . $file );
     }

     public function render_options_page( $title, $option_group = NULL ) {
        if ( !$option_group ) {
            $option_group = $this->slug;
        }
        echo '<div class="wrap">';
        echo "<h2>$title</h2>";
        echo '<form method="post" action="options.php">';   

        do_action( "render_options_page_{$option_group}_before" );
        settings_fields( $option_group );
        do_settings_sections( $option_group );
        do_action( "render_options_page_{$option_group}_after" );
        submit_button();

        echo '</form>';
        echo '</div>';
     }

     public function render_field( $id, $suffix = NULL ) {
        $options = $this->get_options();
        printf(
            '<input type="text" id="%1$s" name="%3$s[%1$s]" value="%2$s" />%4$s',
            $id, isset( $options[$id] ) ? esc_attr( $options[$id]) : '', $this->slug, $suffix
        );         
     }

     public function render_disabled_textbox( $id, $suffix = NULL ) {
        $options = $this->get_options();
        printf(
            '<input type="text" readonly id="%1$s" name="%3$s[%1$s]" value="%2$s" />%4$s',
            $id, isset( $options[$id] ) ? esc_attr( $options[$id]) : '', $this->slug, $suffix
        ); 
     }

     public function render_hidden_textbox( $id, $suffix = NULL ) {
        $options = $this->get_options();
        printf(
            '<input type="hidden" id="%1$s" name="%3$s[%1$s]" value="%2$s" />%4$s',
            $id, isset( $options[$id] ) ? esc_attr( $options[$id]) : '', $this->slug, $suffix
        ); 
     }

     public function render_field_checkbox( $id, $suffix = NULL ) {
        $options = $this->get_options();
        printf(
            '<input type="checkbox" id="%1$s" name="%3$s[%1$s]" %2$s />%4$s',
            $id, isset( $options[$id] ) ? 'checked' : '', $this->slug, $suffix
        );  
     }

     public function render_field_select( $id, $args = array(), $suffix = NULL ) {
        $options = $this->get_options();
        $cur_key = isset( $options[$id] ) ? $options[$id] : NULL;

        printf( '<select id="%1$s" name="%2$s[%1$s]">',
            $id, $this->slug );
        foreach( $args as $key => $value ) {
            $selected = $key == $cur_key ? ' selected' : NULL;
            echo "<option value='$key'$selected>$value</option>";
        }
        echo '</select>';
     }

     public function pezplug_admin_init() {
        if( $this->slug ) {
            register_setting( $this->slug, $this->slug );
        }
     }

     public function pezplug_wp_enqueue_scripts() {
        foreach( $this->_scripts as $script ) {
            wp_register_script( $script['file'], plugins_url( $script['file'], __FILE__ ), $script['deps'] );
            wp_enqueue_script( $script['file'] );
        }
        foreach( $this->_styles as $style ) {
            wp_register_style( $style['file'], plugins_url( $style['file'] , __FILE__ ) );
            wp_enqueue_style( $style['file'] );
        }
     }

     public function pezplug_admin_enqueue_scripts() {
        foreach( $this->_scripts_admin as $script ) {
            wp_register_script( $script['file'], plugins_url( $script['file'], __FILE__ ), $script['deps'] );
            wp_enqueue_script( $script['file'] );
        } 
        foreach( $this->_styles_admin as $style ) {
            wp_register_style( $style['file'], plugins_url( $style['file'] , __FILE__ ) );
            wp_enqueue_style( $style['file'] );
        }     
     }
 }
 endif;