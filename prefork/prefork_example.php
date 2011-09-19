<?php

function startup_example() {

    // This function is not run by the children.
    // do things here like creating a temporary database table,
    // loading libraries, or anything else that will need to be done,
    // for all processes.

    /*****
     BIG WARNING: IF YOU CREATE CONNECTIONS HERE, CLOSE THEM BEFORE LEAVING
     THE FUNCTION OR REALLY FREAKY THINGS CAN HAPPEN IN THE CHILDREN AS
     THEY CAN ALL END UP SHARING THE SAME CONNECTION.  FOR EXAMPLE, A CONNECTION
     TO MYSQL
    *****/

}


function fork_example() {

    $start = time();

    echo "Hi, my process ID is ".getmypid().". I am going to do nothing for 5 minutes.\n";

    while(time() - $start < 300) {
        usleep(500000);
    }

}


function shutdown_example() {

    // This function is not run by the children.
    // Any clean up work is done here.

}

?>
