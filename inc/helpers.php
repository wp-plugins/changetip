<?php

function changetip_contains_callout( $text ) {
    return preg_match( '/@changetip/i', $text ) === 1;
}

function changetip_mark_comment_as_tip( $comment ) {
    $comment_id = $comment->comment_ID;
    $key = md5(microtime().rand());

    update_comment_meta( $comment_id, 'changetip_is_tip', 1 );
    update_comment_meta( $comment_id, 'changetip_comment_id', $key );
}