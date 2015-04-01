<?php

function changetip_submit_autoreply( $message, $post_id, $comment_parent = 0 ) {
    $options = get_option( 'changetip' );
    if ( !isset( $options['changetip_autoreply'] ) ) {
        return NULL;
    }

    $time = current_time('mysql');

    $data = array(
        'comment_post_ID' => $post_id,
        'comment_author' => '@ChangeTip',
        'comment_author_email' => 'admin@changetip.com',
        'comment_author_url' => 'https://changetip.com',
        'comment_content' => $message,
        'comment_parent' => $comment_parent,
        'comment_author_IP' => '78.108.177.219',
        'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
        'comment_date' => $time,
        'comment_approved' => 0
    );

    $approved = check_comment(
        $data['comment_author'],
        $data['comment_author_email'],
        $data['comment_author_url'],
        $data['comment_content'],
        $data['comment_author_IP'],
        $data['comment_agent'],
        'user submitted comment'
    );

    if( $approved ) {
        if( $comment_parent == 0 ) {
            $data['comment_approved'] = 1;
        } else {
            $comment_parent_obj = get_comment( $comment_parent );
            $data['comment_approved'] = $comment_parent_obj->comment_approved;
        }
    }

    //slip link in past filters. weee....
    $data['comment_content'] = $data['comment_content'] . "<div class='changetip-more-info-link'><a href='http://tip.me/how-to-send-tips/'>More info</a></div>";

    wp_insert_comment($data);
}

function changetip_receive_message_callback() {
    global $changetip;

    $response = changetip_receive_message_validate();

    if( !$changetip->autoreply_is_on() || isset( $response['error'] ) ) {
        $response['message'] = 'Changetip autoreply is off.';
        echo json_encode( $response );
        die();
    }

    $changetip_type = $_POST['changetipType'];
    $message = $_POST['changetipType'];

    if( $changetip_type == 'button' ) {
        $response = changetip_receive_message_callback_button( $response, $message );
    } else {
        $response = changetip_receive_message_callback_comment( $response, $message );
    }
    echo json_encode( $response );
    die();
}

function changetip_str_contains_link( $content ) {
    return preg_match( '/\b(?:(?:https?|ftp|file):\/\/|www\.|ftp\.)[-A-Z0-9+&@#\/%=~_|$?!:,.]*[A-Z0-9+&@#\/%=~_|$]/i', $content );
}

function changetip_valid_message_sender( $sender ) {
    return $sender !== NULL && strlen( $sender ) < 30 && !changetip_str_contains_link( $sender );
}

function changetip_valid_message_amount( $amount ) {
    return $amount !== NULL && strlen( $amount ) < 50 && !changetip_str_contains_link( $amount );
}

function changetip_receive_message_validate() {
    $response = array();
    $response['close'] = TRUE;
    if( !isset( $_POST['changetipMessageSender'] )
        || !isset( $_POST['changetipMessageAmount'] )
        || !isset( $_POST['changetipType'] ) ) {
        $response['error'] = 'Changetip autoreply is off.';
    }

    if( !changetip_valid_message_amount( $_POST['changetipMessageAmount'] ) ) {
         $response['error'] = 'Changetip autoreply is off.';
    }

    if( !changetip_valid_message_sender( $_POST['changetipMessageSender'] ) ) {
         $response['error'] = 'Changetip autoreply is off.';
    }

    return $response;
}

function changetip_receive_message_callback_button( $response, $message ) {
    if( !$message
        || !isset( $_POST['changetipType'] ) ) {
        $response['error'] = 'Changetip autoreply is off.';
        return $response;
    }

    $messageSender = $_POST['changetipMessageSender'];
    $messageAmount = $_POST['changetipMessageAmount'];
    $message = $messageSender . ' just left a Bitcoin tip worth ' . $messageAmount . '.';

    $post_id = $_POST['changetipPostId'];

    changetip_submit_autoreply( $message, $post_id );
    $response['response'] = 200;
    $response['close'] = FALSE;
    return $response;
}

function changetip_receive_message_callback_comment( $response, $message ) {
    if( !$message
        || !isset( $_POST['changetipApproveId'] )
        || !isset( $_POST['changetipCommentKey'] )
        || !isset( $_POST['changetipType'] ) ) {
        $response['error'] = 'Changetip autoreply is off.';
        return $response;
    }

    //TODO: get username and amount,
    //convert into a message.
    $comment_id = $_POST['changetipApproveId'];
    $comment_key = $_POST['changetipCommentKey'];

    //verify comment exists
    $comment = get_comment( $comment_id );
    if( !$comment ) {
        $response['error'] = 'Comment not found.';
        return $response;
    }

    $messageAmount = $_POST['changetipMessageAmount'];
    $message = $comment->comment_author . ' just left a Bitcoin tip worth ' . $messageAmount . '.';

    //verify comment and key
    $real_key = get_comment_meta( $comment_id, 'changetip_comment_id', TRUE );
    if( $comment_key != $real_key ) {
        $response['error'] = 'Invalid comment key.';
        return $response;
    }

    //changetip_submit_autoreply( $message, $comment->comment_post_ID );
    changetip_submit_autoreply( $message, $comment->comment_post_ID, $comment_id );
    $response['response'] = 200;
    $response['close'] = FALSE;
    return $response;
}

add_action( 'wp_ajax_changetip_receive_message_callback', 'changetip_receive_message_callback' );
add_action( 'wp_ajax_nopriv_changetip_receive_message_callback', 'changetip_receive_message_callback' );