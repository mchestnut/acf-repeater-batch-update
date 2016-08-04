<?php

class acf_field_repeater_batch_update {

    // Used to capture all repeater fields
    private $update_fields = array();

    
    /*
    * __construct
    *
    * If repeater tag exists, then removes original acf_field_repeater update_value action and
    * replaces it with the new update_value_batch action
    *
    * @since    1.0
    * @date     28/7/16
    */

    function __construct() {
        global $wp_filter;

        $functions_tag = 'acf/update_value';
        $repeater_tag = 'acf/update_value/type=repeater';

        if ( isset( $wp_filter[$repeater_tag] ) ) {
            $this->remove_anonymous_object_filter( $functions_tag, 'acf_field_functions', 'update_value' );
            add_action( 'acf/update_value', array($this, 'update_value'), 5, 3 );
            add_action( 'acf/update_value_batch', array($this, 'update_value_batch'), 5, 3 );
        }
    }


    /*
    * remove_anonymous_object_filter
    * 
    * Removes filter or action if it matches the given class and method. 
    * Source: http://bit.ly/29LkAl8
    *
    * @type     function
    * @since    1.0
    * @date     28/7/16
    *
    * @param    string  $tag: the name of the filter or action to remove
    * @param    string  $class: the name of the singleton class
    * @param    string  $method: the name of the method
    */

    private function remove_anonymous_object_filter( $tag, $class, $method ) {
        global $wp_filter;

        $filters = $wp_filter[$tag];

        if ( empty( $filters ) ) {
            return;
        }

        foreach ( $filters as $priority => $filter ) {
            foreach ( $filter as $identifier => $function ) {
                if ( is_array( $function ) and is_a( $function['function'][0], $class ) and $method === $function['function'][1] ) {
                    remove_filter(
                        $tag,
                        array($function['function'][0], $method),
                        $priority
                    );
                }
            }
        }
    }


    /*
    * get_update_fields
    *
    * Gets all sub fields from repeater field and assigns them to the $update_fields property array.
    *
    * @type     function
    * @since    1.0
    * @date     28/7/16
    *
    * @param    array   $value: the repeater value to be assigned to update_fields
    * @param    int     $post_id: the post ID to save the value to
    * @param    array   $field: the repeater field to be saved
    *
    * @return   array   $this->update_fields: array of repeater fields
    */

    private function get_update_fields( $value, $post_id, $field ) {
        $total = 0;
        
        if ( $value ) {
            // Remove dummy field
            unset( $value['acfcloneindex'] );

            $i = -1;

            // Loop through rows
            foreach ( $value as $row )
            {   
                $i++;

                // Increase total        
                $total++;
                
                // Loop through sub fields
                foreach ( $field['sub_fields'] as $sub_field )
                {   
                    // Get sub field data
                    $v = isset( $row[$sub_field['key']] ) ? $row[$sub_field['key']] : false;

                    // Update full name
                    $sub_field['name'] = $field['name'] . '_' . $i . '_' . $sub_field['name'];

                    if ( 'repeater' == $sub_field['type'] ) {   
                        // Get sub field value
                        $this->get_update_fields($v, $post_id, $sub_field);
                    } else {
                        // Strip slashes
                        $v = stripslashes_deep($v);

                        // Apply filters
                        foreach ( array('key', 'name', 'type') as $key ) {
                            // Run filters
                            $v = apply_filters('acf/update_value/' . $key . '=' . $sub_field[ $key ], $v, $post_id, $sub_field);
                        }

                        // Append sub field to update_fields
                        $this->update_fields[] = array(
                            'meta_key' => $sub_field['name'],
                            'meta_value' => $v
                        );

                        // Append sub field revision to update_fields
                        $this->update_fields[] = array(
                            'meta_key' => '_' . $sub_field['name'],
                            'meta_value' => $sub_field['key']
                        );
                    }
                }
            }
        }

        // Update $value with number of sub fields
        $value = $total;

        // Append field to update_fields
        $this->update_fields[] = [
            'meta_key' => $field['name'],
            'meta_value' => $value
        ];

        // Apend field revision to update_fields
        $this->update_fields[] = array(
            'meta_key' => '_' . $field['name'],
            'meta_value' => $field['key']
        );
        

        /*
        *  Remove Old Data
        *
        *  @credit: http://support.advancedcustomfields.com/discussion/1994/deleting-single-repeater-fields-does-not-remove-entry-from-database
        */

        $old_total = (int) apply_filters('acf/load_value', 0, $post_id, $field);
        
        if ( $old_total > $total ) {
            for ( $j = $total; $j < $old_total; $j++ ) {
                foreach ( $field['sub_fields'] as $sub_field ) {
                    do_action( 'acf/delete_value', $post_id, $field['name'] . '_' . $j . '_' . $sub_field['name'] );
                }
            }
        }
        
        return $this->update_fields;
    }


