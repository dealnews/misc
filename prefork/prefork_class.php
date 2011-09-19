#!/usr/bin/php
<?php

/**
 * This script will fork a process using a class for tracking children and
 * forking new work off. It allows a little more control since the user written
 * class knows about the children moreso than prefork.php
 */

//  class ExampleForkedClass {
//
//      /**
//       * Number of children
//       */
//      public $children;
//
//      /**
//       * Determines if a process is a child or parent
//       */
//      public $is_parent = true;
//
//      /**
//       * Constructor
//       *
//       * @param   int     $children   Number of children to start up
//       * @return  void
//       *
//       */
//      public function __construct($children) {
//      }
//
//      /**
//       * destructor
//       */
//      public function __destruct() {
//      }
//
//      /**
//       * Called in the parent scope before each child is forked
//       */
//      public function before_fork() {
//      }
//
//      /**
//       * Called in the parent scope before each child is forked
//       *
//       * @param   int     $pid    The PID of the new child
//       * @return  void
//       *
//       */
//      public function after_fork($pid) {
//      }
//
//
//      /**
//       * Called in the parent scope before each child dies
//       *
//       * @param   int     $pid    The PID of the new child
//       * @return  void
//       *
//       */
//      public function after_death($pid) {
//      }
//
//      /**
//       * Called in the child scope after it is forked. Returning from this function
//       * indicates a desire to have the child die.
//       *
//       * Any number of arguments can be passed to the child from the command line
//       *
//       * @return  void
//       *
//       */
//      public function fork() {
//      }
//
//  }


/**
 * Start option checking
 */
$opts = getopt("c:f:hoqz:a:n:");

if(isset($opts["h"])){
    prefork_usage();
}

if(empty($opts["f"]) || empty($opts["c"])){
    prefork_usage("You must provide a file and a class to be used.");
}

$file = $opts["f"];

if(!file_exists($file) || !is_readable($file)){
    prefork_usage("$file does not exist or is not readable.");
} else {
    include_once($file);
}

$fork_class = $opts["c"];
if(!class_exists($fork_class)){
    prefork_usage("class $fork_class does not exist in $file.");
}

$func_params = array();
if(isset($opts["a"]) && !empty($opts["a"])){
    $func_params = explode(",", $opts["a"]);
}

if(isset($opts["z"])){
    $snooze = (int)$opts["z"];
    if($snooze<1){
        prefork_usage("-z must be numeric and greater than 0.");
    }
}

if(isset($opts["n"])){
    $children = (int)$opts["n"];
} else {
    $children = null;
}

$onepass = isset($opts["o"]);
$verbose = !isset($opts["q"]);

/**
 * End option checking
 */



/**
 * MAIN PROCESS LOOP
 */

// start up the children
global $PREFORK;
prefork_startup($children);

// setup signal handlers
declare(ticks = 1);
pcntl_signal(SIGTERM, "prefork_sig_handler");
pcntl_signal(SIGHUP,  "prefork_sig_handler");

// loop and monitor children
while(count($pid_arr)){
    foreach($pid_arr as $key => $pid){
        $exited = pcntl_waitpid( $pid, $status, WNOHANG );
        if($exited) {
            unset($pid_arr[$key]);
            prefork_log("Child $pid exited");
            if(method_exists($PREFORK, "after_death")){
                $PREFORK->after_death($pid);
            }
        }
    }

    if(!$onepass && count($pid_arr)<$PREFORK->children){
        prefork_start_child();
    }

    // php will eat up your cpu
    // if you don't have this
    usleep(500000);
}


prefork_log("Shutting down");
exit();






/*************** FUNCTIONS ***************/


/**
 * Start children
 */

function prefork_startup($children=null) {

    global $PREFORK, $fork_class;

    prefork_log("Creating $fork_class object");
    $PREFORK = new $fork_class($children);

    for($x=1;$x<=$PREFORK->children;$x++){

        prefork_start_child();

        // PHP will lock if you
        // start too many to fast
        usleep(500);

    }

    prefork_log("$PREFORK->children children started");

}

/**
 * Starts a child process and records its pid
 */

function prefork_start_child() {

    global $pid_arr, $snooze, $func_params, $PREFORK;

    $cnt = count($pid_arr)+1;

    prefork_log("Starting child $cnt");

    if(method_exists($PREFORK, "before_fork")){
        $PREFORK->before_fork();
    }

    $pid = pcntl_fork();
    if ($pid == -1){
        prefork_log("could not fork");
        prefork_stop_children();
    } else {
        if ($pid) {
            // parent
            $pid_arr[] = $pid;

            if(method_exists($PREFORK, "after_fork")){
                $PREFORK->after_fork($pid);
            }

        } else {
            //child
            if(!empty($snooze)){
                sleep($snooze);
            }
            $PREFORK->is_parent = false;
            if (is_array($func_params) && count($func_params)) {
                call_user_func_array(array($PREFORK, "fork"), $func_params);
            } else {
                $PREFORK->fork();
            }
            exit();
        }
    }

}

/**
 * Shutsdown children
 */

function prefork_stop_children() {
    global $pid_arr;

    prefork_log("Stopping children");

    foreach($pid_arr as $key => $pid){
        prefork_log("killing $pid");
        posix_kill($pid, SIGKILL);
    }
}

/**
 * Signal function
 */

// signal handler function
function prefork_sig_handler($signo) {

    global $PREFORK;

    switch ($signo) {
        case SIGTERM:
            prefork_log("SIGTERM caught");
            $PREFORK->children = 0;
            prefork_stop_children();
            break;
        case SIGHUP:
            prefork_log("SIGHUP caught");
            prefork_stop_children();
            prefork_startup();
            break;
        default:
        // handle all other signals
    }

}

/**
 * Logging function
 */
function prefork_log($log) {

    global $verbose;

    if($verbose) {
        echo "$log\n";
        flush();
    }
}

/**
 * Usage function
 */

function prefork_usage($error=""){
    if($error){
        echo "Error: $error\n";
    } else {
        echo "This script allows you to fork off children using a specially crafted class.";
    }
    echo "USAGE: ".__FILE__." [-h] -f FILE -c CLASS [-a PARAM1,PARAM2,...] [-z SNOOZE] [-qo]\n";
    echo "OPTIONS:\n";
    echo "  -c CLASS  The name of the class to be used for forking.\n";
    echo "  -f FILE   The PHP file that defines the class [-c] .\n";
    echo "  -a PARAM  Function parameters to be passed to the fork() method of the class.\n            Multiple parameters can be separated by a comma.\n";
    echo "  -o        If set, new children will not be started as children die off.\n            This is useful for one time processing uses.\n";
    echo "  -z        Seconds for each child to sleep before running its function.\n            (Forking can be taxing and this can help performance)\n";
    echo "  -q        Be quiet.\n";
    echo "  -h        Show this help.\n";
    echo "\n";
    exit();
}

?>
