#!/usr/bin/php
<?php

/**
 * This script forks using functions. It is a very basic system for forking work.
 * For a more interactive system, prefork_class.php is reccommended.
 */

/**
 * Start option checking
 */
$opts = getopt("f:n:a:p:s:e:z:qoh");

if(isset($opts["h"])){
    prefork_usage();
}

$children = (int)$opts["n"];
if($children<1){
    prefork_usage("-n must be numeric and greater than 0.");
}

if(empty($opts["f"]) || empty($opts["n"]) || empty($opts["p"])){
    prefork_usage("You must provide a file, a number of children greater than 0 and a function to be forked.");
}

$file = $opts["f"];

if(!file_exists($file) || !is_readable($file)){
    prefork_usage("$file does not exist or is not readable.");
} else {
    include_once($file);
}

$fork_function = $opts["p"];
if(!function_exists($fork_function)){
    prefork_usage("function $fork_function does not exist in $file.");
}

if(isset($opts["s"])){
    $setup_function = $opts["s"];
    if(!function_exists($setup_function)){
        prefork_usage("function $setup_function does not exist in $file.");
    }
}

if(isset($opts["a"])){
    $func_params = explode(",", $opts["a"]);
} else {
    $func_params = array();
}

if(isset($opts["e"])){
    $shutdown_function = $opts["e"];
    if(!function_exists($shutdown_function)){
        prefork_usage("function $shutdown_function does not exist in $file.");
    }
}

if(isset($opts["z"])){
    $snooze = (int)$opts["z"];
    if($snooze<1){
        prefork_usage("-z must be numeric and greater than 0.");
    }
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
prefork_startup();

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
        }
    }

    if(!$onepass && count($pid_arr)<$children){
        prefork_start_child();
    }

    // php will eat up your cpu
    // if you don't have this
    usleep(500000);
}


if(isset($shutdown_function)){
    prefork_log("Running shutdown function $shutdown_function");
    $shutdown_function();
}

prefork_log("Shutting down");
exit();






/*************** FUNCTIONS ***************/


/**
 * Start children
 */

function prefork_startup() {

    global $children, $setup_function;

    if(!empty($setup_function)){
        prefork_log("Running setup function $setup_function");
        $setup_function();
    }

    for($x=1;$x<=$children;$x++){

        prefork_start_child();

        // PHP will lock if you
        // start too many to fast
        usleep(500);

    }

    prefork_log("$children children started");

}

/**
 * Starts a child process and records its pid
 */

function prefork_start_child() {

    global $pid_arr, $snooze, $fork_function, $func_params;

    $cnt = count($pid_arr)+1;

    prefork_log("Starting child $cnt");

    $pid = pcntl_fork();
    if ($pid == -1){
        die('could not fork');
    } else {
        if ($pid) {
            // parent
            $pid_arr[] = $pid;
        } else {
            //child
            if(!empty($snooze)){
                sleep($snooze);
            }
            call_user_func_array($fork_function, $func_params);
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

    global $children;

    switch ($signo) {
        case SIGTERM:
            prefork_log("SIGTERM caught");
            $children = 0;
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
        echo "This script allows you to fork off children to perform a function.\n\n";
    }
    echo "usage: ".__FILE__." -f FILE -n CHILDREN -p FORK-FUNCTION [-s SETUP-FUNCTION] [-e END-FUNCTION] [-z SNOOZE] [-v]\n";
    echo "usage: ".__FILE__." -h\n";
    echo "Options:\n";
    echo "  -a  Comma separated parameters to be passed to the fork function.\n";
    echo "  -e  The function to be run once the children are done.\n      This function will not be forked.\n";
    echo "  -f  The PHP file to be used for forking.\n";
    echo "  -h  Show this help.\n";
    echo "  -n  The number of children to fork.\n";
    echo "  -o  If set, new children will not be started as children die off.\n      This is useful for one time processing uses.\n";
    echo "  -p  The function to be run by the forked children.\n";
    echo "  -s  The function to be run before the children are started.\n      This function will not be forked.\n";
    echo "  -q  Be quiet.\n";
    echo "  -z  Seconds for each child to sleep before running its function.\n      (Forking can be taxing and this can help performance)\n";
    echo "\n";
    exit();
}

?>
