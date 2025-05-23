<?php
if (!defined('ABSPATH')) exit;
use Action_Scheduler\WP_CLI\Migration_Command;
use Action_Scheduler\Migration\Controller;
abstract class ActionScheduler {
 private static $plugin_file = '';
 private static $factory = null;
 private static $data_store_initialized = false;
 public static function factory() {
 if ( ! isset( self::$factory ) ) {
 self::$factory = new ActionScheduler_ActionFactory();
 }
 return self::$factory;
 }
 public static function store() {
 return ActionScheduler_Store::instance();
 }
 public static function lock() {
 return ActionScheduler_Lock::instance();
 }
 public static function logger() {
 return ActionScheduler_Logger::instance();
 }
 public static function runner() {
 return ActionScheduler_QueueRunner::instance();
 }
 public static function admin_view() {
 return ActionScheduler_AdminView::instance();
 }
 public static function plugin_path( $path ) {
 $base = dirname( self::$plugin_file );
 if ( $path ) {
 return trailingslashit( $base ) . $path;
 } else {
 return untrailingslashit( $base );
 }
 }
 public static function plugin_url( $path ) {
 return plugins_url( $path, self::$plugin_file );
 }
 public static function autoload( $class ) {
 $d = DIRECTORY_SEPARATOR;
 $classes_dir = self::plugin_path( 'classes' . $d );
 $separator = strrpos( $class, '\\' );
 if ( false !== $separator ) {
 if ( 0 !== strpos( $class, 'Action_Scheduler' ) ) {
 return;
 }
 $class = substr( $class, $separator + 1 );
 }
 if ( 'Deprecated' === substr( $class, -10 ) ) {
 $dir = self::plugin_path( 'deprecated' . $d );
 } elseif ( self::is_class_abstract( $class ) ) {
 $dir = $classes_dir . 'abstracts' . $d;
 } elseif ( self::is_class_migration( $class ) ) {
 $dir = $classes_dir . 'migration' . $d;
 } elseif ( 'Schedule' === substr( $class, -8 ) ) {
 $dir = $classes_dir . 'schedules' . $d;
 } elseif ( 'Action' === substr( $class, -6 ) ) {
 $dir = $classes_dir . 'actions' . $d;
 } elseif ( 'Schema' === substr( $class, -6 ) ) {
 $dir = $classes_dir . 'schema' . $d;
 } elseif ( strpos( $class, 'ActionScheduler' ) === 0 ) {
 $segments = explode( '_', $class );
 $type = isset( $segments[1] ) ? $segments[1] : '';
 switch ( $type ) {
 case 'WPCLI':
 $dir = $classes_dir . 'WP_CLI' . $d;
 break;
 case 'DBLogger':
 case 'DBStore':
 case 'HybridStore':
 case 'wpPostStore':
 case 'wpCommentLogger':
 $dir = $classes_dir . 'data-stores' . $d;
 break;
 default:
 $dir = $classes_dir;
 break;
 }
 } elseif ( self::is_class_cli( $class ) ) {
 $dir = $classes_dir . 'WP_CLI' . $d;
 } elseif ( strpos( $class, 'CronExpression' ) === 0 ) {
 $dir = self::plugin_path( 'lib' . $d . 'cron-expression' . $d );
 } elseif ( strpos( $class, 'WP_Async_Request' ) === 0 ) {
 $dir = self::plugin_path( 'lib' . $d );
 } else {
 return;
 }
 if ( file_exists( $dir . "{$class}.php" ) ) {
 include $dir . "{$class}.php";
 return;
 }
 }
 public static function init( $plugin_file ) {
 self::$plugin_file = $plugin_file;
 spl_autoload_register( array( __CLASS__, 'autoload' ) );
 do_action( 'action_scheduler_pre_init' );
 require_once self::plugin_path( 'functions.php' );
 ActionScheduler_DataController::init();
 $store = self::store();
 $logger = self::logger();
 $runner = self::runner();
 $admin_view = self::admin_view();
 // Ensure initialization on plugin activation.
 if ( ! did_action( 'init' ) ) {
 // phpcs:ignore Squiz.PHP.CommentedOutCode
 add_action( 'init', array( $admin_view, 'init' ), 0, 0 ); // run before $store::init().
 add_action( 'init', array( $store, 'init' ), 1, 0 );
 add_action( 'init', array( $logger, 'init' ), 1, 0 );
 add_action( 'init', array( $runner, 'init' ), 1, 0 );
 add_action(
 'init',
 function () {
 self::$data_store_initialized = true;
 do_action( 'action_scheduler_init' );
 },
 1
 );
 } else {
 $admin_view->init();
 $store->init();
 $logger->init();
 $runner->init();
 self::$data_store_initialized = true;
 do_action( 'action_scheduler_init' );
 }
 if ( apply_filters( 'action_scheduler_load_deprecated_functions', true ) ) {
 require_once self::plugin_path( 'deprecated/functions.php' );
 }
 if ( defined( 'WP_CLI' ) && WP_CLI ) {
 WP_CLI::add_command( 'action-scheduler', 'ActionScheduler_WPCLI_Scheduler_command' );
 WP_CLI::add_command( 'action-scheduler', 'ActionScheduler_WPCLI_Clean_Command' );
 WP_CLI::add_command( 'action-scheduler action', '\Action_Scheduler\WP_CLI\Action_Command' );
 WP_CLI::add_command( 'action-scheduler', '\Action_Scheduler\WP_CLI\System_Command' );
 if ( ! ActionScheduler_DataController::is_migration_complete() && Controller::instance()->allow_migration() ) {
 $command = new Migration_Command();
 $command->register();
 }
 }
 if ( is_a( $logger, 'ActionScheduler_DBLogger' ) && ActionScheduler_DataController::is_migration_complete() && ActionScheduler_WPCommentCleaner::has_logs() ) {
 ActionScheduler_WPCommentCleaner::init();
 }
 add_action( 'action_scheduler/migration_complete', 'ActionScheduler_WPCommentCleaner::maybe_schedule_cleanup' );
 }
 public static function is_initialized( $function_name = null ) {
 if ( ! self::$data_store_initialized && ! empty( $function_name ) ) {
 $message = sprintf(
 __( '%s() was called before the Action Scheduler data store was initialized', 'action-scheduler' ),
 esc_attr( $function_name )
 );
 _doing_it_wrong( esc_html( $function_name ), esc_html( $message ), '3.1.6' );
 }
 return self::$data_store_initialized;
 }
 protected static function is_class_abstract( $class ) {
 static $abstracts = array(
 'ActionScheduler' => true,
 'ActionScheduler_Abstract_ListTable' => true,
 'ActionScheduler_Abstract_QueueRunner' => true,
 'ActionScheduler_Abstract_Schedule' => true,
 'ActionScheduler_Abstract_RecurringSchedule' => true,
 'ActionScheduler_Lock' => true,
 'ActionScheduler_Logger' => true,
 'ActionScheduler_Abstract_Schema' => true,
 'ActionScheduler_Store' => true,
 'ActionScheduler_TimezoneHelper' => true,
 'ActionScheduler_WPCLI_Command' => true,
 );
 return isset( $abstracts[ $class ] ) && $abstracts[ $class ];
 }
 protected static function is_class_migration( $class ) {
 static $migration_segments = array(
 'ActionMigrator' => true,
 'BatchFetcher' => true,
 'DBStoreMigrator' => true,
 'DryRun' => true,
 'LogMigrator' => true,
 'Config' => true,
 'Controller' => true,
 'Runner' => true,
 'Scheduler' => true,
 );
 $segments = explode( '_', $class );
 $segment = isset( $segments[1] ) ? $segments[1] : $class;
 return isset( $migration_segments[ $segment ] ) && $migration_segments[ $segment ];
 }
 protected static function is_class_cli( $class ) {
 static $cli_segments = array(
 'QueueRunner' => true,
 'Command' => true,
 'ProgressBar' => true,
 '\Action_Scheduler\WP_CLI\Action_Command' => true,
 '\Action_Scheduler\WP_CLI\System_Command' => true,
 );
 $segments = explode( '_', $class );
 $segment = isset( $segments[1] ) ? $segments[1] : $class;
 return isset( $cli_segments[ $segment ] ) && $cli_segments[ $segment ];
 }
 final public function __clone() {
 trigger_error( 'Singleton. No cloning allowed!', E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
 }
 final public function __wakeup() {
 trigger_error( 'Singleton. No serialization allowed!', E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
 }
 final private function __construct() {}
 public static function get_datetime_object( $when = null, $timezone = 'UTC' ) {
 _deprecated_function( __METHOD__, '2.0', 'wcs_add_months()' );
 return as_get_datetime_object( $when, $timezone );
 }
 public static function check_shutdown_hook( $function_name ) {
 _deprecated_function( __FUNCTION__, '3.1.6' );
 }
}
