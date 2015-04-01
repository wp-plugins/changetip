<?php

function changetip_check_for_updates( $changetip ) {
    $version = $changetip->get_version();

    if( $version < 1.1 ) {
        //allow for multiple usernames and uuids
        $username = $changetip->get_option( 'changetip_username' );
        if( is_string( $username ) ) {
            $uuid = $changetip->get_option( 'changetip_uuid' );
            if( $uuid ) {
                $users = array();
                $users[] = array(
                    'name' => $username,
                    'uuid' => $uuid
                );
                $changetip->update_option( 'changetip_username', json_encode( $users ) );
                $changetip->update_version( 1.1 );
            }
        }
    }
}