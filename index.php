<?php
// don't settle for less
set_error_handler(function ($code, $message, $file, $line) {
    die("$file:$line $message");
}, E_ALL | E_STRICT);

// are we handling a job?
$job = $_GET['job'] ?? null;

if ($job) {
    // create a latency to simulate a long-running process
    $interval = rand(1, 3);

    // simulate a long-running job
    sleep($interval);

    // generate the response
    die(json_encode([
        'number' => intval($job), //<< add the job number
        'latency' => $interval, //<< how long did this job take?
        'success' => boolval(rand(1, 0)) //<< simulate a success/failure
    ]));
}

// create multiple curl resources
$curls = [];

foreach (range(1, 10) as $job) {
    // set curl to hit our self
    $curl = curl_init('http://host.docker.internal:8080?job=' . $job);

    // dont dump response payload
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // append the curl handle
    $curls[$job] = $curl;
}

// create the multiple curl handle
$multi = curl_multi_init();

// add the handles
foreach ($curls as $curl) {
    curl_multi_add_handle($multi, $curl);
}

// start the multi timer
$interval = microtime(true);

// execute the multi handle
do {
    // run each job as another http request
    $status = curl_multi_exec($multi, $active);

    if ($active) {
        // wait for new activity on the handle
        curl_multi_select($multi);
    }
} while ($active && $status == CURLM_OK);

// capture the time lapsed
$interval = microtime(true) - $interval;

// get the contents
$bodies = [];

foreach ($curls as $job => $curl) {
    $bodies[$job] = curl_multi_getcontent($curl);
}

// check for curl errors
$errors = [];

foreach ($curls as $job => $curl) {
    $errors[$job] = curl_error($curl);
}

// close the curl handles
foreach ($curls as $curl) {
    curl_multi_remove_handle($multi, $curl);
}

// check for multi error
$error = curl_multi_errno($multi);

// close the multi handle
curl_multi_close($multi);

// parse the bodies
foreach ($bodies as $job => $body) {
    $bodies[$job] = json_decode($body, true);
}

// build response
foreach ($bodies as $job => $body) {
    $bodies[$job]['error'] = $errors[$job];
}

// respond with the combined output
print(json_encode([
    'jobs' => $bodies,
    'error' => $error ?? null,
    'latency' => $interval
], JSON_PRETTY_PRINT));
