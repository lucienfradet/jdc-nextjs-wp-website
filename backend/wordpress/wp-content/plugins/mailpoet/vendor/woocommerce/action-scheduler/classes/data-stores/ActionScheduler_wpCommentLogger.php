<?php
if (!defined('ABSPATH')) exit;
class ActionScheduler_wpCommentLogger extends ActionScheduler_Logger {
 const AGENT = 'ActionScheduler';
 const TYPE = 'action_log';
 public function log( $action_id, $message, ?DateTime $date = null ) {
 if ( empty( $date ) ) {
 $date = as_get_datetime_object();
 } else {
 $date = as_get_datetime_object( clone $date );
 }
 $comment_id = $this->create_wp_comment( $action_id, $message, $date );
 return $comment_id;
 }
 protected function create_wp_comment( $action_id, $message, DateTime $date ) {
 $comment_date_gmt = $date->format( 'Y-m-d H:i:s' );
 ActionScheduler_TimezoneHelper::set_local_timezone( $date );
 $comment_data = array(
 'comment_post_ID' => $action_id,
 'comment_date' => $date->format( 'Y-m-d H:i:s' ),
 'comment_date_gmt' => $comment_date_gmt,
 'comment_author' => self::AGENT,
 'comment_content' => $message,
 'comment_agent' => self::AGENT,
 'comment_type' => self::TYPE,
 );
 return wp_insert_comment( $comment_data );
 }
 public function get_entry( $entry_id ) {
 $comment = $this->get_comment( $entry_id );
 if ( empty( $comment ) || self::TYPE !== $comment->comment_type ) {
 return new ActionScheduler_NullLogEntry();
 }
 $date = as_get_datetime_object( $comment->comment_date_gmt );
 ActionScheduler_TimezoneHelper::set_local_timezone( $date );
 return new ActionScheduler_LogEntry( $comment->comment_post_ID, $comment->comment_content, $date );
 }
 public function get_logs( $action_id ) {
 $status = 'all';
 $logs = array();
 if ( get_post_status( $action_id ) === 'trash' ) {
 $status = 'post-trashed';
 }
 $comments = get_comments(
 array(
 'post_id' => $action_id,
 'orderby' => 'comment_date_gmt',
 'order' => 'ASC',
 'type' => self::TYPE,
 'status' => $status,
 )
 );
 foreach ( $comments as $c ) {
 $entry = $this->get_entry( $c );
 if ( ! empty( $entry ) ) {
 $logs[] = $entry;
 }
 }
 return $logs;
 }
 protected function get_comment( $comment_id ) {
 return get_comment( $comment_id );
 }
 public function filter_comment_queries( $query ) {
 foreach ( array( 'ID', 'parent', 'post_author', 'post_name', 'post_parent', 'type', 'post_type', 'post_id', 'post_ID' ) as $key ) {
 if ( ! empty( $query->query_vars[ $key ] ) ) {
 return; // don't slow down queries that wouldn't include action_log comments anyway.
 }
 }
 $query->query_vars['action_log_filter'] = true;
 add_filter( 'comments_clauses', array( $this, 'filter_comment_query_clauses' ), 10, 2 );
 }
 public function filter_comment_query_clauses( $clauses, $query ) {
 if ( ! empty( $query->query_vars['action_log_filter'] ) ) {
 $clauses['where'] .= $this->get_where_clause();
 }
 return $clauses;
 }
 public function filter_comment_feed( $where, $query ) {
 if ( is_comment_feed() ) {
 $where .= $this->get_where_clause();
 }
 return $where;
 }
 protected function get_where_clause() {
 global $wpdb;
 return sprintf( " AND {$wpdb->comments}.comment_type != '%s'", self::TYPE );
 }
 public function filter_comment_count( $stats, $post_id ) {
 global $wpdb;
 if ( 0 === $post_id ) {
 $stats = $this->get_comment_count();
 }
 return $stats;
 }
 protected function get_comment_count() {
 global $wpdb;
 $stats = get_transient( 'as_comment_count' );
 if ( ! $stats ) {
 $stats = array();
 $count = $wpdb->get_results( "SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} WHERE comment_type NOT IN('order_note','action_log') GROUP BY comment_approved", ARRAY_A );
 $total = 0;
 $stats = array();
 $approved = array(
 '0' => 'moderated',
 '1' => 'approved',
 'spam' => 'spam',
 'trash' => 'trash',
 'post-trashed' => 'post-trashed',
 );
 foreach ( (array) $count as $row ) {
 // Don't count post-trashed toward totals.
 if ( 'post-trashed' !== $row['comment_approved'] && 'trash' !== $row['comment_approved'] ) {
 $total += $row['num_comments'];
 }
 if ( isset( $approved[ $row['comment_approved'] ] ) ) {
 $stats[ $approved[ $row['comment_approved'] ] ] = $row['num_comments'];
 }
 }
 $stats['total_comments'] = $total;
 $stats['all'] = $total;
 foreach ( $approved as $key ) {
 if ( empty( $stats[ $key ] ) ) {
 $stats[ $key ] = 0;
 }
 }
 $stats = (object) $stats;
 set_transient( 'as_comment_count', $stats );
 }
 return $stats;
 }
 public function delete_comment_count_cache() {
 delete_transient( 'as_comment_count' );
 }
 public function init() {
 add_action( 'action_scheduler_before_process_queue', array( $this, 'disable_comment_counting' ), 10, 0 );
 add_action( 'action_scheduler_after_process_queue', array( $this, 'enable_comment_counting' ), 10, 0 );
 parent::init();
 add_action( 'pre_get_comments', array( $this, 'filter_comment_queries' ), 10, 1 );
 add_action( 'wp_count_comments', array( $this, 'filter_comment_count' ), 20, 2 ); // run after WC_Comments::wp_count_comments() to make sure we exclude order notes and action logs.
 add_action( 'comment_feed_where', array( $this, 'filter_comment_feed' ), 10, 2 );
 // Delete comments count cache whenever there is a new comment or a comment status changes.
 add_action( 'wp_insert_comment', array( $this, 'delete_comment_count_cache' ) );
 add_action( 'wp_set_comment_status', array( $this, 'delete_comment_count_cache' ) );
 }
 public function disable_comment_counting() {
 wp_defer_comment_counting( true );
 }
 public function enable_comment_counting() {
 wp_defer_comment_counting( false );
 }
}