    /*
    * add_metadata_batch
    *
    * Adds metadata in one batched SQL update statement. Based on WordPress add_metadata function.
    *
    * @type     function
    * @since    1.0
    * @date     28/7/16
    *
    * @param    string  $meta_type: type of object metadata is for
    * @param    int     $object_id: ID of the object
    * @param    array   $meta_fields: the fields to be updated
    */

    private function add_metadata_batch( $meta_type, $object_id, $meta_fields ) {
        global $wpdb;
 
        if ( ! $meta_type || ! $meta_fields || ! is_numeric( $object_id ) ) {
            return false;
        }
     
        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return false;
        }

        $table = _get_meta_table( $meta_type );
        if ( ! $table ) {
            return false;
        }
     
        $column = sanitize_key($meta_type . '_id');
        $id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';
        $id_column .= ', meta_key';
        
        $insert_values = '';
        $i = count($meta_fields);

        // Loop through meta_fields
        while ( $i > 0 ) {
            $i--;

            if ( isset( $meta_fields[$i]['raw_meta_key'] ) ) {
                $meta_fields[$i]['meta_key'] = $meta_fields[$i]['raw_meta_key'];
            }

            if ( isset( $meta_fields[$i]['raw_meta_value'] ) ) {
                $meta_fields[$i]['meta_value'] = $meta_fields[$i]['raw_meta_value'];
            }

            $meta_fields[$i]['meta_key'] = wp_unslash($meta_fields[$i]['meta_key']);
            $meta_fields[$i]['meta_value'] = wp_unslash($meta_fields[$i]['meta_value']);
            $meta_fields[$i]['meta_value'] = sanitize_meta( $meta_fields[$i]['meta_key'], $meta_fields[$i]['meta_value'], $meta_type );

            /*
            * Filter whether to add metadata of a specific type.
            *
            * For more details, see wp-includes/meta.php
            */
            $check = apply_filters( "add_{$meta_type}_metadata", null, $object_id, $meta_fields[$i]['meta_key'], $meta_fields[$i]['meta_value'], null );
            if ( null !== $check ) {
                unset( $meta_fields[$i] );
                continue;
            }

            $meta_fields[$i]['_meta_value'] = $meta_fields[$i]['meta_value'];
            $meta_fields[$i]['meta_value'] = maybe_serialize($meta_fields[$i]['meta_value']);

            /*
            * Fires immediately before meta of a specific type is added.
            *
            * For more details, see wp-includes/meta.php
            */
            do_action( "add_{$meta_type}_meta", $object_id, $meta_fields[$i]['meta_key'], $meta_fields[$i]['_meta_value'] );

            // Build SQL statement
            $insert_values .= "(" . $object_id . ", ";
            $insert_values .= "'" . $meta_fields[$i]['meta_key'] . "', ";
            $insert_values .= "'" . $wpdb->_real_escape( $meta_fields[$i]['meta_value'] ) . "'), ";
        }

        $insert_values = rtrim( $insert_values, ', ' );


        // Insert values into database        
        $result = $wpdb->query( "INSERT INTO $table (post_id, meta_key, meta_value) VALUES $insert_values" );
        $insert_id = $wpdb->insert_id;

        if ( ! $result ) {
            return false;
        }
        
        wp_cache_delete( $object_id, $meta_type . '_meta' );
        
