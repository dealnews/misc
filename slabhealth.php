#!/usr/bin/env php
<?php

/*

Copyright (c) 2011, dealnews.com, Inc.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
 * Neither the name of dealnews.com, Inc. nor the names of its contributors
   may be used to endorse or promote products derived from this software
   without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

 */

$opts = getopt("hs:u:");

if(isset($opts["h"])){
    echo basename(__FILE__)." -h | -s SERVER\n";
    exit(0);
}

$host = $opts["s"];

$mc = new Memcache();

$mc->connect($host);

$stats = $mc->getStats("slabs");

echo "Slab ID  ";
echo "    Chunk Size";
echo "  Total Chunks";
echo "   Used Chunks";
echo "        % Used";
echo "   Memory Used";
echo "  \n";
foreach($stats as $s=>$d){

    if(is_numeric($s)){

        $used = ($d["used_chunks"] / $d["total_chunks"]) * 100;

        $mem_used = ($d["used_chunks"] * $d["chunk_size"]) / 1024;

        echo str_pad($s, 9, " ");
        echo str_pad(number_format($d["chunk_size"]), 14, " ", STR_PAD_LEFT);
        echo str_pad(number_format($d["total_chunks"]), 14, " ", STR_PAD_LEFT);
        echo str_pad(number_format($d["used_chunks"]), 14, " ", STR_PAD_LEFT);
        echo str_pad(round($used)."%", 14, " ", STR_PAD_LEFT);
        echo str_pad(number_format(round($mem_used))."k", 14, " ", STR_PAD_LEFT);
        echo "\n";
    }
}



?>