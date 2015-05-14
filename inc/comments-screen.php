<?php

function changetip_filter_current_screen( $screen )
{
    if ( $screen->id != 'edit-comments' ) {
        return;
    }

    //get total count before adding filter
    //TODO: hacky, fix?
    global $changetip_tips_count;
    $changetip_tips = get_comments( array(
        'meta_query' => array(
            array(
                'key'     => 'changetip_is_tip',
                'value'   => '1'
            )
        ),
        'count' => FALSE // TRUE => breaks :/
    ) );
    $changetip_tips_count = count( $changetip_tips );
    //END TODO

    if( isset( $_GET['changetip_tips'] ) ) {
        add_action( 'pre_get_comments', 'changetip_hook_pre_get_comments', 10, 1 );
    }
    add_filter( 'comment_status_links', 'changetip_filter_comment_status_links' );
}

function changetip_hook_pre_get_comments( $query )
{
    $meta_query_args = array(
        array(
            'key'     => 'changetip_is_tip',
            'value'   => '1'
        )
    );
    $query->meta_query = new WP_Meta_Query( $meta_query_args );
}

function changetip_filter_comment_status_links( $links )
{
    global $changetip_tips_count;

    $tips_href = "edit-comments.php?comment_status=all&changetip_tips=1";
    $tips_count_span = "<span class='count'>(<span class='tip_count'>$changetip_tips_count</span>)</span>";

    if( isset( $_GET['changetip_tips'] ) )
    {
        $links['all'] = '<a href="edit-comments.php?comment_status=all">All</a>';
        $links['changetip_tips'] = "<a href='$tips_href' class='current'>Tips $tips_count_span</a>";
    }
    else
    {
        $links['changetip_tips'] = "<a href='$tips_href'>Tips $tips_count_span</a>";
    }
    return $links;
}

add_action( 'current_screen', 'changetip_filter_current_screen', 10, 2 );