        for ( $i = 0; $i < $result; $i++ ) {
            $mid = $i + $insert_id;

            /*
            * Fires immediately after meta of a specific type is added.
            *
            * For more details, see wp-includes/meta.php
            */
            do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_fields[$i]['meta_key'], $meta_fields[$i]['_meta_value'] );
        }

        return true;
    }


    /*
    * update_metadata_batch
    *
    * Updates metadata in one batched SQL update statement. If values don't already exist, the field
    * is added used add_metadata_batch. Based on WordPress update_metadata function.
    *
    * @type     function
    * @since    1.0
    * @date     28/7/16
    *
    * @param    string  $meta_type: type of object metadata is for
    * @param    int     $object_id: ID of the object
    * @param    array   $meta_fields: the fields to be updated
    */

    private function update_metadata_batch( $meta_type, $object_id, $meta_fields ) {
        global $wpdb;
 
        if ( ! $meta_type || ! $meta_fields || ! is_numeric( $object_id ) ) {
            return false;
        }
     
        $object_id = absint( $object_id );
        if ( ! $object_id ) {
            return false;
        }

        $table = _get_meta_table( $meta_type );
        if ( ! $table ) {
            return false;
        }
     
        $column = sanitize_key($meta_type . '_id');
        $id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';
        $id_column .= ', meta_key';

        $i = count( $meta_fields );

        // Loop through meta_fields
        while ( $i > 0 ) {
            $i--;

            $meta_fields[$i]['raw_meta_key'] = $meta_fields[$i]['meta_key'];
            $meta_fields[$i]['meta_key'] = wp_unslash($meta_fields[$i]['meta_key']);
            $meta_fields[$i]['raw_meta_value'] = $meta_fields[$i]['meta_value'];
            $meta_fields[$i]['meta_value'] = wp_unslash($meta_fields[$i]['meta_value']);
            $meta_fields[$i]['meta_value'] = sanitize_meta( $meta_fields[$i]['meta_key'], $meta_fields[$i]['meta_value'], $meta_type );

            /*
            * Filter whether to update metadata of a specific type.
            *
            * For more details, see wp-includes/meta.php
            */
            $check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_fields[$i]['meta_key'], $meta_fields[$i]['meta_value'], null );
            if ( null !== $check ) {
                unset( $meta_fields[$i] );
                continue;
            }
            
            // Compare existing value to new value if no prev value given and the key exists only once.
            $old_value = get_metadata($meta_type, $object_id, $meta_fields[$i]['meta_key']);
            if ( count($old_value) >= 1 ) {
                if ( $old_value[0] == $meta_fields[$i]['meta_value'] ) {
                    unset( $meta_fields[$i] );
                }                        
            }
        }

        if ( ! $meta_fields ) {
            return false;
        }

        // Remove empty keys from $meta_fields
        $meta_fields = array_values( $meta_fields );
      
        $where_keys = '';
        foreach ( $meta_fields as $meta_field ) {
            $where_keys .= "'" . $meta_field['meta_key'] . "', ";
        }
        $where_keys = rtrim( $where_keys, ', ' );

        // Get array of IDs for each meta_field  
        $meta_ids = $wpdb->get_results( "SELECT $id_column FROM $table WHERE meta_key IN ($where_keys) AND $column = $object_id" );
                
        $add_fields = array();
        $i = count( $meta_fields );

        // Loop through meta_fields
        while ( $i > 0 ) {
            $i--;
            $found = false;

            // Loop through meta_ids
            foreach ( $meta_ids as $meta_id ) {
                if ( $meta_id->meta_key === $meta_fields[$i]['meta_key'] ) {
                    $meta_fields[$i]['meta_id'] = $meta_id->meta_id;
                    $found = true;
                }
            }

            if ( ! $found ) {
                $add_fields[] = $meta_fields[$i];
                unset( $meta_fields[$i] );
            }
        }

        // Send meta_fields not found in db to be inserted
        if ( $add_fields ) {
            $this->add_metadata_batch( $meta_type, $object_id, $add_fields );
        }

        $set_values = '`meta_value` = CASE `meta_key` ';
        $where_keys = '`meta_key` IN (';

        // Loop through meta_fields as reference
        foreach ( $meta_fields as &$meta_field ) {
            $meta_field['_meta_value'] = $meta_field['meta_value'];
            $meta_field['meta_value'] = maybe_serialize($meta_field['meta_value']);

            // Build SQL statement
            $set_values .= "WHEN '" . $meta_field['meta_key'] . "' ";
            $set_values .= "THEN '" . $wpdb->_real_escape( $meta_field['meta_value'] ) . "' ";
            $where_keys .= "'" . $meta_field['meta_key'] . "', ";

            /*
            * Fires immediately before updating a post's metadata.
            *
            * For more details, see wp-includes/meta.php
            */
            do_action( 'update_postmeta', $meta_field['meta_id'], $object_id, $meta_field['meta_key'], $meta_field['meta_value'] );
        };

        $set_values .= 'END';
        $where_keys = rtrim( $where_keys, ', ' ) . ')';

        $result = false;

        // Update database
        if ( $meta_fields ) {
            $result = $wpdb->query( "UPDATE `$table` SET $set_values WHERE $where_keys AND `post_id` = $object_id" );
        }

        if ( ! $result ) {
            return false;
        }

        wp_cache_delete( $object_id, $meta_type . '_meta' );

        foreach ( $meta_fields as $meta_field ) {
            /*
            * Fires immediately after updating a post's metadata.
            *
            * For more details, see wp-includes/meta.php
            */
            do_action( 'updated_postmeta', $meta_field['meta_id'], $object_id, $meta_field['meta_key'], $meta_field['meta_value'] );
        }

        return true;
    }


    /*
    * update_value_batch
    *
    * Updates repeater values all in one batch. Called from update_value.
    *
    * @type     action
    * @since    1.0
    * @date     28/7/16
    *
    * @param    array   $value: the repeater value to be updated
    * @param    int     $post_id: the post ID to save the values to
    * @param    array   $field: the repeater field array to be updated
    */        
    function update_value_batch( $value, $post_id, $field ) {

        // Reset update_fields for each post update
        $this->update_fields = array();

        // Get all repeater fields
        $this->get_update_fields( $value, $post_id, $field );

        // Update metadata
        $result = $this->update_metadata_batch( 'post', $post_id, $this->update_fields );

        // Loop through repeater fields
        foreach ( $this->update_fields as $field ) {
            wp_cache_set( 'load_value/post_id=' . $post_id . '/name=' . $field['meta_key'], $field['meta_value'], 'acf' );
        }       
    }

    /*
    * update_value
    *
    * Replaces update_value filter from ACF Repeater. The core functionality remains the same except
    * that repeater fields are rerouted to the update_value_batch action.
    *
    * @type     action
    * @since    1.0
    * @date     28/7/16
    *
    * @param    mixed   $value: the value to be updated
    * @param    int     $post_id: the post ID to save the value to
    * @param    array   $field: the field array to be updated
    */ 
    function update_value( $value, $post_id, $field ) {
        $value = stripslashes_deep( $value );

        // Reroute repeater fields to update_value_batch        
        if ( 'repeater' == $field['type'] ) {
            do_action( 'acf/update_value_batch', $value, $post_id, $field );
            return;
        }

        // Apply filters
        foreach ( array('key', 'name', 'type') as $key ) {
            // Run filters
            $value = apply_filters( 'acf/update_value/' . $key . '=' . $field[$key], $value, $post_id, $field );
        }
        
        // If $post_id is a string, then it is used in the everything fields and can be found in the options table
        if ( is_numeric( $post_id ) ) {
            // Allow ACF to save to revision!
            update_metadata( 'post', $post_id, $field['name'], $value );
            update_metadata( 'post', $post_id, '_' . $field['name'], $field['key'] );
        } elseif ( false !== strpos( $post_id, 'user_' ) ) {
            $user_id = str_replace( 'user_', '', $post_id );
            update_metadata( 'user', $user_id, $field['name'], $value );
            update_metadata( 'user', $user_id, '_' . $field['name'], $field['key'] );
        } else {
            // For some reason, update_option does not use stripslashes_deep.
            // update_metadata -> http://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/meta.php#L82: line 101 (does use stripslashes_deep)
            // update_option -> http://core.trac.wordpress.org/browser/tags/3.5.1/wp-includes/option.php#L0: line 215 (does not use stripslashes_deep)
            $value = stripslashes_deep( $value );

            $this->update_option( $post_id . '_' . $field['name'], $value );
            $this->update_option( '_' . $post_id . '_' . $field['name'], $field['key'] );
        }
        
        // Update the cache
        wp_cache_set( 'load_value/post_id=' . $post_id . '/name=' . $field['name'], $value, 'acf' );
    }
}

new acf_field_repeater_batch_update();

?>